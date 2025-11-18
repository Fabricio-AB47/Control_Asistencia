<?php
// Minimal bootstrap and autoloader for MVC-style organization
// Keeps compatibility with existing codebase. No external dependencies.

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Cargar helpers de Core (funciones globales appBasePath, conexion, etc.)
require_once __DIR__ . '/Controllers/Core.php';

// ---- Seguridad HTTP global (cabeceras de protección básicas) ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Evita cacheo en páginas autenticadas de manera general
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    // CSP: intentar evitar 'unsafe-inline' para scripts; mantener temporalmente style inline
    // Recomendado: migrar scripts inline a archivos .js y usar nonces/hashes para CSP.
    $csp = "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://nominatim.openstreetmap.org; frame-src https://www.google.com; frame-ancestors 'none'";
    header("Content-Security-Policy: $csp");

    // Añadir HSTS si la conexión es HTTPS
    $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if ($https) {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    }
}

// Lightweight helpers
if (!function_exists('app_json_input')) {
    function app_json_input(): array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $_POST ?: [];
        }
        $data = json_decode($raw, true);
        return (is_array($data) && !empty($data)) ? $data : ($_POST ?: []);
    }
}

if (!function_exists('app_csrf_valid')) {
    function app_csrf_valid(): bool {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $postToken = $_POST['csrf_token'] ?? ($_POST['csrf'] ?? '');
        $token  = is_string($header) && $header !== '' ? $header : (is_string($postToken) ? $postToken : '');
        return isset($_SESSION['token']) && is_string($token) && hash_equals($_SESSION['token'], $token);
    }
}

if (!function_exists('app_ensure_csrf_token')) {
    function app_ensure_csrf_token(): void {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
    }
}

if (!function_exists('app_session_guard')) {
    function app_session_guard(int $maxIdleSeconds = 900): void {
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: ' . (function_exists('appBasePath') ? appBasePath() : '') . '/index.php');
            exit();
        }
        $now = time();
        if (isset($_SESSION['ultimo_acceso']) && ($now - (int)$_SESSION['ultimo_acceso']) > $maxIdleSeconds) {
            session_unset();
            session_destroy();
            header('Location: ' . (function_exists('appBasePath') ? appBasePath() : '') . '/index.php');
            exit();
        }
        $_SESSION['ultimo_acceso'] = $now;
    }
}

// ---- Sesión endurecida y helper para arrancarla con parámetros seguros ----
if (!function_exists('app_boot_session')) {
    function app_boot_session(): void {
        // Parámetros de cookie de sesión
        $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
        $forceSecure = (getEnvVar('FORCE_SECURE_COOKIES', $fileEnv, 'force_secure_cookies') ?? '0') === '1';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $secure = $https || $forceSecure;
        $params = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (PHP_VERSION_ID >= 70300) session_set_cookie_params($params);
            ini_set('session.use_strict_mode', '1');
            session_start();
        }
    }
}

// ---- Rate limiting simple por sesión ----
if (!function_exists('app_rate_limit')) {
    function app_rate_limit(string $key, int $limit, int $windowSec): bool {
        if (!isset($_SESSION)) return true; // si no hay sesión, permitir (o arrancarla antes)
        $now = time();
        $_SESSION['rate'] = $_SESSION['rate'] ?? [];
        $_SESSION['rate'][$key] = array_filter($_SESSION['rate'][$key] ?? [], function($ts) use($now,$windowSec){ return ($now - $ts) < $windowSec; });
        if (count($_SESSION['rate'][$key]) >= $limit) return false;
        $_SESSION['rate'][$key][] = $now;
        return true;
    }
}
