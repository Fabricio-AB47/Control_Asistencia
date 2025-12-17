<?php
// Prevent double bootstrap when index.php and public/index.php include this file
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

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

// ---- Seguridad HTTP global (cabeceras mejoradas) ----
use App\Security\SecurityHeaders;
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
SecurityHeaders::setHeaders($https);

// Helpers
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

if (!function_exists('app_redirect_login')) {
    /**
     * Redirige al login/index respetando APP_BASE_PATH.
     * Cuando la sesión expiró, marca un header para que el frontend pueda detectarlo.
     */
    function app_redirect_login(bool $expired = false): void {
        $base = function_exists('appBasePath') ? appBasePath() : '';
        $base = ($base === '/' || $base === '') ? '' : rtrim($base, '/');
        $target = $base . '/index.php';
        if ($expired && !headers_sent()) {
            header('X-Session-Expired: 1');
        }
        header('Location: ' . $target);
        exit();
    }
}

if (!function_exists('app_session_guard')) {
    function app_session_guard(int $maxIdleSeconds = 900): void {
        if (!isset($_SESSION['id_usuario'])) {
            app_redirect_login();
        }
        $now = time();
        if (isset($_SESSION['ultimo_acceso']) && ($now - (int)$_SESSION['ultimo_acceso']) > $maxIdleSeconds) {
            session_unset();
            session_destroy();
            app_redirect_login(true);
        }
        $_SESSION['ultimo_acceso'] = $now;
    }
}

// ---- Sesión endurecida y helper para arrancarla con parámetros seguros ----
if (!function_exists('app_boot_session')) {
    function app_boot_session(): void {
        $fileEnv = function_exists('loadDotEnv') ? loadDotEnv() : [];
        $forceSecure = (getEnvVar('FORCE_SECURE_COOKIES', $fileEnv, 'force_secure_cookies') ?? '0') === '1';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $secure = $https || $forceSecure;
        $sameSite = $secure ? 'Strict' : 'Lax';
        $params = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
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
        if (!isset($_SESSION)) return true;
        $now = time();
        $_SESSION['rate'] = $_SESSION['rate'] ?? [];
        $_SESSION['rate'][$key] = array_filter($_SESSION['rate'][$key] ?? [], function($ts) use($now,$windowSec){ return ($now - $ts) < $windowSec; });
        if (count($_SESSION['rate'][$key]) >= $limit) return false;
        $_SESSION['rate'][$key][] = $now;
        return true;
    }
}
