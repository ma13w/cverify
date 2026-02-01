<?php

declare(strict_types=1);

namespace CVerify;

use Exception;

class OlaCV
{
    private string $apiKey;
    private string $apiBase = 'https://developer.ola.cv/api/v1';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Automatically configures DNS records for a .cv domain
     */
    public function configureRecords(string $domain, array $records): array
    {
        // 1. Find the Zone ID for the domain
        $zoneId = $this->getZoneIdForDomain($domain);
        if (!$zoneId) {
            throw new Exception("DNS Zone not found for domain: $domain");
        }

        $results = [];

        // 2. Configure Identity Record
        if (!empty($records['identity'])) {
            $results[] = $this->createRecord($zoneId, 'TXT', '@', $records['identity']);
        }

        // 3. Configure Public Key Records (Multipart)
        if (!empty($records['public_key'])) {
            foreach ($records['public_key'] as $keyRecord) {
                $results[] = $this->createRecord($zoneId, 'TXT', '@', $keyRecord['value']);
            }
        }

        return $results;
    }

    private function getZoneIdForDomain(string $domain): ?string
    {
        // Iterate pages if necessary, but for now just check first page
        // or loop until found.
        $page = 1;
        do {
            $response = $this->request('GET', '/zones', ['page' => $page]);
            $zones = $response['data'] ?? [];
            if (empty($zones)) break;

            foreach ($zones as $zone) {
                if ($zone['name'] === $domain) {
                    return $zone['id'];
                }
            }
            $page++;
        } while ($page <= 5); // Limit limit to avoid infinite loops

        return null;
    }

    /**
     * Call the Ola.cv API to create a DNS record
     */
    private function createRecord(string $zoneId, string $type, string $name, string $content): array
    {
        // Endpoint: POST /zones/:zoneid/records
        $endpoint = '/zones/' . $zoneId . '/records';
        
        $data = [
            'type' => $type,
            'name' => $name, // "@" or subdomain
            'content' => $content,
            'ttl' => 60 // Low TTL for faster propagation during setup
        ];

        return $this->request('POST', $endpoint, $data);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $url = $this->apiBase . $path;
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Fix for localhost/dev
            CURLOPT_SSL_VERIFYHOST => false
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            throw new Exception('OlaCV API Network Error: ' . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? 'Unknown error';
            throw new Exception("API Request Failed ($httpCode): $msg");
        }

        return $decoded ?: [];
    }
}