<?php
// ajax_alumnos_hoy.php
// Devuelve la lista de ingresos del día (ALUMNOS HOY) desde accesos_gimnasio

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
@date_default_timezone_set('America/Argentina/San_Luis');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
  http_response_code(403);
  exit('Acceso no permitido.');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// === Ingresos de HOY (misma lógica de accesos_gimnasio.php) ===
$sql = "
  SELECT a.id,
         a.fecha_ingreso,
         TIME_FORMAT(CONVERT_TZ(a.fecha_ingreso, @@session.time_zone, '-03:00'), '%H:%i') AS hora_local,
         c.nombre,
         c.apellido
    FROM accesos_gimnasio a
    JOIN clientes c ON c.id = a.cliente_id
   WHERE a.gimnasio_id = ?
     AND a.fecha_ingreso >= CURRENT_DATE()
     AND a.fecha_ingreso <  (CURRENT_DATE() + INTERVAL 1 DAY)
   ORDER BY a.fecha_ingreso ASC
";

$stmt = $conexion->prepare($sql);
if(!$stmt){
  http_response_code(500);
  exit('Error preparando consulta.');
}
$stmt->bind_param('i', $gimnasio_id);
$stmt->execute();
$rs = $stmt->get_result();

$rows = [];
while ($r = $rs->fetch_assoc()) {
  $rows[] = $r;
}
$stmt->close();

// === Salida HTML simple (el JS del index lo normaliza) ===
if (!$rows) {
  echo '<div>No hubo ingresos hoy.</div>';
  exit;
}

$total = count($rows);

// Primero una línea con el total: el JS busca "<n> ingresos"
echo '<div><strong>' . $total . ' ingresos hoy</strong></div>';

echo '<ul>';
foreach ($rows as $r) {
  $hora = $r['hora_local'] ?: date('H:i', strtotime((string)$r['fecha_ingreso']));
  $nombre = trim(($r['apellido'] ?? '') . ', ' . ($r['nombre'] ?? ''));
  echo '<li>' . h($nombre) . ' - ' . h($hora) . '</li>';
}
echo '</ul>';
