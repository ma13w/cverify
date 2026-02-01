<?php
/**
 * CVerify - Landing Page
 * Pagina principale che presenta il protocollo e i tre ruoli.
 */
$pageTitle = 'Home';
include 'includes/header.php';
?>

    <main>
        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-emerald-500/5 to-transparent"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32 relative">
                <div class="text-center max-w-4xl mx-auto">
                    <div class="inline-flex items-center px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-medium mb-8">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Powered by DNS + RSA Cryptography
                    </div>
                    
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Professional Credentials,
                        <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">
                            Cryptographically Verified
                        </span>
                    </h1>
                    
                    <p class="text-lg sm:text-xl text-navy-300 mb-10 max-w-2xl mx-auto leading-relaxed">
                        CVerify is a decentralized protocol allowing companies to attest 
                        professional work experiences using DNS-verifiable digital signatures.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row justify-center gap-4">
                        <a href="verifier/index.php" class="btn-primary px-8 py-4 rounded-xl text-white font-semibold text-lg inline-flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span>Verifica un Profilo</span>
                        </a>
                        <a href="#roles" class="btn-secondary px-8 py-4 rounded-xl text-white font-medium text-lg inline-flex items-center justify-center space-x-2">
                            <span>Scopri Come Funziona</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="py-20 border-t border-navy-800/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-white mb-4">How It Works</h2>
                    <p class="text-navy-400 max-w-2xl mx-auto">
                        A trustless system where no central authority controls the data. 
                        Everything is mathematically verifiable.
                    </p>
                </div>
                
                <div class="grid md:grid-cols-4 gap-6">
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">1</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Generate Keys</h3>
                        <p class="text-sm text-navy-400">The user generates a pair of RSA keys and publishes the public key in their domain's DNS.</p>
                    </div>
                    
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">2</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Request Validation</h3>
                        <p class="text-sm text-navy-400">The user sends a signed request to the company they worked for.</p>
                    </div>
                    
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">3</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Company Signature</h3>
                        <p class="text-sm text-navy-400">The company verifies and cryptographically signs the attestation.</p>
                    </div>
                    
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">4</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Public Verification</h3>
                        <p class="text-sm text-navy-400">Anyone can mathematically verify the authenticity of the credentials.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Role Cards -->
        <section id="roles" class="py-20 border-t border-navy-800/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-white mb-4">Choose Your Role</h2>
                    <p class="text-navy-400 max-w-2xl mx-auto">
                        Three dedicated portals to manage every aspect of the attestation system.
                    </p>
                </div>
                
                <div class="grid lg:grid-cols-3 gap-8">
                    <!-- User Card -->
                    <a href="user/dashboard.php" class="glass-card glass-card-hover rounded-2xl p-8 group transition-all duration-300 hover:-translate-y-2">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3">User Dashboard</h3>
                        <p class="text-navy-400 mb-6">
                            Manage your digital identity, add work experiences and request validations from companies.
                        </p>
                        <ul class="space-y-2 text-sm text-navy-300 mb-6">
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Generate RSA keys</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Configura DNS</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Gestisci CV</span>
                            </li>
                        </ul>
                        <span class="inline-flex items-center text-blue-400 font-medium group-hover:text-blue-300">
                            Login
                            <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <!-- Company Card -->
                    <a href="company/dashboard.php" class="glass-card glass-card-hover rounded-2xl p-8 group transition-all duration-300 hover:-translate-y-2">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3">Company Portal</h3>
                        <p class="text-navy-400 mb-6">
                            Receive validation requests from professionals and sign the attestations.
                        </p>
                        <ul class="space-y-2 text-sm text-navy-300 mb-6">
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Gestisci richieste</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Firma attestazioni</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Configura DNS aziendale</span>
                            </li>
                        </ul>
                        <span class="inline-flex items-center text-purple-400 font-medium group-hover:text-purple-300">
                            Login
                            <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>

                    <!-- Verifier Card -->
                    <a href="verifier/index.php" class="glass-card glass-card-hover rounded-2xl p-8 group transition-all duration-300 hover:-translate-y-2">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3">Verifier Lens</h3>
                        <p class="text-navy-400 mb-6">
                            Cryptographically verify the authenticity of anyone's professional credentials.
                        </p>
                        <ul class="space-y-2 text-sm text-navy-300 mb-6">
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>DNS Identity Verification</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Valida firme RSA</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Visualizza report</span>
                            </li>
                        </ul>
                        <span class="inline-flex items-center text-emerald-400 font-medium group-hover:text-emerald-300">
                            Login
                            <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Trust Section -->
        <section class="py-20 border-t border-navy-800/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="glass-card rounded-3xl p-8 md:p-12 text-center">
                    <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
                        Why is CVerify Trustworthy?
                    </h2>
                    <p class="text-navy-300 max-w-3xl mx-auto mb-10">
                        Unlike centralized systems like LinkedIn, CVerify does not require trust in third parties. 
                        Everything is mathematically verifiable using open cryptographic standards.
                    </p>
                    
                    <div class="grid md:grid-cols-3 gap-8">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-emerald-400 mb-2">RSA-2048</div>
                            <div class="text-sm text-navy-400">Industry Standard Cryptography</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-emerald-400 mb-2">DNS TXT</div>
                            <div class="text-sm text-navy-400">Decentralized Infrastructure</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-emerald-400 mb-2">SHA-256</div>
                            <div class="text-sm text-navy-400">Guaranteed Integrity</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php include 'includes/footer.php'; ?>
