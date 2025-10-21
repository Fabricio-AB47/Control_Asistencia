<?php
declare(strict_types=1);

/**
 * Carga variables desde $_ENV/getenv y, si no existen, intenta leer un archivo .env plano.
 * Acepta claves en mayúsculas (DB_HOST) o minúsculas (db_host).
 */

function getEnvVar(string $key, ?array $fallbackEnv = null, ?string $alt = null): ?string {
    // Prioridad: $_ENV / getenv
    $v = $_ENV[$key] ?? getenv($key);
    if ($v !== false && $v !== null) return $v;

    // Alternativa con clave secundaria (ej: buscar db_host si no hay DB_HOST)
    if ($alt) {
        $v = $_ENV[$alt] ?? getenv($alt);
        if ($v !== false && $v !== null) return $v;
    }

    // Fallback desde arreglo cargado del archivo .env
    if ($fallbackEnv) {
        if (array_key_exists($key, $fallbackEnv)) return $fallbackEnv[$key];
        if ($alt && array_key_exists($alt, $fallbackEnv)) return $fallbackEnv[$alt];
    }
    return null;
}

/**
 * Intenta parsear un archivo .env simple "CLAVE=valor".
 * No falla si no existe; devuelve [].
 */
function loadDotEnv(string $path = __DIR__.'/.env'): array {
    if (!is_file($path)) return [];
    // Lee como INI plano sin interpretar tipos
    $ini = parse_ini_file($path, false, INI_SCANNER_RAW);
    return is_array($ini) ? $ini : [];
}

/**
 * Devuelve el path base público de la app (p.ej. "/Control_Asistencia/control_horario").
 * Lee APP_BASE_PATH/app_base_path o BASE_PATH/base_path del entorno o core/.env
 */
function appBasePath(): string {
    static $cached = null;
    if ($cached !== null) return $cached;
    $fileEnv = loadDotEnv(__DIR__.'/.env');

    // Intenta múltiples claves
    $candidates = [
        getEnvVar('APP_BASE_PATH', $fileEnv, 'app_base_path'),
        getEnvVar('BASE_PATH', $fileEnv, 'base_path'),
    ];
    $val = null;
    foreach ($candidates as $c) {
        if (is_string($c) && $c !== '') { $val = $c; break; }
    }
    if ($val === null) {
        $val = '/Control_Asistencia/control_horario';
    }
    $val = trim($val);
    if ($val === '') $val = '/';
    if ($val[0] !== '/') $val = '/' . $val; // siempre empieza con '/'
    if (strlen($val) > 1) $val = rtrim($val, '/'); // sin '/' final salvo raíz
    return $cached = $val;
}

/**
 * Conexión PDO a **MySQL** (acorde a tu DDL).
 * Variables esperadas: DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PORT (opcional, por defecto 3306)
 */
function conexion(): PDO {
    // Cargar .env local (opcional)
    $fileEnv = loadDotEnv(__DIR__.'/.env');

    // Soportar claves en mayúsculas y minúsculas
    $host = getEnvVar('DB_HOST', $fileEnv, 'db_host');
    $db   = getEnvVar('DB_NAME', $fileEnv, 'db_name');
    $user = getEnvVar('DB_USER', $fileEnv, 'db_user');
    $pass = getEnvVar('DB_PASSWORD', $fileEnv, 'db_password');
    $port = getEnvVar('DB_PORT', $fileEnv, 'db_port');

    if (!$host || !$db || !$user) {
        // No exponer credenciales ni paths exactos en producción
        throw new RuntimeException('Faltan variables de conexión (DB_HOST/DB_NAME/DB_USER).');
    }

    // DSN para MySQL (charset utf8mb4 para emojis/acentos)
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // usar prepares nativos
            // PDO::ATTR_PERSISTENT      => true, // (opcional) conexiones persistentes
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // En producción, registra el error y muestra un mensaje genérico
        // error_log('DB connection error: '.$e->getMessage());
        throw new RuntimeException('Error al conectar a la base de datos.');
    }
}
