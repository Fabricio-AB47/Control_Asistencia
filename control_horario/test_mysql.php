<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO PHP/MySQL ===\n\n";

echo "Versión de PHP: " . PHP_VERSION . "\n";
echo "Archivo php.ini cargado: " . php_ini_loaded_file() . "\n\n";

echo "=== Extensiones PDO ===\n";
echo "PDO disponible: " . (extension_loaded('pdo') ? 'SÍ' : 'NO') . "\n";
echo "PDO MySQL disponible: " . (extension_loaded('pdo_mysql') ? 'SÍ' : 'NO') . "\n";
echo "MySQLi disponible: " . (extension_loaded('mysqli') ? 'SÍ' : 'NO') . "\n\n";

echo "=== Drivers PDO disponibles ===\n";
if (extension_loaded('pdo')) {
    $drivers = PDO::getAvailableDrivers();
    if (empty($drivers)) {
        echo "NINGUNO\n";
    } else {
        foreach ($drivers as $driver) {
            echo "  - $driver\n";
        }
    }
} else {
    echo "PDO no está cargado\n";
}

echo "\n=== Todas las extensiones cargadas ===\n";
$extensions = get_loaded_extensions();
sort($extensions);
foreach ($extensions as $ext) {
    if (stripos($ext, 'mysql') !== false || stripos($ext, 'pdo') !== false) {
        echo "  * $ext (MYSQL/PDO relacionado)\n";
    }
}
