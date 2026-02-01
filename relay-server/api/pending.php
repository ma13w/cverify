<?php
/**
 * CVerify Relay API - Get Pending Requests
 * 
 * GET /api/pending.php?domain=company.com
 * 
 * Recupera le richieste di validazione pending per un'azienda.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Metodo non consentito', 405);
}

$domain = $_GET['domain'] ?? '';
if (empty($domain)) {
    errorResponse('Parametro domain richiesto');
}

$companyDomain = sanitizeDomain($domain);
$companyDir = PENDING_DIR . '/' . $companyDomain;

$requests = [];

if (is_dir($companyDir)) {
    foreach (glob($companyDir . '/*.json') as $file) {
        $json = file_get_contents($file);
        if (strlen($json) > 1000000) { // 1MB limit
            throw new Exception('File too large');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if ($data) {
            $requests[] = $data;
        }
    }
}

// Sort by date (newest first)
usort($requests, function($a, $b) {
    return strtotime($b['submitted_at'] ?? '0') - strtotime($a['submitted_at'] ?? '0');
});

jsonResponse([
    'success' => true,
    'domain' => $domain,
    'count' => count($requests),
    'requests' => $requests,
]);
