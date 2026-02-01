<?php
/**
 * CVerify - Verifier Lens
 * Portale pubblico per verificare le credenziali professionali.
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';
require_once __DIR__ . '/../src/Security.php';

use CVerify\Crypto;
use CVerify\DNS;
use CVerify\Security;

// Enable production mode (set to false for debugging)
define('PRODUCTION_MODE', true);

$pageTitle = 'Verifier Lens';

$verificationResult = null;
$profileUrl = $_GET['url'] ?? '';

// Gestione AJAX per verifica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        $url = trim($_POST['url'] ?? '');
        if (empty($url)) {
            throw new Exception('URL del profilo richiesto');
        }
        
        // Fetch CV JSON
        $cv = fetchCV($url);
        
        // Verifica identità utente via DNS
        $userDomain = $cv['domain'] ?? $cv['owner_domain'] ?? null;
        $identityResult = verifyUserIdentity($userDomain);
        
        // Verifica ogni esperienza/attestazione
        $experiences = [];
        foreach ($cv['experiences'] ?? [] as $exp) {
            $expResult = [
                'data' => $exp,
                'verified' => false,
                'issuer' => null,
                'attestation' => null,
                'crypto_details' => null
            ];
            
            if (!empty($exp['attestation'])) {
                $attResult = verifyAttestation($exp['attestation']);
                $expResult['verified'] = $attResult['verified'];
                $expResult['issuer'] = $attResult['issuer_domain'];
                $expResult['attestation'] = $exp['attestation'];
                $expResult['crypto_details'] = $attResult;
            }
            
            $experiences[] = $expResult;
        }
        
        echo json_encode([
            'success' => true,
            'cv' => $cv,
            'identity' => $identityResult,
            'experiences' => $experiences,
            'verified_count' => count(array_filter($experiences, fn($e) => $e['verified'])),
            'total_count' => count($experiences)
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function fetchCV(string $url): array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL non valido');
    }
    
    $context = stream_context_create([
        'http' => ['timeout' => 15, 'header' => "Accept: application/json\r\n"],
        'ssl' => ['verify_peer' => false]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception('Impossibile scaricare il profilo');
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception('JSON non valido');
    }
    
    return $data;
}

function verifyUserIdentity(?string $domain): array {
    $result = ['verified' => false, 'domain' => $domain, 'errors' => []];
    
    if (empty($domain)) {
        $result['errors'][] = 'Dominio mancante';
        return $result;
    }
    
    try {
        $dns = new DNS();
        $dnsResult = $dns->verifyDomain($domain);
        $result['verified'] = !empty($dnsResult['cverify_id']);
        $result['dns_data'] = $dnsResult;
    } catch (Exception $e) {
        $result['errors'][] = $e->getMessage();
    }
    
    return $result;
}

function verifyAttestation(array $attestation): array {
    $result = [
        'verified' => false,
        'issuer_domain' => $attestation['issuer_domain'] ?? null,
        'issued_at' => $attestation['issued_at'] ?? null,
        'experience_hash' => $attestation['experience_hash'] ?? null,
        'signature' => substr($attestation['signature'] ?? '', 0, 32) . '...',
        'errors' => []
    ];
    
    if (empty($result['issuer_domain'])) {
        $result['errors'][] = 'Issuer domain mancante';
        return $result;
    }
    
    try {
        // Recupera chiave pubblica issuer da DNS
        $dns = new DNS();
        $issuerPublicKey = $dns->getPublicKeyFromDNS($result['issuer_domain']);
        
        if ($issuerPublicKey) {
            // Verifica firma
            $dataToVerify = $attestation;
            unset($dataToVerify['signature']);
            unset($dataToVerify['encrypted']); // Campo aggiunto dal relay
            unset($dataToVerify['relayed_at']); // Campo aggiunto dal relay
            
            // Ordina le chiavi per garantire ordine consistente
            $dataToVerify = ksortRecursive($dataToVerify);
            
            $crypto = new Crypto();
            $verified = $crypto->verifySignature($dataToVerify, $attestation['signature'], $issuerPublicKey);
            $result['verified'] = $verified;
            $result['public_key_found'] = true;
            
            // Remove debug information in production mode
            if (!PRODUCTION_MODE) {
                $dataString = json_encode($dataToVerify, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $hash = hash('sha256', $dataString);
                $result['debug'] = [
                    'data_string' => $dataString,
                    'hash' => $hash
                ];
            }
        } else {
            $result['errors'][] = 'Chiave pubblica issuer non trovata nel DNS';
            $result['public_key_found'] = false;
        }
    } catch (Exception $e) {
        $result['errors'][] = $e->getMessage();
    }
    
    return $result;
}

function ksortRecursive(array $array): array {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $array[$key] = ksortRecursive($value);
        }
    }
    ksort($array);
    return $array;
}

include __DIR__ . '/../includes/header.php';
?>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Hero Search Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium mb-6">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Cryptographic Verification
            </div>
            
            <h1 class="text-4xl sm:text-5xl font-bold text-white mb-4">
                Verifier <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Lens</span>
            </h1>
            <p class="text-navy-300 text-lg max-w-2xl mx-auto mb-10">
                Verifica crittograficamente le credenziali professionali di qualsiasi utente CVerify.
            </p>
            
            <!-- Search Box -->
            <form id="verifyForm" class="max-w-3xl mx-auto">
                <div class="relative">
                    <input type="url" name="url" id="profileUrl" 
                           placeholder="https://dominio.com/cv.json" 
                           value="<?= htmlspecialchars($profileUrl) ?>"
                           class="w-full px-6 py-5 pr-32 rounded-2xl text-lg input-field text-white placeholder-navy-500 focus:ring-2 focus:ring-emerald-500/50" 
                           required>
                    <button type="submit" id="verifyBtn" 
                            class="absolute right-2 top-1/2 -translate-y-1/2 btn-primary px-6 py-3 rounded-xl font-medium flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Verifica</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Container -->
        <div id="results" class="hidden space-y-6 fade-in">
            <!-- Identity Card -->
            <div id="identityCard" class="glass-card rounded-2xl p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-center space-x-4">
                        <div id="identityAvatar" class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                            ?
                        </div>
                        <div>
                            <h2 id="identityDomain" class="text-xl font-bold text-white">-</h2>
                            <p id="identityStatus" class="text-navy-400 text-sm mt-1">Verificando...</p>
                        </div>
                    </div>
                    <div id="identityBadge" class="status-pending px-4 py-2 rounded-xl text-sm font-medium">
                        Checking...
                    </div>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-3 gap-4">
                <div class="glass-card rounded-xl p-4 text-center">
                    <div id="statTotal" class="text-3xl font-bold text-white">0</div>
                    <div class="text-xs text-navy-400">Esperienze</div>
                </div>
                <div class="glass-card rounded-xl p-4 text-center verified-glow">
                    <div id="statVerified" class="text-3xl font-bold text-emerald-400">0</div>
                    <div class="text-xs text-navy-400">Verificate</div>
                </div>
                <div class="glass-card rounded-xl p-4 text-center pending-glow">
                    <div id="statPending" class="text-3xl font-bold text-yellow-400">0</div>
                    <div class="text-xs text-navy-400">Non Verificate</div>
                </div>
            </div>

            <!-- Experiences List -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="p-6 border-b border-navy-800/50">
                    <h3 class="text-lg font-semibold text-white flex items-center space-x-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span>Esperienze Professionali</span>
                    </h3>
                </div>
                <div id="experiencesList" class="divide-y divide-navy-800/50">
                    <!-- Populated by JS -->
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loading" class="hidden text-center py-20">
            <svg class="w-16 h-16 mx-auto text-emerald-400 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-navy-400 mt-4">Verifica in corso...</p>
        </div>

        <!-- Error State -->
        <div id="errorState" class="hidden glass-card rounded-2xl p-8 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/20 flex items-center justify-center">
                <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Errore di Verifica</h3>
            <p id="errorMessage" class="text-navy-400">-</p>
        </div>
    </main>

    <!-- Crypto Details Modal -->
    <div id="cryptoModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="glass-card rounded-2xl max-w-2xl w-full overflow-hidden">
            <div class="p-6 border-b border-navy-800 flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Dettagli Crittografici</h3>
                    <p class="text-sm text-navy-400">Attestazione firmata digitalmente</p>
                </div>
                <button onclick="closeCryptoModal()" class="ml-auto text-navy-400 hover:text-white p-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="cryptoContent" class="p-6 space-y-4 max-h-96 overflow-auto">
                <!-- Populated by JS -->
            </div>
        </div>
    </div>

    <script>
        document.getElementById('verifyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const url = document.getElementById('profileUrl').value.trim();
            if (!url) return;
            
            // Show loading
            document.getElementById('results').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('loading').classList.remove('hidden');
            
            const formData = new FormData();
            formData.append('url', url);
            
            try {
                const res = await fetch('', { 
                    method: 'POST', 
                    body: formData, 
                    headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const data = await res.json();
                
                document.getElementById('loading').classList.add('hidden');
                
                if (data.success) {
                    displayResults(data);
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('errorState').classList.remove('hidden');
                document.getElementById('errorMessage').textContent = err.message;
            }
        });
        
        function displayResults(data) {
            const results = document.getElementById('results');
            results.classList.remove('hidden');
            
            // Identity Card
            const domain = data.cv.domain || data.cv.owner_domain || 'Unknown';
            document.getElementById('identityDomain').textContent = domain;
            document.getElementById('identityAvatar').textContent = domain.substring(0, 2).toUpperCase();
            
            if (data.identity.verified) {
                document.getElementById('identityBadge').className = 'status-verified px-4 py-2 rounded-xl text-sm font-medium flex items-center space-x-1';
                document.getElementById('identityBadge').innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg><span>DNS Verified</span>';
                document.getElementById('identityStatus').textContent = 'Identità confermata via DNS TXT record';
            } else {
                document.getElementById('identityBadge').className = 'status-pending px-4 py-2 rounded-xl text-sm font-medium';
                document.getElementById('identityBadge').textContent = 'DNS Not Verified';
                document.getElementById('identityStatus').textContent = data.identity.errors.join(', ') || 'DNS non configurato';
            }
            
            // Stats
            document.getElementById('statTotal').textContent = data.total_count;
            document.getElementById('statVerified').textContent = data.verified_count;
            document.getElementById('statPending').textContent = data.total_count - data.verified_count;
            
            // Experiences
            const list = document.getElementById('experiencesList');
            list.innerHTML = '';
            
            if (data.experiences.length === 0) {
                list.innerHTML = '<div class="p-8 text-center text-navy-400">Nessuna esperienza nel profilo</div>';
                return;
            }
            
            data.experiences.forEach((exp, idx) => {
                const expData = exp.data;
                list.innerHTML += `
                    <div class="p-6 hover:bg-navy-900/30 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h4 class="text-white font-semibold">${escapeHtml(expData.role || 'N/A')}</h4>
                                    ${exp.verified ? `
                                        <button onclick="showCryptoDetails(${idx})" class="status-verified px-3 py-1 rounded-full text-xs font-medium flex items-center space-x-1 cursor-pointer hover:opacity-80 transition-opacity">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                            <span>Verified by ${escapeHtml(exp.issuer)}</span>
                                        </button>
                                    ` : `
                                        <button onclick="showCryptoDetails(${idx})" class="status-pending px-3 py-1 rounded-full text-xs font-medium flex items-center space-x-1 cursor-pointer hover:opacity-80 transition-opacity">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            <span>Not Verified</span>
                                        </button>
                                    `}
                                </div>
                                <p class="text-navy-300 text-sm flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-navy-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                    <span class="font-mono">${escapeHtml(expData.company_domain || 'N/A')}</span>
                                </p>
                                ${expData.description ? `<p class="text-navy-400 text-sm mt-2">${escapeHtml(expData.description)}</p>` : ''}
                                <p class="text-navy-500 text-xs mt-2">📅 ${expData.start_date || 'N/A'} — ${expData.end_date || 'Presente'}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Store data for modal
            window.verificationData = data;
        }
        
        function showCryptoDetails(idx) {
            const exp = window.verificationData.experiences[idx];
            const details = exp.crypto_details;
            
            document.getElementById('cryptoContent').innerHTML = `
                <div class="space-y-4">
                    <div class="bg-black/30 rounded-xl p-4">
                        <div class="text-xs text-navy-400 mb-1">Issuer Domain</div>
                        <div class="text-emerald-400 font-mono">${escapeHtml(details.issuer_domain)}</div>
                    </div>
                    <div class="bg-black/30 rounded-xl p-4">
                        <div class="text-xs text-navy-400 mb-1">Issued At</div>
                        <div class="text-white font-mono">${escapeHtml(details.issued_at)}</div>
                    </div>
                    <div class="bg-black/30 rounded-xl p-4">
                        <div class="text-xs text-navy-400 mb-1">Experience Hash (SHA-256)</div>
                        <div class="text-purple-400 font-mono text-xs break-all">${escapeHtml(details.experience_hash)}</div>
                    </div>
                    <div class="bg-black/30 rounded-xl p-4">
                        <div class="text-xs text-navy-400 mb-1">Digital Signature (RSA-SHA256)</div>
                        <div class="text-blue-400 font-mono text-xs break-all">${escapeHtml(details.signature)}</div>
                    </div>
                    <div class="bg-black/30 rounded-xl p-4">
                        <div class="text-xs text-navy-400 mb-1">Debug Hash (SHA-256 of data)</div>
                        <div class="text-orange-400 font-mono text-xs break-all">${escapeHtml(details.debug?.hash || 'N/A')}</div>
                    </div>
                    <div class="flex items-center space-x-2 text-sm ${details.verified ? 'text-emerald-400' : 'text-red-400'}">
                        ${details.verified ? `
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            <span>Signature verified against DNS public key</span>
                        ` : `
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Verification failed: ${details.errors.join(', ')}</span>
                        `}
                    </div>
                </div>
            `;
            
            document.getElementById('cryptoModal').classList.remove('hidden');
            document.getElementById('cryptoModal').classList.add('flex');
        }
        
        function closeCryptoModal() {
            document.getElementById('cryptoModal').classList.add('hidden');
            document.getElementById('cryptoModal').classList.remove('flex');
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
