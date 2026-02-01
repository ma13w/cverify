<?php
/**
 * CVerify Relay Server - Homepage
 * Server centrale per il relay di richieste e attestazioni.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$stats = getStats();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CVerify Relay Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #0a0f1c 0%, #1a1f35 100%); min-height: 100vh; }
        .glass-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="text-white">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-2xl mb-6">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold mb-4">CVerify Relay Server</h1>
            <p class="text-gray-400 text-lg">Server centrale per il relay di richieste di validazione e attestazioni</p>
        </div>

        <!-- Stats -->
        <div class="grid md:grid-cols-4 gap-6 mb-12">
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-4xl font-bold text-yellow-400"><?= $stats['pending_requests'] ?></div>
                <div class="text-gray-400 mt-2">Richieste Pending</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-4xl font-bold text-emerald-400"><?= $stats['attestations'] ?></div>
                <div class="text-gray-400 mt-2">Attestazioni</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-4xl font-bold text-blue-400"><?= $stats['unique_users'] ?></div>
                <div class="text-gray-400 mt-2">Utenti Unici</div>
            </div>
            <div class="glass-card rounded-2xl p-6 text-center">
                <div class="text-4xl font-bold text-purple-400"><?= $stats['unique_companies'] ?></div>
                <div class="text-gray-400 mt-2">Aziende Uniche</div>
            </div>
        </div>

        <!-- API Endpoints -->
        <div class="glass-card rounded-2xl p-8 mb-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                </svg>
                API Endpoints
            </h2>
            
            <div class="space-y-4">
                <div class="bg-black/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-emerald-400 font-mono font-bold">POST</span>
                        <code class="text-gray-300">/api/request</code>
                    </div>
                    <p class="text-gray-400 text-sm">Invia una richiesta di validazione (utente → azienda)</p>
                </div>
                
                <div class="bg-black/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-emerald-400 font-mono font-bold">POST</span>
                        <code class="text-gray-300">/api/attestation</code>
                    </div>
                    <p class="text-gray-400 text-sm">Pubblica un'attestazione firmata (azienda → utente)</p>
                </div>
                
                <div class="bg-black/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-blue-400 font-mono font-bold">GET</span>
                        <code class="text-gray-300">/api/pending/{company_domain}</code>
                    </div>
                    <p class="text-gray-400 text-sm">Recupera richieste pending per un'azienda</p>
                </div>
                
                <div class="bg-black/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-blue-400 font-mono font-bold">GET</span>
                        <code class="text-gray-300">/api/attestations/{user_domain}</code>
                    </div>
                    <p class="text-gray-400 text-sm">Recupera attestazioni per un utente</p>
                </div>
                
                <div class="bg-black/30 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-blue-400 font-mono font-bold">GET</span>
                        <code class="text-gray-300">/api/feed</code>
                    </div>
                    <p class="text-gray-400 text-sm">Feed pubblico di tutte le attività recenti</p>
                </div>
            </div>
        </div>

        <!-- Public Feed -->
        <div class="glass-card rounded-2xl p-8">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                </svg>
                Feed Pubblico (Ultime Attività)
            </h2>
            
            <div id="publicFeed" class="space-y-3">
                <p class="text-gray-500 text-center py-8">Caricamento feed...</p>
            </div>
            
            <div class="mt-6 text-center">
                <a href="feed.php" class="text-blue-400 hover:text-blue-300 transition-colors">
                    Vedi feed completo →
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-12 text-gray-500 text-sm">
            <p>CVerify Relay Server v1.0 — Decentralized Professional Attestation Protocol</p>
        </div>
    </div>

    <script>
        async function loadFeed() {
            try {
                const res = await fetch('api/feed.php?limit=10');
                const data = await res.json();
                
                if (data.success && data.feed.length > 0) {
                    document.getElementById('publicFeed').innerHTML = data.feed.map(item => `
                        <div class="bg-black/30 rounded-xl p-4 flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm ${
                                item.type === 'request' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-emerald-500/20 text-emerald-400'
                            }">
                                ${item.type === 'request' ? '📤' : '✅'}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-white font-medium">${item.type === 'request' ? 'Nuova Richiesta' : 'Attestazione'}</span>
                                    <span class="text-gray-500 text-xs">${new Date(item.timestamp).toLocaleString('it-IT')}</span>
                                </div>
                                <p class="text-gray-400 text-sm mt-1">
                                    <span class="text-blue-400">${item.user_domain}</span>
                                    ${item.type === 'request' ? '→' : '←'}
                                    <span class="text-purple-400">${item.company_domain}</span>
                                </p>
                                <p class="text-gray-500 text-xs mt-1">${item.role || ''}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('publicFeed').innerHTML = '<p class="text-gray-500 text-center py-8">Nessuna attività recente</p>';
                }
            } catch (err) {
                document.getElementById('publicFeed').innerHTML = '<p class="text-red-400 text-center py-8">Errore caricamento feed</p>';
            }
        }
        
        loadFeed();
        setInterval(loadFeed, 30000); // Refresh ogni 30 secondi
    </script>
</body>
</html>
