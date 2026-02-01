<?php

declare(strict_types=1);

namespace CVerify;

use Exception;

class OlaCV
{
    private string $apiKey;
    // TODO: Update this URL based on https://docs.ola.cv/api/
    private string $apiBase = 'https://api.ola.cv/v1'; 

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Automatically configures DNS records for a .cv domain
     */
    public function configureRecords(string $domain, array $records): array
    {
        $results = [];

        // 1. Configure Identity Record
        if (!empty($records['identity'])) {
            $results[] = $this->createTxtRecord($domain, '@', $records['identity']);
        }

        // 2. Configure Public Key Records (Multipart)
        if (!empty($records['public_key'])) {
            foreach ($records['public_key'] as $keyRecord) {
                // Determine host/name (usually @ for main domain)
                $host = '@'; 
                $results[] = $this->createTxtRecord($domain, $host, $keyRecord['value']);
            }
        }

        return $results;
    }

    /**
     * Call the Ola.cv API to create a TXT record
     */
    private function createTxtRecord(string $domain, string $host, string $value): array
    {
        // TODO: Verify this endpoint structure in docs.ola.cv
        // Example: POST /domains/{domain}/records
        $endpoint = '/domains/' . urlencode($domain) . '/records';
        
        $data = [
            'type' => 'TXT',
            'name' => $host, // Subdomain or @
            'content' => $value,
            'ttl' => 300
        ];

        return $this->request('POST', $endpoint, $data);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->apiBase . $path);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('OlaCV API Error: ' . curl_error($ch));
        }
        
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new Exception('API Request Failed (' . $httpCode . '): ' . ($decoded['message'] ?? 'Unknown error'));
        }

        return $decoded ?: [];
    }
}