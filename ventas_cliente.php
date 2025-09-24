<?php
/* ventas_cliente.php — Movimientos del cliente (reservas, pedidos, pagos) */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_cliente.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function must_prepare(mysqli $db, string $sql){ $st=$db->prepare($sql); if(!$st) die('❌ SQL error: '.$db->error.'<br><code>'.$sql.'</code>'); return $st; }
function table_exists(mysqli $db, string $name): bool {
  $st = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $st->bind_param('s',$name); $st->execute(); $st->bind_result($x); $ok = (bool)$st->fetch(); $st->close(); return $ok;
}

/* ====== Filtros UI ====== */
$hoy     = date('Y-m-d');
$desde   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
$hasta   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : $hoy;
$estadoF = $_GET['estado'] ?? ''; // reservado, cancelado, entregado, pendiente, etc.
$tipoF   = $_GET['tipo'] ?? '';   // reserva|pedido

/* ====== Contenedores (lista unificada) ====== */
$movs = []; // cada mov: ['fecha','tipo','detalle','estado','monto','metodo','comprobante_url','id','origen']

/* ===================== RESERVAS (turnos) ===================== */
/* Tablas/tipos esperados:
   - reservas_clientes (id, cliente_id, gimnasio_id, turno_id, fecha?, estado?, pago_monto?, pago_tipo?, comprobante_url?)
   - turnos_disponibles (id, hora_inicio, hora_fin, profesor_id, dia)
   - profesores (id, nombre, apellido)
*/
if (table_exists($conexion, 'reservas_clientes')) {
  $sql = "
    SELECT 
      rc.id,
      COALESCE(rc.fecha, rc.fecha_clase, CURDATE()) AS fecha,
      COALESCE(rc.estado,'reservado') AS estado,
      COALESCE(rc.pago_monto, 0)     AS monto,
      COALESCE(rc.pago_tipo,  '')    AS metodo,
      COALESCE(rc.comprobante_url,'') AS comp_url,
      td.hora_inicio, td.hora_fin,
      CONCAT(p.apellido,' ',p.nombre) AS profesor
    FROM reservas_clientes rc
    LEFT JOIN turnos_disponibles td ON td.id = rc.turno_id
    LEFT JOIN profesores p          ON p.id  = td.profesor_id
    WHERE rc.cliente_id = ? AND rc.gimnasio_id = ?
      AND COALESCE(rc.fecha, rc.fecha_clase, CURDATE()) BETWEEN ? AND ?
    ORDER BY fecha DESC, rc.id DESC
  ";
  $st = must_prepare($conexion, $sql);
  $st->bind_param('iiss', $cliente_id, $gimnasio_id, $desde, $hasta);
  $st->execute();
  $rs = $st->get_result();
  while($r=$rs->fetch_assoc()){
    if ($tipoF && $tipoF!=='reserva') continue;
    if ($estadoF && strcasecmp($estadoF,(string)$r['estado'])!==0) continue;
    $detalle = 'Clase ';
    if ($r['hora_inicio'] || $r['hora_fin']) $detalle .= h(substr((string)$r['hora_inicio'],0,5)).'–'.h(substr((string)$r['hora_fin'],0,5)).' ';
    if (!empty($r['profesor'])) $detalle .= 'con '.h($r['profesor']);
    $movs[] = [
      'fecha' => (string)$r['fecha'],
      'tipo'  => 'reserva',
      'detalle' => $detalle,
      'estado'  => (string)$r['estado'],
      'monto'   => (float)$r['monto'],
      'metodo'  => (string)$r['metodo'],
      'comprobante_url' => (string)$r['comp_url'],
      'id' => (int)$r['id'],
      'origen' => 'reservas_clientes'
    ];
  }
  $st->close();
}

/* ===================== PEDIDOS INDUMENTARIA ===================== */
/* Escenario típico:
   - ind_pedidos (id, cliente_id, gimnasio_id, total, sena_monto, pago_tipo, estado, comprobante_url, creado_en)
   Si tu nombre es otro, cambia acá o duplicá el bloque.
*/
if (table_exists($conexion, 'ind_pedidos')) {
  $sql = "
    SELECT 
      id, 
      COALESCE(creado_en, NOW()) AS fecha,
      COALESCE(estado,'pendiente') AS estado,
      COALESCE(total,0) AS total,
      COALESCE(sena_monto,0) AS sena_monto,
      COALESCE(pago_tipo,'') AS pago_tipo,
      COALESCE(comprob
