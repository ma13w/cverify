<?php
declare(strict_types=1);

namespace Registrant;

use Exception;

class OlaCV {
    private string $apiKey;
    private string $baseUrl;
    private bool $debug;

    public function __construct(string $apiKey, string $baseUrl = 'https://developer.ola.cv/api/v1', bool $debug = false) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->debug = $debug;
    }

    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: $error");
        }

        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->debug) {
                 error_log("JSON Decode Error: " . json_last_error_msg());
            }
            // If it's a 200 OK but not JSON, that's weird but maybe empty?
            if ($httpCode < 400 && trim($response) === '') {
                 return [];
            }
        }

        if ($this->debug) {
            error_log("OlaCV Request: $method $url");
            error_log("Response Code: $httpCode");
             error_log("Response Body: " . $response);
        }

        if ($httpCode >= 400) {
            $msg = $decodedResponse['message'] ?? 'Unknown error';
            if (empty($decodedResponse) && !empty($response)) {
                $msg .= " - Raw response: " . substr($response, 0, 200);
            }
            throw new Exception("API Error ($httpCode): $msg", $httpCode);
        }

        return $decodedResponse ?? [];
    }

    // --- Contacts ---

    public function createContact(array $data): array {
        return $this->request('POST', '/contacts', $data);
    }

    public function getContacts(int $page = 1, int $perPage = 20): array {
        return $this->request('GET', '/contacts', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getContact(string $id): array {
        return $this->request('GET', '/contacts/' . $id);
    }

    public function deleteContact(string $id): array {
        return $this->request('DELETE', '/contacts/' . $id);
    }

    // --- Domains ---

    public function checkDomain(array $domains, string $fees = 'registration'): array {
        // fees can be 'registration' or 'all'
        // Endpoint: POST /domains/check?fees=...
        $endpoint = '/domains/check?fees=' . $fees;
        return $this->request('POST', $endpoint, ['domains' => $domains]);
    }

    public function registerDomain(array $data): array {
        // $data should contain name, registrant, (optional) nameservers, etc.
        return $this->request('POST', '/domains', $data);
    }

    public function getDomains(int $page = 1, int $perPage = 20): array {
        return $this->request('GET', '/domains', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getDomain(string $id): array {
        return $this->request('GET', '/domains/' . $id);
    }

    public function updateDomain(string $id, array $data): array {
        return $this->request('POST', '/domains/' . $id, $data);
    }

    public function renewDomain(string $id, string $domainName, string $registrantId): array {
        return $this->request('POST', '/domains/' . $id . '/renew', [
            'name' => $domainName,
            'registrant' => $registrantId
        ]);
    }
    
    public function deleteTestDomain(string $id): array {
        return $this->request('DELETE', '/domains/' . $id . '/test');
    }

    public function getDomainZone(string $domainId): array {
        return $this->request('GET', '/domains/' . $domainId . '/zone');
    }

    // --- DNS Zones ---

    public function getZones(int $page = 1, int $perPage = 20): array {
        return $this->request('GET', '/zones', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getZone(string $id): array {
        return $this->request('GET', '/zones/' . $id);
    }

    // --- DNS Records ---

    public function getZoneRecords(string $zoneId, int $page = 1, int $perPage = 20): array {
        return $this->request('GET', '/zones/' . $zoneId . '/records', ['page' => $page, 'per_page' => $perPage]);
    }

    public function getZoneRecord(string $zoneId, string $recordId): array {
        return $this->request('GET', '/zones/' . $zoneId . '/records/' . $recordId);
    }

    public function createZoneRecord(string $zoneId, array $data): array {
        // data: type, name, ttl, content, (optional) comment, priority
        return $this->request('POST', '/zones/' . $zoneId . '/records', $data);
    }

    public function updateZoneRecord(string $zoneId, string $recordId, array $data): array {
        return $this->request('POST', '/zones/' . $zoneId . '/records/' . $recordId, $data);
    }

    public function deleteZoneRecord(string $zoneId, string $recordId): array {
        return $this->request('DELETE', '/zones/' . $zoneId . '/records/' . $recordId);
    }
}
