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
