<?php

declare(strict_types=1);

/**
 * CVerify - Setup Azienda
 * Script per generare le chiavi RSA e configurare il profilo aziendale.
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';

use CVerify\Crypto;
use CVerify\DNS;

// Configurazione
define('COMPANY_DATA_DIR', __DIR__ . '/data');
define('PRIVATE_KEY_FILE', COMPANY_DATA_DIR . '/private_key.pem');
define('PUBLIC_KEY_FILE', COMPANY_DATA_DIR . '/public_key.pem');
define('CONFIG_FILE', COMPANY_DATA_DIR . '/config.json');

// Crea la directory dati se non esiste
if (!is_dir(COMPANY_DATA_DIR)) {
    mkdir(COMPANY_DATA_DIR, 0700, true);
}

$crypto = new Crypto();
$dns = new DNS($crypto);

$message = '';
$messageType = '';
$dnsRecords = [];
$config = [];
$debugInfo = null;
$generatedPrivateKey = null;

// Carica configurazione esistente
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true) ?: [];
}

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'generate':
            $domain = trim($_POST['domain'] ?? '');
            $companyName = trim($_POST['company_name'] ?? '');
            $vatNumber = trim($_POST['vat_number'] ?? '');
            $passphrase = $_POST['passphrase'] ?? '';

            if (empty($domain)) {
                $message = 'Company domain is required.';
                $messageType = 'error';
                break;
            }

            if (empty($companyName)) {
                $message = 'Company name is required.';
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
                session_set_cookie_params([
                    'lifetime' => 3600,
                    'path' => '/',
                    'domain' => $_SERVER['HTTP_HOST'],
                    'secure' => true,      // HTTPS only
                    'httponly' => true,    // No JavaScript access
                    'samesite' => 'Strict' // CSRF protection
                ]);
                session_start();
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
                    'type' => 'company',
                    'domain' => $domain,
                    'company_name' => $companyName,
                    'vat_number' => $vatNumber,
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
                
                // Salva la chiave privata per mostrarla all'utente
                $generatedPrivateKey = $keyPair['privateKey'];
                
                $message = 'Chiavi aziendali generate con successo! IMPORTANTE: Salva la chiave privata mostrata sotto, ti servirà per accedere.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $message = 'Error generating keys: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'show_dns':
            if (!file_exists(PUBLIC_KEY_FILE) || !file_exists(CONFIG_FILE)) {
                $message = 'Generate keys first.';
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
                    $message = 'DNS verificato con successo! Il profilo CVerify aziendale è attivo.';
                    $messageType = 'success';
                } else {
                    // Messaggio più dettagliato
                    if ($currentDnsIdentity && $currentDnsIdentity !== $expectedIdentity) {
                        $message = 'I record DNS non corrispondono! Nel DNS: "' . substr($currentDnsIdentity, 0, 20) . '..." - Richiesto: "' . substr($expectedIdentity, 0, 20) . '..." - Devi AGGIORNARE i record DNS con i nuovi valori.';
                    } else {
                        $errorDetails = implode(', ', $result['errors']);
                        $message = 'Verifica DNS fallita: ' . $errorDetails;
                    }
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error in verification: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;

        case 'reset':
            // Elimina tutti i file
            //@unlink(PRIVATE_KEY_FILE);
            //@unlink(PUBLIC_KEY_FILE);
            @unlink(CONFIG_FILE);
            $config = [];
            $message = 'Configurazione aziendale resettata.';
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
    <title>CVerify - Setup Azienda</title>
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
        .btn-primary { background: #8b5cf6; color: white; }
        .btn-primary:hover { background: #7c3aed; }
        .btn-secondary { background: #475569; color: white; }
        .btn-secondary:hover { background: #334155; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .dns-record {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
            word-break: break-all;
        }
        .dns-record strong { color: #a78bfa; }
        .dns-record code {
            background: rgba(139, 92, 246, 0.2);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            color: #c4b5fd;
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
            color: #a78bfa;
        }
        .nav-links {
            text-align: center;
            margin-top: 2rem;
        }
        .nav-links a {
            color: #a78bfa;
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
        .company-badge {
            display: inline-block;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏢 CVerify - Setup Azienda <span class="company-badge">BUSINESS</span></h1>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$isConfigured): ?>
            <!-- Form Generazione Chiavi -->
            <div class="card">
                <h2>🔑 Genera Chiavi RSA Aziendali</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    
                    <label for="domain">Dominio aziendale *</label>
                    <input type="text" id="domain" name="domain" placeholder="azienda.com" required>
                    
                    <label for="company_name">Ragione Sociale *</label>
                    <input type="text" id="company_name" name="company_name" placeholder="Azienda S.r.l." required>
                    
                    <label for="passphrase">Passphrase chiave privata (opzionale)</label>
                    <input type="password" id="passphrase" name="passphrase" placeholder="Lascia vuoto per nessuna passphrase">
                    
                    <button type="submit" class="btn-primary">🔐 Genera Chiavi Aziendali</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Configurazione Esistente -->
            <div class="card">
                <h2>✅ Configurazione Aziendale Attiva</h2>
                
                <div class="info-box">
                    <label>Dominio</label>
                    <span><?= htmlspecialchars($config['domain'] ?? '') ?></span>
                </div>
                <div class="info-box">
                    <label>Ragione Sociale</label>
                    <span><?= htmlspecialchars($config['company_name'] ?? 'N/A') ?></span>
                </div>
                <?php if (!empty($config['vat_number'])): ?>
                <div class="info-box">
                    <label>Partita IVA</label>
                    <span><?= htmlspecialchars($config['vat_number']) ?></span>
                </div>
                <?php endif; ?>
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
                          onsubmit="return confirm('Sei sicuro? Questa azione eliminerà le chiavi aziendali.')">
                        <input type="hidden" name="action" value="reset">
                        <button type="submit" class="btn-danger">🗑️ Reset</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($generatedPrivateKey)): ?>
            <div class="card" style="background: rgba(239, 68, 68, 0.1); border: 2px solid #ef4444;">
                <h2>🔑 COMPANY PRIVATE KEY - SAVE IT NOW!</h2>
                <div class="warning-box" style="background: rgba(239, 68, 68, 0.2);">
                    ⚠️ <strong>WARNING:</strong> This key will be shown ONLY ONCE. 
                    Copy and save it in a safe place. You will need it to access your company account.
                    <br><br>
                    <strong>🔒 Tip:</strong> Store this key in a company password manager or a secure location accessible only to authorized personnel.
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

        <?php if (!empty($dnsRecords)): ?>
            <!-- Record DNS da configurare -->
            <div class="card">
                <h2>📡 Record DNS da Configurare</h2>
                
                <div class="warning-box">
                    ⚠️ <strong>IMPORTANTE:</strong> I record vanno inseriti sul dominio aziendale 
                    <strong><?= htmlspecialchars($config['domain'] ?? '') ?></strong> con Host/Name impostato su 
                    <code>@</code> (oppure lasciato vuoto, a seconda del provider).
                </div>

                <h3 style="margin-top: 1.5rem;">1️⃣ Record Identificativo:</h3>
                <div class="dns-record">
                    <strong>Host/Name:</strong> <code>@</code> (or leave empty)<br>
                    <strong>Type:</strong> <code>TXT</code><br>
                    <strong>Value/Content:</strong><br>
                    <code><?= htmlspecialchars($dnsRecords['identity']) ?></code>
                </div>

                <h3 style="margin-top: 1.5rem;">2️⃣ Record Chiave Pubblica:</h3>
                <?php foreach ($dnsRecords['public_key'] as $index => $record): ?>
                    <div class="dns-record">
                        <strong>Host/Name:</strong> <code>@</code> (or leave empty)<br>
                        <strong>Type:</strong> <code>TXT</code><br>
                        <strong>Value/Content (Part <?= $index + 1 ?>):</strong><br>
                        <code><?= htmlspecialchars($record['value']) ?></code>
                    </div>
                <?php endforeach; ?>

                <div class="dns-instructions">
                    <h3>📖 Configuration Instructions:</h3>
                    <ol>
                        <li>Access your registrar's DNS panel (e.g., Cloudflare, GoDaddy, Namecheap, OVH)</li>
                        <li>For each record above, create a new record <strong>TXT</strong></li>
                        <li>In the field <strong>Host</strong> or <strong>Name</strong>, enter <code>@</code> (or leave empty for main domain)</li>
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
                <h2>🔍 Debug DNS</h2>
                
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
                            ❌ <strong>I record NON corrispondono!</strong> Devi aggiornare i record DNS:
                            <ol style="margin: 0.5rem 0 0 1.5rem;">
                                <li>Elimina i vecchi record TXT che iniziano con <code>cverify-</code></li>
                                <li>Inserisci i nuovi record mostrati sopra</li>
                            </ol>
                        </div>
                    <?php elseif ($idsMatch): ?>
                        <div style="background: rgba(16,185,129,0.3); padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem; color: #34d399;">
                            ✅ <strong>Record identificativo corretto!</strong>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="debug-box">
                    <h4>Dominio interrogato:</h4>
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

        
        <div class="nav-links">
            <a href="dashboard.php">📊 Dashboard Azienda</a>
            <a href="login.php">🔐 Login Azienda</a>
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
        a.download = 'company_private_key.pem';
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
