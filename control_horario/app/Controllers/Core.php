<?php
declare(strict_types=1);

/**
 * Helpers de configuración y conexión (migrados desde core/conexion.php)
 * Nota: lee el archivo .env en la raíz del proyecto.
 */

function app_project_root(): string {
    // app/Controllers => raíz es dos niveles arriba
    return dirname(__DIR__, 2);
}

function loadDotEnv(string $path = null): array {
    $path = $path ?? (app_project_root() . '/.env');
    if (!is_file($path)) return [];
    $ini = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($ini) ? $ini : [];
}

function getEnvVar(string $key, ?array $fallbackEnv = null, ?string $alt = null): ?string {
    $v = $_ENV[$key] ?? getenv($key);
    if ($v !== false && $v !== null) return $v;
    if ($alt) {
        $v = $_ENV[$alt] ?? getenv($alt);
        if ($v !== false && $v !== null) return $v;
    }
    if ($fallbackEnv) {
        if (array_key_exists($key, $fallbackEnv)) return $fallbackEnv[$key];
        if ($alt && array_key_exists($alt, $fallbackEnv)) return $fallbackEnv[$alt];
    }
    return null;
}

function appBasePath(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $fileEnv = loadDotEnv();
    $candidates = [
        getEnvVar('APP_BASE_PATH', $fileEnv, 'app_base_path'),
        getEnvVar('BASE_PATH', $fileEnv, 'base_path'),
    ];
    $val = null;
    foreach ($candidates as $c) { if (is_string($c) && $c !== '') { $val = $c; break; } }
    // AutodetecciA3n cuando .env estA-c vacA-o o mal configurado.
    if ($val === null || trim((string)$val) === '') {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($script));
        $dir = ($dir === '/' || $dir === '\\' || $dir === '.') ? '' : $dir;
        // Si se ejecuta desde /public/index.php normaliza quitando "/public"
        if ($dir !== '' && preg_match('#/public$#', $dir)) {
            $dir = rtrim(preg_replace('#/public$#', '', $dir), '/');
        }
        $val = ($dir === '') ? '/' : $dir;
    }
    $val = trim($val);
    if ($val === '') $val = '/';
    // Normalize double slashes and trailing slash
    $val = preg_replace('#/+#', '/', $val);
    if ($val[0] !== '/') $val = '/' . $val;
    if (strlen($val) > 1) $val = rtrim($val, '/');
    return $cached = $val;
}

/**
 * Base ya normalizada para assets/rutas estA-ticas.
 */
function appAssetBase(): string {
    $base = appBasePath();
    return ($base === '/' || $base === '') ? '' : rtrim($base, '/');
}

/**
 * Base para rutas del front controller (detecta si se estA-a sirviendo /public).
 */
function appRouterBase(): string {
    $asset = appAssetBase();
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $inPublicByPath = (strpos($script, '/public/') !== false) || preg_match('#/public/index\\.php$#', $script);
    $docRootIsPublic = preg_match('#/public/?$#', $docRoot) === 1;

    // Si el sitio ya se sirve con /public como docroot, no agregamos /public.
    if ($inPublicByPath || $docRootIsPublic) {
        return $asset;
    }
    return $asset . '/public';
}

if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Función segura para escapar atributos HTML5
 */
if (!function_exists('attr')) {
    function attr($s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Función segura para escapar JSON
 */
if (!function_exists('json_safe')) {
    function json_safe($data): string {
        return htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
    }
}

function conexion(): \PDO {
    $fileEnv = loadDotEnv();
    $host = getEnvVar('DB_HOST', $fileEnv, 'db_host');
    $db   = getEnvVar('DB_NAME', $fileEnv, 'db_name');
    $user = getEnvVar('DB_USER', $fileEnv, 'db_user');
    $pass = getEnvVar('DB_PASSWORD', $fileEnv, 'db_password');
    $port = getEnvVar('DB_PORT', $fileEnv, 'db_port') ?? '3306';
    $debug = (getEnvVar('APP_DEBUG', $fileEnv, 'app_debug') ?? '0') === '1';
    if (!$host || !$db || !$user) {
        $msg = 'Faltan variables de conexión (DB_HOST/DB_NAME/DB_USER) en .env de la raíz.';
        if ($debug) {
            $msg .= ' Valores actuales => DB_HOST=' . var_export($host,true) . ', DB_NAME=' . var_export($db,true) . ', DB_USER=' . var_export($user,true);
        }
        throw new \RuntimeException($msg);
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    try {
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (\PDOException $e) {
        // Registra detalle en logs y muestra mensaje claro (detallado si APP_DEBUG=1)
        error_log('DB connection error: ' . $e->getMessage() . ' DSN=' . $dsn . ' USER=' . $user);
        if ($debug) {
            throw new \RuntimeException('Error al conectar a la base de datos: ' . $e->getMessage());
        }
        throw new \RuntimeException('Error al conectar a la base de datos. Verifica credenciales en .env');
    }
}

/**
 * Obtiene el cliente IP de forma segura (OWASP A01:2021)
 */
function getClientIP(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: 'unknown';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return filter_var($_SERVER['REMOTE_ADDR'] ?? 'unknown', FILTER_VALIDATE_IP) ?: 'unknown';
}

/**
 * Valida que una solicitud sea del dominio (OWASP A01:2021 - CORS)
 */
function validateOrigin(): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (empty($origin)) {
        return true; // Navegadores no CORS omiten origen en algunas solicitudes
    }
    $allowedOrigins = [
        preg_replace('|^https?://|', '', $_SERVER['HTTP_HOST'] ?? ''),
    ];
    foreach ($allowedOrigins as $allowed) {
        if (preg_match('/' . preg_quote($allowed, '/') . '$/', $origin)) {
            return true;
        }
    }
    return false;
}

/**
 * Valida que la solicitud sea JSON válido
 */
function validateJsonRequest(): bool {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    return strpos($contentType, 'application/json') !== false;
}
