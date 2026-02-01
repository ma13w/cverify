<?php

declare(strict_types=1);

/**
 * CVerify - Gestione CV
 * Dashboard per gestire le esperienze lavorative e richiedere validazioni.
 * RICHIEDE AUTENTICAZIONE via chiave privata RSA.
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/ValidationRequest.php';

use CVerify\Crypto;
use CVerify\DNS;
use CVerify\Auth;

// Configurazione
define('USER_DATA_DIR', __DIR__ . '/data');
define('PRIVATE_KEY_FILE', USER_DATA_DIR . '/private_key.pem');
define('CONFIG_FILE', USER_DATA_DIR . '/config.json');
define('CV_FILE', __DIR__ . '/cv.json');

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

// Verifica che il DNS sia ancora valido
$session = $auth->getSession();
$config = file_exists(CONFIG_FILE) ? json_decode(file_get_contents(CONFIG_FILE), true) : [];

if (empty($config['domain'])) {
    header('Location: setup.php');
    exit;
}

// Verifica DNS prima di permettere operazioni
$crypto = new Crypto();
$dns = new DNS($crypto);

try {
    $dnsResult = $dns->verifyDomain($config['domain'], $config['fingerprint'] ?? null);
    if (!$dnsResult['valid']) {
        // DNS non più valido, logout forzato
        $auth->logout();
        header('Location: login.php?error=dns_invalid');
        exit;
    }
} catch (Exception $e) {
    // Errore verifica DNS
    $auth->logout();
    header('Location: login.php?error=dns_error');
    exit;
}

// Rinnova sessione
$auth->renewSession();

$validationRequest = new ValidationRequest($crypto);

$message = '';
$messageType = '';

// Carica o inizializza CV
function loadCV(): array {
    global $config;
    
    if (file_exists(CV_FILE)) {
        $cv = json_decode(file_get_contents(CV_FILE), true);
        if (is_array($cv)) {
            return $cv;
        }
    }
    
    return [
        'owner_domain' => $config['domain'] ?? '',
        'owner_name' => $config['owner_name'] ?? '',
        'fingerprint' => $config['fingerprint'] ?? '',
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'pdf_hash' => null,
        'experiences' => [],
    ];
}

function saveCV(array $cv): void {
    $cv['updated_at'] = date('c');
    file_put_contents(CV_FILE, json_encode($cv, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateExperienceId(): string {
    return 'exp_' . bin2hex(random_bytes(8));
}

$cv = loadCV();

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_experience':
            $role = trim($_POST['role'] ?? '');
            $companyDomain = trim($_POST['company_domain'] ?? '');
            $skills = trim($_POST['skills'] ?? '');
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $description = trim($_POST['description'] ?? '');
            
            if (empty($role) || empty($companyDomain)) {
                $message = 'Ruolo e dominio azienda sono obbligatori.';
                $messageType = 'error';
                break;
            }

            // Pulisci il dominio
            $companyDomain = preg_replace('#^https?://#', '', $companyDomain);
            $companyDomain = rtrim($companyDomain, '/');

            $experience = [
                'id' => generateExperienceId(),
                'role' => $role,
                'company_domain' => $companyDomain,
                'skills' => array_filter(array_map('trim', explode(',', $skills))),
                'start_date' => $startDate,
                'end_date' => $endDate ?: null,
                'description' => $description,
                'status' => 'pending',
                'validation_token' => null,
                'validated_at' => null,
                'created_at' => date('c'),
            ];

            $cv['experiences'][] = $experience;
            saveCV($cv);
            
            $message = 'Esperienza aggiunta con successo!';
            $messageType = 'success';
            break;

        case 'delete_experience':
            $expId = $_POST['experience_id'] ?? '';
            
            $cv['experiences'] = array_values(array_filter(
                $cv['experiences'],
                fn($exp) => $exp['id'] !== $expId
            ));
            saveCV($cv);
            
            $message = 'Esperienza eliminata.';
            $messageType = 'info';
            break;

        case 'request_validation':
            $expId = $_POST['experience_id'] ?? '';
            $passphrase = $_POST['passphrase'] ?? null;
            
            // Trova l'esperienza
            $expIndex = null;
            foreach ($cv['experiences'] as $index => $exp) {
                if ($exp['id'] === $expId) {
                    $expIndex = $index;
                    break;
                }
            }
            
            if ($expIndex === null) {
                $message = 'Esperienza non trovata.';
                $messageType = 'error';
                break;
            }

            $experience = $cv['experiences'][$expIndex];

            try {
                // Carica chiave privata
                $privateKey = $_SESSION['private_key'] ?? null;

                if (!$privateKey) {
                    throw new Exception('Chiave privata utente non configurata');
                }
                
                // Invia richiesta di validazione
                $result = $validationRequest->sendValidationRequest(
                    $experience,
                    $config['domain'],
                    $config['fingerprint'],
                    $privateKey,
                    $passphrase ?: null
                );

                if ($result['success']) {
                    // Aggiorna lo stato dell'esperienza
                    $cv['experiences'][$expIndex]['status'] = 'validation_requested';
                    $cv['experiences'][$expIndex]['validation_token'] = $result['token'];
                    $cv['experiences'][$expIndex]['validation_requested_at'] = date('c');
                    saveCV($cv);
                    
                    $message = 'Richiesta di validazione inviata con successo a ' . $experience['company_domain'];
                    $messageType = 'success';
                } else {
                    $message = 'Errore nell\'invio della richiesta: ' . $result['error'];
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Errore: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'update_pdf_hash':
            $pdfPath = $_POST['pdf_path'] ?? '';
            
            if (!empty($pdfPath) && file_exists($pdfPath)) {
                $cv['pdf_hash'] = hash_file('sha256', $pdfPath);
                saveCV($cv);
                $message = 'Hash PDF aggiornato.';
                $messageType = 'success';
            } elseif (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $cv['pdf_hash'] = hash_file('sha256', $_FILES['pdf_file']['tmp_name']);
                saveCV($cv);
                $message = 'Hash PDF aggiornato.';
                $messageType = 'success';
            } else {
                $message = 'Nessun file PDF valido fornito.';
                $messageType = 'error';
            }
            break;

        case 'refresh_status':
            // Ricarica il CV per vedere eventuali aggiornamenti
            $cv = loadCV();
            $message = 'Stato aggiornato.';
            $messageType = 'info';
            break;

        case 'import_attestation':
            // Importa attestazione da file JSON o da input diretto
            $attestationJson = '';
            
            // Da file upload
            if (isset($_FILES['attestation_file']) && $_FILES['attestation_file']['error'] === UPLOAD_ERR_OK) {
                $attestationJson = file_get_contents($_FILES['attestation_file']['tmp_name']);
            }
            // Da textarea
            elseif (!empty($_POST['attestation_json'])) {
                $attestationJson = $_POST['attestation_json'];
            }
            
            if (empty($attestationJson)) {
                $message = 'Nessuna attestazione fornita.';
                $messageType = 'error';
                break;
            }
            
            $attestationData = json_decode($attestationJson, true);
            
            if ($attestationData === null) {
                $message = 'JSON attestazione non valido.';
                $messageType = 'error';
                break;
            }
            
            // Valida struttura attestazione
            if (!isset($attestationData['attestation']) || !isset($attestationData['signature'])) {
                $message = 'Struttura attestazione non valida. Richiesti: attestation, signature.';
                $messageType = 'error';
                break;
            }
            
            $attestation = $attestationData['attestation'];
            $signature = $attestationData['signature'];
            $issuerPublicKey = $attestationData['issuer_public_key'] ?? null;
            
            // Verifica che l'attestazione sia per questo utente
            $subjectDomain = $attestation['subject']['domain'] ?? null;
            $subjectFingerprint = $attestation['subject']['fingerprint'] ?? null;
            
            if ($subjectDomain !== $config['domain']) {
                $message = 'Questa attestazione non è per il tuo dominio.';
                $messageType = 'error';
                break;
            }
            
            // Verifica firma crittografica
            $signatureValid = false;
            $issuerVerified = false;
            
            if ($issuerPublicKey) {
                try {
                    $signatureValid = $crypto->verifySignature($attestation, $signature, $issuerPublicKey);
                    
                    // Verifica anche che la chiave corrisponda al DNS dell'issuer
                    $issuerDomain = $attestation['issuer']['domain'] ?? null;
                    if ($issuerDomain) {
                        try {
                            $dnsKey = $dns->getPublicKeyFromDNS($issuerDomain);
                            if ($dnsKey !== null) {
                                // Confronta fingerprint delle chiavi
                                $dnsFingerprint = $crypto->getKeyFingerprint($dnsKey);
                                $attestationFingerprint = $crypto->getKeyFingerprint($issuerPublicKey);
                                $issuerVerified = ($dnsFingerprint === $attestationFingerprint);
                            }
                        } catch (Exception $e) {
                            // DNS non raggiungibile, ma firma valida
                            $issuerVerified = false;
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Errore verifica firma: ' . $e->getMessage();
                    $messageType = 'error';
                    break;
                }
            }
            
            if (!$signatureValid) {
                $message = 'Firma dell\'attestazione non valida!';
                $messageType = 'error';
                break;
            }
            
            // Aggiorna le esperienze nel CV
            $updatedCount = 0;
            $validatedExperiences = $attestation['validated_experiences'] ?? [];
            
            foreach ($validatedExperiences as $validatedExp) {
                $expId = $validatedExp['id'] ?? null;
                $expRole = $validatedExp['role'] ?? null;
                
                foreach ($cv['experiences'] as &$cvExp) {
                    // Match per ID o per ruolo + dominio azienda
                    $isMatch = false;
                    
                    if ($expId && isset($cvExp['id']) && $cvExp['id'] === $expId) {
                        $isMatch = true;
                    } elseif ($expRole && isset($cvExp['role']) && $cvExp['role'] === $expRole) {
                        // Verifica anche che il dominio corrisponda
                        $issuerDomain = strtolower($attestation['issuer']['domain'] ?? '');
                        $cvDomain = strtolower($cvExp['company_domain'] ?? '');
                        $issuerDomain = preg_replace('#^www\.#', '', $issuerDomain);
                        $cvDomain = preg_replace('#^www\.#', '', $cvDomain);
                        
                        if ($issuerDomain === $cvDomain) {
                            $isMatch = true;
                        }
                    }
                    
                    if ($isMatch && $cvExp['status'] !== 'validated') {
                        $cvExp['status'] = 'validated';
                        $cvExp['validated_at'] = date('c');
                        $cvExp['attestation'] = $attestationData;
                        $cvExp['attestation_verified'] = $signatureValid;
                        $cvExp['issuer_dns_verified'] = $issuerVerified;
                        $updatedCount++;
                    }
                }
            }
            
            if ($updatedCount > 0) {
                saveCV($cv);
                $verifiedNote = $issuerVerified ? ' (issuer verificato via DNS)' : ' (firma valida, DNS non verificato)';
                $message = "Importate $updatedCount esperienze validate" . $verifiedNote;
                $messageType = 'success';
            } else {
                $message = 'Nessuna esperienza corrispondente trovata nel CV.';
                $messageType = 'info';
            }
            break;
    }
    
    // Ricarica CV dopo modifiche
    $cv = loadCV();
}

// Funzione per badge status
function getStatusBadge(string $status): string {
    $badges = [
        'pending' => ['⏳', 'In attesa', '#ffc107'],
        'validation_requested' => ['📨', 'Richiesta inviata', '#17a2b8'],
        'validated' => ['✅', 'Validato', '#28a745'],
        'rejected' => ['❌', 'Rifiutato', '#dc3545'],
    ];
    
    $badge = $badges[$status] ?? ['❓', 'Sconosciuto', '#6c757d'];
    
    return sprintf(
        '<span class="status-badge" style="background: %s20; color: %s; border: 1px solid %s;">%s %s</span>',
        $badge[2], $badge[2], $badge[2], $badge[0], $badge[1]
    );
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CVerify - Gestione CV</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #e4e4e4;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 0.5rem;
            color: #00d4ff;
        }
        .subtitle {
            text-align: center;
            color: #a0a0a0;
            margin-bottom: 2rem;
        }
        .card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .card h2 {
            color: #00d4ff;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #a0a0a0;
            font-size: 0.9rem;
        }
        input[type="text"],
        input[type="date"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            font-size: 1rem;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #00d4ff;
        }
        button {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00d4ff, #0099cc);
            color: #000;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,212,255,0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .btn-success {
            background: #28a745;
            color: #fff;
        }
        .btn-danger {
            background: #dc3545;
            color: #fff;
        }
        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .message.success {
            background: rgba(40,167,69,0.2);
            border: 1px solid #28a745;
        }
        .message.error {
            background: rgba(220,53,69,0.2);
            border: 1px solid #dc3545;
        }
        .message.info {
            background: rgba(0,212,255,0.2);
            border: 1px solid #00d4ff;
        }
        .experience-card {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid #00d4ff;
        }
        .experience-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .experience-title {
            font-size: 1.1rem;
            color: #fff;
            font-weight: 600;
        }
        .experience-company {
            color: #00d4ff;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .experience-dates {
            color: #a0a0a0;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        .experience-skills {
            margin-top: 0.75rem;
        }
        .skill-tag {
            display: inline-block;
            background: rgba(0,212,255,0.2);
            color: #00d4ff;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .experience-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #a0a0a0;
        }
        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }
        .nav-links {
            text-align: center;
            margin-top: 2rem;
        }
        .nav-links a {
            color: #00d4ff;
            text-decoration: none;
            margin: 0 1rem;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .info-item {
            background: rgba(0,0,0,0.2);
            padding: 0.75rem;
            border-radius: 6px;
        }
        .info-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .info-item span {
            display: block;
            color: #fff;
            font-size: 0.9rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1a1a2e;
            padding: 2rem;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .modal-content h3 {
            color: #00d4ff;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📄 CVerify - Gestione CV</h1>
        <p class="subtitle">
            <?= htmlspecialchars($config['owner_name']) ?> • 
            <?= htmlspecialchars($config['domain']) ?>
        </p>

        <?php if ($message): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Informazioni CV -->
        <div class="card">
            <h2>📊 Riepilogo CV</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Esperienze</label>
                    <span><?= count($cv['experiences']) ?></span>
                </div>
                <div class="info-item">
                    <label>Validate</label>
                    <span><?= count(array_filter($cv['experiences'], fn($e) => $e['status'] === 'validated')) ?></span>
                </div>
                <div class="info-item">
                    <label>In Attesa</label>
                    <span><?= count(array_filter($cv['experiences'], fn($e) => in_array($e['status'], ['pending', 'validation_requested']))) ?></span>
                </div>
                <div class="info-item">
                    <label>Hash PDF</label>
                    <span><?= $cv['pdf_hash'] ? substr($cv['pdf_hash'], 0, 12) . '...' : 'Non impostato' ?></span>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
                <input type="hidden" name="action" value="update_pdf_hash">
                <div class="form-group">
                    <label>Carica PDF per generare hash</label>
                    <input type="file" name="pdf_file" accept=".pdf" 
                           style="background: transparent; border: none; color: #fff;">
                </div>
                <button type="submit" class="btn-secondary btn-small">Aggiorna Hash PDF</button>
            </form>
        </div>

        <!-- Aggiungi esperienza -->
        <div class="card">
            <h2>➕ Aggiungi Esperienza</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_experience">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Ruolo *</label>
                        <input type="text" id="role" name="role" 
                               placeholder="es. Senior Developer" required>
                    </div>
                    <div class="form-group">
                        <label for="company_domain">Dominio Azienda *</label>
                        <input type="text" id="company_domain" name="company_domain" 
                               placeholder="es. acme-corp.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="skills">Skills (separate da virgola)</label>
                    <input type="text" id="skills" name="skills" 
                           placeholder="es. PHP, Laravel, MySQL, REST API">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Data Inizio</label>
                        <input type="date" id="start_date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label for="end_date">Data Fine (vuoto se attuale)</label>
                        <input type="date" id="end_date" name="end_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Descrizione</label>
                    <textarea id="description" name="description" 
                              placeholder="Descrizione delle responsabilità e progetti..."></textarea>
                </div>
                
                <button type="submit" class="btn-primary">➕ Aggiungi Esperienza</button>
            </form>
        </div>

        <!-- Lista esperienze -->
        <div class="card">
            <h2>💼 Le Tue Esperienze</h2>
            
            <?php if (empty($cv['experiences'])): ?>
                <div class="empty-state">
                    <span>📋</span>
                    <p>Nessuna esperienza inserita.<br>Aggiungi la tua prima esperienza lavorativa!</p>
                </div>
            <?php else: ?>
                <?php foreach ($cv['experiences'] as $exp): ?>
                    <div class="experience-card">
                        <div class="experience-header">
                            <div>
                                <div class="experience-title"><?= htmlspecialchars($exp['role']) ?></div>
                                <div class="experience-company">🏢 <?= htmlspecialchars($exp['company_domain']) ?></div>
                                <div class="experience-dates">
                                    📅 <?= $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : 'N/A' ?>
                                    - <?= $exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : 'Presente' ?>
                                </div>
                            </div>
                            <?= getStatusBadge($exp['status']) ?>
                        </div>
                        
                        <?php if (!empty($exp['skills'])): ?>
                            <div class="experience-skills">
                                <?php foreach ($exp['skills'] as $skill): ?>
                                    <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($exp['description'])): ?>
                            <p style="margin-top: 0.75rem; color: #a0a0a0; font-size: 0.9rem;">
                                <?= nl2br(htmlspecialchars($exp['description'])) ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="experience-actions">
                            <?php if ($exp['status'] === 'pending'): ?>
                                <button type="button" class="btn-success btn-small" 
                                        onclick="openValidationModal('<?= $exp['id'] ?>', '<?= htmlspecialchars($exp['company_domain']) ?>')">
                                    📨 Richiedi Validazione
                                </button>
                            <?php elseif ($exp['status'] === 'validation_requested'): ?>
                                <span style="color: #17a2b8; font-size: 0.85rem;">
                                    ⏳ Richiesta inviata il <?= isset($exp['validation_requested_at']) ? date('d/m/Y', strtotime($exp['validation_requested_at'])) : 'N/A' ?>
                                </span>
                            <?php elseif ($exp['status'] === 'validated'): ?>
                                <span style="color: #28a745; font-size: 0.85rem;">
                                    ✅ Validato il <?= isset($exp['validated_at']) ? date('d/m/Y', strtotime($exp['validated_at'])) : 'N/A' ?>
                                </span>
                            <?php endif; ?>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Eliminare questa esperienza?')">
                                <input type="hidden" name="action" value="delete_experience">
                                <input type="hidden" name="experience_id" value="<?= $exp['id'] ?>">
                                <button type="submit" class="btn-danger btn-small">🗑️ Elimina</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Importa Attestazione -->
        <div class="card">
            <h2>📥 Importa Attestazione</h2>
            <p style="color: #a0a0a0; margin-bottom: 1rem;">
                Se hai ricevuto un'attestazione firmata da un'azienda (file JSON), puoi importarla qui 
                per aggiornare automaticamente lo stato delle tue esperienze a "Validato".
            </p>
            <button type="button" class="btn-primary" onclick="openImportModal()">
                📥 Importa Attestazione
            </button>
        </div>

        <div class="nav-links">
            <a href="setup.php">🔐 Setup Chiavi</a>
            <a href="cv.json" target="_blank">📄 Visualizza cv.json</a>
        </div>
    </div>

    <!-- Modal per validazione -->
    <div id="validationModal" class="modal">
        <div class="modal-content">
            <h3>📨 Richiedi Validazione</h3>
            <p style="margin-bottom: 1rem; color: #a0a0a0;">
                Stai per inviare una richiesta di validazione a <strong id="modalCompanyDomain"></strong>
            </p>
            <form method="POST" id="validationForm">
                <input type="hidden" name="action" value="request_validation">
                <input type="hidden" name="experience_id" id="modalExperienceId">
                
                <?php if ($config['has_passphrase'] ?? false): ?>
                    <div class="form-group">
                        <label>Passphrase chiave privata</label>
                        <input type="password" name="passphrase" placeholder="Inserisci passphrase">
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn-success">📨 Invia Richiesta</button>
                    <button type="button" class="btn-secondary" onclick="closeValidationModal()">Annulla</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal per importazione attestazione -->
    <div id="importModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <h3>📥 Importa Attestazione</h3>
            <p style="margin-bottom: 1rem; color: #a0a0a0;">
                Importa un'attestazione firmata ricevuta da un'azienda per validare le tue esperienze.
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_attestation">
                
                <div class="form-group">
                    <label>Carica file JSON attestazione</label>
                    <input type="file" name="attestation_file" accept=".json,application/json" 
                           style="background: transparent; border: none; color: #fff;">
                </div>
                
                <p style="text-align: center; color: #666; margin: 1rem 0;">— oppure —</p>
                
                <div class="form-group">
                    <label>Incolla JSON attestazione</label>
                    <textarea name="attestation_json" rows="8" 
                              placeholder='{"attestation": {...}, "signature": "...", "issuer_public_key": "..."}'></textarea>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn-primary">📥 Importa e Verifica</button>
                    <button type="button" class="btn-secondary" onclick="closeImportModal()">Annulla</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openValidationModal(expId, companyDomain) {
            document.getElementById('modalExperienceId').value = expId;
            document.getElementById('modalCompanyDomain').textContent = companyDomain;
            document.getElementById('validationModal').classList.add('active');
        }
        
        function closeValidationModal() {
            document.getElementById('validationModal').classList.remove('active');
        }
        
        function openImportModal() {
            document.getElementById('importModal').classList.add('active');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.remove('active');
        }
        
        // Chiudi modal cliccando fuori
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
