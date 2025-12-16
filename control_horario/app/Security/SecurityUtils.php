<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Utilidades de Seguridad Adicionales
 * Funciones helper para validaciones comunes y prácticas seguras
 */
class SecurityUtils
{
    /**
     * Genera un token seguro para formularios/sesiones
     */
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Valida contraseña de manera segura sin timing attacks
     */
    public static function comparePasswords(string $hash, string $password): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash de contraseña seguro con BCRYPT
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Valida si una contraseña necesita rehashing
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Sanitiza nombres de archivo
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path separators
        $filename = basename($filename);
        // Remove special characters except . - _
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        // Limit length
        $filename = substr($filename, 0, 255);
        // Remove leading dots
        $filename = ltrim($filename, '.');
        
        if (empty($filename)) {
            $filename = 'file';
        }
        
        return $filename;
    }

    /**
     * Valida MIME type de archivo
     */
    public static function validateMimeType(string $filepath, array $allowed = []): bool
    {
        if (!is_file($filepath)) {
            return false;
        }

        if (empty($allowed)) {
            $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        }

        $mimeType = mime_content_type($filepath);
        return in_array($mimeType, $allowed, true);
    }

    /**
     * Genera código OTP de 6 dígitos para 2FA
     */
    public static function generateOTP(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Hash de OTP para almacenar de manera segura
     */
    public static function hashOTP(string $otp): string
    {
        return hash('sha256', $otp);
    }

    /**
     * Valida OTP sin timing attacks
     */
    public static function verifyOTP(string $userOTP, string $storedHash): bool
    {
        $userHash = hash('sha256', $userOTP);
        return hash_equals($storedHash, $userHash);
    }

    /**
     * Genera URL segura para redirecciones
     */
    public static function getSafeRedirectURL(string $path, string $basePath = ''): string
    {
        if (empty($basePath)) {
            $basePath = function_exists('appBasePath') ? appBasePath() : '/';
        }

        // Remove protocol and domain
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            $parsed = parse_url($path);
            $path = $parsed['path'] ?? '/';
            if (!empty($parsed['query'])) {
                $path .= '?' . $parsed['query'];
            }
        }

        // Only allow relative paths
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        // Prevent directory traversal
        $path = str_replace('..', '', $path);

        // Ensure path starts with basePath
        if (strpos($path, $basePath) !== 0) {
            $path = $basePath;
        }

        return $path;
    }

    /**
     * Valida dirección IP
     */
    public static function validateIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Verifica si IP está en rango CIDR
     */
    public static function isIPInRange(string $ip, string $cidr): bool
    {
        if (!self::validateIP($ip)) {
            return false;
        }

        list($subnet, $mask) = explode('/', $cidr);

        if (!self::validateIP($subnet)) {
            return false;
        }

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - (int)$mask);
        $subnet &= $mask;

        return ($ip & $mask) === $subnet;
    }

    /**
     * Obtiene parámetro GET/POST de forma segura
     */
    public static function getParam(string $key, mixed $default = null, string $type = 'string'): mixed
    {
        $value = $_GET[$key] ?? $_POST[$key] ?? $default;

        if ($value === null || $value === $default) {
            return $default;
        }

        return match ($type) {
            'int' => (int)$value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ?: $default,
            'url' => filter_var($value, FILTER_VALIDATE_URL) ?: $default,
            'json' => json_decode($value, true),
            default => (string)$value,
        };
    }

    /**
     * Registra evento de seguridad en syslog (si disponible)
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        if (function_exists('syslog')) {
            $message = $event;
            if (!empty($context)) {
                $message .= ' ' . json_encode($context);
            }
            syslog(LOG_ALERT, $message);
        }

        // Fallback a error_log
        error_log('SECURITY: ' . $event . ' ' . json_encode($context));
    }

    /**
     * Valida header HTTP requerido
     */
    public static function requireHeader(string $headerName): bool
    {
        $headerValue = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $headerName))] ?? null;
        return !empty($headerValue);
    }

    /**
     * Obtiene header HTTP de forma segura
     */
    public static function getHeader(string $headerName, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
        $value = $_SERVER[$key] ?? $default;
        return trim((string)$value);
    }

    /**
     * Valida que el método HTTP sea permitido
     */
    public static function validateMethod(array $allowed = ['GET', 'POST']): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return in_array($method, $allowed, true);
    }

    /**
     * Obtiene método HTTP actual
     */
    public static function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Genera hash para verificación de integridad
     */
    public static function generateHash(string $data, string $algo = 'sha256'): string
    {
        return hash($algo, $data);
    }

    /**
     * Verifica hash sin timing attacks
     */
    public static function verifyHash(string $data, string $hash, string $algo = 'sha256'): bool
    {
        $computed = hash($algo, $data);
        return hash_equals($computed, $hash);
    }

    /**
     * Limpia variables de sesión después de uso
     */
    public static function clearSensitiveSessionData(array $keys): void
    {
        foreach ($keys as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }
        session_write_close();
    }

    /**
     * Obtiene fingerprint del navegador (no es 100% seguro pero ayuda)
     */
    public static function getClientFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Verifica que el fingerprint no haya cambiado
     */
    public static function verifyClientFingerprint(): bool
    {
        $current = self::getClientFingerprint();
        $stored = $_SESSION['client_fingerprint'] ?? null;

        if ($stored === null) {
            $_SESSION['client_fingerprint'] = $current;
            return true;
        }

        return hash_equals($current, $stored);
    }

    /**
     * Rate limit simple por key
     */
    public static function checkRateLimit(string $key, int $limit, int $windowSeconds): bool
    {
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $now = time();
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key] ?? [],
            fn($ts) => ($now - $ts) < $windowSeconds
        );

        if (count($_SESSION['rate_limit'][$key]) >= $limit) {
            return false;
        }

        $_SESSION['rate_limit'][$key][] = $now;
        return true;
    }

    /**
     * Obtiene tiempo de expiración de sesión
     */
    public static function getSessionExpiration(): int
    {
        return $_SESSION['ultimo_acceso'] ?? 0;
    }

    /**
     * Verifica si sesión expiró
     */
    public static function isSessionExpired(int $maxIdleSeconds = 900): bool
    {
        $lastAccess = $_SESSION['ultimo_acceso'] ?? 0;
        if ($lastAccess === 0) {
            return false;
        }

        return (time() - $lastAccess) > $maxIdleSeconds;
    }
}
