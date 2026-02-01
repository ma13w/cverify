<?php
/**
 * CVerify Relay API - Acknowledge/Delete Pending Request
 * 
 * DELETE /api/acknowledge.php
 * POST /api/acknowledge.php (with _method=DELETE)
 * 
 * Rimuove una richiesta pending dopo che è stata processata.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['success' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
}

if ($method !== 'DELETE' && $method !== 'POST') {
    errorResponse('Metodo non consentito', 405);
}

// Get data from body (sempre JSON per semplicità)
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true) ?: [];

$requestId = $data['request_id'] ?? '';
$companyDomain = $data['company_domain'] ?? '';

if (empty($requestId) || empty($companyDomain)) {
    errorResponse('Parametri request_id e company_domain richiesti');
}

$companyDomain = sanitizeDomain($companyDomain);
$requestFile = PENDING_DIR . '/' . $companyDomain . '/' . $requestId . '.json';

if (!file_exists($requestFile)) {
    errorResponse('Richiesta non trovata', 404);
}

unlink($requestFile);

jsonResponse([
    'success' => true,
    'message' => 'Richiesta rimossa',
    'request_id' => $requestId,
]);
