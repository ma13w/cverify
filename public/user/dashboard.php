<?php
/**
 * CVerify - User Dashboard
 * Gestione CV e richieste di validazione.
 * Richiede autenticazione via chiave privata.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Security.php';

use CVerify\Crypto;
use CVerify\DNS;
use CVerify\Auth;
use CVerify\Security;

// Start secure session
Security::startSecureSession();

$pageTitle = 'User Dashboard';

// Percorsi dati
$dataDir = __DIR__ . '/data';
$cvFile = __DIR__ . '/cv.json';
$configFile = $dataDir . '/config.json';
$privateKeyFile = $dataDir . '/private_key.pem';

// Inizializza directory
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

// Inizializza Auth e verifica autenticazione
$auth = new Auth($dataDir);

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Rinnova sessione ad ogni accesso
$auth->renewSession();
$session = $auth->getSession();

// Carica dati
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

// NUOVO: Verifica DNS ad ogni accesso
$crypto = new Crypto();
$dns = new DNS($crypto);
$dnsValid = false;

if (!empty($config['domain'])) {
    try {
        $dnsResult = $dns->verifyDomain($config['domain'], $config['fingerprint'] ?? null);
        $dnsValid = $dnsResult['valid'];
        
        if (!$dnsValid) {
            // DNS non valido, forza logout
            $auth->logout();
            header('Location: login.php?error=dns_invalid');
            exit;
        }
    } catch (Exception $e) {
        $auth->logout();
        header('Location: login.php?error=dns_error');
        exit;
    }
}

$cv = file_exists($cvFile) ? json_decode(file_get_contents($cvFile), true) : [
    'version' => '1.0',
    'domain' => $session['domain'] ?? $config['domain'] ?? '',
    'experiences' => []
];

// Gestione AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'logout':
                $auth->logout();
                echo json_encode(['success' => true]);
                break;
                
            case 'check_dns':
                $domain = $session['domain'] ?? $config['domain'] ?? '';
                if (empty($domain)) {
                    throw new Exception('Nessun dominio configurato');
                }
                
                $dns = new DNS();
                $dnsResult = $dns->verifyDomain($domain);
                $verified = !empty($dnsResult['cverify_id']);
                
                echo json_encode([
                    'success' => true,
                    'verified' => $verified,
                    'records' => $dnsResult
                ]);
                break;
                
            case 'add_experience':
                $exp = [
                    'id' => uniqid('exp_'),
                    'company_domain' => trim($_POST['company_domain'] ?? ''),
                    'role' => trim($_POST['role'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                    'start_date' => $_POST['start_date'] ?? '',
                    'end_date' => $_POST['end_date'] ?? null,
                    'validated' => false,
                    'attestation' => null,
                    'created_at' => date('c')
                ];
                
                if (empty($exp['company_domain']) || empty($exp['role'])) {
                    throw new Exception('Dominio azienda e ruolo sono obbligatori');
                }
                
                $cv['experiences'][] = $exp;
                file_put_contents($cvFile, json_encode($cv, JSON_PRETTY_PRINT));
                
                echo json_encode([
                    'success' => true,
                    'experience' => $exp
                ]);
                break;
                
            case 'delete_experience':
                $expId = $_POST['experience_id'] ?? '';
                $cv['experiences'] = array_values(array_filter(
                    $cv['experiences'],
                    fn($e) => $e['id'] !== $expId
                ));
                file_put_contents($cvFile, json_encode($cv, JSON_PRETTY_PRINT));
                
                echo json_encode(['success' => true]);
                break;
                
            case 'request_validation':
                $expId = $_POST['experience_id'] ?? '';
                $exp = null;
                
                foreach ($cv['experiences'] as &$e) {
                    if ($e['id'] === $expId) {
                        $exp = &$e;
                        break;
                    }
                }
                
                if (!$exp) {
                    throw new Exception('Esperienza non trovata');
                }
                
                if ($exp['validated']) {
                    throw new Exception('Esperienza già validata');
                }
                
                $crypto = new Crypto();
                $dns = new DNS($crypto);
                
                $userDomain = $session['domain'] ?? $config['domain'] ?? '';
                if (empty($userDomain)) {
                    throw new Exception('Domain not configured');
                }
                
                // Retrieve attestations from Relay Server
                $url = RELAY_SERVER_URL . '/api/attestations.php?domain=' . urlencode($userDomain);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    throw new Exception('Error retrieving attestations');
                }
                
                $data = json_decode($response, true);
                $attestations = $data['attestations'] ?? [];
                
                // Decripta attestazioni criptate e carica chiave privata
                $privateKey = $_SESSION['private_key'] ?? null;
                $passPhrase = $_SESSION['passphrase'] ?? null;
                if (!$privateKey || !$passPhrase) {
                    throw new Exception('Private key not available');
                }
                
                $companyDomain = $session['domain'] ?? $config['domain'] ?? '';
                $companyPublicKey = null;
                try {
                    $companyPublicKey = $dns->getPublicKeyFromDNS($companyDomain);
                } catch (Exception $e) {
                    // Azienda non ha chiave DNS, invieremo in chiaro
                }
                
                // Prepara i dati della richiesta
                $requestData = [
                    'user_domain' => $userDomain,
                    'company_domain' => $companyDomain,
                    'experience_id' => $exp['id'],
                    'experience_data' => [
                        'role' => $exp['role'],
                        'description' => $exp['description'] ?? '',
                        'start_date' => $exp['start_date'],
                        'end_date' => $exp['end_date']
                    ],
                    'timestamp' => date('c')
                ];

                // Rimuovi: Se l'azienda ha una chiave pubblica, cripta i dati sensibili
                // if ($companyPublicKey) {
                //     $sensitiveData = [
                //         'experience_id' => $exp['id'],
                //         'experience_data' => $requestData['experience_data'],
                //         'user_public_key' => $config['public_key'] ?? null,
                //         'timestamp' => date('c')
                //     ];
                //     $encryptedPayload = $crypto->encryptForRecipient($sensitiveData, $companyPublicKey);
                //     $requestData['encrypted_payload'] = $encryptedPayload;
                //     $requestData['encrypted'] = true;
                // } else {
                //     // Invia in chiaro
                // }

                // Invia sempre in chiaro
                $requestData['encrypted'] = false;

                // Invia al Relay Server
                $ch = curl_init(RELAY_SERVER_URL . '/api/request.php');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($requestData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    $error = json_decode($response, true);
                    throw new Exception($error['error'] ?? 'Errore invio al relay server');
                }
                
                $relayResponse = json_decode($response, true);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Richiesta inviata al relay server',
                    'encrypted' => $companyPublicKey !== null,
                    'request_id' => $relayResponse['request_id'] ?? null
                ]);
                break;
            
            case 'fetch_attestations':
                $userDomain = $session['domain'] ?? $config['domain'] ?? '';
                if (empty($userDomain)) {
                    throw new Exception('Domain not configured');
                }
                
                // Retrieve attestations from Relay Server
                $url = RELAY_SERVER_URL . '/api/attestations.php?domain=' . urlencode($userDomain);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    throw new Exception('Error retrieving attestations');
                }
                
                $data = json_decode($response, true);
                $attestations = $data['attestations'] ?? [];
                
                // Decripta attestazioni criptate e carica chiave privata
                $privateKey = $_SESSION['private_key'] ?? null;
                $passPhrase = $_SESSION['passphrase'] ?? null;
                if (!$privateKey || !$passPhrase) {
                    throw new Exception('Private key not available');
                }
                
                $companyDomain = $session['domain'] ?? $config['domain'] ?? '';
                $companyPublicKey = null;
                try {
                    $companyPublicKey = $dns->getPublicKeyFromDNS($companyDomain);
                } catch (Exception $e) {
                    // Azienda non ha chiave DNS, invieremo in chiaro
                }
                
                // Prepara i dati della richiesta
                $requestData = [
                    'user_domain' => $userDomain,
                    'company_domain' => $companyDomain,
                    'experience_id' => $exp['id'],
                    'experience_data' => [
                        'role' => $exp['role'],
                        'description' => $exp['description'] ?? '',
                        'start_date' => $exp['start_date'],
                        'end_date' => $exp['end_date']
                    ],
                    'timestamp' => date('c')
                ];

                // Rimuovi: Se l'azienda ha una chiave pubblica, cripta i dati sensibili
                // if ($companyPublicKey) {
                //     $sensitiveData = [
                //         'experience_id' => $exp['id'],
                //         'experience_data' => $requestData['experience_data'],
                //         'user_public_key' => $config['public_key'] ?? null,
                //         'timestamp' => date('c')
                //     ];
                //     $encryptedPayload = $crypto->encryptForRecipient($sensitiveData, $companyPublicKey);
                //     $requestData['encrypted_payload'] = $encryptedPayload;
                //     $requestData['encrypted'] = true;
                // } else {
                //     // Invia in chiaro
                // }

                // Invia sempre in chiaro
                $requestData['encrypted'] = false;

                // Invia al Relay Server
                $ch = curl_init(RELAY_SERVER_URL . '/api/request.php');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($requestData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    $error = json_decode($response, true);
                    throw new Exception($error['error'] ?? 'Errore invio al relay server');
                }
                
                $relayResponse = json_decode($response, true);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Richiesta inviata al relay server',
                    'encrypted' => $companyPublicKey !== null,
                    'request_id' => $relayResponse['request_id'] ?? null
                ]);
                break;
                
            case 'reject':
                $requestId = $_POST['request_id'] ?? '';
                $reason = $_POST['reason'] ?? 'Request rejected';
                
                // Remove from pending
                $cv['experiences'] = array_values(array_filter(
                    $cv['experiences'],
                    fn($e) => $e['id'] !== $requestId
                ));
                file_put_contents($cvFile, json_encode($cv, JSON_PRETTY_PRINT));
                
                echo json_encode(['success' => true]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span>User Dashboard</span>
                </h1>
                <p class="text-navy-400 mt-2">Manage your verifiable CV and attestations</p>
            </div>
            
            <!-- Session Info & Logout -->
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm text-navy-400">Autenticato come</p>
                    <p class="text-white font-mono text-sm"><?= htmlspecialchars($session['domain'] ?? '') ?></p>
                </div>
                <button onclick="logout()" class="btn-secondary px-4 py-2 rounded-lg text-sm flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span>Logout</span>
                </button>
            </div>
        </div>

        <div class="grid lg:grid-cols-4 gap-8">
            <!-- Left Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Identity Card -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                        </svg>
                        <span>La Tua Identità</span>
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-navy-400 text-sm">Dominio</span>
                            <span class="text-white font-mono text-sm"><?= htmlspecialchars($session['domain'] ?? '') ?></span>
                        </div>
                        <?php if (!empty($session['fingerprint'])): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-navy-400 text-sm">Fingerprint</span>
                            <span class="text-blue-400 font-mono text-xs"><?= substr($session['fingerprint'], 0, 12) ?>...</span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sessione -->
                        <div class="bg-emerald-500/20 border border-emerald-500/30 rounded-xl p-3">
                            <div class="flex items-center space-x-2 text-emerald-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span class="text-sm font-medium">Sessione Attiva</span>
                            </div>
                            <p class="text-xs text-emerald-300/70 mt-1">
                                Scade: <?= date('H:i', strtotime($session['expires_at'] ?? 'now')) ?>
                            </p>
                        </div>
                    </div>
                </div>

                
                <!-- Sync Attestations -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Attestazioni</span>
                    </h2>
                    <p class="text-navy-400 text-sm mb-4">Sincronizza le attestazioni ricevute dal relay server.</p>
                    <button id="syncAttestationsBtn" onclick="syncAttestations()" class="btn-primary w-full px-4 py-3 rounded-xl text-white font-medium flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Sincronizza Attestazioni</span>
                    </button>
                </div>

                <!-- JSON Export -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Esporta</h2>
                    <a href="cv.json" target="_blank" class="btn-secondary w-full px-4 py-2 rounded-lg text-sm flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Download cv.json</span>
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Add Experience Form -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <span>Aggiungi Esperienza</span>
                    </h2>
                    
                    <form id="addExperienceForm" class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-navy-300 mb-2">Dominio Azienda *</label>
                            <input type="text" name="company_domain" placeholder="azienda.com" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-navy-300 mb-2">Role *</label>
                            <input type="text" name="role" placeholder="Software Developer" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-navy-300 mb-2">Description</label>
                            <textarea name="description" rows="2" placeholder="Describe your responsibilities..." 
                                      class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-navy-300 mb-2">Start Date</label>
                            <input type="date" name="start_date" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-navy-300 mb-2">End Date</label>
                            <input type="date" name="end_date" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white">
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-white font-medium flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <span>Aggiungi Esperienza</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Experiences List -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <div class="p-6 border-b border-navy-800/50">
                        <h2 class="text-lg font-semibold text-white flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>Le Tue Esperienze</span>
                            <span class="ml-auto text-sm font-normal text-navy-400"><?= count($cv['experiences']) ?> esperienze</span>
                        </h2>
                    </div>
                    
                    <div id="experiencesList">
                        <?php if (empty($cv['experiences'])): ?>
                        <div class="text-center py-16 text-navy-400">
                            <svg class="w-20 h-20 mx-auto mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-lg mb-2">Nessuna esperienza</p>
                            <p class="text-sm">Aggiungi la tua prima esperienza lavorativa</p>
                        </div>
                        <?php else: ?>
                        <div class="divide-y divide-navy-800/50">
                            <?php foreach ($cv['experiences'] as $exp): ?>
                            <div class="p-6 hover:bg-navy-900/30 transition-colors" data-exp-id="<?= $exp['id'] ?>">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start space-x-4">
                                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br <?= $exp['validated'] ? 'from-emerald-400 to-emerald-600' : 'from-yellow-400 to-yellow-600' ?> flex items-center justify-center text-white font-bold text-sm">
                                            <?= strtoupper(substr($exp['company_domain'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <h3 class="text-white font-semibold"><?= htmlspecialchars($exp['role']) ?></h3>
                                            <p class="text-navy-400 text-sm"><?= htmlspecialchars($exp['company_domain']) ?></p>
                                            <?php if (!empty($exp['description'])): ?>
                                            <p class="text-navy-500 text-sm mt-1"><?= htmlspecialchars(substr($exp['description'], 0, 100)) ?><?= strlen($exp['description']) > 100 ? '...' : '' ?></p>
                                            <?php endif; ?>
                                            <p class="text-navy-500 text-xs mt-2">
                                                📅 <?= $exp['start_date'] ?? 'N/A' ?> → <?= $exp['end_date'] ?? 'Presente' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end space-y-2">
                                        <?php if ($exp['validated']): ?>
                                        <span class="status-verified px-3 py-1 rounded-lg text-xs font-medium flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                            </svg>
                                            <span>Validata</span>
                                        </span>
                                        <?php else: ?>
                                        <span class="status-pending px-3 py-1 rounded-lg text-xs font-medium">In Attesa</span>
                                        <button onclick="requestValidation('<?= $exp['id'] ?>')" 
                                                class="btn-primary px-3 py-1 rounded-lg text-xs font-medium flex items-center space-x-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                            <span>Richiedi Validazione</span>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="deleteExperience('<?= $exp['id'] ?>')" 
                                                class="text-red-400 hover:text-red-300 text-xs flex items-center space-x-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Elimina</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Debug DNS Status on page load
        console.group('%c📄 CVerify User Dashboard - DNS Status', 'color: #3b82f6; font-weight: bold; font-size: 14px;');
        console.log('%cSessione autenticata attiva', 'color: #10b981; font-weight: bold;');
        console.log('Dominio:', '<?= htmlspecialchars($session["domain"] ?? $config["domain"] ?? "N/A") ?>');
        console.log('DNS Verificato:', <?= json_encode($dnsValid) ?>);
        console.log('Sessione scade:', '<?= htmlspecialchars($session["expires_at"] ?? "N/A") ?>');
        console.log('Ultimo check DNS:', '<?= htmlspecialchars($session["last_dns_check"] ?? "Mai") ?>');
        console.table({
            'Dominio': '<?= htmlspecialchars($session["domain"] ?? $config["domain"] ?? "N/A") ?>',
            'DNS Valido': <?= json_encode($dnsValid) ?>,
            'Esperienze nel CV': <?= count($cv['experiences'] ?? []) ?>,
            'Sessione Attiva': true
        });
        console.groupEnd();
        
        // Periodic DNS check every 5 minutes
        setInterval(async () => {
            console.log('%c🔄 CVerify: Verifica DNS periodica...', 'color: #fbbf24;');
            const formData = new FormData();
            formData.append('action', 'check_dns');
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.verified) {
                    console.log('%c✅ DNS ancora valido', 'color: #10b981;');
                } else {
                    console.error('%c❌ DNS non più valido! Redirect a login...', 'color: #ef4444;');
                    window.location.href = 'login.php?error=dns_invalid';
                }
            } catch (err) {
                console.error('Errore check DNS:', err);
            }
        }, 300000); // 5 minuti

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-xl text-white font-medium z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-emerald-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }
        
        async function logout() {
            if (!confirm('Do you want to logout?')) return;
            
            console.log('%c🚪 CVerify: Logging out...', 'color: #f59e0b;');
            
            const formData = new FormData();
            formData.append('action', 'logout');
            
            await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            console.log('%c✅ Sessione terminata', 'color: #10b981;');
            window.location.href = 'login.php';
        }
        
        // Add Experience Form
        document.getElementById('addExperienceForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            
            const formData = new FormData(form);
            formData.append('action', 'add_experience');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Esperienza aggiunta!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
            }
        });
        
        async function syncAttestations() {
            const btn = document.getElementById('syncAttestationsBtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sincronizzazione...';
            
            const formData = new FormData();
            formData.append('action', 'fetch_attestations');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    if (data.matched > 0) {
                        showToast(`${data.matched} esperienze validate!`, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else if (data.count > 0) {
                        showToast(`${data.count} attestazioni trovate, ma nessuna corrisponde alle tue esperienze`, 'info');
                    } else {
                        showToast('Nessuna nuova attestazione', 'info');
                    }
                    // Update counter
                    const countEl = document.getElementById('pendingCount');
                    countEl.textContent = data.count;
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }
        
        async function deleteExperience(expId) {
            if (!confirm('Eliminare questa esperienza?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_experience');
            formData.append('experience_id', expId);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    document.querySelector(`[data-exp-id="${expId}"]`)?.remove();
                    showToast('Esperienza eliminata', 'success');
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
        
        async function requestValidation(expId) {
            if (!confirm('Inviare richiesta di validazione?')) return;
            
            const formData = new FormData();
            formData.append('action', 'request_validation');
            formData.append('experience_id', expId);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    showToast('Richiesta inviata! ' + (data.encrypted ? '(criptata)' : ''), 'success');
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
