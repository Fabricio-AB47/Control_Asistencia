<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "=== PRUEBA DE CONEXIÓN MYSQL ===\n\n";

// Información del sistema
echo "PHP Version: " . phpversion() . "\n";
echo "php.ini: " . php_ini_loaded_file() . "\n\n";

// Verificar extensiones
echo "=== EXTENSIONES ===\n";
echo "PDO: " . (extension_loaded('pdo') ? 'SI' : 'NO') . "\n";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'SI' : 'NO') . "\n";
echo "MySQLi: " . (extension_loaded('mysqli') ? 'SI' : 'NO') . "\n\n";

// Drivers PDO
if (extension_loaded('pdo')) {
    $drivers = PDO::getAvailableDrivers();
    echo "Drivers PDO: " . implode(', ', $drivers) . "\n\n";
} else {
    echo "PDO NO CARGADO\n\n";
}

// Intentar conexión
if (extension_loaded('pdo_mysql')) {
    try {
        $dsn = "mysql:host=localhost;port=3306;dbname=control_horario;charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', '');
        echo "=== CONEXIÓN EXITOSA ===\n";
        echo "Conectado a MySQL correctamente\n";
    } catch (PDOException $e) {
        echo "=== ERROR DE CONEXIÓN ===\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "=== NO SE PUEDE CONECTAR ===\n";
    echo "La extensión pdo_mysql NO está cargada\n";
}
