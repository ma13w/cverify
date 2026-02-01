<?php

declare(strict_types=1);

use CVerify\Crypto;

/**
 * Classe per gestire le richieste di validazione alle aziende.
 */
class ValidationRequest
{
    private Crypto $crypto;
    
    // Endpoint standard per la validazione CVerify
    private const VALIDATION_ENDPOINT = '/cverify/api/validate';
    
    // Timeout per le richieste HTTP
    private const REQUEST_TIMEOUT = 30;

    public function __construct(Crypto $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Invia una richiesta di validazione a un'azienda.
     *
     * @param array $experience I dati dell'esperienza da validare
     * @param string $ownerDomain Il dominio del proprietario del CV
     * @param string $ownerFingerprint Il fingerprint della chiave pubblica del proprietario
     * @param string $privateKeyPem La chiave privata per firmare la richiesta
     * @param string|null $passphrase Passphrase della chiave privata
     * @return array{success: bool, token?: string, error?: string, response?: array}
     */
    public function sendValidationRequest(
        array $experience,
        string $ownerDomain,
        string $ownerFingerprint,
        string $privateKeyPem,
        ?string $passphrase = null
    ): array {
        // Genera un token univoco per questa richiesta
        $token = $this->generateValidationToken();
        
        // Costruisci il payload della richiesta
        $payload = [
            'request_type' => 'validation_request',
            'version' => '1.0',
            'timestamp' => date('c'),
            'token' => $token,
            'owner' => [
                'domain' => $ownerDomain,
                'fingerprint' => $ownerFingerprint,
            ],
            'experience' => [
                'id' => $experience['id'],
                'role' => $experience['role'],
                'skills' => $experience['skills'] ?? [],
                'start_date' => $experience['start_date'] ?? null,
                'end_date' => $experience['end_date'] ?? null,
            ],
        ];

        try {
            // Firma il payload
            $signature = $this->crypto->signJson($payload, $privateKeyPem, $passphrase);
            
            // Costruisci la richiesta completa
            $request = [
                'payload' => $payload,
                'signature' => $signature,
            ];

            // Costruisci l'URL dell'endpoint aziendale
            $companyDomain = $experience['company_domain'];
            $url = $this->buildValidationUrl($companyDomain);

            // Invia la richiesta POST
            $response = $this->sendHttpPost($url, $request);

            if ($response['success']) {
                return [
                    'success' => true,
                    'token' => $token,
                    'response' => $response['data'] ?? [],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Errore sconosciuto nella risposta',
                    'token' => $token,
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'token' => $token,
            ];
        }
    }

    /**
     * Genera un token univoco per la richiesta di validazione.
     *
     * @return string Token in formato esadecimale
     */
    private function generateValidationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Costruisce l'URL per l'endpoint di validazione aziendale.
     *
     * @param string $companyDomain Il dominio dell'azienda
     * @return string L'URL completo
     */
    private function buildValidationUrl(string $companyDomain): string
    {
        // Rimuovi protocollo se presente
        $domain = preg_replace('#^https?://#', '', $companyDomain);
        $domain = rtrim($domain, '/');

        // Usa HTTPS per default
        return 'https://' . $domain . self::VALIDATION_ENDPOINT;
    }

    /**
     * Invia una richiesta HTTP POST.
     *
     * @param string $url L'URL di destinazione
     * @param array $data I dati da inviare (verranno convertiti in JSON)
     * @return array{success: bool, data?: array, error?: string, http_code?: int}
     */
    private function sendHttpPost(string $url, array $data): array
    {
        $jsonData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if ($jsonData === false) {
            return [
                'success' => false,
                'error' => 'Impossibile serializzare i dati in JSON',
            ];
        }

        // Usa cURL se disponibile
        if (function_exists('curl_init')) {
            return $this->sendWithCurl($url, $jsonData);
        }
        
        // Fallback a file_get_contents
        return $this->sendWithFileGetContents($url, $jsonData);
    }

    /**
     * Invia richiesta usando cURL.
     */
    private function sendWithCurl(string $url, string $jsonData): array
    {
        $ch = curl_init($url);
        
        if ($ch === false) {
            return [
                'success' => false,
                'error' => 'Impossibile inizializzare cURL',
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-CVerify-Version: 1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($response === false || !empty($error)) {
            return [
                'success' => false,
                'error' => 'Errore cURL: ' . ($error ?: 'Risposta vuota'),
                'http_code' => $httpCode,
            ];
        }

        // Parse della risposta JSON
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode,
            ];
        }

        return [
            'success' => false,
            'error' => $responseData['error'] ?? "HTTP $httpCode",
            'data' => $responseData,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Invia richiesta usando file_get_contents (fallback).
     */
    private function sendWithFileGetContents(string $url, string $jsonData): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-CVerify-Version: 1.0',
                    'Content-Length: ' . strlen($jsonData),
                ]),
                'content' => $jsonData,
                'timeout' => self::REQUEST_TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            return [
                'success' => false,
                'error' => 'Impossibile contattare il server: ' . ($error['message'] ?? 'Errore sconosciuto'),
            ];
        }

        // Estrai HTTP code dalle headers
        $httpCode = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                    $httpCode = (int) $matches[1];
                }
            }
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode,
            ];
        }

        return [
            'success' => false,
            'error' => $responseData['error'] ?? "HTTP $httpCode",
            'data' => $responseData,
            'http_code' => $httpCode,
        ];
    }

    /**
     * Verifica se un dominio ha un endpoint CVerify attivo.
     *
     * @param string $companyDomain Il dominio da verificare
     * @return bool True se l'endpoint risponde
     */
    public function checkCompanyEndpoint(string $companyDomain): bool
    {
        $url = $this->buildValidationUrl($companyDomain);
        
        // Prova con OPTIONS per verificare se l'endpoint esiste
        $ch = curl_init($url);
        
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'OPTIONS',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Accetta 200, 204, 405 (method not allowed ma endpoint esiste)
        return in_array($httpCode, [200, 204, 405]);
    }

    /**
     * Prepara un payload di callback per quando l'azienda valida/rifiuta.
     *
     * @param string $token Il token della richiesta originale
     * @param string $status Lo stato: 'validated' o 'rejected'
     * @param string|null $message Messaggio opzionale
     * @return array Il payload del callback
     */
    public static function prepareCallbackPayload(
        string $token,
        string $status,
        ?string $message = null
    ): array {
        return [
            'callback_type' => 'validation_response',
            'version' => '1.0',
            'timestamp' => date('c'),
            'token' => $token,
            'status' => $status,
            'message' => $message,
        ];
    }
}
