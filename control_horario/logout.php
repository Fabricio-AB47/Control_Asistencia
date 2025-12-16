<?php
declare(strict_types=1);
require_once __DIR__.'/app/init.php';

// Inicia la sesión solo si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Permitir cierre solo por POST + CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    exit;
}
if (!function_exists('app_csrf_valid') || !app_csrf_valid()) {
    http_response_code(403);
    echo 'CSRF inválido';
    exit;
}

// Guarda los params actuales de la cookie de sesión ANTES de destruir
$params      = session_get_cookie_params();
$sessionName = session_name();

// Limpia variables de sesión y destruye
$_SESSION = [];
session_unset();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Borra la cookie de sesión usando los mismos parámetros (compatibilidad PHP)
if (ini_get('session.use_cookies')) {
    $path     = $params['path']     ?? '/';
    $domain   = $params['domain']   ?? '';
    $secure   = (bool)($params['secure']   ?? false);
    $httponly = (bool)($params['httponly'] ?? true);
    $samesite = $params['samesite'] ?? 'Lax'; // Lax por defecto

    if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
        setcookie($sessionName, '', [
            'expires'  => time() - 42000,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    } else {
        $pathWithSamesite = $path . '; samesite=' . $samesite;
        setcookie($sessionName, '', time() - 42000, $pathWithSamesite, $domain, $secure, $httponly);
    }
}

// Evita que el navegador muestre páginas cacheadas con botón Atrás
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Redirige al login (ruta basada en .env)
$REDIRECT_PATH = appBasePath() . '/index.php';
header('Location: ' . $REDIRECT_PATH);
exit;

