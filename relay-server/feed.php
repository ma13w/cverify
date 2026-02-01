<?php
/**
 * CVerify Relay - Full Public Feed Page
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Pubblico - CVerify Relay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: linear-gradient(135deg, #0a0f1c 0%, #1a1f35 100%); min-height: 100vh; }
        .glass-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="text-white">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold">Feed Pubblico</h1>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="filterFeed('all')" id="btn-all" class="px-4 py-2 rounded-lg text-sm font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30">
                    Tutti
                </button>
                <button onclick="filterFeed('request')" id="btn-request" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-500/20 text-gray-400 border border-gray-500/30">
                    Richieste
                </button>
                <button onclick="filterFeed('attestation')" id="btn-attestation" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-500/20 text-gray-400 border border-gray-500/30">
                    Attestazioni
                </button>
            </div>
        </div>

        <!-- Feed -->
        <div id="feedContainer" class="space-y-3">
            <p class="text-gray-500 text-center py-12">Caricamento...</p>
        </div>

        <!-- Load More -->
        <div id="loadMoreContainer" class="text-center mt-8 hidden">
            <button onclick="loadMore()" id="loadMoreBtn" class="px-6 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-medium transition-colors">
                Carica altri
            </button>
        </div>

        <!-- Auto-refresh indicator -->
        <div class="text-center mt-8 text-gray-500 text-sm">
            <span id="refreshIndicator">⏱️ Aggiornamento automatico ogni 30 secondi</span>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let currentOffset = 0;
        const limit = 50;
        let allItems = [];

        function renderFeedItem(item) {
            const isRequest = item.type === 'request';
            return `
                <div class="glass-card rounded-xl p-4 flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl ${
                        isRequest ? 'bg-yellow-500/10' : 'bg-emerald-500/10'
                    }">
                        ${isRequest ? '📤' : '✅'}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-semibold ${isRequest ? 'text-yellow-400' : 'text-emerald-400'}">
                                ${isRequest ? 'Richiesta di Validazione' : 'Attestazione Firmata'}
                            </span>
                            <span class="text-gray-500 text-sm">${new Date(item.timestamp).toLocaleString('it-IT')}</span>
                        </div>
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-blue-400 font-mono">${item.user_domain || 'N/A'}</span>
                            <span class="text-gray-600">${isRequest ? '→' : '←'}</span>
                            <span class="text-purple-400 font-mono">${item.company_domain || 'N/A'}</span>
                        </div>
                        ${item.role ? `<p class="text-gray-400 text-sm mt-2">👔 ${item.role}</p>` : ''}
                    </div>
                </div>
            `;
        }

        async function loadFeed(append = false) {
            try {
                const typeParam = currentFilter !== 'all' ? `&type=${currentFilter}` : '';
                const res = await fetch(`api/feed.php?limit=${limit}&offset=${currentOffset}${typeParam}`);
                const data = await res.json();

                if (data.success) {
                    if (append) {
                        allItems = [...allItems, ...data.feed];
                    } else {
                        allItems = data.feed;
                    }

                    if (allItems.length === 0) {
                        document.getElementById('feedContainer').innerHTML = 
                            '<p class="text-gray-500 text-center py-12">Nessuna attività trovata</p>';
                    } else {
                        document.getElementById('feedContainer').innerHTML = allItems.map(renderFeedItem).join('');
                    }

                    // Show/hide load more button
                    const loadMoreContainer = document.getElementById('loadMoreContainer');
                    if (data.feed.length >= limit && currentOffset + limit < data.total) {
                        loadMoreContainer.classList.remove('hidden');
                    } else {
                        loadMoreContainer.classList.add('hidden');
                    }
                }
            } catch (err) {
                document.getElementById('feedContainer').innerHTML = 
                    '<p class="text-red-400 text-center py-12">Errore nel caricamento del feed</p>';
            }
        }

        function loadMore() {
            currentOffset += limit;
            loadFeed(true);
        }

        function filterFeed(type) {
            currentFilter = type;
            currentOffset = 0;
            
            // Update button styles
            ['all', 'request', 'attestation'].forEach(t => {
                const btn = document.getElementById(`btn-${t}`);
                if (t === type) {
                    btn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30';
                } else {
                    btn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-gray-500/20 text-gray-400 border border-gray-500/30 hover:bg-gray-500/30';
                }
            });
            
            loadFeed();
        }

        // Initial load
        loadFeed();

        // Auto-refresh
        setInterval(() => {
            if (currentOffset === 0) { // Only refresh if on first page
                loadFeed();
            }
        }, 30000);
    </script>
</body>
</html>
