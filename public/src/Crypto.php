<?php

declare(strict_types=1);

namespace CVerify;

use RuntimeException;
use InvalidArgumentException;

// Previene ridefinizione se già caricata (fix per OPcache)
if (class_exists('CVerify\\Crypto', false)) {
    return;
}

/**
 * Classe per la gestione crittografica RSA.
 * Gestisce generazione chiavi, firma e verifica di dati JSON.
 */
class Crypto
{
    private const KEY_BITS = 2048;
    private const DIGEST_ALGORITHM = OPENSSL_ALGO_SHA256;

    /**
     * Genera una nuova coppia di chiavi RSA.
     *
     * @param string|null $passphrase Passphrase opzionale per proteggere la chiave privata
     * @return array{privateKey: string, publicKey: string, publicKeyDns: string}
     * @throws RuntimeException Se la generazione fallisce
     */
    public function generateKeyPair(?string $passphrase = null): array
    {
        // Find OpenSSL config file (required on Windows)
        $opensslCnf = $this->findOpenSSLConfig();

        $config = [
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];

        if ($opensslCnf !== null) {
            $config['config'] = $opensslCnf;
        }

        $resource = openssl_pkey_new($config);
        
        if ($resource === false) {
            throw new RuntimeException(
                'Impossibile generare la coppia di chiavi RSA: ' . $this->getOpenSSLErrors()
            );
        }

        // Estrai la chiave privata
        $privateKeyPem = '';
        $encryptionConfig = $passphrase !== null 
            ? ['encrypt_key' => true, 'encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC]
            : null;

        // Aggiungi config anche per export se disponibile
        $exportConfig = $encryptionConfig ?? [];
        if ($opensslCnf !== null) {
            $exportConfig['config'] = $opensslCnf;
        }

        $success = openssl_pkey_export($resource, $privateKeyPem, $passphrase, $exportConfig ?: null);
        
        if (!$success) {
            throw new RuntimeException(
                'Impossibile esportare la chiave privata: ' . $this->getOpenSSLErrors()
            );
        }

        // Estrai la chiave pubblica
        $keyDetails = openssl_pkey_get_details($resource);
        
        if ($keyDetails === false) {
            throw new RuntimeException(
                'Impossibile ottenere i dettagli della chiave: ' . $this->getOpenSSLErrors()
            );
        }

        $publicKeyPem = $keyDetails['key'];

        // Genera versione DNS-friendly della chiave pubblica
        $publicKeyDns = self::pemToDnsFormat($publicKeyPem);

        // Pulisci la risorsa dalla memoria
        unset($resource);

        return [
            'privateKey' => $privateKeyPem,
            'publicKey' => $publicKeyPem,
            'publicKeyDns' => $publicKeyDns,
        ];
    }

    /**
     * Firma dati JSON con la chiave privata.
     *
     * @param array $data I dati da firmare
     * @param string $privateKeyPem La chiave privata in formato PEM
     * @param string|null $passphrase Passphrase della chiave privata (se protetta)
     * @return string Firma in formato base64
     * @throws InvalidArgumentException Se i dati sono vuoti
     * @throws RuntimeException Se la firma fallisce
     */
    public function signJson(array $data, string $privateKeyPem, ?string $passphrase = null): string
    {
        if (empty($data)) {
            throw new InvalidArgumentException('I dati da firmare non possono essere vuoti');
        }

        $jsonData = $this->canonicalizeJson($data);

        // Normalizza la chiave privata (fix per copia/incolla da browser)
        $privateKeyPem = $this->normalizePrivateKey($privateKeyPem);
        
        // Verifica che la chiave abbia un formato valido
        if (!preg_match('/----BEGIN (RSA |ENCRYPTED |)PRIVATE KEY----/', $privateKeyPem)) {
            throw new RuntimeException(
                'Formato chiave privata non valido. Deve iniziare con "-----BEGIN PRIVATE KEY-----" o "-----BEGIN RSA PRIVATE KEY-----"'
            );
        }

        // Carica la chiave privata
        $privateKey = @openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');
        
        if ($privateKey === false) {
            $errors = $this->getOpenSSLErrors();
            
            // Fornisci messaggi di errore più chiari
            if (strpos($errors, 'unsupported') !== false) {
                if (strpos($privateKeyPem, 'ENCRYPTED') !== false || strpos($privateKeyPem, 'Proc-Type: 4,ENCRYPTED') !== false) {
                    throw new RuntimeException(
                        'La chiave privata è protetta da passphrase. Inserisci la passphrase corretta.'
                    );
                }
                throw new RuntimeException(
                    'Formato chiave non supportato. Prova a rigenerare le chiavi dal setup.'
                );
            }
            
            if (strpos($errors, 'bad decrypt') !== false || strpos($errors, 'bad password') !== false) {
                throw new RuntimeException(
                    'Passphrase errata. Verifica la passphrase e riprova.'
                );
            }
            
            throw new RuntimeException(
                'Impossibile caricare la chiave privata: ' . $errors
            );
        }

        // Firma i dati
        $signature = '';
        $success = openssl_sign($jsonData, $signature, $privateKey, self::DIGEST_ALGORITHM);
        
        unset($privateKey);

        if (!$success) {
            throw new RuntimeException(
                'Impossibile firmare i dati: ' . $this->getOpenSSLErrors()
            );
        }

        return base64_encode($signature);
    }

    /**
     * Verifica una firma su dati JSON.
     *
     * @param array $data I dati originali
     * @param string $signatureBase64 La firma in formato base64
     * @param string $publicKeyPem La chiave pubblica in formato PEM
     * @return bool True se la firma è valida
     * @throws RuntimeException Se la verifica fallisce per errore
     * @throws InvalidArgumentException Se i parametri non sono validi
     */
    public function verifySignature(array $data, string $signatureBase64, string $publicKeyPem): bool
    {
        if (empty($data)) {
            throw new InvalidArgumentException('I dati da verificare non possono essere vuoti');
        }

        if (empty($signatureBase64)) {
            throw new InvalidArgumentException('La firma non può essere vuota');
        }

        // Decodifica la firma
        $signature = base64_decode($signatureBase64, true);
        
        if ($signature === false) {
            throw new InvalidArgumentException('Firma base64 non valida');
        }

        // Serializza i dati nello stesso modo usato per la firma
        $jsonData = $this->canonicalizeJson($data);

        // Carica la chiave pubblica
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        
        if ($publicKey === false) {
            throw new RuntimeException(
                'Impossibile caricare la chiave pubblica: ' . $this->getOpenSSLErrors()
            );
        }

        // Verifica la firma
        $result = openssl_verify($jsonData, $signature, $publicKey, self::DIGEST_ALGORITHM);

        // Pulisci la chiave dalla memoria
        unset($publicKey);

        if ($result === -1) {
            throw new RuntimeException(
                'Errore durante la verifica della firma: ' . $this->getOpenSSLErrors()
            );
        }

        return $result === 1;
    }

    /**
     * Converte una chiave PEM in formato DNS-friendly.
     * Rimuove header/footer PEM e newlines.
     *
     * @param string $pemKey La chiave in formato PEM
     * @return string La chiave in formato DNS (base64 senza header)
     */
    public function pemToDnsFormat(string $pemKey): string
    {
        $key = preg_replace('/-----BEGIN [A-Z ]+-----/', '', $pemKey);
        $key = preg_replace('/-----END [A-Z ]+-----/', '', $key);
        $key = preg_replace('/\s+/', '', $key);

        return $key;
    }

    /**
     * Ricostruisce una chiave pubblica PEM dal formato DNS.
     *
     * @param string $dnsKey La chiave in formato DNS (base64 senza header)
     * @return string La chiave in formato PEM
     */
    public function dnsFormatToPem(string $dnsKey): string
    {
        // Pulisci la stringa
        $cleanKey = preg_replace('/\s+/', '', $dnsKey);
        
        // Formatta in righe da 64 caratteri (standard PEM)
        $formattedKey = wordwrap($cleanKey, 64, "\n", true);
        
        // Aggiungi header e footer PEM
        return "-----BEGIN PUBLIC KEY-----\n" . $formattedKey . "\n-----END PUBLIC KEY-----";
    }

    /**
     * Valida che una stringa sia una chiave pubblica PEM valida.
     *
     * @param string $publicKeyPem La chiave da validare
     * @return bool True se la chiave è valida
     */
    public function isValidPublicKey(string $publicKeyPem): bool
    {
        $key = @openssl_pkey_get_public($publicKeyPem);
        
        if ($key === false) {
            return false;
        }

        $details = @openssl_pkey_get_details($key);
        unset($key);

        return $details !== false 
            && isset($details['type']) 
            && $details['type'] === OPENSSL_KEYTYPE_RSA
            && isset($details['bits'])
            && $details['bits'] >= self::KEY_BITS;
    }

    /**
     * Valida che una stringa sia una chiave privata PEM valida.
     *
     * @param string $privateKeyPem La chiave da validare
     * @param string|null $passphrase Passphrase opzionale
     * @return bool True se la chiave è valida
     */
    public function isValidPrivateKey(string $privateKeyPem, ?string $passphrase = null): bool
    {
        $key = @openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');
        
        if ($key === false) {
            return false;
        }

        $details = @openssl_pkey_get_details($key);
        unset($key);

        return $details !== false 
            && isset($details['type']) 
            && $details['type'] === OPENSSL_KEYTYPE_RSA;
    }

    /**
     * Normalizza una chiave privata PEM.
     * Fix per problemi di copia/incolla da browser (newlines, spazi, encoding).
     *
     * @param string $privateKeyPem La chiave da normalizzare
     * @return string La chiave normalizzata
     */
    private function normalizePrivateKey(string $privateKeyPem): string
    {
        // Rimuovi BOM se presente
        $privateKeyPem = preg_replace('/^\xEF\xBB\xBF/', '', $privateKeyPem);
        
        // Converti \r\n e \r in \n
        $privateKeyPem = str_replace(["\r\n", "\r"], "\n", $privateKeyPem);
        
        // Trim spazi iniziali e finali
        $privateKeyPem = trim($privateKeyPem);
        
        // Se la chiave ha newlines letterali (da escape JSON), convertili
        $privateKeyPem = str_replace('\\n', "\n", $privateKeyPem);
        
        // Verifica se la chiave è tutta su una linea (senza newlines tra header e body)
        if (preg_match('/^(-----BEGIN [A-Z ]+-----)(.+)(-----END [A-Z ]+-----)$/s', $privateKeyPem, $matches)) {
            $header = $matches[1];
            $body = trim($matches[2]);
            $footer = $matches[3];
            
            // Se il body non ha newlines, aggiungili ogni 64 caratteri
            if (strpos($body, "\n") === false && strlen($body) > 64) {
                $body = wordwrap($body, 64, "\n", true);
            }
            
            $privateKeyPem = $header . "\n" . $body . "\n" . $footer;
        }
        
        return $privateKeyPem;
    }

    /**
     * Genera una rappresentazione JSON canonica dei dati.
     * Garantisce ordine deterministico delle chiavi per firme consistenti.
     *
     * @param array $data I dati da serializzare
     * @return string JSON canonico
     */
    private function canonicalizeJson(array $data): string
    {
        // Ordina le chiavi ricorsivamente per garantire consistenza
        $this->sortKeysRecursive($data);
        
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($json === false) {
            throw new RuntimeException('Impossibile serializzare i dati in JSON');
        }

        return $json;
    }

    /**
     * Ordina ricorsivamente le chiavi di un array.
     *
     * @param array &$data L'array da ordinare
     */
    private function sortKeysRecursive(array &$data): void
    {
        ksort($data, SORT_STRING);
        
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->sortKeysRecursive($value);
            }
        }
    }

    /**
     * Trova il file di configurazione OpenSSL.
     * Necessario su Windows dove il percorso non è in variabili d'ambiente.
     *
     * @return string|null Percorso al file openssl.cnf o null se non trovato
     */
    private function findOpenSSLConfig(): ?string
    {
        // Prima controlla se già configurato in PHP
        $configured = getenv('OPENSSL_CONF');
        if ($configured && file_exists($configured)) {
            return $configured;
        }

        // Percorsi comuni su Windows
        $possiblePaths = [
            // XAMPP
            'C:/xampp/apache/conf/openssl.cnf',
            'C:/xampp/php/extras/openssl/openssl.cnf',
            'C:/xampp/php/extras/ssl/openssl.cnf',
            // WAMP
            'C:/wamp64/bin/apache/apache2.4.51/conf/openssl.cnf',
            'C:/wamp/bin/apache/apache2.4.51/conf/openssl.cnf',
            // Laragon
            'C:/laragon/bin/apache/httpd-2.4.54-win64-VS16/conf/openssl.cnf',
            'C:/laragon/etc/ssl/openssl.cnf',
            // PHP standalone
            'C:/php/extras/ssl/openssl.cnf',
            // OpenSSL standalone
            'C:/OpenSSL-Win64/bin/openssl.cnf',
            'C:/OpenSSL-Win32/bin/openssl.cnf',
            'C:/Program Files/OpenSSL-Win64/bin/openssl.cnf',
            // Linux/Mac standard paths
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/usr/local/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
            '/opt/homebrew/etc/openssl/openssl.cnf',
        ];

        // Aggiungi percorso relativo a PHP se disponibile
        $phpDir = defined('PHP_BINARY') ? dirname(PHP_BINARY) : null;
        if ($phpDir) {
            array_unshift($possiblePaths, $phpDir . '/extras/ssl/openssl.cnf');
            array_unshift($possiblePaths, $phpDir . '/extras/openssl/openssl.cnf');
            array_unshift($possiblePaths, dirname($phpDir) . '/apache/conf/openssl.cnf');
        }

        foreach ($possiblePaths as $path) {
            // Normalizza il percorso per Windows
            $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if (file_exists($normalizedPath)) {
                return $normalizedPath;
            }
        }

        // Ultima risorsa: crea un file di configurazione minimale temporaneo
        $tempConfig = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl_cverify.cnf';
        if (!file_exists($tempConfig)) {
            $minimalConfig = <<<CNF
# Minimal OpenSSL configuration for CVerify
HOME = .
RANDFILE = \$ENV::HOME/.rnd

[ req ]
default_bits = 2048
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name

[ req_distinguished_name ]
countryName = Country Name (2 letter code)
countryName_default = IT
countryName_min = 2
countryName_max = 2

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
CNF;
            @file_put_contents($tempConfig, $minimalConfig);
        }

        if (file_exists($tempConfig)) {
            return $tempConfig;
        }

        return null;
    }

    /**
     * Ottiene gli errori OpenSSL come stringa.
     *
     * @return string Messaggio di errore concatenato
     */
    private function getOpenSSLErrors(): string
    {
        $errors = [];
        while ($error = openssl_error_string()) {
            $errors[] = $error;
        }
        return implode('; ', $errors) ?: 'Errore sconosciuto';
    }

    /**
     * Genera il fingerprint SHA-256 di una chiave pubblica.
     *
     * @param string $publicKeyPem La chiave pubblica in formato PEM
     * @return string Fingerprint in formato esadecimale
     */
    public function getKeyFingerprint(string $publicKeyPem): string
    {
        // Rimuovi header/footer e newlines per ottenere solo i dati della chiave
        $keyData = $this->pemToDnsFormat($publicKeyPem);
        
        // Decodifica base64 e calcola hash
        $binaryKey = base64_decode($keyData);
        
        return hash('sha256', $binaryKey);
    }

    /**
     * Cripta dati usando crittografia ibrida (AES + RSA).
     * Genera una chiave AES-256 random, cripta i dati con AES-256-GCM,
     * poi cripta la chiave AES con la chiave pubblica RSA del destinatario.
     *
     * @param array $data I dati da criptare
     * @param string $recipientPublicKeyPem La chiave pubblica del destinatario
     * @return array{encrypted_data: string, encrypted_key: string, iv: string, tag: string}
     * @throws RuntimeException Se la crittografia fallisce
     */
    // public function encryptForRecipient(array $data, string $recipientPublicKeyPem): array
    // {
    //     // Serializza i dati
    //     $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    //     // Genera chiave AES-256 random (32 bytes)
    //     $aesKey = random_bytes(32);
        
    //     // Genera IV per AES-GCM (12 bytes raccomandati)
    //     $iv = random_bytes(12);
        
    //     // Cripta i dati con AES-256-GCM
    //     $tag = '';
    //     $encryptedData = openssl_encrypt(
    //         $jsonData,
    //         'aes-256-gcm',
    //         $aesKey,
    //         OPENSSL_RAW_DATA,
    //         $iv,
    //         $tag,
    //         '',
    //         16
    //     );
        
    //     if ($encryptedData === false) {
    //         throw new RuntimeException('Impossibile criptare i dati: ' . $this->getOpenSSLErrors());
    //     }
        
    //     // Carica la chiave pubblica del destinatario
    //     $publicKey = openssl_pkey_get_public($recipientPublicKeyPem);
    //     if ($publicKey === false) {
    //         throw new RuntimeException('Chiave pubblica destinatario non valida: ' . $this->getOpenSSLErrors());
    //     }
        
    //     // Cripta la chiave AES con RSA
    //     $encryptedKey = '';
    //     $success = openssl_public_encrypt($aesKey, $encryptedKey, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
    //     unset($publicKey);
        
    //     if (!$success) {
    //         throw new RuntimeException('Impossibile criptare la chiave AES: ' . $this->getOpenSSLErrors());
    //     }
        
    //     return [
    //         'encrypted_data' => base64_encode($encryptedData),
    //         'encrypted_key' => base64_encode($encryptedKey),
    //         'iv' => base64_encode($iv),
    //         'tag' => base64_encode($tag)
    //     ];
    // }

    /**
     * Decripta dati usando crittografia ibrida (AES + RSA).
     *
     * @param array $encryptedPayload Il payload criptato con encrypted_data, encrypted_key, iv, tag
     * @param string $privateKeyPem La propria chiave privata
     * @param string|null $passphrase Passphrase della chiave privata (se protetta)
     * @return array I dati decriptati
     * @throws RuntimeException Se la decrittografia fallisce
     */
    // public function decryptWithPrivateKey(array $encryptedPayload, string $privateKeyPem, ?string $passphrase = null): array
    // {
    //     // Valida payload
    //     if (!isset($encryptedPayload['encrypted_data'], $encryptedPayload['encrypted_key'], 
    //                $encryptedPayload['iv'], $encryptedPayload['tag'])) {
    //         throw new RuntimeException('Payload criptato non valido: campi mancanti');
    //     }
        
    //     // Decodifica i componenti
    //     $encryptedData = base64_decode($encryptedPayload['encrypted_data'], true);
    //     $encryptedKey = base64_decode($encryptedPayload['encrypted_key'], true);
    //     $iv = base64_decode($encryptedPayload['iv'], true);
    //     $tag = base64_decode($encryptedPayload['tag'], true);
        
    //     if ($encryptedData === false || $encryptedKey === false || $iv === false || $tag === false) {
    //         throw new RuntimeException('Payload criptato non valido: base64 corrotto');
    //     }
        
    //     // Carica la chiave privata
    //     $privateKey = @openssl_pkey_get_private($privateKeyPem, $passphrase ?? '');
    //     if ($privateKey === false) {
    //         throw new RuntimeException('Impossibile caricare la chiave privata: ' . $this->getOpenSSLErrors());
    //     }
        
    //     // Decripta la chiave AES con RSA
    //     $aesKey = '';
    //     $success = openssl_private_decrypt($encryptedKey, $aesKey, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);
    //     unset($privateKey);
        
    //     if (!$success) {
    //         throw new RuntimeException('Impossibile decriptare la chiave AES: ' . $this->getOpenSSLErrors());
    //     }
        
    //     // Decripta i dati con AES-256-GCM
    //     $decryptedData = openssl_decrypt(
    //         $encryptedData,
    //         'aes-256-gcm',
    //         $aesKey,
    //         OPENSSL_RAW_DATA,
    //         $iv,
    //         $tag
    //     );
        
    //     if ($decryptedData === false) {
    //         throw new RuntimeException('Impossibile decriptare i dati: autenticazione fallita o dati corrotti');
    //     }
        
    //     // Parse JSON
    //     $data = json_decode($decryptedData, true);
    //     if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    //         throw new RuntimeException('Dati decriptati non sono JSON valido');
    //     }
        
    //     return $data;
    // }
}
