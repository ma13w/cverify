<?php
/**
 * CVerify - Common Header Navigation
 * Include questo file in tutte le pagine per navigazione coerente.
 */

// Determina la pagina attiva
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null): string {
    global $currentPage, $currentDir;
    if ($dir && $currentDir === $dir) return 'active';
    if ($currentPage === $page) return 'active';
    return '';
}

// Calcola percorsi relativi
$basePath = '';
if (in_array($currentDir, ['user', 'company', 'verifier', 'includes'])) {
    $basePath = '../';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'CVerify' ?> - Professional Credential Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': {
                            50: '#f0f4f8',
                            100: '#d9e2ec',
                            200: '#bcccdc',
                            300: '#9fb3c8',
                            400: '#829ab1',
                            500: '#627d98',
                            600: '#486581',
                            700: '#334e68',
                            800: '#243b53',
                            900: '#102a43',
                            950: '#0a1929',
                        },
                        'slate': {
                            850: '#1a2332',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background: linear-gradient(135deg, #0a1929 0%, #102a43 50%, #0a1929 100%);
            min-height: 100vh;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card-hover:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.12);
        }
        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }
        .nav-link.active {
            color: #10b981;
        }
        .verified-glow {
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.25);
        }
        .pending-glow {
            box-shadow: 0 0 20px rgba(234, 179, 8, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
        }
        .input-field {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }
        .status-verified {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .status-pending {
            background: rgba(234, 179, 8, 0.15);
            border: 1px solid rgba(234, 179, 8, 0.3);
            color: #fbbf24;
        }
        .status-rejected {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .copy-feedback {
            animation: copyPulse 0.5s ease;
        }
        @keyframes copyPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .fade-in {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="text-gray-100">
    <!-- Navigation -->
    <nav class="border-b border-navy-800/50 bg-navy-950/80 backdrop-blur-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="<?= $basePath ?>index.php" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-xl flex items-center justify-center transform group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-bold text-white">CVerify</span>
                        <span class="hidden sm:inline text-xs text-navy-400 ml-2">Professional Credentials</span>
                    </div>
                </a>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-1">
                    <a href="<?= $basePath ?>user/dashboard.php" 
                       class="nav-link <?= isActive('dashboard', 'user') ?> px-4 py-2 text-sm font-medium text-navy-300 hover:text-white rounded-lg transition-colors">
                        <span class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>User Dashboard</span>
                        </span>
                    </a>
                    <a href="<?= $basePath ?>company/dashboard.php" 
                       class="nav-link <?= isActive('dashboard', 'company') ?> px-4 py-2 text-sm font-medium text-navy-300 hover:text-white rounded-lg transition-colors">
                        <span class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span>Company Portal</span>
                        </span>
                    </a>
                    <a href="<?= $basePath ?>verifier/index.php" 
                       class="nav-link <?= isActive('index', 'verifier') ?> px-4 py-2 text-sm font-medium text-navy-300 hover:text-white rounded-lg transition-colors">
                        <span class="flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>Verifier Lens</span>
                        </span>
                    </a>
                </div>

                <!-- Mobile menu button -->
                <button type="button" id="mobileMenuBtn" class="md:hidden p-2 rounded-lg text-navy-400 hover:text-white hover:bg-navy-800 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-navy-800/50">
            <div class="px-4 py-3 space-y-1">
                <a href="<?= $basePath ?>user/dashboard.php" class="block px-4 py-3 text-sm font-medium text-navy-300 hover:text-white hover:bg-navy-800/50 rounded-lg transition-colors <?= isActive('dashboard', 'user') === 'active' ? 'bg-navy-800/50 text-emerald-400' : '' ?>">
                    👤 User Dashboard
                </a>
                <a href="<?= $basePath ?>company/dashboard.php" class="block px-4 py-3 text-sm font-medium text-navy-300 hover:text-white hover:bg-navy-800/50 rounded-lg transition-colors <?= isActive('dashboard', 'company') === 'active' ? 'bg-navy-800/50 text-emerald-400' : '' ?>">
                    🏢 Company Portal
                </a>
                <a href="<?= $basePath ?>verifier/index.php" class="block px-4 py-3 text-sm font-medium text-navy-300 hover:text-white hover:bg-navy-800/50 rounded-lg transition-colors <?= isActive('index', 'verifier') === 'active' ? 'bg-navy-800/50 text-emerald-400' : '' ?>">
                    🔍 Verifier Lens
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        // Utility functions
        async function copyToClipboard(text, btnElement) {
            try {
                await navigator.clipboard.writeText(text);
                btnElement.classList.add('copy-feedback');
                const originalText = btnElement.innerHTML;
                btnElement.innerHTML = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
                setTimeout(() => {
                    btnElement.innerHTML = originalText;
                    btnElement.classList.remove('copy-feedback');
                }, 2000);
            } catch (err) {
                console.error('Copy failed:', err);
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-xl text-sm font-medium z-50 fade-in ${
                type === 'success' ? 'bg-emerald-500/90 text-white' : 
                type === 'error' ? 'bg-red-500/90 text-white' : 
                'bg-navy-700 text-white'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
