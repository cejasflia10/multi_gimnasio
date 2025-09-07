<?php
// qr_png.php — genera el QR al vuelo para un ticket por su code, sin guardar en disco.
// Muestra el QR solo si el pedido está 'aprobado' o 'pagado'.
// Si no está habilitado, devuelve un PNG "NO HABILITADO".

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit; }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
if ($code===''){ http_response_code(400); exit('code requerido'); }

/* Traemos estado del pedido por el code del ticket */
$sql = "SELECT p.estado
        FROM tickets t
        JOIN pedidos p ON p.id = t.pedido_id
        WHERE t.code = ?
        LIMIT 1";
$st = $conexion->prepare($sql);
$st->bind_param('s',$code);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row){ http_response_code(404); exit('Ticket no encontrado'); }

$estado = strtolower((string)$row['estado']);
$habilitado = in_array($estado, ['aprobado','pagado'], true);

/* Función: PNG simple con texto (para "no habilitado" o errores) */
function png_text($txt, $w=420, $h=420){
  if (!function_exists('imagecreatetruecolor')) {
    // Si no existe GD, devolvemos 403 simple
    http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo $txt; exit;
  }
  $im = imagecreatetruecolor($w,$h);
  $bg = imagecolorallocate($im, 42, 20, 20);
  $bd = imagecolorallocate($im, 94, 38, 38);
  $tx = imagecolorallocate($im, 255, 180, 180);
  imagefilledrectangle($im,0,0,$w-1,$h-1,$bg);
  imagerectangle($im,0,0,$w-1,$h-1,$bd);
  $lines = explode("\n", wordwrap($txt, 22));
  $y = (int)($h/2 - count($lines)*8);
  foreach($lines as $ln){ imagestring($im, 5, 20, $y, $ln, $tx); $y += 22; }
  header('Content-Type: image/png');
  header('Cache-Control: no-store');
  imagepng($im); imagedestroy($im); exit;
}

if (!$habilitado){
  // Mostrar un PNG claro de "NO HABILITADO"
  png_text("QR NO HABILITADO\nEstado: ".$estado);
}

/* Generar QR al vuelo SIN guardar.
   Usamos un servicio público (sin tracking) y lo proxyeamos para que el <img> cargue siempre.
   Si tu hosting no permite fopen remota, hacemos un redirect.
*/
$payload = $code; // si querés: "https://TU_DOMINIO/mi_entrada.php?code=$code"
$size = '420x420';
$url  = 'https://api.qrserver.com/v1/create-qr-code/?size='.rawurlencode($size).'&data='.rawurlencode($payload);

$png = @file_get_contents($url);
if ($png !== false) {
  header('Content-Type: image/png');
  header('Cache-Control: public, max-age=300'); // 5 min
  echo $png; exit;
}

// Último recurso: redirigir
header('Cache-Control: no-store');
header('Location: '.$url);
exit;
