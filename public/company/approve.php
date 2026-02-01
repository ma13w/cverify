<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security.php';

use CVerify\Security;

// Start secure session
Security::startSecureSession();

/**
 * CVerify - Approvazione Validazione Aziendale
 * Script per creare e firmare attestazioni crittografiche.
 * 
 * Può essere usato sia come API (POST) che come download diretto (GET con ID).
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';

use CVerify\Crypto;
use CVerify\DNS;

// Configurazione
define('COMPANY_DATA_DIR', __DIR__ . '/data');
define('PENDING_FILE', COMPANY_DATA_DIR . '/pending_validations.json');
define('COMPANY_CONFIG_FILE', COMPANY_DATA_DIR . '/config.json');
define('PRIVATE_KEY_FILE', COMPANY_DATA_DIR . '/private_key.pem');
define('APPROVED_FILE', COMPANY_DATA_DIR . '/approved_validations.json');
define('ATTESTATIONS_DIR', COMPANY_DATA_DIR . '/attestations');

// Crea directory se non esistono
if (!is_dir(COMPANY_DATA_DIR)) {
    mkdir(COMPANY_DATA_DIR, 0755, true);
}
if (!is_dir(ATTESTATIONS_DIR)) {
    mkdir(ATTESTATIONS_DIR, 0755, true);
}

$crypto = new Crypto();
$dns = new DNS($crypto);

/**
 * Carica le validazioni pendenti.
 */
function loadPendingValidations(): array
{
    if (!file_exists(PENDING_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents(PENDING_FILE), true);
    return is_array($data) ? $data : [];
}

/**
 * Salva le validazioni pendenti.
 */
function savePendingValidations(array $validations): void
{
    file_put_contents(
        PENDING_FILE,
        json_encode($validations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * Carica le validazioni approvate.
 */
function loadApprovedValidations(): array
{
    if (!file_exists(APPROVED_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents(APPROVED_FILE), true);
    return is_array($data) ? $data : [];
}

/**
 * Salva le validazioni approvate.
 */
function saveApprovedValidations(array $validations): void
{
    file_put_contents(
        APPROVED_FILE,
        json_encode($validations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * Carica configurazione aziendale.
 */
function loadCompanyConfig(): array
{
    if (!file_exists(COMPANY_CONFIG_FILE)) {
        return [];
    }
    return json_decode(file_get_contents(COMPANY_CONFIG_FILE), true) ?: [];
}

/**
 * Crea un oggetto Attestation firmato.
 */
function createAttestation(
    array $validation,
    array $companyConfig,
    Crypto $crypto,
    string $privateKey,
    ?string $passphrase = null
): array {
    // Crea hash dei dati utente per integrità
    $userDataHash = hash('sha256', json_encode([
        'owner_domain' => $validation['owner_domain'],
        'owner_fingerprint' => $validation['owner_fingerprint'],
        'experiences' => $validation['experiences'],
    ]));

    // Costruisci l'oggetto Attestation
    $attestation = [
        'type' => 'cverify_attestation',
        'version' => '1.0',
        'attestation_id' => 'att_' . bin2hex(random_bytes(16)),
        
        // Dati dell'issuer (azienda)
        'issuer' => [
            'domain' => $companyConfig['domain'],
            'name' => $companyConfig['company_name'],
            'fingerprint' => $companyConfig['fingerprint'],
        ],
        
        // Dati del soggetto (utente)
        'subject' => [
            'domain' => $validation['owner_domain'],
            'name' => $validation['owner_name'] ?? null,
            'fingerprint' => $validation['owner_fingerprint'],
        ],
        
        // Hash dei dati originali dell'utente
        'user_data_hash' => $userDataHash,
        
        // Esperienze validate
        'validated_experiences' => array_map(function($exp) {
            return [
                'id' => $exp['id'] ?? null,
                'role' => $exp['role'],
                'skills' => $exp['skills'] ?? [],
                'start_date' => $exp['start_date'] ?? null,
                'end_date' => $exp['end_date'] ?? null,
                'validated' => true,
            ];
        }, $validation['experiences']),
        
        // Timestamp
        'issued_at' => date('c'),
        'issued_timestamp' => time(),
        
        // Token originale della richiesta
        'request_token' => $validation['token'],
    ];

    // Firma l'attestazione
    $signature = $crypto->signJson($attestation, $privateKey, $passphrase);

    // Crea documento completo firmato
    $signedAttestation = [
        'attestation' => $attestation,
        'signature' => $signature,
        'signature_algorithm' => 'RSA-SHA256',
        'issuer_public_key' => $companyConfig['public_key'],
    ];

    return $signedAttestation;
}

/**
 * Invia l'attestazione all'utente via callback.
 */
function sendAttestationToUser(string $userDomain, array $signedAttestation, string $token): array
{
    $domain = preg_replace('#^https?://#', '', $userDomain);
    $domain = rtrim($domain, '/');
    
    $callbackUrls = [
        'https://' . $domain . '/user/validation_callback.php',
        'https://' . $domain . '/cverify/callback',
        'http://' . $domain . '/user/validation_callback.php', // Fallback HTTP per dev
    ];
    
    $callbackData = [
        'callback_type' => 'validation_response',
        'version' => '1.0',
        'token' => $token,
        'status' => 'validated',
        'attestation' => $signedAttestation,
        'timestamp' => date('c'),
    ];
    
    $jsonData = json_encode($callbackData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    foreach ($callbackUrls as $url) {
        $ch = curl_init($url);
        
        if ($ch === false) continue;
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-CVerify-Version: 1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => false, // Per development
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'url' => $url,
                'http_code' => $httpCode,
            ];
        }
    }
    
    return [
        'success' => false,
        'error' => 'Impossibile contattare l\'utente su nessun endpoint',
    ];
}

// ==============================================
// GESTIONE RICHIESTE
// ==============================================

$config = loadCompanyConfig();

// Verifica configurazione con chave privata nella sessione
// if (empty($config) || !file_exists(PRIVATE_KEY_FILE)) {
//     if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['format'])) {
//         header('Content-Type: application/json');
//         http_response_code(500);
//         echo json_encode(['error' => 'Azienda non configurata']);
//         exit;
//     }
//     header('Location: dashboard.php');
//     exit;
// }
if (empty($config)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['format'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Azienda non configurata']);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

// GET: Download attestazione esistente
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download'])) {
    $attestationId = $_GET['download'];
    $attestationFile = ATTESTATIONS_DIR . '/' . $attestationId . '.json';
    
    if (!file_exists($attestationFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Attestazione non trovata']);
        exit;
    }
    
    $attestation = json_decode(file_get_contents($attestationFile), true);
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="attestation_' . $attestationId . '.json"');
    echo json_encode($attestation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: Processo di approvazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationId = $_POST['validation_id'] ?? $_GET['id'] ?? null;
    $passphrase = $_POST['passphrase'] ?? null;
    $sendCallback = ($_POST['send_callback'] ?? 'true') === 'true';
    
    // Risposta JSON se richiesto
    $jsonResponse = isset($_SERVER['HTTP_ACCEPT']) && 
                    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    
    if (empty($validationId)) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Validation ID missing']);
            exit;
        }
        header('Location: dashboard.php?error=missing_id');
        exit;
    }
    
    // Trova la validazione
    $pending = loadPendingValidations();
    $validationIndex = null;
    $validation = null;
    
    foreach ($pending as $index => $p) {
        if ($p['id'] === $validationId) {
            $validationIndex = $index;
            $validation = $p;
            break;
        }
    }
    
    if ($validation === null) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'Validazione non trovata']);
            exit;
        }
        header('Location: dashboard.php?error=not_found');
        exit;
    }
    
    try {
        // Carica chiave privata
        $privateKey = $_SESSION['private_key'] ?? null;
        if (!$privateKey) {
            throw new Exception('Chiave privata aziendale non configurata');
        }
        
        // Crea attestazione firmata
        $signedAttestation = createAttestation(
            $validation,
            $config,
            $crypto,
            $privateKey,
            $passphrase
        );
        
        $attestationId = $signedAttestation['attestation']['attestation_id'];
        
        // Salva attestazione su file
        $attestationFile = ATTESTATIONS_DIR . '/' . $attestationId . '.json';
        file_put_contents(
            $attestationFile,
            json_encode($signedAttestation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        // Aggiorna record validazione
        $validation['status'] = 'approved';
        $validation['approved_at'] = date('c');
        $validation['attestation_id'] = $attestationId;
        $validation['attestation'] = $signedAttestation;
        
        // Rimuovi da pending
        unset($pending[$validationIndex]);
        $pending = array_values($pending);
        savePendingValidations($pending);
        
        // Aggiungi ad approved
        $approved = loadApprovedValidations();
        $approved[] = $validation;
        saveApprovedValidations($approved);
        
        // Invia callback all'utente
        $callbackResult = ['success' => false];
        if ($sendCallback) {
            $callbackResult = sendAttestationToUser(
                $validation['owner_domain'],
                $signedAttestation,
                $validation['token']
            );
        }
        
        // Risposta
        if ($jsonResponse || isset($_GET['format']) && $_GET['format'] === 'json') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'attestation_id' => $attestationId,
                'attestation' => $signedAttestation,
                'callback_sent' => $callbackResult['success'],
                'callback_error' => $callbackResult['error'] ?? null,
                'download_url' => 'approve.php?download=' . urlencode($attestationId),
            ], JSON_PRETTY_PRINT);
            exit;
        }
        
        // Redirect con messaggio
        $msg = $callbackResult['success'] 
            ? 'approved_sent' 
            : 'approved_manual';
        header('Location: dashboard.php?success=' . $msg . '&attestation=' . urlencode($attestationId));
        exit;
        
    } catch (Exception $e) {
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// GET: Mostra form di approvazione o lista attestazioni
$validationId = $_GET['id'] ?? null;
$validation = null;

if ($validationId) {
    $pending = loadPendingValidations();
    foreach ($pending as $p) {
        if ($p['id'] === $validationId) {
            $validation = $p;
            break;
        }
    }
}

// Lista attestazioni approvate
$approved = loadApprovedValidations();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CVerify - Approvazione Validazione</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a3e 100%);
            min-height: 100vh;
            color: #e4e4e4;
            padding: 2rem;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 2rem; color: #ff6b35; }
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 { color: #ff6b35; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #a0a0a0; }
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            background: rgba(0,0,0,0.3);
            color: #fff;
        }
        button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-right: 0.5rem;
        }
        .btn-success { background: #28a745; color: #fff; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
        .info-box {
            background: rgba(0,0,0,0.3);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .info-box label { font-size: 0.75rem; text-transform: uppercase; color: #666; }
        .info-box span { display: block; color: #fff; margin-top: 0.25rem; }
        .attestation-item {
            background: rgba(0,0,0,0.2);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        a { color: #ff6b35; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .back-link { display: block; text-align: center; margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Approvazione Validazione</h1>

        <?php if ($validation): ?>
            <div class="card">
                <h2>Dettagli Richiesta</h2>
                
                <div class="info-box">
                    <label>Utente</label>
                    <span><?= htmlspecialchars($validation['owner_name'] ?? 'N/A') ?></span>
                </div>
                
                <div class="info-box">
                    <label>Dominio</label>
                    <span><?= htmlspecialchars($validation['owner_domain']) ?></span>
                </div>
                
                <div class="info-box">
                    <label>Esperienze da validare</label>
                    <?php foreach ($validation['experiences'] ?? [] as $exp): ?>
                        <span>• <?= htmlspecialchars($exp['role'] ?? 'N/A') ?></span>
                    <?php endforeach; ?>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="validation_id" value="<?= htmlspecialchars($validation['id']) ?>">
                    
                    <?php if ($config['has_passphrase'] ?? false): ?>
                        <div class="form-group">
                            <label>Private Key Passphrase</label>
                            <input type="password" name="passphrase" placeholder="Enter passphrase">
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="send_callback" value="true" checked>
                            Automatically send to user
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-success">✅ Approve and Sign</button>
                    <a href="dashboard.php" class="btn-secondary" style="display: inline-block; padding: 0.75rem 1.5rem; text-decoration: none;">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>📜 Issued Attestations</h2>
                
                <?php if (empty($approved)): ?>
                    <p style="color: #666;">No attestations issued.</p>
                <?php else: ?>
                    <?php foreach ($approved as $att): ?>
                        <div class="attestation-item">
                            <div>
                                <strong><?= htmlspecialchars($att['owner_name'] ?? $att['owner_domain']) ?></strong>
                                <br>
                                <small style="color: #666;">
                                    <?= isset($att['approved_at']) ? date('d/m/Y H:i', strtotime($att['approved_at'])) : 'N/A' ?>
                                </small>
                            </div>
                            <?php if (isset($att['attestation_id'])): ?>
                                <a href="approve.php?download=<?= urlencode($att['attestation_id']) ?>">
                                    📥 Download
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">← Torna alla Dashboard</a>
    </div>
</body>
</html>
