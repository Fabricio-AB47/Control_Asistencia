<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Gestión centralizada de encabezados de seguridad HTTP
 * Implementa OWASP Top 10 y mejores prácticas de seguridad
 */
class SecurityHeaders
{
    private static ?string $nonce = null;

    public static function setHeaders(bool $isHttps = false): void
    {
        if (headers_sent()) {
            return;
        }

        // Previene ataques de tipo MIME
        header('X-Content-Type-Options: nosniff');

        // Controla el acceso a información del referente
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Previene clickjacking
        header('X-Frame-Options: DENY');

        // Controla características y APIs del navegador
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=(), fullscreen=(self), payment=()');

        // Previene caché no autorizado en páginas autenticadas
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Content Security Policy mejorada
        self::setCSP();

        // HSTS para conexiones HTTPS
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // X-UA-Compatible para IE
        header('X-UA-Compatible: IE=edge');

        // Feature Policy adicionales
        header('X-Permitted-Cross-Domain-Policies: none');
    }

    public static function setCSP(): void
    {
        $appBasePath = function_exists('appBasePath') ? appBasePath() : '/';
        $nonce = bin2hex(random_bytes(16));
        self::$nonce = $nonce;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['csp_nonce'] = $nonce;
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
            "style-src 'self' 'nonce-{$nonce}' 'unsafe-hashes' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "connect-src 'self' https://nominatim.openstreetmap.org",
            "frame-src https://www.google.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        $headerValue = implode('; ', $csp);
        header("Content-Security-Policy: {$headerValue}");
    }

    /**
     * Obtiene el nonce CSP para uso en atributos nonce de scripts/estilos
     */
    public static function getCSPNonce(): string
    {
        if (self::$nonce !== null) {
            return self::$nonce;
        }
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csp_nonce'])) {
            self::$nonce = $_SESSION['csp_nonce'];
            return self::$nonce;
        }
        return '';
    }
}
