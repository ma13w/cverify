<?php

declare(strict_types=1);

namespace CVerify;

use RuntimeException;

require_once __DIR__ . '/Security.php';

/**
 * Sistema di autenticazione basato su chiave privata RSA.
 * L'utente si autentica firmando un challenge con la propria chiave privata.
 */
class Auth
{
    private const SESSION_LIFETIME = 3600; // 1 ora
    private const CHALLENGE_LIFETIME = 300; // 5 minuti
    
    private Crypto $crypto;
    private DNS $dns;
    private string $dataDir;
    private string $sessionFile;
    private string $challengeFile;
    
    public function __construct(string $dataDir, ?Crypto $crypto = null)
    {
        $this->dataDir = $dataDir;
        $this->crypto = $crypto ?? new Crypto();
        $this->dns = new DNS($this->crypto);
        $this->sessionFile = $dataDir . '/session.json';
        $this->challengeFile = $dataDir . '/challenge.json';
        
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * Verifica se l'utente è autenticato E se il DNS è ancora valido.
     */
    public function isAuthenticated(): bool
    {
        if (!file_exists($this->sessionFile)) {
            return false;
        }
        
        $session = json_decode(file_get_contents($this->sessionFile), true);
        
        if (!$session || !isset($session['authenticated'], $session['expires_at'])) {
            return false;
        }
        
        if (strtotime($session['expires_at']) < time()) {
            $this->logout();
            return false;
        }
        
        // NUOVO: Verifica periodica DNS (ogni 5 minuti)
        $lastDnsCheck = $session['last_dns_check'] ?? null;
        if ($lastDnsCheck === null || (time() - strtotime($lastDnsCheck)) > 300) {
            $domain = $session['domain'] ?? null;
            if ($domain) {
                try {
                    $dnsResult = $this->dns->verifyDomain($domain);
                    if (empty($dnsResult['cverify_id'])) {
                        $this->logout();
                        return false;
                    }
                    // Aggiorna timestamp verifica DNS
                    $session['last_dns_check'] = date('c');
                    file_put_contents($this->sessionFile, json_encode($session, JSON_PRETTY_PRINT));
                } catch (\Exception $e) {
                    // In caso di errore DNS, non fare logout immediato
                    // ma logga l'errore
                }
            }
        }
        
        return $session['authenticated'] === true;
    }
    
    /**
     * Verifica se il dominio ha i record DNS configurati correttamente.
     */
    public function isDomainVerified(string $domain): array
    {
        try {
            $result = $this->dns->verifyDomain($domain);
            return [
                'verified' => $result['valid'],
                'has_identity' => !empty($result['cverify_id']),
                'has_public_key' => !empty($result['publicKey']),
                'errors' => $result['errors'] ?? [],
                'public_key' => $result['publicKey'] ?? null
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'has_identity' => false,
                'has_public_key' => false,
                'errors' => [$e->getMessage()],
                'public_key' => null
            ];
        }
    }
    
    /**
     * Genera un challenge da firmare per l'autenticazione.
     */
    public function generateChallenge(string $domain): array
    {
        // Verifica prima che il dominio abbia i DNS configurati
        $dnsCheck = $this->isDomainVerified($domain);
        
        if (!$dnsCheck['verified']) {
            return [
                'success' => false,
                'error' => 'DNS non verificato',
                'dns_errors' => $dnsCheck['errors']
            ];
        }
        
        // Genera challenge random con timestamp per prevenire replay attacks
        $challenge = [
            'domain' => $domain,
            'nonce' => bin2hex(random_bytes(32)),
            'timestamp' => time(), // Unix timestamp for signature verification
            'issued_at' => date('c'),
            'expires_at' => date('c', time() + self::CHALLENGE_LIFETIME),
            'message' => "CVerify Authentication Challenge for {$domain}"
        ];
        
        // Salva challenge con permessi sicuri
        Security::safeFileWrite($this->challengeFile, json_encode($challenge, JSON_PRETTY_PRINT), 0600);
        
        return [
            'success' => true,
            'challenge' => $challenge
        ];
    }
    
    /**
     * Verifica la firma del challenge e autentica l'utente.
     */
    public function authenticate(string $domain, string $signature, ?string $privateKeyPem = null): array
    {
        // Verifica che esista un challenge valido
        if (!file_exists($this->challengeFile)) {
            return [
                'success' => false,
                'error' => 'Nessun challenge attivo. Genera un nuovo challenge.'
            ];
        }
        
        $challenge = json_decode(file_get_contents($this->challengeFile), true);
        
        // Verifica scadenza challenge (usa timestamp unix per maggiore precisione)
        $expiresAt = isset($challenge['expires_at']) ? strtotime($challenge['expires_at']) : 0;
        $timestamp = $challenge['timestamp'] ?? 0;
        
        if ($expiresAt < time() || ($timestamp > 0 && (time() - $timestamp) > self::CHALLENGE_LIFETIME)) {
            @unlink($this->challengeFile);
            return [
                'success' => false,
                'error' => 'Challenge scaduto. Genera un nuovo challenge.'
            ];
        }
        
        // Verifica che il dominio corrisponda
        if ($challenge['domain'] !== $domain) {
            return [
                'success' => false,
                'error' => 'Il dominio non corrisponde al challenge.'
            ];
        }
        
        // Recupera la chiave pubblica dal DNS
        $dnsCheck = $this->isDomainVerified($domain);
        
        if (!$dnsCheck['verified'] || !$dnsCheck['public_key']) {
            return [
                'success' => false,
                'error' => 'Impossibile recuperare la chiave pubblica dal DNS.'
            ];
        }
        
        // Verifica la firma
        try {
            // Rimuovi expires_at dal challenge per la verifica (non era nel payload firmato)
            $challengeToVerify = $challenge;
            unset($challengeToVerify['expires_at']);
            
            $valid = $this->crypto->verifySignature($challengeToVerify, $signature, $dnsCheck['public_key']);
            
            if (!$valid) {
                return [
                    'success' => false,
                    'error' => 'Firma non valida. Assicurati di usare la chiave privata corretta.'
                ];
            }
            
            // Autenticazione riuscita - crea sessione
            $session = [
                'authenticated' => true,
                'domain' => $domain,
                'authenticated_at' => date('c'),
                'expires_at' => date('c', time() + self::SESSION_LIFETIME),
                'fingerprint' => $this->crypto->getKeyFingerprint($dnsCheck['public_key'])
            ];
            
            Security::safeFileWrite($this->sessionFile, json_encode($session, JSON_PRETTY_PRINT), 0600);
            @unlink($this->challengeFile);
            
            return [
                'success' => true,
                'message' => 'Autenticazione riuscita',
                'session' => $session
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Errore verifica firma: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Firma un challenge con la chiave privata (helper per il client).
     */
    public function signChallenge(array $challenge, string $privateKeyPem, ?string $passphrase = null): string
    {
        // Rimuovi expires_at dal challenge per la firma
        $challengeToSign = $challenge;
        unset($challengeToSign['expires_at']);
        
        return $this->crypto->signJson($challengeToSign, $privateKeyPem, $passphrase);
    }
    
    /**
     * Recupera i dati della sessione corrente.
     */
    public function getSession(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return json_decode(file_get_contents($this->sessionFile), true);
    }
    
    /**
     * Effettua il logout.
     */
    public function logout(): void
    {
        @unlink($this->sessionFile);
        @unlink($this->challengeFile);
    }
    
    /**
     * Rinnova la sessione se ancora valida.
     */
    public function renewSession(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $session = json_decode(file_get_contents($this->sessionFile), true);
        $session['expires_at'] = date('c', time() + self::SESSION_LIFETIME);
        
        Security::safeFileWrite($this->sessionFile, json_encode($session, JSON_PRETTY_PRINT), 0600);
        
        return true;
    }
    
    /**
     * Verifica che la sessione corrente appartenga a un determinato dominio.
     */
    public function isAuthenticatedAs(string $domain): bool
    {
        $session = $this->getSession();
        
        if (!$session) {
            return false;
        }
        
        return $session['domain'] === $domain;
    }
}