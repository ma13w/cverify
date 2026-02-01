<?php
/**
 * CVerify Relay API - Public Feed
 * 
 * GET /api/feed.php?limit=50
 * 
 * Recupera il feed pubblico delle attività recenti.
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

$limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
$offset = max(0, intval($_GET['offset'] ?? 0));
$type = $_GET['type'] ?? null; // 'request' or 'attestation'

$feed = json_decode(file_get_contents(FEED_FILE), true) ?: [];

// Filter by type if specified
if ($type) {
    $feed = array_filter($feed, fn($item) => $item['type'] === $type);
    $feed = array_values($feed);
}

$total = count($feed);
$feed = array_slice($feed, $offset, $limit);

jsonResponse([
    'success' => true,
    'total' => $total,
    'offset' => $offset,
    'limit' => $limit,
    'feed' => $feed,
]);
