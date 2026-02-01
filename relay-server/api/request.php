<?php
/**
 * CVerify Relay API - Submit Validation Request
 * 
 * POST /api/request.php
 * 
 * Riceve una richiesta di validazione da un utente e la salva
 * in pending per l'azienda destinataria.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../src/Security.php';

use CVerify\Security;

// Initialize security
$security = new Security(DATA_DIR);

// Rate limiting by IP
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    $security->enforceRateLimit('request_' . $clientIp, 20, 60); // 20 requests per minute
} catch (RuntimeException $e) {
    errorResponse($e->getMessage(), 429);
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Metodo non consentito', 405);
}

// Get JSON body with size validation
$rawBody = file_get_contents('php://input');
try {
    $data = Security::validateJson($rawBody);
} catch (InvalidArgumentException $e) {
    errorResponse('JSON non valido: ' . $e->getMessage());
}

// Required fields (sempre in chiaro)
$required = ['user_domain', 'company_domain'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        errorResponse("Campo richiesto mancante: $field");
    }
}

$userDomain = sanitizeDomain($data['user_domain']);
$companyDomain = sanitizeDomain($data['company_domain']);

// Create company pending directory
$companyDir = PENDING_DIR . '/' . $companyDomain;
if (!is_dir($companyDir)) {
    mkdir($companyDir, 0755, true);
}

// Generate request ID
$requestId = 'req_' . bin2hex(random_bytes(8));

// Determina se è payload criptato o in chiaro
if (isset($data['encrypted_payload'])) {
    // Payload criptato end-to-end
    $request = [
        'id' => $requestId,
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['company_domain'],
        'encrypted' => true,
        'encrypted_payload' => $data['encrypted_payload'],
        'submitted_at' => date('c'),
        'status' => 'pending',
    ];
    
    // Feed pubblico mostra solo i domini - tipo 'request'
    addToFeed('request', [
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['company_domain'],
        'encrypted' => true
    ]);
} else {
    // Payload in chiaro (retrocompatibilità)
    $required = ['experience_id', 'experience_data'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            errorResponse("Campo richiesto mancante: $field");
        }
    }
    
    $request = [
        'id' => $requestId,
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['company_domain'],
        'encrypted' => false,
        'experience_id' => $data['experience_id'],
        'experience_data' => $data['experience_data'],
        'callback_url' => $data['callback_url'] ?? null,
        'signature' => $data['signature'] ?? null,
        'timestamp' => $data['timestamp'] ?? date('c'),
        'submitted_at' => date('c'),
        'status' => 'pending',
    ];
    
    addToFeed('request', [
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['company_domain'],
        'role' => $data['experience_data']['role'] ?? null
    ]);
}

// Save request
file_put_contents(
    $companyDir . '/' . $requestId . '.json',
    json_encode($request, JSON_PRETTY_PRINT),
    LOCK_EX
);

jsonResponse([
    'success' => true,
    'message' => 'Richiesta di validazione inoltrata',
    'request_id' => $requestId,
    'company_domain' => $companyDomain,
]);
