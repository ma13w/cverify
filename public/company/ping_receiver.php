<?php

declare(strict_types=1);

/**
 * CVerify - Ping Receiver (Endpoint Aziendale)
 * API pubblica per ricevere richieste di validazione dagli utenti.
 * 
 * Endpoint: POST /cverify/api/validate
 */

require_once __DIR__ . '/../src/Crypto.php';
require_once __DIR__ . '/../src/DNS.php';

use CVerify\Crypto;
use CVerify\DNS;

// Configurazione
define('COMPANY_DATA_DIR', __DIR__ . '/data');
define('PENDING_FILE', COMPANY_DATA_DIR . '/pending_validations.json');
define('COMPANY_CONFIG_FILE', COMPANY_DATA_DIR . '/config.json');
define('LOG_FILE', COMPANY_DATA_DIR . '/requests.log');

// Crea directory se non esiste
if (!is_dir(COMPANY_DATA_DIR)) {
    mkdir(COMPANY_DATA_DIR, 0755, true);
}

// Headers per API
header('Content-Type: application/json');
header('X-CVerify-Version: 1.0');

// Inizializza classi
$crypto = new Crypto();
$dns = new DNS($crypto);

/**
 * Risponde con un errore JSON.
 */
function respondError(int $httpCode, string $message, ?string $details = null): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Risponde con successo JSON.
 */
function respondSuccess(array $data = []): void
{
    http_response_code(200);
    echo json_encode(array_merge([
        'success' => true,
        'timestamp' => date('c'),
    ], $data), JSON_PRETTY_PRINT);
    exit;
}

/**
 * Logga una richiesta.
 */
function logRequest(string $message, array $context = []): void
{
    $logEntry = sprintf(
        "[%s] %s %s\n",
        date('c'),
        $message,
        !empty($context) ? json_encode($context) : ''
    );
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Carica le validazioni pendenti.
 */
function loadPendingValidations(): array
{
    if (!file_exists(PENDING_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents(PENDING_FILE), true);
    return is_array($data) ? $data : [];
}

/**
 * Salva le validazioni pendenti.
 */
function savePendingValidations(array $validations): void
{
    file_put_contents(
        PENDING_FILE,
        json_encode($validations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * Scarica e verifica il cv.json dell'utente.
 */
function fetchUserCV(string $userDomain): ?array
{
    // Pulisci dominio
    $domain = preg_replace('#^https?://#', '', $userDomain);
    $domain = rtrim($domain, '/');
    
    // Prova diversi percorsi comuni
    $paths = [
        '/user/cv.json',
        '/cverify/cv.json',
        '/cv.json',
        '/.well-known/cverify/cv.json',
    ];
    
    foreach ($paths as $path) {
        $url = 'https://' . $domain . $path;
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $cv = json_decode($response, true);
            if (is_array($cv) && isset($cv['experiences'])) {
                return $cv;
            }
        }
    }
    
    return null;
}

/**
 * Ottiene il dominio di questa azienda dalla configurazione.
 */
function getCompanyDomain(): ?string
{
    if (!file_exists(COMPANY_CONFIG_FILE)) {
        return null;
    }
    
    $config = json_decode(file_get_contents(COMPANY_CONFIG_FILE), true);
    return $config['domain'] ?? null;
}

// ============================================
// GESTIONE RICHIESTE
// ============================================

// Gestisci OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CVerify-Version');
    http_response_code(204);
    exit;
}

// Accetta solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError(405, 'Metodo non consentito', 'Usa POST per inviare richieste di validazione');
}

// Leggi il body della richiesta
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    logRequest('Richiesta vuota ricevuta');
    respondError(400, 'Body richiesta vuoto');
}

// Parse JSON
$request = json_decode($rawBody, true);

if ($request === null) {
    logRequest('JSON non valido', ['raw' => substr($rawBody, 0, 200)]);
    respondError(400, 'JSON non valido');
}

// Valida struttura richiesta
if (!isset($request['payload']) || !isset($request['signature'])) {
    logRequest('Struttura richiesta non valida', ['keys' => array_keys($request)]);
    respondError(400, 'Struttura richiesta non valida', 'Richiesti: payload, signature');
}

$payload = $request['payload'];
$signature = $request['signature'];

// Valida payload
$requiredFields = ['request_type', 'token', 'owner', 'experience'];
foreach ($requiredFields as $field) {
    if (!isset($payload[$field])) {
        respondError(400, "Campo mancante: $field");
    }
}

if ($payload['request_type'] !== 'validation_request') {
    respondError(400, 'Tipo richiesta non valido');
}

$ownerDomain = $payload['owner']['domain'] ?? null;
$ownerFingerprint = $payload['owner']['fingerprint'] ?? null;
$experience = $payload['experience'] ?? [];
$token = $payload['token'] ?? '';

if (empty($ownerDomain) || empty($ownerFingerprint)) {
    respondError(400, 'Dati proprietario incompleti');
}

logRequest('Richiesta validazione ricevuta', [
    'owner_domain' => $ownerDomain,
    'experience_role' => $experience['role'] ?? 'N/A',
]);

// ============================================
// VERIFICA DNS E FIRMA
// ============================================

// 1. Verifica che il dominio abbia record DNS CVerify
try {
    $dnsResult = $dns->verifyDomain($ownerDomain, $ownerFingerprint);
    
    if (!$dnsResult['valid']) {
        logRequest('Verifica DNS fallita', ['errors' => $dnsResult['errors']]);
        respondError(403, 'Verifica DNS fallita', implode('; ', $dnsResult['errors']));
    }
    
    $userPublicKey = $dnsResult['publicKey'];
    
} catch (Exception $e) {
    logRequest('Errore verifica DNS', ['error' => $e->getMessage()]);
    respondError(500, 'Errore nella verifica DNS', $e->getMessage());
}

// 2. Verifica la firma della richiesta
try {
    $signatureValid = $crypto->verifySignature($payload, $signature, $userPublicKey);
    
    if (!$signatureValid) {
        logRequest('Firma non valida', ['owner' => $ownerDomain]);
        respondError(403, 'Firma non valida', 'La firma della richiesta non corrisponde alla chiave pubblica');
    }
    
} catch (Exception $e) {
    logRequest('Errore verifica firma', ['error' => $e->getMessage()]);
    respondError(500, 'Errore nella verifica della firma', $e->getMessage());
}

// 3. Scarica il CV dell'utente
$userCV = fetchUserCV($ownerDomain);

if ($userCV === null) {
    logRequest('CV non trovato', ['domain' => $ownerDomain]);
    // Non blocchiamo, usiamo i dati dalla richiesta
    $userCV = [
        'owner_domain' => $ownerDomain,
        'owner_name' => 'N/A',
        'experiences' => [],
    ];
}

// 4. Ottieni dominio azienda
$companyDomain = getCompanyDomain();

if ($companyDomain === null) {
    // Se non configurato, estrai dal server
    $companyDomain = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown';
}

// 5. Filtra esperienze relative a questa azienda
$relevantExperiences = [];

// Prima aggiungi l'esperienza dalla richiesta
$relevantExperiences[] = $experience;

// Poi cerca nel CV scaricato
foreach ($userCV['experiences'] ?? [] as $cvExp) {
    $expDomain = $cvExp['company_domain'] ?? '';
    
    // Normalizza e confronta domini
    $expDomain = strtolower(preg_replace('#^(www\.)?#', '', $expDomain));
    $companyDomainNorm = strtolower(preg_replace('#^(www\.)?#', '', $companyDomain));
    
    if ($expDomain === $companyDomainNorm) {
        // Evita duplicati
        $isDuplicate = false;
        foreach ($relevantExperiences as $existing) {
            if (($existing['id'] ?? '') === ($cvExp['id'] ?? '')) {
                $isDuplicate = true;
                break;
            }
        }
        if (!$isDuplicate) {
            $relevantExperiences[] = $cvExp;
        }
    }
}

// ============================================
// SALVA RICHIESTA PENDENTE
// ============================================

$pending = loadPendingValidations();

// Crea record di validazione pendente
$validationRecord = [
    'id' => 'val_' . bin2hex(random_bytes(8)),
    'token' => $token,
    'owner_domain' => $ownerDomain,
    'owner_name' => $userCV['owner_name'] ?? $payload['owner']['name'] ?? 'N/A',
    'owner_fingerprint' => $ownerFingerprint,
    'experiences' => $relevantExperiences,
    'status' => 'pending',
    'received_at' => date('c'),
    'signature' => $signature,
    'public_key' => $userPublicKey,
];

// Controlla se esiste già una richiesta con lo stesso token
$existingIndex = null;
foreach ($pending as $index => $p) {
    if ($p['token'] === $token) {
        $existingIndex = $index;
        break;
    }
}

if ($existingIndex !== null) {
    // Aggiorna richiesta esistente
    $pending[$existingIndex] = $validationRecord;
    logRequest('Richiesta aggiornata', ['token' => $token]);
} else {
    // Aggiungi nuova richiesta
    $pending[] = $validationRecord;
    logRequest('Nuova richiesta salvata', ['token' => $token]);
}

savePendingValidations($pending);

// ============================================
// RISPOSTA
// ============================================

respondSuccess([
    'message' => 'Richiesta di validazione ricevuta',
    'validation_id' => $validationRecord['id'],
    'experiences_count' => count($relevantExperiences),
    'status' => 'pending',
]);
