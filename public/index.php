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
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-mono font-semibold tracking-wide">DNS-BASED PKI &bull; STRONG ENCRYPTION</span>
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
                            <span>Verify a Profile</span>
                        </a>
                        <a href="#hosting" class="btn-secondary px-8 py-4 rounded-xl text-white font-medium text-lg inline-flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                            <span>Get Started</span>
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
                        <h3 class="text-lg font-semibold text-white mb-2">DNS Key Publication</h3>
                        <p class="text-sm text-navy-400">Publish your 2048-bit RSA public key via DNS TXT records. Zero infrastructure required.</p>
                    </div>
                    
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">2</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Signed Request</h3>
                        <p class="text-sm text-navy-400">User cryptographically signs a validation request with their private key.</p>
                    </div>
                    
                    <div class="glass-card rounded-2xl p-6 text-center relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-sm">3</div>
                        <div class="w-14 h-14 bg-navy-800 rounded-xl flex items-center justify-center mx-auto mb-4 mt-2">
                            <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">Cryptographic Attestation</h3>
                        <p class="text-sm text-navy-400">The company signs the work experience with their private key.</p>
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

        <!-- Hosting Options Section -->
        <section id="hosting" class="py-20 border-t border-navy-800/50 bg-gradient-to-b from-navy-900/50 to-transparent">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <div class="inline-flex items-center px-4 py-2 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-sm font-medium mb-6">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                        Deployment Options
                    </div>
                    <h2 class="text-3xl font-bold text-white mb-4">Get Your CVerify Instance</h2>
                    <p class="text-navy-400 max-w-2xl mx-auto">
                        CVerify requires your own domain to work. Choose how you want to run your instance.
                    </p>
                </div>
                
                <div class="grid lg:grid-cols-2 gap-8 max-w-5xl mx-auto">
                    <!-- Self-Hosted Option -->
                    <div class="glass-card rounded-2xl p-8 border-2 border-navy-700 hover:border-blue-500/50 transition-all duration-300">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-14 h-14 bg-gradient-to-br from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Self-Hosted</h3>
                                <p class="text-navy-400 text-sm">Full control on your infrastructure</p>
                            </div>
                        </div>
                        
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Deploy on your own server (VPS, shared hosting, etc.)</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Complete data ownership and privacy</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Open source — customize as you need</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Requires PHP 8.0+ and domain with DNS access</span>
                            </li>
                        </ul>
                        
                        <div class="flex items-center justify-between pt-6 border-t border-navy-700">
                            <div>
                                <span class="text-3xl font-bold text-white">Free</span>
                                <span class="text-navy-400 text-sm ml-2">forever</span>
                            </div>
                            <a href="https://github.com/ma13w/cverify" target="_blank" 
                               class="btn-secondary px-6 py-3 rounded-xl text-white font-medium inline-flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                <span>View on GitHub</span>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Managed Hosting Option -->
                    <div class="glass-card rounded-2xl p-8 border-2 border-purple-500/50 relative overflow-hidden">
                        <!-- Popular badge -->
                        <div class="absolute top-4 right-4">
                            <span class="px-3 py-1 bg-purple-500 text-white text-xs font-bold rounded-full">RECOMMENDED</span>
                        </div>
                        
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-14 h-14 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Managed Hosting</h3>
                                <p class="text-navy-400 text-sm">I handle everything for you</p>
                            </div>
                        </div>
                        
                        <ul class="space-y-3 mb-8">
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-purple-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Hosted on my servers with <strong class="text-white">your domain</strong></span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-purple-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Zero configuration — ready in 24 hours</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-purple-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Automatic updates, backups & SSL certificates</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-purple-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-navy-300">Priority support via email & chat</span>
                            </li>
                        </ul>
                        
                        <!-- .cv Domain Discount -->
                        <div class="bg-gradient-to-r from-emerald-500/20 to-teal-500/20 border border-emerald-500/30 rounded-xl p-4 mb-6">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-emerald-500/30 rounded-lg flex items-center justify-center">
                                    <span class="text-emerald-400 font-bold text-lg">.cv</span>
                                </div>
                                <div>
                                    <p class="text-emerald-400 font-semibold">Special Discount!</p>
                                    <p class="text-emerald-300/80 text-sm">Get <strong>20% off</strong> if you use a <code class="bg-emerald-500/20 px-1 rounded">.cv</code> domain</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-6 border-t border-navy-700">
                            <div>
                                <span class="text-3xl font-bold text-white">Contact me</span>
                                <span class="text-navy-400 text-sm block">for pricing</span>
                            </div>
                            <a href="#contact" 
                               class="btn-primary px-6 py-3 rounded-xl text-white font-medium inline-flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <span>Get in Touch</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact" class="py-20 border-t border-navy-800/50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="glass-card rounded-2xl p-8 md:p-12 text-center">
                    <div class="w-20 h-20 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    
                    <h2 class="text-3xl font-bold text-white mb-4">Let's Talk</h2>
                    <p class="text-navy-400 text-lg mb-8 max-w-2xl mx-auto">
                        Don't have a server? No technical skills? No problem!
                        Contact me to discuss your needs and get a custom quote for managed hosting.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row justify-center gap-4 mb-8">
                        <a href="mailto:me@calimatteo.it" 
                           class="btn-primary px-8 py-4 rounded-xl text-white font-semibold text-lg inline-flex items-center justify-center space-x-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>me@calimatteo.it</span>
                        </a>
                    </div>
                    
                    <div class="flex flex-wrap justify-center gap-6 text-sm text-navy-500">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Usually reply within 24h</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>No commitment required</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span>Free consultation</span>
                        </div>
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
                                <span>Request validations</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Manage CV</span>
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
                                <span>Generate corporate keys</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Approve/reject requests</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Configure corporate DNS</span>
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
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-3">Verifier Lens</h3>
                        <p class="text-navy-400 mb-6">
                            Verify anyone's professional credentials. No account required.
                        </p>
                        <ul class="space-y-2 text-sm text-navy-300 mb-6">
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Verify DNS identity</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Validate signatures</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>No registration</span>
                            </li>
                        </ul>
                        <span class="inline-flex items-center text-emerald-400 font-medium group-hover:text-emerald-300">
                            Start Verifying
                            <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </span>
                    </a>
                </div>
            </div>
        </section>

        <!-- Technical Info -->
        <section class="py-24 border-t border-navy-800/50 relative overflow-hidden">
            <div class="absolute inset-0 bg-navy-900/50"></div>
            <!-- Background Decoration -->
            <div class="absolute top-0 right-0 w-1/3 h-full bg-gradient-to-l from-emerald-500/5 to-transparent pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-1/3 h-full bg-gradient-to-r from-blue-500/5 to-transparent pointer-events-none"></div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-white mb-4">Security Architecture</h2>
                    <p class="text-navy-400 max-w-2xl mx-auto">
                        A robust, layered security model relying on established cryptographic primitives and the global DNS infrastructure.
                    </p>
                </div>
                
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <!-- Left: Technical Specs -->
                    <div class="space-y-6">
                        <div class="glass-card rounded-2xl p-6 border-l-4 border-l-emerald-500 transition-transform hover:translate-x-2">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.2-2.858.59-4.18"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-bold text-white">DNS Root of Trust</h3>
                                    <p class="text-navy-400 text-sm mt-1">
                                        Identity is anchored in the Domain Name System. Public keys are published as TXT records, making the domain owner the only entity capable of signing valid attestations for that organization.
                                    </p>
                                    <div class="mt-3 bg-navy-950 rounded px-3 py-2 font-mono text-xs text-emerald-400">
                                        cverify._domainkey.example.com IN TXT "v=CV1; k=rsa; p=MIIBIjANBg..."
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 border-l-4 border-l-blue-500 transition-transform hover:translate-x-2">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-bold text-white">Asymmetric Cryptography</h3>
                                    <p class="text-navy-400 text-sm mt-1">
                                        We use <strong>RSA-2048</strong> key pairs. The private key never leaves your secure environment. It is used solely to generate digital signatures that any third party can verify using the public key fetched from DNS.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-6 border-l-4 border-l-purple-500 transition-transform hover:translate-x-2">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center">
                                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-bold text-white">Immutable Integrity</h3>
                                    <p class="text-navy-400 text-sm mt-1">
                                        Every attestation is hashed using <strong>SHA-256</strong>. Changing even a single bit of the credential data invalidates the signature immediately, guaranteeing document integrity.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Visual Representation -->
                    <div class="relative">
                        <div class="glass-card rounded-2xl p-8 border border-navy-700 bg-navy-900/80">
                            <div class="space-y-6 font-mono text-sm leading-relaxed">
                                <div>
                                    <span class="text-navy-500">// 1. The document is hashed</span><br>
                                    <span class="text-purple-400">$hash</span> = <span class="text-yellow-400">hash</span>(<span class="text-string">'sha256'</span>, <span class="text-blue-300">$json_data</span>);
                                </div>
                                
                                <div class="pl-4 border-l-2 border-navy-700">
                                    <span class="text-gray-500">Output:</span> <span class="text-emerald-500">e3b0c44298fc1c149...</span>
                                </div>

                                <div>
                                    <span class="text-navy-500">// 2. Hash is signed with Private Key</span><br>
                                    <span class="text-purple-400">$signature</span> = <span class="text-yellow-400">rsa_sign</span>(<span class="text-purple-400">$hash</span>, <span class="text-red-400">$private_key</span>);
                                </div>

                                <div>
                                    <span class="text-navy-500">// 3. Anyone verifies with DNS Public Key</span><br>
                                    <span class="text-purple-400">$pub_key</span> = <span class="text-yellow-400">dns_get_record</span>(<span class="text-string">'company.com'</span>, DNS_TXT);<br>
                                    <span class="text-purple-400">$valid</span> = <span class="text-yellow-400">rsa_verify</span>(<span class="text-purple-400">$hash</span>, <span class="text-purple-400">$signature</span>, <span class="text-purple-400">$pub_key</span>);
                                </div>

                                <div class="text-center pt-4">
                                    <div class="inline-flex items-center space-x-2 bg-emerald-500/10 text-emerald-400 px-4 py-2 rounded-full border border-emerald-500/20">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="font-bold">VERIFIED_TRUE</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Floating Verification Badge (decoration) -->
                        <div class="absolute -bottom-6 -right-6 glass-card p-4 rounded-xl border border-emerald-500/30 bg-navy-900 animate-pulse hidden lg:block">
                             <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                <span class="text-emerald-400 font-mono text-xs">NO CENTRAL AUTHORITY</span>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php include 'includes/footer.php'; ?>
