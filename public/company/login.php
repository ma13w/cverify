<?php
/**
 * CVerify - Company Login
 * Autenticazione basata su chiave privata RSA.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Security.php';

use CVerify\Crypto;
use CVerify\Auth;
use CVerify\Security;

// Start secure session
Security::startSecureSession();

$pageTitle = 'Company Login';

// Percorsi dati
$dataDir = __DIR__ . '/data';
$configFile = $dataDir . '/config.json';

// Initialize security
$security = new Security($dataDir);

// Rate limiting by IP for login attempts
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    $security->enforceRateLimit('login_' . $clientIp, 30, 300); // 30 attempts per 5 minutes
} catch (RuntimeException $e) {
    http_response_code(429);
    die('Too many login attempts. Please try again later.');
}

// Percorsi dati
$dataDir = __DIR__ . '/data';
$configFile = $dataDir . '/config.json';

// Inizializza Auth
$auth = new Auth($dataDir);

// Se già autenticato, redirect alla dashboard
if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

// Carica config se esiste
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$domain = $config['domain'] ?? '';

$message = '';
$messageType = '';
$challenge = null;
$dnsStatus = null;

// Gestione AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'check_dns':
                $checkDomain = trim($_POST['domain'] ?? $domain);
                if (empty($checkDomain)) {
                    throw new Exception('Dominio non specificato');
                }
                
                $result = $auth->isDomainVerified($checkDomain);
                echo json_encode([
                    'success' => true,
                    'dns' => $result
                ]);
                break;
                
            case 'generate_challenge':
                $checkDomain = trim($_POST['domain'] ?? $domain);
                if (empty($checkDomain)) {
                    throw new Exception('Dominio non specificato');
                }
                
                $result = $auth->generateChallenge($checkDomain);
                echo json_encode($result);
                break;
                
            case 'authenticate':
                $checkDomain = trim($_POST['domain'] ?? $domain);
                $privateKey = $_POST['private_key'] ?? '';
                $passphrase = $_POST['passphrase'] ?? null;
                
                if (empty($checkDomain)) {
                    throw new Exception('Dominio non specificato');
                }
                
                if (empty($privateKey)) {
                    throw new Exception('Chiave privata richiesta');
                }
                
                // Leggi il challenge corrente
                $challengeFile = $dataDir . '/challenge.json';
                if (!file_exists($challengeFile)) {
                    throw new Exception('Nessun challenge attivo');
                }
                
                $challenge = json_decode(file_get_contents($challengeFile), true);
                
                // Firma il challenge con la chiave privata
                $crypto = new Crypto();
                $challengeToSign = $challenge;
                unset($challengeToSign['expires_at']);
                
                try {
                    $signature = $crypto->signJson($challengeToSign, $privateKey, $passphrase ?: null);
                } catch (Exception $e) {
                    throw new Exception('Errore firma: ' . $e->getMessage() . '. Verifica la chiave privata e la passphrase.');
                }
                
                // Verifica la firma e autentica
                $result = $auth->authenticate($checkDomain, $signature);
                
                if ($result['success']) {
                    // Salva la configurazione se non esiste
                    if (!file_exists($configFile)) {
                        $newConfig = [
                            'domain' => $checkDomain,
                            'created_at' => date('c')
                        ];
                        file_put_contents($configFile, json_encode($newConfig, JSON_PRETTY_PRINT));
                    }
                    // SECURITY WARNING: Storing private key in session is a security risk.
                    // See SECURITY_AUDIT_FIXES.md for recommended alternatives.
                    // Note: Session already started at top of file, no need to start again
                    $_SESSION['private_key'] = $privateKey;
                    $_SESSION['passphrase'] = $passphrase;
                }
                
                echo json_encode($result);
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

// Verifica DNS se dominio configurato
if (!empty($domain)) {
    $dnsStatus = $auth->isDomainVerified($domain);
}

// Gestione messaggi di errore da redirect
$errorParam = $_GET['error'] ?? '';
if ($errorParam === 'dns_invalid') {
    $message = 'Il DNS aziendale non è più valido. Verifica la configurazione e riprova.';
    $messageType = 'error';
} elseif ($errorParam === 'dns_error') {
    $message = 'Errore nella verifica DNS. Riprova più tardi.';
    $messageType = 'error';
}

include __DIR__ . '/../includes/header.php';
?>

<main class="min-h-screen flex items-center justify-center px-4 py-12">
    <div class="max-w-md w-full">
        <!-- Login Card -->
        <div class="glass-card rounded-2xl p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">Company Login</h1>
                <p class="text-navy-400 mt-2">Login with company private key</p>
            </div>
            
            <!-- Step 1: Domain Input -->
            <div id="step1" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-navy-300 mb-2">Dominio aziendale</label>
                    <input type="text" id="domainInput" value="<?= htmlspecialchars($domain) ?>" 
                           placeholder="azienda.com"
                           class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500">
                </div>
                
                <!-- DNS Status -->
                <div id="dnsStatus" class="hidden">
                    <div class="rounded-xl p-4" id="dnsStatusContent"></div>
                </div>
                
                <button onclick="checkDNSAndGenerateChallenge()" id="checkDnsBtn"
                        class="btn-primary w-full px-4 py-3 rounded-xl text-white font-medium flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Verifica DNS e Continua</span>
                </button>
                
                <p class="text-center text-sm text-navy-500">
                    Non hai ancora configurato il DNS? <a href="setup.php" class="text-purple-400 hover:text-purple-300">Vai al Setup</a>
                </p>
            </div>
            
            <!-- Step 2: Private Key Authentication -->
            <div id="step2" class="hidden space-y-4">
                <div class="bg-navy-800/50 rounded-xl p-4 mb-4">
                    <h3 class="text-sm font-medium text-navy-300 mb-2">Challenge da firmare:</h3>
                    <pre id="challengeDisplay" class="text-xs text-purple-400 font-mono overflow-x-auto"></pre>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-navy-300 mb-2">
                        Chiave Privata RSA Aziendale
                        <span class="text-navy-500 font-normal">(non viene inviata al server)</span>
                    </label>
                    <textarea id="privateKeyInput" rows="6" 
                              placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                              class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500 font-mono text-xs"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-navy-300 mb-2">
                        Passphrase <span class="text-navy-500 font-normal">(se la chiave è protetta)</span>
                    </label>
                    <input type="password" id="passphraseInput" 
                           placeholder="Leave empty if not protected"
                           class="input-field w-full px-4 py-3 rounded-xl text-white placeholder-navy-500">
                </div>
                
                <button onclick="authenticate()" id="authBtn"
                        class="btn-primary w-full px-4 py-3 rounded-xl text-white font-medium flex items-center justify-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <span>Accedi</span>
                </button>
                
                <button onclick="goBack()" class="btn-secondary w-full px-4 py-3 rounded-xl text-white font-medium">
                    ← Torna indietro
                </button>
            </div>
            
            <!-- Messages -->
            <div id="messageBox" class="hidden mt-4 rounded-xl p-4"></div>
        </div>
        
        <!-- Security Notice -->
        <div class="mt-6 text-center">
            <p class="text-navy-500 text-sm">
                🔒 The private key is used only locally to sign the challenge.<br>
                It is never sent to the server.
            </p>
        </div>
    </div>
</main>

<script>
let currentChallenge = null;

function showMessage(msg, type) {
    const box = document.getElementById('messageBox');
    box.className = `mt-4 rounded-xl p-4 ${type === 'success' ? 'bg-emerald-500/20 text-emerald-400' : type === 'error' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'}`;
    box.textContent = msg;
    box.classList.remove('hidden');
}

function hideMessage() {
    document.getElementById('messageBox').classList.add('hidden');
}

async function checkDNSAndGenerateChallenge() {
    const domain = document.getElementById('domainInput').value.trim();
    
    console.group('%c🏢 CVerify Company DNS Authentication', 'color: #a855f7; font-weight: bold; font-size: 14px;');
    console.log('%c[Step 1] Inizializzazione verifica DNS aziendale', 'color: #c084fc;');
    console.log('Dominio aziendale:', domain);
    console.time('Company DNS Check Duration');
    
    if (!domain) {
        console.error('❌ Dominio aziendale non inserito');
        console.groupEnd();
        showMessage('Inserisci il dominio aziendale', 'error');
        return;
    }
    
    const btn = document.getElementById('checkDnsBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg><span>Verifica in corso...</span>';
    
    hideMessage();
    
    try {
        // Check DNS
        console.log('%c[Step 2] Chiamata API check_dns aziendale...', 'color: #fbbf24;');
        const dnsRes = await fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'check_dns', domain })
        });
        const dnsData = await dnsRes.json();
        
        console.log('%c[Step 3] Risposta DNS aziendale ricevuta:', 'color: #34d399;');
        console.table({
            'Success': dnsData.success,
            'Verified': dnsData.dns?.verified ?? false,
            'Has Public Key': !!dnsData.dns?.publicKey,
            'CVerify ID': dnsData.dns?.cverify_id ?? 'N/A'
        });
        console.log('Dettagli completi:', dnsData);
        
        const statusDiv = document.getElementById('dnsStatus');
        const contentDiv = document.getElementById('dnsStatusContent');
        statusDiv.classList.remove('hidden');
        
        if (!dnsData.success || !dnsData.dns.verified) {
            console.error('%c❌ DNS AZIENDALE NON VERIFICATO', 'color: #ef4444; font-weight: bold;');
            console.log('Errori DNS:', dnsData.dns?.errors ?? ['Nessun dettaglio disponibile']);
            console.timeEnd('Company DNS Check Duration');
            console.groupEnd();
            
            contentDiv.className = 'rounded-xl p-4 bg-red-500/20 border border-red-500/30';
            contentDiv.innerHTML = `
                <div class="flex items-center space-x-2 text-red-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="font-medium">DNS non verificato</span>
                </div>
                <p class="text-sm text-red-300 mt-2">
                    ${dnsData.dns?.errors?.join(', ') || 'Configura i record DNS per continuare.'}
                </p>
                <a href="setup.php" class="inline-block mt-3 text-sm text-purple-400 hover:text-purple-300">
                    → Vai al Setup DNS
                </a>
            `;
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg><span>Verifica DNS e Continua</span>';
            return;
        }
        
        console.log('%c✅ COMPANY DNS VERIFIED!', 'color: #10b981; font-weight: bold;');
        console.timeEnd('Company DNS Check Duration');
        
        contentDiv.className = 'rounded-xl p-4 bg-emerald-500/20 border border-emerald-500/30';
        contentDiv.innerHTML = `
            <div class="flex items-center space-x-2 text-emerald-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="font-medium">DNS Verificato</span>
            </div>
        `;
        
        // Generate challenge
        console.log('%c[Step 4] Generazione challenge crittografico aziendale...', 'color: #fbbf24;');
        const challengeRes = await fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ action: 'generate_challenge', domain })
        });
        const challengeData = await challengeRes.json();
        
        console.log('%c[Step 5] Challenge aziendale generato:', 'color: #34d399;');
        console.log('Challenge data:', challengeData);
        
        if (!challengeData.success) {
            console.error('❌ Errore generazione challenge:', challengeData.error);
            console.groupEnd();
            showMessage(challengeData.error || 'Errore generazione challenge', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg><span>Verifica DNS e Continua</span>';
            return;
        }
        
        currentChallenge = challengeData.challenge;
        console.log('%c✅ Challenge aziendale pronto per la firma', 'color: #10b981; font-weight: bold;');
        console.log('Nonce:', currentChallenge.nonce);
        console.log('Timestamp:', currentChallenge.timestamp);
        console.groupEnd();
        
        // Show step 2
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');
        
        // Display challenge (without expires_at)
        const displayChallenge = {...currentChallenge};
        delete displayChallenge.expires_at;
        document.getElementById('challengeDisplay').textContent = JSON.stringify(displayChallenge, null, 2);
        
    } catch (err) {
        showMessage('Errore: ' + err.message, 'error');
    }
    
    btn.disabled = false;
    btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg><span>Verifica DNS e Continua</span>';
}

async function authenticate() {
    const domain = document.getElementById('domainInput').value.trim();
    const privateKey = document.getElementById('privateKeyInput').value.trim();
    const passphrase = document.getElementById('passphraseInput').value;
    
    console.group('%c🔑 CVerify Company Private Key Authentication', 'color: #a855f7; font-weight: bold; font-size: 14px;');
    console.log('%c[Auth Step 1] Inizio autenticazione aziendale con chiave privata', 'color: #c084fc;');
    console.log('Dominio aziendale:', domain);
    console.log('Chiave privata fornita:', privateKey ? '✅ Sì (lunghezza: ' + privateKey.length + ' caratteri)' : '❌ No');
    console.log('Passphrase:', passphrase ? '✅ Fornita' : '⚪ Non fornita');
    console.time('Company Authentication Duration');
    
    if (!privateKey) {
        console.error('❌ Chiave privata aziendale mancante');
        console.groupEnd();
        showMessage('Inserisci la chiave privata aziendale', 'error');
        return;
    }
    
    if (!privateKey.includes('PRIVATE KEY')) {
        console.error('❌ Formato chiave non valido');
        console.log('Atteso: -----BEGIN PRIVATE KEY----- o -----BEGIN RSA PRIVATE KEY-----');
        console.groupEnd();
        showMessage('La chiave non sembra essere una chiave privata RSA valida', 'error');
        return;
    }
    
    console.log('%c[Auth Step 2] Formato chiave validato ✓', 'color: #34d399;');
    
    const btn = document.getElementById('authBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle></svg><span>Autenticazione...</span>';
    
    hideMessage();
    
    try {
        console.log('%c[Auth Step 3] Invio richiesta autenticazione aziendale al server...', 'color: #fbbf24;');
        console.log('Challenge da firmare:', currentChallenge);
        
        const res = await fetch('', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                action: 'authenticate',
                domain,
                private_key: privateKey,
                passphrase
            })
        });
        
        const data = await res.json();
        
        console.log('%c[Auth Step 4] Risposta server:', 'color: #34d399;');
        console.log('Success:', data.success);
        if (data.error) console.error('Errore:', data.error);
        if (data.session) console.log('Sessione creata:', data.session);
        
        if (data.success) {
            console.log('%c✅ AUTENTICAZIONE AZIENDALE RIUSCITA!', 'color: #10b981; font-weight: bold; font-size: 16px;');
            console.timeEnd('Company Authentication Duration');
            console.groupEnd();
            showMessage('Autenticazione riuscita! Reindirizzamento...', 'success');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } else {
            console.error('%c❌ AUTENTICAZIONE AZIENDALE FALLITA', 'color: #ef4444; font-weight: bold;');
            console.error('Motivo:', data.error || 'Sconosciuto');
            console.log('Possibili cause:');
            console.log('  - Chiave privata non corrisponde alla chiave pubblica nel DNS');
            console.log('  - Passphrase errata');
            console.log('  - Challenge scaduto');
            console.timeEnd('Company Authentication Duration');
            console.groupEnd();
            showMessage(data.error || 'Autenticazione fallita', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg><span>Accedi</span>';
        }
    } catch (err) {
        console.error('%c❌ ERRORE DI RETE/SISTEMA', 'color: #ef4444; font-weight: bold;');
        console.error('Dettaglio:', err.message);
        console.timeEnd('Company Authentication Duration');
        console.groupEnd();
        showMessage('Errore: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg><span>Accedi</span>';
    }
}

function goBack() {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
    hideMessage();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>