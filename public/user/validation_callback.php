<?php

declare(strict_types=1);

/**
 * CVerify - Callback Validazione Utente
 * Endpoint per ricevere le risposte di validazione dalle aziende.
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';

use CVerify\Crypto;
use CVerify\DNS;

// Configurazione
define('USER_DATA_DIR', __DIR__ . '/data');
define('CV_FILE', __DIR__ . '/cv.json');
define('CALLBACKS_LOG', USER_DATA_DIR . '/callbacks.log');

header('Content-Type: application/json');
header('X-CVerify-Version: 1.0');

$crypto = new Crypto();
$dns = new DNS($crypto);

/**
 * Risponde con errore JSON.
 */
function respondError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Risponde con successo JSON.
 */
function respondSuccess(array $data = []): void
{
    http_response_code(200);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Logga callback ricevuto.
 */
function logCallback(string $message, array $context = []): void
{
    $entry = sprintf("[%s] %s %s\n", date('c'), $message, json_encode($context));
    @file_put_contents(CALLBACKS_LOG, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Carica il CV.
 */
function loadCV(): array
{
    if (!file_exists(CV_FILE)) {
        return ['experiences' => []];
    }
    return json_decode(file_get_contents(CV_FILE), true) ?: ['experiences' => []];
}

/**
 * Salva il CV.
 */
function saveCV(array $cv): void
{
    $cv['updated_at'] = date('c');
    file_put_contents(CV_FILE, json_encode($cv, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError(405, 'Metodo non consentito');
}

// Leggi body
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if ($data === null) {
    logCallback('JSON non valido');
    respondError(400, 'JSON non valido');
}

// Valida struttura
if (!isset($data['callback_type']) || $data['callback_type'] !== 'validation_response') {
    respondError(400, 'Tipo callback non valido');
}

if (!isset($data['token']) || !isset($data['status'])) {
    respondError(400, 'Campi mancanti: token, status');
}

$token = $data['token'];
$status = $data['status'];
$attestation = $data['attestation'] ?? null;

logCallback('Callback ricevuto', ['token' => $token, 'status' => $status]);

// Carica CV e trova esperienza con questo token
$cv = loadCV();
$found = false;

foreach ($cv['experiences'] as &$exp) {
    if (isset($exp['validation_token']) && $exp['validation_token'] === $token) {
        $found = true;
        
        if ($status === 'validated') {
            $exp['status'] = 'validated';
            $exp['validated_at'] = date('c');
            
            // Salva attestazione se presente
            if ($attestation !== null) {
                // Verifica firma dell'attestazione
                if (isset($attestation['attestation']) && isset($attestation['signature']) && isset($attestation['issuer_public_key'])) {
                    try {
                        $isValid = $crypto->verifySignature(
                            $attestation['attestation'],
                            $attestation['signature'],
                            $attestation['issuer_public_key']
                        );
                        
                        if ($isValid) {
                            $exp['attestation'] = $attestation;
                            $exp['attestation_verified'] = true;
                            logCallback('Attestazione verificata', ['token' => $token]);
                        } else {
                            $exp['attestation'] = $attestation;
                            $exp['attestation_verified'] = false;
                            logCallback('Attestazione NON verificata', ['token' => $token]);
                        }
                    } catch (Exception $e) {
                        $exp['attestation'] = $attestation;
                        $exp['attestation_verified'] = false;
                        logCallback('Errore verifica attestazione', ['error' => $e->getMessage()]);
                    }
                } else {
                    $exp['attestation'] = $attestation;
                }
            }
        } elseif ($status === 'rejected') {
            $exp['status'] = 'rejected';
            $exp['rejected_at'] = date('c');
            $exp['rejection_reason'] = $data['reason'] ?? null;
        }
        
        break;
    }
}

if (!$found) {
    logCallback('Token non trovato', ['token' => $token]);
    respondError(404, 'Token non trovato');
}

// Salva CV aggiornato
saveCV($cv);

logCallback('CV aggiornato', ['token' => $token, 'status' => $status]);

respondSuccess([
    'message' => 'Callback elaborato',
    'status' => $status,
]);
