<?php

declare(strict_types=1);

namespace CVerify;

use RuntimeException;
use InvalidArgumentException;

// Previene ridefinizione se già caricata (fix per OPcache)
if (class_exists('CVerify\\DNS', false)) {
    return;
}

/**
 * Classe per la gestione delle query DNS per CVerify.
 * Permette di verificare identità e recuperare chiavi pubbliche dai record TXT.
 * 
 * I record vengono inseriti direttamente sul dominio principale (@), non su subdomain.
 */
class DNS
{
    private const CVERIFY_ID_PREFIX = 'cverify-id=';
    private const CVERIFY_KEY_PREFIX = 'cverify-pubkey=';
    private const DNS_TXT_MAX_LENGTH = 255;

    private Crypto $crypto;

    public function __construct(?Crypto $crypto = null)
    {
        $this->crypto = $crypto ?? new Crypto();
    }

    /**
     * Verifica che un dominio contenga un record cverify-id con la chiave attesa.
     *
     * @param string $domain Il dominio da verificare
     * @param string $expectedKey La chiave/identificativo atteso
     * @return bool True se il record esiste e corrisponde
     * @throws InvalidArgumentException Se i parametri non sono validi
     */
    public function verifyIdentity(string $domain, string $expectedKey): bool
    {
        $this->validateDomain($domain);
        
        if (empty($expectedKey)) {
            throw new InvalidArgumentException('La chiave attesa non può essere vuota');
        }

        $txtRecords = $this->getTxtRecords($domain);
        $expectedRecord = self::CVERIFY_ID_PREFIX . $this->sanitizeForDns($expectedKey);

        foreach ($txtRecords as $record) {
            $cleanRecord = $this->cleanTxtRecord($record);
            
            if ($cleanRecord === $expectedRecord) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recupera e ricostruisce la chiave pubblica PEM dal DNS di un dominio.
     *
     * @param string $domain Il dominio da cui recuperare la chiave
     * @return string|null La chiave pubblica in formato PEM, o null se non trovata
     * @throws InvalidArgumentException Se il dominio non è valido
     * @throws RuntimeException Se la chiave recuperata non è valida
     */
    public function getPublicKeyFromDNS(string $domain): ?string
    {
        $this->validateDomain($domain);

        $txtRecords = $this->getTxtRecords($domain);
        $keyParts = [];

        foreach ($txtRecords as $record) {
            $cleanRecord = $this->cleanTxtRecord($record);
            
            // Cerca record con prefisso cverify-pubkey=
            if (str_starts_with($cleanRecord, self::CVERIFY_KEY_PREFIX)) {
                $keyData = substr($cleanRecord, strlen(self::CVERIFY_KEY_PREFIX));
                
                // Gestisci chiavi divise in più record (formato: indice.dati)
                if (preg_match('/^(\d+)\.(.+)$/', $keyData, $matches)) {
                    $index = (int) $matches[1];
                    $keyParts[$index] = $matches[2];
                } else {
                    // Chiave singola (non divisa)
                    $keyParts[0] = $keyData;
                }
            }
        }

        if (empty($keyParts)) {
            return null;
        }

        // Ricostruisci la chiave dai frammenti ordinati
        ksort($keyParts, SORT_NUMERIC);
        $dnsKey = implode('', $keyParts);

        // Converti in formato PEM
        $pemKey = $this->crypto->dnsFormatToPem($dnsKey);

        // Valida la chiave ricostruita
        if (!$this->crypto->isValidPublicKey($pemKey)) {
            throw new RuntimeException(
                'La chiave pubblica recuperata dal DNS non è valida'
            );
        }

        return $pemKey;
    }

    /**
     * Recupera l'identificativo CVerify dal DNS.
     *
     * @param string $domain Il dominio da interrogare
     * @return string|null L'identificativo, o null se non trovato
     * @throws InvalidArgumentException Se il dominio non è valido
     */
    public function getIdentityFromDNS(string $domain): ?string
    {
        $this->validateDomain($domain);

        $txtRecords = $this->getTxtRecords($domain);

        foreach ($txtRecords as $record) {
            $cleanRecord = $this->cleanTxtRecord($record);
            
            if (str_starts_with($cleanRecord, self::CVERIFY_ID_PREFIX)) {
                return substr($cleanRecord, strlen(self::CVERIFY_ID_PREFIX));
            }
        }

        return null;
    }

    /**
     * Genera i record DNS TXT necessari per pubblicare una chiave pubblica.
     * Divide automaticamente la chiave se supera il limite DNS.
     * 
     * I record vanno inseriti sul dominio principale (@).
     *
     * @param string $publicKeyPem La chiave pubblica in formato PEM
     * @return array Array di record con name, type e value
     */
    public function generateDnsRecordsForKey(string $publicKey): array
    {
        $dnsKey = $this->crypto->pemToDnsFormat($publicKey);

        // Calcola spazio disponibile per i dati (escludendo prefisso e indice)
        $prefixLength = strlen(self::CVERIFY_KEY_PREFIX);
        $indexOverhead = 4; // "X." dove X può essere 0-99
        $maxDataLength = self::DNS_TXT_MAX_LENGTH - $prefixLength - $indexOverhead;

        // Se la chiave sta in un singolo record
        if (strlen($dnsKey) + $prefixLength <= self::DNS_TXT_MAX_LENGTH) {
            return [
                [
                    'name' => '@',
                    'type' => 'TXT',
                    'value' => self::CVERIFY_KEY_PREFIX . $dnsKey
                ]
            ];
        }

        // Dividi la chiave in più record
        $chunks = str_split($dnsKey, $maxDataLength);
        $records = [];

        foreach ($chunks as $index => $chunk) {
            $records[] = [
                'name' => '@',
                'type' => 'TXT',
                'value' => self::CVERIFY_KEY_PREFIX . $index . '.' . $chunk
            ];
        }

        return $records;
    }

    /**
     * Genera il record DNS TXT per l'identificativo CVerify.
     * 
     * Il record va inserito sul dominio principale (@).
     *
     * @param string $identifier L'identificativo da pubblicare
     * @return string La stringa del record TXT
     */
    public function generateDnsRecordForIdentity(string $identifier): string
    {
        $sanitized = $this->sanitizeForDns($identifier);
        
        $record = self::CVERIFY_ID_PREFIX . $sanitized;
        
        if (strlen($record) > self::DNS_TXT_MAX_LENGTH) {
            throw new InvalidArgumentException(
                'L\'identificativo è troppo lungo per un record DNS TXT'
            );
        }

        return $record;
    }

    /**
     * Recupera tutti i record TXT per un dominio.
     * Query sul dominio principale (record @).
     *
     * @param string $domain Il dominio da interrogare
     * @return array<string> Array di record TXT (senza duplicati)
     */
    private function getTxtRecords(string $domain): array
    {
        // Normalizza il dominio (rimuovi eventuali prefissi)
        $domain = $this->normalizeDomain($domain);
        
        // Query direttamente sul dominio principale (record @)
        $records = @dns_get_record($domain, DNS_TXT);

        if ($records === false) {
            return [];
        }

        $txtValues = [];
        
        foreach ($records as $record) {
            // Preferisci 'txt' che è il campo principale
            // 'entries' spesso contiene gli stessi dati, quindi evitiamo duplicati
            if (isset($record['txt']) && !empty($record['txt'])) {
                $txtValues[] = $record['txt'];
            } elseif (isset($record['entries']) && is_array($record['entries'])) {
                // Usa entries solo se txt non è disponibile
                foreach ($record['entries'] as $entry) {
                    $txtValues[] = $entry;
                }
            }
        }

        // Rimuovi eventuali duplicati
        return array_values(array_unique($txtValues));
    }

    /**
     * Normalizza un dominio rimuovendo protocolli, www e trailing slash.
     *
     * @param string $domain Il dominio da normalizzare
     * @return string Il dominio normalizzato
     */
    private function normalizeDomain(string $domain): string
    {
        // Rimuovi protocollo
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Rimuovi www.
        $domain = preg_replace('#^www\.#i', '', $domain);
        // Rimuovi trailing slash e path
        $domain = preg_replace('#/.*$#', '', $domain);
        // Rimuovi eventuali spazi
        $domain = trim($domain);
        
        return strtolower($domain);
    }

    /**
     * Pulisce un record TXT rimuovendo caratteri non desiderati.
     *
     * @param string $record Il record da pulire
     * @return string Il record pulito
     */
    private function cleanTxtRecord(string $record): string
    {
        // Rimuovi virgolette iniziali e finali
        $record = trim($record, '"\'');
        
        // Rimuovi newlines, carriage returns e tabulazioni
        $record = str_replace(["\r\n", "\r", "\n", "\t"], '', $record);
        
        // Rimuovi spazi multipli
        $record = preg_replace('/\s+/', ' ', $record);
        
        // Trim finale
        return trim($record);
    }

    /**
     * Sanitizza una stringa per l'inserimento in un record DNS.
     * Rimuove caratteri non validi e normalizza la stringa.
     *
     * @param string $value La stringa da sanitizzare
     * @return string La stringa sanitizzata
     */
    private function sanitizeForDns(string $value): string
    {
        // Rimuovi header/footer PEM se presenti
        $value = preg_replace('/-----BEGIN [A-Z ]+-----/', '', $value);
        $value = preg_replace('/-----END [A-Z ]+-----/', '', $value);
        
        // Rimuovi tutti i whitespace
        $value = preg_replace('/\s+/', '', $value);
        
        return $value;
    }

    /**
     * Valida un nome di dominio.
     *
     * @param string $domain Il dominio da validare
     * @throws InvalidArgumentException Se il dominio non è valido
     */
    private function validateDomain(string $domain): void
    {
        if (empty($domain)) {
            throw new InvalidArgumentException('Il dominio non può essere vuoto');
        }

        // Normalizza prima di validare
        $domain = $this->normalizeDomain($domain);

        // Validazione base del formato dominio
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException(
                'Formato dominio non valido: ' . $domain
            );
        }
    }

    /**
     * Verifica completa: controlla identità e validità della chiave.
     *
     * @param string $domain Il dominio da verificare
     * @param string $expectedIdentity L'identità attesa (opzionale, null per saltare)
     * @return array{valid: bool, identity: ?string, cverify_id: ?string, publicKey: ?string, errors: array<string>}
     */
    public function verifyDomain(string $domain, ?string $expectedIdentity = null): array
    {
        $result = [
            'valid' => false,
            'identity' => null,
            'cverify_id' => null,
            'publicKey' => null,
            'errors' => [],
        ];

        try {
            $this->validateDomain($domain);
            $normalizedDomain = $this->normalizeDomain($domain);
        } catch (InvalidArgumentException $e) {
            $result['errors'][] = $e->getMessage();
            return $result;
        }

        // Recupera identità
        try {
            $identity = $this->getIdentityFromDNS($domain);
            $result['identity'] = $identity;
            $result['cverify_id'] = $identity;
            
            if ($identity === null) {
                $result['errors'][] = 'Nessun record cverify-id= trovato nel DNS del dominio';
            } elseif ($expectedIdentity !== null && $identity !== $expectedIdentity) {
                $result['errors'][] = 'L\'identificativo trovato (' . substr($identity, 0, 16) . '...) non corrisponde a quello atteso';
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Errore nel recupero dell\'identità: ' . $e->getMessage();
        }

        // Recupera chiave pubblica
        try {
            $publicKey = $this->getPublicKeyFromDNS($domain);
            $result['publicKey'] = $publicKey;
            
            if ($publicKey === null) {
                $result['errors'][] = 'Nessun record cverify-pubkey= trovato nel DNS del dominio';
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Errore nel recupero della chiave pubblica: ' . $e->getMessage();
        }

        // Valida risultato complessivo
        // È valido se ha almeno l'identità O la chiave pubblica
        // Per una verifica completa servono entrambi
        $hasIdentity = !empty($result['cverify_id']);
        $hasPublicKey = !empty($result['publicKey']);
        
        // Verifica che l'identità corrisponda se specificata
        $identityMatches = $expectedIdentity === null || $result['identity'] === $expectedIdentity;
        
        // Valido se ha entrambi i componenti e l'identità corrisponde
        $result['valid'] = $hasIdentity && $hasPublicKey && $identityMatches;

        return $result;
    }
    
    /**
     * Metodo di debug per ottenere tutti i record TXT grezzi.
     *
     * @param string $domain Il dominio da interrogare
     * @return array Tutti i record TXT trovati
     */
    public function debugGetAllTxtRecords(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $records = @dns_get_record($domain, DNS_TXT);
        
        return [
            'domain' => $domain,
            'raw_records' => $records ?: [],
            'parsed_records' => $this->getTxtRecords($domain),
        ];
    }
}
