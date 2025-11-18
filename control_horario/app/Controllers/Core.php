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
    if ($val === null) $val = '/Control_Asistencia/control_horario';
    $val = trim($val);
    if ($val === '') $val = '/';
    if ($val[0] !== '/') $val = '/' . $val;
    if (strlen($val) > 1) $val = rtrim($val, '/');
    return $cached = $val;
}

if (!function_exists('h')) {
    function h($s): string {
        return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
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
