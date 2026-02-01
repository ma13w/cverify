<?php

declare(strict_types=1);

namespace CVerify;

use RuntimeException;
use InvalidArgumentException;

/**
 * Security utilities for CVerify.
 * Provides CSRF protection, rate limiting, HTTPS enforcement, and input validation.
 */
class Security
{
    private const RATE_LIMIT_DIR = 'rate_limits';
    private const MAX_JSON_SIZE = 1048576; // 1MB
    private const MAX_FILE_SIZE = 10485760; // 10MB
    
    private string $dataDir;
    
    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
        
        $rateLimitDir = $this->dataDir . '/' . self::RATE_LIMIT_DIR;
        if (!is_dir($rateLimitDir)) {
            mkdir($rateLimitDir, 0700, true);
        }
    }
    
    /**
     * Start a secure session with proper cookie parameters.
     * Automatically detects development mode (localhost/HTTP) and adjusts security accordingly.
     */
    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Detect if we're in development mode (localhost or HTTP)
        $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) 
                       || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                   || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
        
        // In production, require HTTPS. In development (localhost), allow HTTP
        $secureCookie = $isHttps || (!$isLocalhost && !$isHttps);
        
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => $isLocalhost ? '' : ($_SERVER['HTTP_HOST'] ?? ''), // Empty domain for localhost
            'secure' => $secureCookie,     // HTTPS only in production
            'httponly' => true,            // No JavaScript access
            'samesite' => 'Strict'         // CSRF protection
        ]);
        
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Enforce HTTPS connection.
     * 
     * @throws RuntimeException If not using HTTPS
     */
    public static function enforceHttps(): void
    {
        if (php_sapi_name() === 'cli') {
            return; // Skip in CLI mode
        }
        
        if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
            throw new RuntimeException('HTTPS connection required for security');
        }
    }
    
    /**
     * Generate a CSRF token.
     * 
     * @return string The CSRF token
     */
    public static function generateCsrfToken(): string
    {
        self::startSecureSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate a CSRF token.
     * 
     * @param string|null $token The token to validate
     * @return bool True if valid
     */
    public static function validateCsrfToken(?string $token): bool
    {
        self::startSecureSession();
        
        if (empty($token) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Require a valid CSRF token or die.
     * 
     * @param string|null $token The token to validate
     * @throws RuntimeException If token is invalid
     */
    public static function requireCsrfToken(?string $token): void
    {
        if (!self::validateCsrfToken($token)) {
            http_response_code(403);
            throw new RuntimeException('CSRF token validation failed');
        }
    }
    
    /**
     * Check rate limit for an identifier.
     * 
     * @param string $identifier Unique identifier (IP, domain, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if within limits
     */
    public function checkRateLimit(string $identifier, int $maxRequests = 10, int $timeWindow = 60): bool
    {
        $cacheFile = $this->dataDir . '/' . self::RATE_LIMIT_DIR . '/' . md5($identifier);
        
        $now = time();
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data && isset($data['count'], $data['start'])) {
                // Clean old entries
                if ($now - $data['start'] >= $timeWindow) {
                    $data = ['count' => 0, 'start' => $now];
                } elseif ($data['count'] >= $maxRequests) {
                    return false;
                }
                
                $data['count']++;
            } else {
                $data = ['count' => 1, 'start' => $now];
            }
        } else {
            $data = ['count' => 1, 'start' => $now];
        }
        
        file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        chmod($cacheFile, 0600);
        
        return true;
    }
    
    /**
     * Enforce rate limit or die.
     * 
     * @param string $identifier Unique identifier
     * @param int $maxRequests Maximum requests allowed
     * @param int $timeWindow Time window in seconds
     * @throws RuntimeException If rate limit exceeded
     */
    public function enforceRateLimit(string $identifier, int $maxRequests = 10, int $timeWindow = 60): void
    {
        if (!$this->checkRateLimit($identifier, $maxRequests, $timeWindow)) {
            http_response_code(429);
            throw new RuntimeException('Rate limit exceeded. Please try again later.');
        }
    }
    
    /**
     * Validate and decode JSON data with size limits.
     * 
     * @param string $json The JSON string
     * @param int $maxSize Maximum size in bytes
     * @return array The decoded data
     * @throws InvalidArgumentException If JSON is invalid or too large
     */
    public static function validateJson(string $json, int $maxSize = self::MAX_JSON_SIZE): array
    {
        if (strlen($json) > $maxSize) {
            throw new InvalidArgumentException('JSON data exceeds maximum size limit');
        }
        
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON data: ' . $e->getMessage());
        }
        
        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON must decode to an array');
        }
        
        return $data;
    }
    
    /**
     * Validate file size before reading.
     * 
     * @param string $filePath Path to file
     * @param int $maxSize Maximum size in bytes
     * @throws InvalidArgumentException If file is too large
     */
    public static function validateFileSize(string $filePath, int $maxSize = self::MAX_FILE_SIZE): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException('File does not exist');
        }
        
        $size = filesize($filePath);
        
        if ($size === false || $size > $maxSize) {
            throw new InvalidArgumentException('File exceeds maximum size limit');
        }
    }
    
    /**
     * Safely write file with proper permissions.
     * 
     * @param string $filePath Path to file
     * @param string $data Data to write
     * @param int $permissions File permissions (default: 0600)
     */
    public static function safeFileWrite(string $filePath, string $data, int $permissions = 0600): void
    {
        $result = file_put_contents($filePath, $data, LOCK_EX);
        
        if ($result === false) {
            throw new RuntimeException('Failed to write file: ' . $filePath);
        }
        
        chmod($filePath, $permissions);
    }
    
    /**
     * Safely read JSON file with validation.
     * 
     * @param string $filePath Path to file
     * @param int $maxSize Maximum file size
     * @return array The decoded JSON data
     */
    public static function safeReadJson(string $filePath, int $maxSize = self::MAX_JSON_SIZE): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        self::validateFileSize($filePath, $maxSize);
        
        $json = file_get_contents($filePath);
        
        if ($json === false) {
            throw new RuntimeException('Failed to read file: ' . $filePath);
        }
        
        return self::validateJson($json, $maxSize);
    }
    
    /**
     * Sanitize domain for filesystem use with path traversal protection.
     * 
     * @param string $domain The domain to sanitize
     * @return string The sanitized domain
     * @throws InvalidArgumentException If domain is invalid
     */
    public static function sanitizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain); // Remove path
        
        // Block path traversal
        if (strpos($domain, '..') !== false || strpos($domain, '/') !== false || strpos($domain, '\\') !== false) {
            throw new InvalidArgumentException('Invalid domain format: path traversal detected');
        }
        
        // Validate domain format
        if (!preg_match('/^[a-z0-9][a-z0-9.-]{0,61}[a-z0-9]\.[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException('Invalid domain format');
        }
        
        // Additional check: ensure no dangerous characters
        if (preg_match('/[^a-z0-9.-]/i', $domain)) {
            throw new InvalidArgumentException('Invalid domain characters');
        }
        
        return preg_replace('/[^a-z0-9.-]/', '_', $domain);
    }
    
    /**
     * Clean up old rate limit files.
     * 
     * @param int $maxAge Maximum age in seconds (default: 24 hours)
     */
    public function cleanupRateLimits(int $maxAge = 86400): void
    {
        $rateLimitDir = $this->dataDir . '/' . self::RATE_LIMIT_DIR;
        
        if (!is_dir($rateLimitDir)) {
            return;
        }
        
        $files = glob($rateLimitDir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
    
    /**
     * Validate input string length.
     * 
     * @param string $input The input to validate
     * @param int $maxLength Maximum length
     * @param string $fieldName Field name for error message
     * @throws InvalidArgumentException If too long
     */
    public static function validateLength(string $input, int $maxLength, string $fieldName = 'Input'): void
    {
        if (strlen($input) > $maxLength) {
            throw new InvalidArgumentException("{$fieldName} exceeds maximum length of {$maxLength} characters");
        }
    }
}
