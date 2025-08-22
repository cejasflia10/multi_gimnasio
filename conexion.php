<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Credenciales ---
// Puedes dejarlas fijas o leer de variables de entorno (si existen).
$host       = getenv('MYSQLHOST')      ?: 'shuttle.proxy.rlwy.net';
$puerto     = getenv('MYSQLPORT')      ?: 51676;
$usuario    = getenv('MYSQLUSER')      ?: 'root';
$contrasena = getenv('MYSQLPASSWORD')  ?: 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$basedatos  = getenv('MYSQLDATABASE')  ?: 'railway';

// Reportar errores como excepciones
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Conexión
    $conexion = new mysqli($host, $usuario, $contrasena, $basedatos, (int)$puerto);

    // Charset recomendado
    $conexion->set_charset('utf8mb4');

    // Zona horaria para PHP y para MySQL
    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $conexion->query("SET time_zone = '-03:00'");

    // Si quieres verificar conexión:
    // echo "✅ Conectado a MySQL";
} catch (Throwable $e) {
    http_response_code(500);
    die('❌ Error de conexión: ' . $e->getMessage());
}
