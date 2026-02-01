<?php
/**
 * CVerify - Test DNS Configuration
 * Script per verificare la corretta configurazione DNS.
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Crypto.php';
require_once __DIR__ . '/src/DNS.php';

use CVerify\Crypto;
use CVerify\DNS;

// Colori per output CLI
function colorize(string $text, string $color): string {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

echo "\n" . colorize("╔═══════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║              CVerify - DNS Configuration Test                  ║", 'blue') . "\n";
echo colorize("╚═══════════════════════════════════════════════════════════════╝", 'blue') . "\n\n";

// Dominio da testare (passato come argomento o default)
$domain = $argv[1] ?? 'calimatteo.info.wf';
$expectedFingerprint = $argv[2] ?? null;

echo colorize("Dominio: ", 'cyan') . $domain . "\n";
if ($expectedFingerprint) {
    echo colorize("Fingerprint atteso: ", 'cyan') . substr($expectedFingerprint, 0, 32) . "...\n";
}
echo "\n";

$crypto = new Crypto();
$dns = new DNS($crypto);

// Step 1: Debug - Mostra tutti i record TXT
echo colorize("📋 Step 1: Recupero record TXT grezzi...", 'yellow') . "\n";
$debug = $dns->debugGetAllTxtRecords($domain);

echo "   Dominio normalizzato: " . colorize($debug['domain'], 'cyan') . "\n";
echo "   Record TXT trovati: " . count($debug['parsed_records']) . "\n\n";

if (empty($debug['raw_records'])) {
    echo colorize("   ⚠ Nessun record TXT trovato!", 'red') . "\n";
    echo "   Verifica che:\n";
    echo "   - Il dominio sia corretto\n";
    echo "   - I record DNS siano stati propagati\n";
    echo "   - I record siano di tipo TXT sul dominio principale (@)\n\n";
} else {
    echo "   Record grezzi:\n";
    foreach ($debug['raw_records'] as $rec) {
        $txt = $rec['txt'] ?? '(vuoto)';
        if (strlen($txt) > 60) {
            $txt = substr($txt, 0, 60) . '...';
        }
        echo "   - " . colorize($txt, 'cyan') . "\n";
    }
    echo "\n";
}

// Step 2: Cerca record CVerify
echo colorize("🔍 Step 2: Ricerca record CVerify...", 'yellow') . "\n";

$cverifyRecords = [
    'identity' => null,
    'pubkey_parts' => [],
];

foreach ($debug['parsed_records'] as $record) {
    if (str_starts_with($record, 'cverify-id=')) {
        $cverifyRecords['identity'] = substr($record, strlen('cverify-id='));
        echo "   ✅ Trovato cverify-id: " . colorize(substr($cverifyRecords['identity'], 0, 32) . '...', 'green') . "\n";
    } elseif (str_starts_with($record, 'cverify-pubkey=')) {
        $keyData = substr($record, strlen('cverify-pubkey='));
        if (preg_match('/^(\d+)\.(.+)$/', $keyData, $matches)) {
            $index = (int)$matches[1];
            $cverifyRecords['pubkey_parts'][$index] = $matches[2];
            echo "   ✅ Trovato cverify-pubkey parte " . colorize((string)$index, 'green') . " (" . strlen($matches[2]) . " chars)\n";
        } else {
            $cverifyRecords['pubkey_parts'][0] = $keyData;
            echo "   ✅ Trovato cverify-pubkey (singolo): " . colorize(strlen($keyData) . " chars", 'green') . "\n";
        }
    }
}

if (!$cverifyRecords['identity']) {
    echo "   " . colorize("❌ Record cverify-id non trovato!", 'red') . "\n";
}
if (empty($cverifyRecords['pubkey_parts'])) {
    echo "   " . colorize("❌ Record cverify-pubkey non trovato!", 'red') . "\n";
}
echo "\n";

// Step 3: Verifica completa
echo colorize("🔐 Step 3: Verifica completa dominio...", 'yellow') . "\n";

$result = $dns->verifyDomain($domain, $expectedFingerprint);

echo "   Risultato: " . ($result['valid'] ? colorize("✅ VALIDO", 'green') : colorize("❌ NON VALIDO", 'red')) . "\n";
echo "   Identità trovata: " . ($result['cverify_id'] ? colorize("Sì", 'green') : colorize("No", 'red')) . "\n";
echo "   Chiave pubblica trovata: " . ($result['publicKey'] ? colorize("Sì", 'green') : colorize("No", 'red')) . "\n";

if (!empty($result['errors'])) {
    echo "\n   " . colorize("Errori:", 'red') . "\n";
    foreach ($result['errors'] as $error) {
        echo "   - " . $error . "\n";
    }
}

if (!empty($result['debug'])) {
    echo "\n   Debug info:\n";
    foreach ($result['debug'] as $key => $value) {
        echo "   - $key: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
    }
}

echo "\n";

// Step 4: Test recupero chiave pubblica
if ($result['publicKey']) {
    echo colorize("🔑 Step 4: Validazione chiave pubblica...", 'yellow') . "\n";
    
    if ($crypto->isValidPublicKey($result['publicKey'])) {
        echo "   " . colorize("✅ Chiave pubblica valida!", 'green') . "\n";
        
        // Mostra fingerprint
        $fingerprint = $crypto->getKeyFingerprint($result['publicKey']);
        echo "   Fingerprint: " . colorize(substr($fingerprint, 0, 32) . '...', 'cyan') . "\n";
        
        if ($expectedFingerprint && $fingerprint === $expectedFingerprint) {
            echo "   " . colorize("✅ Fingerprint corrisponde!", 'green') . "\n";
        } elseif ($expectedFingerprint) {
            echo "   " . colorize("❌ Fingerprint NON corrisponde!", 'red') . "\n";
        }
    } else {
        echo "   " . colorize("❌ Chiave pubblica non valida!", 'red') . "\n";
    }
}

echo "\n" . colorize("═══════════════════════════════════════════════════════════════", 'blue') . "\n";
echo colorize("Test completato.", 'cyan') . "\n\n";