<?php

declare(strict_types=1);

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

/**
 * CVerify - Gestione CV
 * Dashboard per gestire le esperienze lavorative e richiedere validazioni.
 * RICHIEDE AUTENTICAZIONE via chiave privata RSA.
 */

// Configurazione
define('USER_DATA_DIR', __DIR__ . '/data');
define('PRIVATE_KEY_FILE', USER_DATA_DIR . '/private_key.pem');
define('CONFIG_FILE', USER_DATA_DIR . '/config.json');
define('CV_FILE', __DIR__ . '/cv.json');
define('PENDING_FILE', USER_DATA_DIR . '/pending_validations.json');
define('APPROVED_DIR', USER_DATA_DIR . '/approved');
define('RELAY_SERVER_URL', 'http://localhost:7070');
define('HTTP_TIMEOUT', 10);


// Inizializza directory
if (!is_dir(USER_DATA_DIR)) {
    mkdir(USER_DATA_DIR, 0755, true);
}

// Inizializza Auth e verifica autenticazione
$auth = new Auth(USER_DATA_DIR);

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Rinnova sessione ad ogni accesso
$auth->renewSession();
$session = $auth->getSession();

// Carica dati
$config = file_exists(CONFIG_FILE) ? json_decode(file_get_contents(CONFIG_FILE), true) : [];

// NUOVO: Verifica DNS ad ogni accesso
$crypto = new Crypto();
$dns = new DNS($crypto);
$dnsValid = false;

if (!empty($config['domain'])) {
    try {
        $dnsResult = $dns->verifyDomain($config['domain'], $config['fingerprint'] ?? null);
        $dnsValid = !empty($dnsResult['cverify_id']);
        
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

$pendingValidations = file_exists(PENDING_FILE) ? json_decode(file_get_contents(PENDING_FILE), true) : [];

// Verifica che il DNS sia ancora valido
$config = file_exists(CONFIG_FILE) ? json_decode(file_get_contents(CONFIG_FILE), true) : [];

if (empty($config['domain'])) {
    header('Location: setup.php');
    exit;
}

// Verifica DNS prima di permettere operazioni
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
            
            case 'fetch_pending':
                $companyDomain = $session['domain'] ?? $config['domain'] ?? '';
                
                if (empty($companyDomain)) {
                    throw new Exception('Azienda non configurata');
                }
                
                // Recupera pending dal Relay Server
                $url = RELAY_SERVER_URL . '/api/pending.php?domain=' . urlencode($companyDomain);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200) {
                    throw new Exception('Errore recupero richieste dal relay');
                }
                
                $data = json_decode($response, true);
                $relayPending = $data['requests'] ?? [];
                
                // Prepara per decriptazione se necessario
                $privateKey = $_SESSION['private_key'] ?? null;
                $passPhrase = $_SESSION['passphrase'] ?? null;
                if (!$privateKey || !$passPhrase) {
                    throw new Exception('Chiave privata non disponibile');
                }
                $crypto = new Crypto();
                
                // Assicura che $pendingValidations sia un array
                if (!is_array($pendingValidations)) {
                    $pendingValidations = [];
                }

                // Merge con pending locali
                foreach ($relayPending as $req) {
                    $reqId = $req['id'] ?? ('relay_' . uniqid());

                    // Verifica se già presente
                    $exists = false;
                    foreach ($pendingValidations as $pv) {
                        if (($pv['request_id'] ?? $pv['id'] ?? '') === $reqId) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        // Se è criptato, prova a decriptare
                        if (isset($req['encrypted']) && $req['encrypted'] && isset($req['encrypted_payload'])) {
                            if ($privateKey) {
                                try {
                                    $decryptedData = $crypto->decryptWithPrivateKey($req['encrypted_payload'], $privateKey);
                                    $pendingValidations[] = [
                                        'id' => $reqId,
                                        'request_id' => $reqId,
                                        'user_domain' => $req['user_domain'],
                                        'experience_id' => $decryptedData['experience_id'] ?? null,
                                        'experience_data' => $decryptedData['experience_data'] ?? [],
                                        'user_public_key' => $decryptedData['user_public_key'] ?? null,
                                        'timestamp' => $decryptedData['timestamp'] ?? $req['submitted_at'] ?? date('c'),
                                        'received_at' => date('c'),
                                        'from_relay' => true,
                                        'decrypted' => true
                                    ];
                                } catch (Exception $e) {
                                    // Non riesco a decriptare
                                    $pendingValidations[] = [
                                        'id' => $reqId,
                                        'request_id' => $reqId,
                                        'user_domain' => $req['user_domain'],
                                        'encrypted' => true,
                                        'decrypt_error' => $e->getMessage(),
                                        'timestamp' => $req['submitted_at'] ?? date('c'),
                                        'received_at' => date('c'),
                                        'from_relay' => true
                                    ];
                                }
                            } else {
                                // Nessuna chiave privata per decriptare
                                $pendingValidations[] = [
                                    'id' => $reqId,
                                    'request_id' => $reqId,
                                    'user_domain' => $req['user_domain'],
                                    'encrypted' => true,
                                    'decrypt_error' => 'Chiave privata non disponibile',
                                    'timestamp' => $req['submitted_at'] ?? date('c'),
                                    'received_at' => date('c'),
                                    'from_relay' => true
                                ];
                            }
                        } else {
                            // Richiesta in chiaro
                            $pendingValidations[] = [
                                'id' => $reqId,
                                'request_id' => $reqId,
                                'user_domain' => $req['user_domain'],
                                'experience_id' => $req['experience_id'],
                                'experience_data' => $req['experience_data'],
                                'callback_url' => $req['callback_url'] ?? null,
                                'timestamp' => $req['submitted_at'] ?? $req['timestamp'] ?? date('c'),
                                'received_at' => date('c'),
                                'from_relay' => true
                            ];
                        }
                    }
                }
                
                file_put_contents(PENDING_FILE, json_encode($pendingValidations, JSON_PRETTY_PRINT));
                
                echo json_encode([
                    'success' => true,
                    'count' => count($relayPending),
                    'total_pending' => count($pendingValidations)
                ]);
                break;
            
            case 'search_profile':
                $profileUrl = trim($_POST['profile_url'] ?? '');
                if (empty($profileUrl)) {
                    throw new Exception('URL del profilo richiesto');
                }
                
                $companyDomain = strtolower($session['domain'] ?? $config['domain'] ?? '');
                $companyDomain = preg_replace('#^www\.#', '', $companyDomain);
                
                if (empty($companyDomain)) {
                    throw new Exception('Azienda non configurata');
                }
                
                // Fetch CV
                $context = stream_context_create([
                    'http' => ['timeout' => 15, 'ignore_errors' => true],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
                ]);
                
                $cvJson = @file_get_contents($profileUrl, false, $context);
                if ($cvJson === false) {
                    throw new Exception('Impossibile recuperare il profilo');
                }
                
                $cv = json_decode($cvJson, true);
                if (!$cv || !isset($cv['experiences'])) {
                    throw new Exception('Formato CV non valido');
                }
                
                // Filtra esperienze relative a questa azienda
                $relevantExperiences = [];
                foreach ($cv['experiences'] as $exp) {
                    $expDomain = strtolower($exp['company_domain'] ?? '');
                    $expDomain = preg_replace('#^www\.#', '', $expDomain);
                    
                    if ($expDomain === $companyDomain) {    
                        $relevantExperiences[] = $exp;
                    }
                }
                
                if (empty($relevantExperiences)) {
                    throw new Exception('Nessuna esperienza trovata per ' . ($session['domain'] ?? $config['domain']));
                }
                
                // Verifica identità DNS dell'utente
                $userDomain = $cv['domain'] ?? $cv['owner_domain'] ?? null;
                $dnsVerified = false;
                $dnsInfo = null;
                
                if ($userDomain) {
                    try {
                        $dns = new DNS();
                        $dnsResult = $dns->verifyDomain($userDomain);
                        $dnsVerified = !empty($dnsResult['cverify_id']);
                        $dnsInfo = $dnsResult;
                    } catch (Exception $e) {
                        // DNS non verificato
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'user_domain' => $userDomain,
                    'dns_verified' => $dnsVerified,
                    'experiences' => $relevantExperiences,
                    'total_experiences' => count($cv['experiences'])
                ]);
                break;
                
            case 'approve':
                $requestId = $_POST['request_id'] ?? '';
                $request = null;
                $requestIndex = null;
                
                foreach ($pendingValidations as $key => $r) {
                    $rId = $r['id'] ?? $r['request_id'] ?? '';
                    if ($rId === $requestId) {
                        $request = $r;
                        $requestIndex = $key;
                        break;
                    }
                }
                
                if (!$request) {
                    throw new Exception('Richiesta non trovata');
                }
                
                $crypto = new Crypto();
                $dns = new DNS($crypto);
                
                // Fix: assign privateKeyFile from constant
                $privateKey = $_SESSION['private_key'] ?? null;
                $passPhrase = $_SESSION['passphrase'] ?? null;

                if (!$privateKey || !$privateKey) {
                    throw new Exception('Chiave privata aziendale non configurata');
                }
                
                $privateKey = $_SESSION['private_key'] ?? null;
                $passPhrase = $_SESSION['passphrase'] ?? null;
                if (!$privateKey || !$passPhrase) {
                    throw new Exception('Chiave privata non disponibile');
                }
                
                $companyDomain = $session['domain'] ?? $config['domain'] ?? '';
                
                // Crea attestazione
                $attestation = [
                    'version' => '1.0',
                    'type' => 'work_experience_attestation',
                    'issuer_domain' => $companyDomain,
                    'user_domain' => $request['user_domain'],
                    'experience_id' => $request['experience_id'],
                    'experience_data' => [
                        'role' => $request['experience_data']['role'] ?? '',
                        'start_date' => $request['experience_data']['start_date'] ?? '',
                        'end_date' => $request['experience_data']['end_date'] ?? null,
                        'description' => $request['experience_data']['description'] ?? ''
                    ],
                    'experience_hash' => hash('sha256', json_encode($request['experience_data'])),
                    'issued_at' => date('c'),
                    'valid_until' => date('c', strtotime('+10 years')),
                    'attestation_id' => 'att_' . bin2hex(random_bytes(8))
                ];
           
                $signature = $crypto->signJson($attestation, $privateKey, $passPhrase);
                $attestation['signature'] = $signature;
                $approvedDir = APPROVED_DIR;
                
                // Salva attestazione localmente
                file_put_contents(
                    $approvedDir . '/' . $attestation['attestation_id'] . '.json',
                    json_encode($attestation, JSON_PRETTY_PRINT)
                );
                
                // Determina se criptare l'attestazione per l'utente
                $userPublicKey = $request['user_public_key'] ?? null;
                $userDomain = $request['user_domain'] ?? '';

                if (!$userPublicKey && !empty($userDomain)) {
                    try {
                        $userPublicKey = $dns->getPublicKeyFromDNS($userDomain);
                    } catch (Exception $e) {
                        // Nessuna chiave DNS trovata
                    }
                }

                // Invia sempre in chiaro
                $relayData = $attestation;
                $encrypted = false;
                
                // Invia attestazione al Relay Server
                $ch = curl_init(RELAY_SERVER_URL . '/api/attestation.php');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($relayData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $relayResponse = curl_exec($ch);
                $relayHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Rimuovi dalla pending list
                unset($pendingValidations[$requestIndex]);
                $pendingValidations = array_values($pendingValidations);
                file_put_contents(PENDING_FILE, json_encode($pendingValidations, JSON_PRETTY_PRINT));
                
                // Acknowledge sul relay
                if ($request['from_relay'] ?? false) {
                    $ackUrl = RELAY_SERVER_URL . '/api/acknowledge.php';
                    $ch = curl_init($ackUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode([
                            'request_id' => $requestId,
                            'company_domain' => $companyDomain
                        ]),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => HTTP_TIMEOUT
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
                
                echo json_encode([
                    'success' => true,
                    'attestation' => $attestation,
                    'relay_sent' => $relayHttpCode === 200,
                    'encrypted' => $encrypted
                ]);
                break;
                
            case 'reject':
                $requestId = $_POST['request_id'] ?? '';
                $reason = $_POST['reason'] ?? 'Richiesta rifiutata';
                
                // Rimuovi dalla pending
                $pendingValidations = array_values(array_filter(
                    $pendingValidations,
                    function($r) use ($requestId) {
                        return ($r['id'] ?? $r['request_id'] ?? '') !== $requestId;
                    }
                ));
                
                file_put_contents(PENDING_FILE, json_encode($pendingValidations, JSON_PRETTY_PRINT));
                
                echo json_encode(['success' => true]);
                break;
            
            case 'validate_experience':
                // Validazione diretta di un'esperienza da ricerca profilo
                $userDomain = trim($_POST['user_domain'] ?? '');
                $experienceId = trim($_POST['experience_id'] ?? '');
                $experienceData = json_decode($_POST['experience_data'] ?? '{}', true);
                
                if (empty($userDomain) || empty($experienceId) || empty($experienceData)) {
                    throw new Exception('Dati mancanti per la validazione');
                }
                
                $crypto = new Crypto();
                $dns = new DNS($crypto);
                
                if (!$privateKey || !$passPhrase) {
                    throw new Exception('Chiave privata aziendale non configurata');
                }
                
                $companyDomain = $config['domain'] ?? '';
                
                // Verifica che l'esperienza sia effettivamente per questa azienda
                $expDomain = strtolower($experienceData['company_domain'] ?? '');
                $expDomain = preg_replace('#^www\.#', '', $expDomain);
                $checkDomain = strtolower($companyDomain);
                $checkDomain = preg_replace('#^www\.#', '', $checkDomain);
                
                if ($expDomain !== $checkDomain) {
                    throw new Exception('Questa esperienza non è relativa alla tua azienda');
                }
                
                // Crea attestazione
                $attestation = [
                    'version' => '1.0',
                    'type' => 'work_experience_attestation',
                    'issuer_domain' => $companyDomain,
                    'user_domain' => $userDomain,
                    'experience_id' => $experienceId,
                    'experience_data' => [
                        'role' => $experienceData['role'] ?? '',
                        'start_date' => $experienceData['start_date'] ?? '',
                        'end_date' => $experienceData['end_date'] ?? null,
                        'description' => $experienceData['description'] ?? ''
                    ],
                    'experience_hash' => hash('sha256', json_encode($experienceData)),
                    'issued_at' => date('c'),
                    'valid_until' => date('c', strtotime('+10 years')),
                    'attestation_id' => 'att_' . bin2hex(random_bytes(8))
                ];
                
                $signature = $crypto->signJson($attestation, $privateKey);
                $attestation['signature'] = $signature;
                
                // Salva attestazione localmente
                file_put_contents(
                    $approvedDir . '/' . $attestation['attestation_id'] . '.json',
                    json_encode($attestation, JSON_PRETTY_PRINT)
                );
                
                // Prova a recuperare la chiave pubblica dell'utente per crittografia
                $userPublicKey = null;
                try {
                    $userPublicKey = $dns->getPublicKeyFromDNS($userDomain);
                } catch (Exception $e) {
                    // Nessuna chiave DNS trovata
                }
                
                // Invia sempre in chiaro
                $relayData = $attestation;
                $encrypted = false;
                
                // Invia attestazione al Relay Server
                $ch = curl_init(RELAY_SERVER_URL . '/api/attestation.php');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($relayData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => HTTP_TIMEOUT
                ]);
                $relayResponse = curl_exec($ch);
                $relayHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                echo json_encode([
                    'success' => true,
                    'attestation' => $attestation,
                    'relay_sent' => $relayHttpCode === 200,
                    'encrypted' => $encrypted
                ]);
                break;
                
            default:
                throw new Exception('Azione non valida');
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
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <span>Company Portal</span>
            </h1>
            <p class="text-navy-400 mt-2">Gestisci le richieste di validazione e firma le attestazioni</p>
        </div>

        <div class="grid lg:grid-cols-4 gap-8">
            <!-- Left Sidebar: Company Setup -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Company Identity -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span>Identità Azienda</span>
                    </h2>
                    
                    <?php if (empty($config['domain'])): ?>
                    <!-- Setup Form -->
                    <form id="setupCompanyForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-navy-300 mb-2">Dominio Aziendale</label>
                            <input type="text" name="domain" placeholder="azienda.com" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500" required>
                        </div>
                        <button type="submit" class="btn-primary w-full px-4 py-3 rounded-xl text-white font-medium flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <span>Configura Azienda</span>
                        </button>
                    </form>
                    <?php else: ?>
                    <!-- Company Info -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-navy-400 text-sm">Dominio</span>
                            <span class="text-white font-mono text-sm"><?= htmlspecialchars($config['domain']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-navy-400 text-sm">Fingerprint</span>
                            <span class="text-purple-400 font-mono text-xs"><?= substr($config['fingerprint'] ?? '', 0, 12) ?>...</span>
                        </div>
                        
                        <!-- DNS Status -->
                        <div id="dnsStatus" class="mt-4">
                            <button onclick="checkDNS()" class="btn-secondary w-full px-4 py-2 rounded-lg text-sm flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Verifica DNS</span>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- DNS Records -->
                <?php if (!empty($config['dns_records']) && is_array($config['dns_records'])): ?>
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                        <span>Record DNS</span>
                    </h2>
                    <p class="text-navy-400 text-sm mb-4">Aggiungi al DNS aziendale:</p>
                    
                    <div class="space-y-3">
                        <?php foreach ($config['dns_records'] as $index => $record): ?>
                        <div class="bg-navy-900/50 rounded-lg p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-navy-400">Record <?= $index + 1 ?></span>
                                <span class="text-xs text-purple-400"><?= $record['type'] ?? 'TXT' ?></span>
                            </div>
                            <code class="text-xs text-emerald-400 break-all"><?= htmlspecialchars(is_string($record) ? $record : ($record['value'] ?? '')) ?></code>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Statistiche</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-yellow-400" id="pendingCount"><?php echo count($pendingValidations ?? []) ?></div>
                            <div class="text-xs text-navy-400">In Attesa</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-emerald-400"><?= count(glob(APPROVED_DIR . '/*.json')) ?></div>
                            <div class="text-xs text-navy-400">Approvate</div>
                        </div>
                    </div>
                </div>
                
                <!-- Sync Pending Requests -->
                <?php if (!empty($config['domain'])): ?>
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Relay Server</span>
                    </h2>
                    <p class="text-navy-400 text-sm mb-4">Sincronizza le richieste di validazione dal relay.</p>
                    <button id="syncPendingBtn" onclick="syncPending()" class="btn-primary w-full px-4 py-3 rounded-xl text-white font-medium flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Sincronizza Richieste</span>
                    </button>
                </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Search Profile Section -->
                <?php if (!empty($config['domain'])): ?>
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-4 flex items-center space-x-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Cerca e Verifica Profili</span>
                    </h2>
                    <p class="text-navy-400 text-sm mb-4">
                        Cerca un profilo CVerify per verificare se ha esperienze con <strong class="text-purple-400"><?= htmlspecialchars($config['domain']) ?></strong>
                    </p>
                    
                    <form id="searchProfileForm" class="flex gap-3">
                        <div class="flex-1">
                            <input type="url" name="profile_url" id="profileSearchUrl" 
                                   placeholder="https://dominio-utente.com/cv.json" 
                                   class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500" required>
                        </div>
                        <button type="submit" id="searchBtn" class="btn-primary px-6 py-3 rounded-xl text-white font-medium flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>Cerca</span>
                        </button>
                    </form>
                    
                    <!-- Search Results -->
                    <div id="searchResults" class="hidden mt-6">
                        <!-- Results will be inserted here -->
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Pending Requests -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <div class="p-6 border-b border-navy-800/50">
                        <h2 class="text-lg font-semibold text-white flex items-center space-x-2">
                            <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            <span>Richieste in Attesa</span>
                            <span class="ml-auto text-sm font-normal text-navy-400"><?= count($pendingValidations ?? []) ?> richieste</span>
                        </h2>
                    </div>
                    
                    <div id="requestsList">
                        <?php if (empty($pendingValidations)): ?>
                        <div class="text-center py-16 text-navy-400">
                            <svg class="w-20 h-20 mx-auto mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-lg mb-2">Nessuna richiesta in attesa</p>
                            <p class="text-sm">Le nuove richieste di validazione appariranno qui</p>
                        </div>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-navy-900/50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-navy-400 uppercase tracking-wider">Utente</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-navy-400 uppercase tracking-wider">Ruolo</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-navy-400 uppercase tracking-wider">Periodo</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-navy-400 uppercase tracking-wider">Ricevuto</th>
                                        <th class="px-6 py-4 text-right text-xs font-medium text-navy-400 uppercase tracking-wider">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-navy-800/50">
                                    <?php foreach ($pendingValidations as $request): ?>
                                    <?php $reqId = $request['id'] ?? $request['request_id'] ?? ''; ?>
                                    <tr class="hover:bg-navy-900/30 transition-colors" data-request-id="<?= $reqId ?>">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm">
                                                    <?= strtoupper(substr($request['user_domain'] ?? '', 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div class="text-white font-medium"><?= htmlspecialchars($request['user_domain'] ?? 'N/A') ?></div>
                                                    <div class="text-xs text-navy-500 font-mono"><?= substr($request['id'] ?? '', 0, 12) ?>...</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-white"><?= htmlspecialchars($request['experience_data']['role'] ?? 'N/A') ?></div>
                                            <?php if (!empty($request['experience_data']['description'])): ?>
                                            <div class="text-xs text-navy-400 mt-1"><?= htmlspecialchars(substr($request['experience_data']['description'], 0, 40)) ?>...</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-navy-300">
                                            <?= $request['experience_data']['start_date'] ?? 'N/A' ?><br>
                                            <span class="text-navy-500">→ <?= $request['experience_data']['end_date'] ?? 'Presente' ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-navy-400">
                                            <?= date('d/m/Y H:i', strtotime($request['received_at'] ?? 'now')) ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end space-x-2">
                                                <button onclick="approveRequest('<?= $request['id'] ?? $request['request_id'] ?? '' ?>')" 
                                                        class="btn-primary px-4 py-2 rounded-lg text-sm font-medium flex items-center space-x-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    <span>Approva</span>
                                                </button>
                                                <button onclick="rejectRequest('<?= $request['id'] ?? $request['request_id'] ?? '' ?>')" 
                                                        class="px-4 py-2 rounded-lg text-sm font-medium bg-red-500/10 text-red-400 border border-red-500/20 hover:bg-red-500/20 transition-colors flex items-center space-x-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                    <span>Rifiuta</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Attestation Modal -->
    <div id="attestationModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 items-center justify-center p-4">
        <div class="glass-card rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
            <div class="p-6 border-b border-navy-800/50 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-emerald-500/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-white">Attestazione Firmata</h3>
                        <p class="text-sm text-navy-400">L'esperienza è stata verificata e firmata</p>
                    </div>
                </div>
                <button onclick="closeModal()" class="text-navy-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-auto max-h-96">
                <pre id="attestationContent" class="text-xs text-emerald-400 bg-navy-900/50 rounded-xl p-4 overflow-x-auto"></pre>
            </div>
            <div class="p-6 border-t border-navy-800/50 flex justify-end space-x-3">
                <button onclick="copyAttestation()" class="btn-secondary px-4 py-2 rounded-lg text-sm">Copia JSON</button>
                <button onclick="closeModal()" class="btn-primary px-4 py-2 rounded-lg text-sm">Chiudi</button>
            </div>
        </div>
    </div>

    <script>
        // Debug DNS Status on page load
        console.group('%c🏢 CVerify Company Dashboard - DNS Status', 'color: #a855f7; font-weight: bold; font-size: 14px;');
        console.log('%cSessione aziendale autenticata attiva', 'color: #10b981; font-weight: bold;');
        console.log('Dominio aziendale:', '<?= htmlspecialchars($session["domain"] ?? $config["domain"] ?? "N/A") ?>');
        console.log('DNS Verificato:', <?= json_encode($dnsValid) ?>);
        console.log('Sessione scade:', '<?= htmlspecialchars($session["expires_at"] ?? "N/A") ?>');
        console.log('Ultimo check DNS:', '<?= htmlspecialchars($session["last_dns_check"] ?? "Mai") ?>');
        console.table({
            'Dominio Aziendale': '<?= htmlspecialchars($session["domain"] ?? $config["domain"] ?? "N/A") ?>',
            'DNS Valido': <?= json_encode($dnsValid) ?>,
            'Richieste Pendenti': <?= count($pendingValidations ?? []) ?>,
            'Sessione Attiva': true
        });
        console.groupEnd();
        
        // Periodic DNS check every 5 minutes
        setInterval(async () => {
            console.log('%c🔄 CVerify Company: Verifica DNS periodica...', 'color: #fbbf24;');
            const formData = new FormData();
            formData.append('action', 'check_dns');
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.verified) {
                    console.log('%c✅ DNS aziendale ancora valido', 'color: #10b981;');
                } else {
                    console.error('%c❌ DNS aziendale non più valido! Redirect a login...', 'color: #ef4444;');
                    window.location.href = 'login.php?error=dns_invalid';
                }
            } catch (err) {
                console.error('Errore check DNS aziendale:', err);
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
        
        function closeModal() {
            document.getElementById('attestationModal').classList.add('hidden');
            document.getElementById('attestationModal').classList.remove('flex');
        }
        
        function copyAttestation() {
            const content = document.getElementById('attestationContent').textContent;
            navigator.clipboard.writeText(content).then(() => showToast('Copiato!', 'success'));
        }
        
        // Setup Company Form
        document.getElementById('setupCompanyForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg>';
            
            const formData = new FormData(form);
            formData.append('action', 'setup_company');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Azienda configurata!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
                btn.disabled = false;
            }
        });
        
        async function checkDNS() {
            console.group('%c🔍 CVerify Company: Check DNS manuale', 'color: #a855f7;');
            console.time('DNS Check Duration');
            
            const statusDiv = document.getElementById('dnsStatus');
            statusDiv.innerHTML = '<div class="flex items-center justify-center space-x-2 text-navy-400"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg><span>Verifica...</span></div>';
            
            const formData = new FormData();
            formData.append('action', 'check_dns');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                console.log('Risposta DNS:', data);
                console.timeEnd('DNS Check Duration');
                
                if (data.success && data.verified) {
                    console.log('%c✅ DNS Verificato', 'color: #10b981; font-weight: bold;');
                    console.log('Records:', data.records);
                    console.groupEnd();
                    statusDiv.innerHTML = '<div class="status-verified rounded-xl p-3 flex items-center space-x-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="font-medium">DNS Verificato</span></div>';
                } else {
                    console.error('%c❌ DNS non configurato', 'color: #ef4444; font-weight: bold;');
                    console.groupEnd();
                    statusDiv.innerHTML = '<div class="status-pending rounded-xl p-3 flex items-center space-x-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span class="font-medium">DNS Non Configurato</span></div>';
                }
            } catch (err) {
                console.error('Errore verifica DNS:', err);
                console.groupEnd();
                statusDiv.innerHTML = '<div class="status-rejected rounded-xl p-3"><span>Errore verifica</span></div>';
            }
        }
        
        // Search Profile Form
        document.getElementById('searchProfileForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById('searchBtn');
            const resultsDiv = document.getElementById('searchResults');
            
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg> Cerca...';
            
            const formData = new FormData(form);
            formData.append('action', 'search_profile');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    resultsDiv.classList.remove('hidden');
                    resultsDiv.innerHTML = renderSearchResults(data);
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                resultsDiv.classList.remove('hidden');
                resultsDiv.innerHTML = `
                    <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 flex items-center space-x-3">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-red-400">${err.message}</span>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><span>Cerca</span>';
            }
        });
        
        function renderSearchResults(data) {
            // Salva i dati per uso successivo
            window.lastSearchData = data;
            
            const dnsStatus = data.dns_verified 
                ? '<span class="status-verified px-3 py-1 rounded-lg text-xs font-medium">DNS Verificato</span>'
                : '<span class="status-pending px-3 py-1 rounded-lg text-xs font-medium">DNS Non Verificato</span>';
            
            let experiencesHtml = data.experiences.map((exp, index) => {
                const expDataEncoded = encodeURIComponent(JSON.stringify(exp));
                const validateBtn = !exp.validated 
                    ? `<button onclick="validateExperience('${data.user_domain}', '${exp.id}', ${index})" 
                               class="btn-primary px-4 py-2 rounded-lg text-xs font-medium flex items-center space-x-1 mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span>Valida Esperienza</span>
                        </button>`
                    : '';
                
                return `
                <div class="bg-navy-900/50 rounded-xl p-4 border border-navy-800/50" id="exp-result-${index}">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="text-white font-medium">${exp.role || 'N/A'}</h4>
                            <p class="text-navy-400 text-sm mt-1">${exp.description || 'Nessuna descrizione'}</p>
                            <p class="text-navy-500 text-xs mt-2">
                                📅 ${exp.start_date || 'N/A'} → ${exp.end_date || 'Presente'}
                            </p>
                            ${validateBtn}
                        </div>
                        <div>
                            ${exp.validated 
                                ? '<span class="status-verified px-3 py-1 rounded-lg text-xs font-medium">Validato</span>' 
                                : '<span class="status-pending px-3 py-1 rounded-lg text-xs font-medium">Non Validato</span>'
                            }
                        </div>
                    </div>
                </div>
            `;
            }).join('');
            
            return `
                <div class="border-t border-navy-800/50 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold">
                                ${(data.user_domain || '?').substring(0, 2).toUpperCase()}
                            </div>
                            <div>
                                <h3 class="text-white font-semibold">${data.user_domain || 'Dominio sconosciuto'}</h3>
                                <p class="text-navy-400 text-sm">${data.total_experiences} esperienze totali</p>
                            </div>
                        </div>
                        ${dnsStatus}
                    </div>
                    
                    <h4 class="text-navy-300 text-sm font-medium mb-3">
                        Esperienze con la tua azienda (${data.experiences.length}):
                    </h4>
                    
                    <div class="space-y-3">
                        ${experiencesHtml}
                    </div>
                </div>
            `;
        }
        
        async function validateExperience(userDomain, experienceId, expIndex) {
            if (!confirm('Confermi la validazione di questa esperienza lavorativa?')) return;
            
            const exp = window.lastSearchData.experiences[expIndex];
            
            const formData = new FormData();
            formData.append('action', 'validate_experience');
            formData.append('user_domain', userDomain);
            formData.append('experience_id', experienceId);
            formData.append('experience_data', JSON.stringify(exp));
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    // Mostra attestazione
                    document.getElementById('attestationContent').textContent = JSON.stringify(data.attestation, null, 2);
                    document.getElementById('attestationModal').classList.remove('hidden');
                    document.getElementById('attestationModal').classList.add('flex');
                    
                    // Aggiorna UI - marca come validato
                    const expDiv = document.getElementById(`exp-result-${expIndex}`);
                    if (expDiv) {
                        const btn = expDiv.querySelector('button');
                        if (btn) btn.remove();
                        
                        const statusSpan = expDiv.querySelector('.status-pending');
                        if (statusSpan) {
                            statusSpan.classList.remove('status-pending');
                            statusSpan.classList.add('status-verified');
                            statusSpan.textContent = 'Validato';
                        }
                    }
                    
                    showToast('Esperienza validata e attestazione firmata!', 'success');
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
        
        async function approveRequest(requestId) {
            if (!confirm('Confermi l\'approvazione e la firma?')) return;
            
            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('request_id', requestId);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('attestationContent').textContent = JSON.stringify(data.attestation, null, 2);
                    document.getElementById('attestationModal').classList.remove('hidden');
                    document.getElementById('attestationModal').classList.add('flex');
                    document.querySelector(`[data-request-id="${requestId}"]`)?.remove();
                    showToast('Attestazione firmata!', 'success');
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
        
        async function rejectRequest(requestId) {
            if (!confirm('Rifiutare questa richiesta?')) return;
            
            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('request_id', requestId);
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    document.querySelector(`[data-request-id="${requestId}"]`)?.remove();
                    showToast('Richiesta rifiutata', 'info');
                } else throw new Error(data.error);
            } catch (err) {
                showToast(err.message, 'error');
            }
        }
        
        async function syncPending() {
            const btn = document.getElementById('syncPendingBtn');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sincronizzazione...';
            
            const formData = new FormData();
            formData.append('action', 'fetch_pending');
            
            try {
                const res = await fetch('', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    if (data.count > 0) {
                        showToast(`${data.count} nuove richieste trovate!`, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Nessuna nuova richiesta', 'info');
                    }
                    // Aggiorna contatore
                    const countEl = document.getElementById('pendingCount');
                    if (countEl) countEl.textContent = data.total_pending;
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
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
