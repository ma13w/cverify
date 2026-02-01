<?php
/**
 * CVerify Relay Server - Configuration
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/src/Security.php';

use CVerify\Security;

// Directories
define('DATA_DIR', __DIR__ . '/data');
define('PENDING_DIR', DATA_DIR . '/pending');
define('ATTESTATIONS_DIR', DATA_DIR . '/attestations');
define('FEED_FILE', DATA_DIR . '/feed.json');

// Create directories if not exist
foreach ([DATA_DIR, PENDING_DIR, ATTESTATIONS_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize feed file
if (!file_exists(FEED_FILE)) {
    file_put_contents(FEED_FILE, json_encode([]));
}

/**
 * Sanitize domain for filesystem use
 * Now uses Security class for enhanced protection
 */
function sanitizeDomain(string $domain): string {
    return Security::sanitizeDomain($domain);
}

/**
 * Add entry to public feed
 */
function addToFeed(string $type, array $data): void {
    $feed = json_decode(file_get_contents(FEED_FILE), true) ?: [];
    
    // Tipo deve essere 'request' o 'attestation'
    $feedType = $type;
    if (strpos($type, 'request') !== false) {
        $feedType = 'request';
    } elseif (strpos($type, 'attestation') !== false) {
        $feedType = 'attestation';
    }
    
    array_unshift($feed, [
        'id' => uniqid('feed_'),
        'type' => $feedType,
        'user_domain' => $data['user_domain'] ?? null,
        'company_domain' => $data['company_domain'] ?? $data['issuer_domain'] ?? null,
        'role' => $data['role'] ?? null,
        'encrypted' => $data['encrypted'] ?? false,
        'timestamp' => date('c'),
    ]);
    
    // Keep only last 1000 entries
    $feed = array_slice($feed, 0, 1000);
    
    Security::safeFileWrite(FEED_FILE, json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0644);
}

/**
 * Get statistics
 */
function getStats(): array {
    $pendingCount = 0;
    $attestationCount = 0;
    $users = [];
    $companies = [];
    
    // Count pending requests
    if (is_dir(PENDING_DIR)) {
        foreach (glob(PENDING_DIR . '/*', GLOB_ONLYDIR) as $companyDir) {
            $companies[basename($companyDir)] = true;
            $pendingCount += count(glob($companyDir . '/*.json'));
        }
    }
    
    // Count attestations
    if (is_dir(ATTESTATIONS_DIR)) {
        foreach (glob(ATTESTATIONS_DIR . '/*', GLOB_ONLYDIR) as $userDir) {
            $users[basename($userDir)] = true;
            $attestationCount += count(glob($userDir . '/*.json'));
        }
    }
    
    return [
        'pending_requests' => $pendingCount,
        'attestations' => $attestationCount,
        'unique_users' => count($users),
        'unique_companies' => count($companies),
    ];
}

/**
 * JSON Response helper
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Error response helper
 */
function errorResponse(string $message, int $code = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $code);
}
