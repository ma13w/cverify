<?php
/**
 * CVerify Relay API - Submit Attestation
 * 
 * POST /api/attestation.php
 * 
 * Riceve un'attestazione firmata da un'azienda e la salva
 * per l'utente destinatario.
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
    $security->enforceRateLimit('attestation_' . $clientIp, 20, 60); // 20 requests per minute
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

// Determina il formato (criptato o in chiaro)
$userDomain = null;
$issuerDomain = null;
$attestationId = null;

if (isset($data['encrypted_payload'])) {
    // Payload criptato
    if (empty($data['user_domain']) || empty($data['company_domain'])) {
        errorResponse('Campi user_domain e company_domain richiesti per payload criptato');
    }
    
    $userDomain = sanitizeDomain($data['user_domain']);
    $issuerDomain = sanitizeDomain($data['company_domain']);
    $attestationId = $data['attestation_id'] ?? ('att_' . bin2hex(random_bytes(8)));

    $attestation = [
        'attestation_id' => $attestationId,
        'issuer_domain' => $data['company_domain'],
        'user_domain' => $data['user_domain'],
        'encrypted' => true,
        'encrypted_payload' => $data['encrypted_payload'],
        'relayed_at' => date('c'),
    ];
    
    // Feed pubblico - tipo 'attestation'
    addToFeed('attestation', [
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['company_domain'],
        'encrypted' => true
    ]);
} else {
    // Payload in chiaro
    if (empty($data['user_domain']) || empty($data['issuer_domain'])) {
        errorResponse('Campi user_domain e issuer_domain richiesti');
    }
    
    $userDomain = sanitizeDomain($data['user_domain']);
    $issuerDomain = sanitizeDomain($data['issuer_domain']);
    $attestationId = $data['attestation_id'] ?? ('att_' . bin2hex(random_bytes(8)));

    $attestation = $data;
    $attestation['encrypted'] = false;
    $attestation['relayed_at'] = date('c');
    
    addToFeed('attestation', [
        'user_domain' => $data['user_domain'],
        'company_domain' => $data['issuer_domain'],
        'role' => $data['experience_data']['role'] ?? null
    ]);
}

// Create user attestations directory
$userDir = ATTESTATIONS_DIR . '/' . $userDomain;
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}

// Save attestation
file_put_contents(
    $userDir . '/' . $attestationId . '.json',
    json_encode($attestation, JSON_PRETTY_PRINT),
    LOCK_EX
);

jsonResponse([
    'success' => true,
    'message' => 'Attestazione pubblicata',
    'attestation_id' => $attestationId,
    'user_domain' => $userDomain,
]);
