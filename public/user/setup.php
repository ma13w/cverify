<?php

declare(strict_types=1);

/**
 * CVerify - Setup Utente
 * Script per generare le chiavi RSA e configurare il profilo utente.
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';
require_once __DIR__ . '/../src/OlaCV.php'; // Add this include
require_once __DIR__ . '/../src/Security.php';

use CVerify\Crypto;
use CVerify\DNS;
use CVerify\OlaCV; // Add this use
use CVerify\Security;

// Start secure session
Security::startSecureSession();

// Configurazione
define('USER_DATA_DIR', __DIR__ . '/data');
define('PRIVATE_KEY_FILE', USER_DATA_DIR . '/private_key.pem');
define('PUBLIC_KEY_FILE', USER_DATA_DIR . '/public_key.pem');
define('CONFIG_FILE', USER_DATA_DIR . '/config.json');

// Crea la directory dati se non esiste
if (!is_dir(USER_DATA_DIR)) {
    mkdir(USER_DATA_DIR, 0700, true);
}

$crypto = new Crypto();
$dns = new DNS($crypto);

$message = '';
$messageType = '';
$dnsRecords = [];
$config = [];
$debugInfo = null;

// Carica configurazione esistente
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true) ?: [];
}

// NUOVO: Il dominio è SEMPRE quello dell'host corrente
$currentDomain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$currentDomain = preg_replace('#^www\.#', '', $currentDomain);
$currentDomain = preg_replace('#:\d+$#', '', $currentDomain);

if (empty($currentDomain) || $currentDomain === 'localhost') {
    die('Errore: Dominio non valido. CVerify richiede un dominio reale per funzionare.');
}

// Verifica che la config esistente corrisponda al dominio corrente
if (!empty($config['domain']) && $config['domain'] !== $currentDomain) {
    die('Errore: Questa installazione è configurata per il dominio "' . htmlspecialchars($config['domain']) . '" ma stai accedendo da "' . htmlspecialchars($currentDomain) . '".');
}

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'generate':
            // Usa sempre il dominio corrente, ignora input
            $domain = $currentDomain;
            $ownerName = trim($_POST['owner_name'] ?? '');
            $passphrase = $_POST['passphrase'] ?? '';

            if (empty($domain)) {
                $message = 'Domain is required.';
                $messageType = 'error';
                break;
            }

            // Normalizza dominio
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = preg_replace('#^www\.#', '', $domain);
            $domain = rtrim($domain, '/');

            try {
                // Genera coppia di chiavi
                $keyPair = $crypto->generateKeyPair($passphrase ?: null);

                // Session already started by Security::startSecureSession()
                $_SESSION['private_key'] = $keyPair['privateKey'];
                $_SESSION['passphrase'] = $passphrase;
                
                // Salva chiavi
                // file_put_contents(PRIVATE_KEY_FILE, $keyPair['privateKey']);
                // chmod(PRIVATE_KEY_FILE, 0600);
                
                // file_put_contents(PUBLIC_KEY_FILE, $keyPair['publicKey']);
                // chmod(PUBLIC_KEY_FILE, 0644);
                
                // Calcola fingerprint
                $fingerprint = $crypto->getKeyFingerprint($keyPair['publicKey']);
                
                // Salva configurazione
                $config = [
                    'domain' => $domain,
                    'owner_name' => $ownerName,
                    'fingerprint' => $fingerprint,
                    'created_at' => date('c'),
                    'has_passphrase' => !empty($passphrase),
                ];
                file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
                chmod(CONFIG_FILE, 0600);
                
                // Genera record DNS
                $dnsRecords = [
                    'identity' => $dns->generateDnsRecordForIdentity($fingerprint),
                    'public_key' => $dns->generateDnsRecordsForKey($keyPair['publicKey']),
                ];
                
                // AGGIUNGI: Salva la chiave privata per mostrarla all'utente
                $generatedPrivateKey = $keyPair['privateKey'];
                
                $message = 'Chiavi generate con successo! IMPORTANTE: Salva la chiave privata mostrata sotto, ti servirà per accedere.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $message = 'Error in key generation: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'show_dns':
            if (!file_exists(PUBLIC_KEY_FILE) || !file_exists(CONFIG_FILE)) {
                $message = 'Genera prima le chiavi.';
                $messageType = 'error';
                break;
            }

            try {
                $publicKey = file_get_contents(PUBLIC_KEY_FILE);
                $config = json_decode(file_get_contents(CONFIG_FILE), true);
                
                $dnsRecords = [
                    'identity' => $dns->generateDnsRecordForIdentity($config['fingerprint']),
                    'public_key' => $dns->generateDnsRecordsForKey($publicKey),
                ];
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'verify_dns':
            if (empty($config['domain'])) {
                $message = 'No domain configured.';
                $messageType = 'error';
                break;
            }

            try {
                $result = $dns->verifyDomain($config['domain'], $config['fingerprint']);
                $debugInfo = $dns->debugGetAllTxtRecords($config['domain']);
                
                // Recupera l'identità attualmente nel DNS per confronto
                $currentDnsIdentity = $result['cverify_id'] ?? null;
                $expectedIdentity = $config['fingerprint'];
                
                if ($result['valid']) {
                    $message = 'DNS verified with success! Your CVerify profile is active.';
                    $messageType = 'success';
                } else {
                    // Messaggio più dettagliato
                    if ($currentDnsIdentity && $currentDnsIdentity !== $expectedIdentity) {
                        $message = 'The DNS records do not correspond! In the DNS: "' . substr($currentDnsIdentity, 0, 20) . '..." - Requested: "' . substr($expectedIdentity, 0, 20) . '..." - You must UPDATE the DNS records with the new values.';
                    } else {
                        $errorDetails = implode(', ', $result['errors']);
                        $message = 'DNS verification failed: ' . $errorDetails;
                    }
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error in verification: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'ola_auto_configure':
            if (!file_exists(PUBLIC_KEY_FILE) || !file_exists(CONFIG_FILE)) {
                $message = 'Generate keys first.';
                $messageType = 'error';
                break;
            }

            $apiKey = trim($_POST['api_key'] ?? '');
            if (empty($apiKey)) {
                $message = 'OlaCV API Key required.';
                $messageType = 'error';
                break;
            }

            try {
                // Load config and records
                $publicKey = file_get_contents(PUBLIC_KEY_FILE);
                $config = json_decode(file_get_contents(CONFIG_FILE), true);
                
                // Only allow .cv domains
                if (!str_ends_with($config['domain'], '.cv')) {
                    throw new Exception('This feature is only available for .cv domains');
                }

                $dnsRecords = [
                    'identity' => $dns->generateDnsRecordForIdentity($config['fingerprint']),
                    'public_key' => $dns->generateDnsRecordsForKey($publicKey),
                ];

                // Configure via API
                $ola = new OlaCV($apiKey);
                $ola->configureRecords($config['domain'], $dnsRecords);

                $message = '✅ DNS records successfully configured on Ola.cv!';
                $messageType = 'success';
                
                // Force show DNS section
                $_POST['action'] = 'show_dns'; 
                
            } catch (Exception $e) {
                $message = 'OlaCV Error: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'reset':
            // Elimina tutti i file
            // @unlink(PRIVATE_KEY_FILE);
            // @unlink(PUBLIC_KEY_FILE);
            @unlink(CONFIG_FILE);
            $config = [];
            $message = 'Configuration reset.';
            $messageType = 'info';
            break;
    }
}

$isConfigured = !empty($config) && isset($_SESSION['private_key']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CVerify - Setup Utente</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            color: #e2e8f0;
            padding: 2rem;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { margin-bottom: 1.5rem; color: #fff; }
        h2 { margin-bottom: 1rem; color: #94a3b8; font-size: 1.25rem; }
        h3 { margin-bottom: 0.5rem; color: #cbd5e1; font-size: 1rem; }
        .card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .message.success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #34d399; }
        .message.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }
        .message.info { background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; color: #60a5fa; }
        label { display: block; margin-bottom: 0.5rem; color: #94a3b8; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 6px;
            color: #fff;
            margin-bottom: 1rem;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #3b82f6;
        }
        button {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-secondary { background: #475569; color: white; }
        .btn-secondary:hover { background: #334155; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .dns-record {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }
        .dns-record strong { color: #60a5fa; }
        .dns-record code {
            background: rgba(59, 130, 246, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            color: #93c5fd;
        }
        .dns-instructions {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .dns-instructions h3 { color: #fbbf24; }
        .dns-instructions ol {
            margin-left: 1.5rem;
            color: #fcd34d;
        }
        .dns-instructions li { margin-bottom: 0.5rem; }
        .info-box {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(0,0,0,0.2);
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        .info-box label { margin: 0; }
        .info-box span {
            font-family: monospace;
            color: #00d4ff;
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
        .nav-links a:hover { text-decoration: underline; }
        .debug-box {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: monospace;
            font-size: 0.75rem;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .debug-box h4 { color: #a78bfa; margin-bottom: 0.5rem; }
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #fbbf24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 CVerify - Setup Utente</h1>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$isConfigured): ?>
            <!-- Form Generazione Chiavi -->
            <div class="card">
                <h2>🔑 Generate RSA Keys</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    
                    <label for="domain">Your domain *</label>
                    <input type="text" id="domain" name="domain" placeholder="yourdomain.com" required>
                    
                    <label for="owner_name">Full name (optional)</label>
                    <input type="text" id="owner_name" name="owner_name" placeholder="John Doe">
                    
                    <label for="passphrase">Private key passphrase (optional)</label>
                    <input type="password" id="passphrase" name="passphrase" placeholder="Leave empty for no passphrase">
                    
                    <button type="submit" class="btn-primary">🔐 Generate Keys</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Configurazione Esistente -->
            <div class="card">
                <h2>✅ Active Configuration</h2>
                
                <div class="info-box">
                    <label>Dominio</label>
                    <span><?= htmlspecialchars($config['domain'] ?? '') ?></span>
                </div>
                <div class="info-box">
                    <label>Nome</label>
                    <span><?= htmlspecialchars($config['owner_name'] ?? 'N/A') ?></span>
                </div>
                <div class="info-box">
                    <label>Fingerprint</label>
                    <span><?= substr($config['fingerprint'] ?? '', 0, 32) ?>...</span>
                </div>
                <div class="info-box">
                    <label>Creato il</label>
                    <span><?= isset($config['created_at']) ? date('d/m/Y H:i', strtotime($config['created_at'])) : 'N/A' ?></span>
                </div>

                <div style="margin-top: 1.5rem;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="show_dns">
                        <button type="submit" class="btn-primary">📋 Mostra Record DNS</button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="verify_dns">
                        <button type="submit" class="btn-secondary">✅ Verifica DNS</button>
                    </form>
                    <form method="POST" style="display: inline;" 
                          onsubmit="return confirm('Sei sicuro? Questa azione eliminerà le tue chiavi.')">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="btn-danger">🗑️ Reset</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($dnsRecords)): ?>
            <!-- Record DNS da configurare -->
            <div class="card">
                <h2>📡 Record DNS da Configurare</h2>
                
                <!-- NEW: Check for .cv domain and show button -->
                <?php if (str_ends_with($config['domain'] ?? '', '.cv')): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="color: #34d399;">Ola.cv Domain Detected!</strong>
                        <p style="font-size: 0.9em; color: #a0a0a0; margin: 0;">You can configure these records automatically.</p>
                    </div>
                    <button type="button" onclick="showOlaPrompt()" class="btn-primary" style="background: #059669;">
                        ⚡ Auto Configure DNS
                    </button>
                </div>
                <!-- Hidden form for API submission -->
                <form id="olaForm" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="ola_auto_configure">
                    <input type="hidden" name="api_key" id="olaApiKey">
                </form>
                <script>
                function showOlaPrompt() {
                    const key = prompt("Please enter your Ola.cv API Key to authorize DNS updates:");
                    if (key) {
                        document.getElementById('olaApiKey').value = key;
                        document.getElementById('olaForm').submit();
                    }
                }
                </script>
                <?php endif; ?>

                <div class="warning-box">
                    ⚠️ <strong>IMPORTANT:</strong> The records must be inserted on the main domain 
                    <strong><?= htmlspecialchars($config['domain'] ?? '') ?></strong> with Host/Name set to 
                    <code>@</code> (or left blank, depending on the provider).
                </div>

                <h3 style="margin-top: 1.5rem;">1️⃣ Record Identificativo:</h3>
                <div class="dns-record">
                    <strong>Host/Name:</strong> <code>@</code> (or leave blank)<br>
                    <strong>Type:</strong> <code>TXT</code><br>
                    <strong>Value/Content:</strong><br>
                    <code><?= htmlspecialchars($dnsRecords['identity']) ?></code>
                </div>

                <h3 style="margin-top: 1.5rem;">2️⃣ Record Chiave Pubblica:</h3>
                <?php foreach ($dnsRecords['public_key'] as $index => $record): ?>
                    <div class="dns-record">
                        <strong>Host/Name:</strong> <code>@</code> (or leave blank)<br>
                        <strong>Type:</strong> <code>TXT</code><br>
                        <strong>Value/Content (Parte <?= $index + 1 ?>):</strong><br>
                        <code><?= htmlspecialchars($record['value']) ?></code>
                    </div>
                <?php endforeach; ?>

                <div class="dns-instructions">
                    <h3>📖 Instructions for configuration:</h3>
                    <ol>
                        <li>Access the DNS panel of your registrar (e.g. Cloudflare, GoDaddy, Aruba, OVH)</li>
                        <li>For each record above, create a new record <strong>TXT</strong></li>
                        <li>In the field <strong>Host</strong> or <strong>Name</strong>, insert <code>@</code> (or leave blank for the main domain)</li>
                        <li>In the field <strong>Value</strong> or <strong>Content</strong>, copy the entire value shown (starts with <code>cverify-</code>)</li>
                        <li>Save and wait for DNS propagation (usually a few minutes, max 48 hours)</li>
                        <li>Use the button <strong>"Verify DNS"</strong> to check the configuration</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($debugInfo): ?>
            <!-- Debug Info -->
            <div class="card">
                <h2>🔍 DNS Debug</h2>
                
                <?php 
                // Estrai l'identità attuale dal DNS
                $currentDnsId = null;
                foreach ($debugInfo['parsed_records'] as $rec) {
                    if (str_starts_with($rec, 'cverify-id=')) {
                        $currentDnsId = substr($rec, strlen('cverify-id='));
                        break;
                    }
                }
                $expectedId = $config['fingerprint'] ?? '';
                $idsMatch = $currentDnsId === $expectedId;
                ?>
                
                <!-- Confronto chiaro -->
                <div style="margin-bottom: 1.5rem;">
                    <h4 style="color: #fbbf24;">⚡ Confronto Record Identificativo:</h4>
                    <div class="info-box" style="background: <?= $currentDnsId ? 'rgba(239,68,68,0.2)' : 'rgba(100,100,100,0.2)' ?>;">
                        <label>Nel DNS ora:</label>
                        <span style="color: <?= $idsMatch ? '#34d399' : '#f87171' ?>;">
                            <?= $currentDnsId ? htmlspecialchars(substr($currentDnsId, 0, 40)) . '...' : '(non trovato)' ?>
                        </span>
                    </div>
                    <div class="info-box" style="background: rgba(16,185,129,0.2);">
                        <label>Richiesto:</label>
                        <span style="color: #34d399;"><?= htmlspecialchars(substr($expectedId, 0, 40)) ?>...</span>
                    </div>
                    <?php if ($currentDnsId && !$idsMatch): ?>
                        <div style="background: rgba(239,68,68,0.3); padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem; color: #f87171;">
                            ❌ <strong>The records do NOT correspond!</strong> You must update the DNS records:
                            <ol style="margin: 0.5rem 0 0 1.5rem;">
                                <li>Remove the old TXT records that start with <code>cverify-</code></li>
                                <li>Insert the new records shown above</li>
                            </ol>
                        </div>
                    <?php elseif ($idsMatch): ?>
                        <div style="background: rgba(16,185,129,0.3); padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem; color: #34d399;">
                            ✅ <strong>Record identificativo corretto!</strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="debug-box">
                    <h4>Domain interrogato:</h4>
                    <?= htmlspecialchars($debugInfo['domain']) ?>
                    
                    <h4 style="margin-top: 1rem;">Record TXT trovati (<?= count($debugInfo['parsed_records']) ?>):</h4>
                    <?php if (empty($debugInfo['parsed_records'])): ?>
                        Nessun record TXT trovato
                    <?php else: ?>
                        <?php foreach ($debugInfo['parsed_records'] as $i => $rec): ?>
[<?= $i ?>] <?= htmlspecialchars($rec) ?>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($generatedPrivateKey)): ?>
            <div class="card" style="background: rgba(239, 68, 68, 0.1); border: 2px solid #ef4444;">
                <h2>🔑 YOUR PRIVATE KEY - SAVE IT NOW!</h2>
                <div class="warning-box" style="background: rgba(239, 68, 68, 0.2);">
                    ⚠️ <strong>WARNING:</strong> This key will be shown ONLY ONCE. 
                    Copy and save it in a safe place. You will need it to access your account.
                </div>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px; background: #1a1a2e; color: #10b981; padding: 1rem; border-radius: 8px;"><?= htmlspecialchars($generatedPrivateKey) ?></textarea>
                <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Chiave copiata!');" class="btn-primary" style="margin-top: 1rem;">
                    📋 Copia Chiave Privata
                </button>
                <button type="button" onclick="downloadPrivateKey()" class="btn-primary" style="margin-top: 1rem;">
                     📥 Scarica Chiave Privata
                </button>
            </div>
        <?php endif; ?>

        <div class="nav-links">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="login.php">🔐 Login</a>
            <a href="../index.php">🏠 Home</a>
        </div>
    </div>

    <script>
                function downloadPrivateKey() {
                    var textarea = document.querySelector('textarea[readonly]');
                    if (!textarea) return;
                    var pem = textarea.value;
                    var blob = new Blob([pem], {type: 'application/x-pem-file'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'private_key.pem';
                    document.body.appendChild(a);
                    a.click();
                    setTimeout(function() {
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    }, 100);
                }
                </script>
</body>
</html>
