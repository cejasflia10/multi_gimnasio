<?php
// conexion.php
// ------------------------------------------------------------
// Conexión a BD (Railway) + utilidades básicas
// ------------------------------------------------------------

// (Opcional) Evitar "headers already sent" si haces redirecciones luego
if (!headers_sent()) {
  if (function_exists('ob_start') && !defined('APP_OB_STARTED')) {
    ob_start();
    define('APP_OB_STARTED', true);
  }
}

// Mostrar errores en desarrollo (podés apagar en producción)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ------------------------------------------------------------
// Dominio/base pública del sitio (con barra final)
// Usado para construir URLs absolutas (WhatsApp, comprobantes, etc.)
// ------------------------------------------------------------
if (!defined('APP_BASE_URL')) {
  define('APP_BASE_URL', 'https://multi-gimnasio-51bq.onrender.com/');
}

// (Opcional) Zona horaria coherente
if (function_exists('date_default_timezone_set')) {
  date_default_timezone_set('America/Argentina/Buenos_Aires');
}

// ------------------------------------------------------------
// Credenciales BD (pueden venir por variables de entorno o usar defaults)
// ------------------------------------------------------------
$DB_HOST = getenv('DB_HOST') ?: 'shuttle.proxy.rlwy.net';
$DB_PORT = (int)(getenv('DB_PORT') ?: 51676);
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$DB_NAME = getenv('DB_NAME') ?: 'railway';

// Evitar warnings de mysqli en producción
if (function_exists('mysqli_report')) {
  mysqli_report(MYSQLI_REPORT_OFF);
}

// ------------------------------------------------------------
// Crear conexión sólo si no existe
// ------------------------------------------------------------
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  $conexion = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

  if ($conexion->connect_error) {
    http_response_code(500);
    exit('❌ Sin conexión a la base de datos.');
  }

  // Charset Unicode completo
  if (method_exists($conexion, 'set_charset')) {
    @$conexion->set_charset('utf8mb4');
  }

  // Opcional: endurecer SQL_MODE si querés más seguridad/consistencia
  // @$conexion->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
}

// ------------------------------------------------------------
// (Opcional) Helper para construir URLs absolutas
// ------------------------------------------------------------
if (!function_exists('app_url')) {
  /**
   * Retorna URL absoluta a partir de un path relativo.
   * Ej: app_url('ver_ventas_evento.php?evento_id=7')
   */
  function app_url(string $path = ''): string {
    $base = rtrim(APP_BASE_URL, '/') . '/';
    $p = ltrim($path, '/');
    return $base . $p;
  }
}
