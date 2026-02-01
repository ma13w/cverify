<?php
/**
 * CVerify Relay API - Get Attestations
 * 
 * GET /api/attestations.php?domain=user.com
 * 
 * Recupera le attestazioni per un utente.
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

$userDomain = sanitizeDomain($domain);
$userDir = ATTESTATIONS_DIR . '/' . $userDomain;

$attestations = [];

if (is_dir($userDir)) {
    foreach (glob($userDir . '/*.json') as $file) {
        $json = file_get_contents($file);
        if (strlen($json) > 1000000) { // 1MB limit
            throw new Exception('File too large');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if ($data) {
            $attestations[] = $data;
        }
    }
}

// Sort by date (newest first)
usort($attestations, function($a, $b) {
    return strtotime($b['issued_at'] ?? '0') - strtotime($a['issued_at'] ?? '0');
});

jsonResponse([
    'success' => true,
    'domain' => $domain,
    'count' => count($attestations),
    'attestations' => $attestations,
]);
