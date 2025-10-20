<?php
declare(strict_types=1);

// Inicia la sesión sólo si no está activa
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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
        // PHP 7.3+ — array de opciones soportado
        setcookie($sessionName, '', [
            'expires'  => time() - 42000,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    } else {
        // PHP < 7.3 — hack de SameSite en el path
        $pathWithSamesite = $path . '; samesite=' . $samesite;
        setcookie($sessionName, '', time() - 42000, $pathWithSamesite, $domain, $secure, $httponly);
    }
}

// Evita que el navegador muestre páginas cacheadas con botón Atrás
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Redirige al login (usa ruta ABSOLUTA para evitar problemas de '../..')
$REDIRECT_PATH = '../../../index.php';
// Si tu “router” es el index del root, usa:
// $REDIRECT_PATH = '/index.php?logout=1';

header('Location: ' . $REDIRECT_PATH);
exit;
