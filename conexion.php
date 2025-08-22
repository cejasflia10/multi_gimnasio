<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1) Intentar tomar todo desde MYSQL_PUBLIC_URL (recomendado)
$publicUrl = getenv('MYSQL_PUBLIC_URL'); // p.ej: mysql://root:PASS@shuttle.proxy.rlwy.net:51676/railway

if ($publicUrl) {
    $u = parse_url($publicUrl);
    $host       = $u['host'] ?? 'shuttle.proxy.rlwy.net';
    $puerto     = isset($u['port']) ? (int)$u['port'] : 3306;
    $usuario    = $u['user'] ?? 'root';
    $contrasena = $u['pass'] ?? '';
    $basedatos  = isset($u['path']) ? ltrim($u['path'], '/') : 'railway';
} else {
    // 2) Fallback: variables de entorno individuales o valores por defecto
    $host       = getenv('MYSQLHOST')      ?: 'shuttle.proxy.rlwy.net';
    $puerto     = (int)(getenv('MYSQLPORT') ?: 51676);  // <- cambia si el puerto cambia
    $usuario    = getenv('MYSQLUSER')      ?: 'root';
    $contrasena = getenv('MYSQLPASSWORD')  ?: 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
    $basedatos  = getenv('MYSQLDATABASE')  ?: 'railway';
}

// 3) Conexión robusta con timeout
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $cx = mysqli_init();
    mysqli_options($cx, MYSQLI_OPT_CONNECT_TIMEOUT, 10); // evita “greeting packet” si tarda

    if (!mysqli_real_connect($cx, $host, $usuario, $contrasena, $basedatos, $puerto)) {
        throw new Exception('No se pudo conectar a MySQL.');
    }

    $cx->set_charset('utf8mb4');

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $cx->query("SET time_zone = '-03:00'");

    // Exponer como $conexion para el resto de la app
    $conexion = $cx;

    // echo "✅ Conectado a $host:$puerto / $basedatos";
} catch (Throwable $e) {
    http_response_code(500);
    die("❌ Error conectando a MySQL en {$host}:{$puerto}: " . $e->getMessage());
}
