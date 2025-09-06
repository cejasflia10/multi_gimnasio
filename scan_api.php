<?php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['evento_usuario_id'])) {
  http_response_code(403);
  echo json_encode(['status'=>'error','message'=>'No autorizado']); exit;
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Sin BD']); exit; }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function jerr($m,$code=400){ http_response_code($code); echo json_encode(['status'=>'error','message'=>$m]); exit; }

$raw = file_get_contents('php://input');
$js  = json_decode($raw,true);
$code = isset($js['code']) ? trim((string)$js['code']) : '';
$evento_id = isset($js['evento_id']) ? (int)$js['evento_id'] : 0;
$gate = isset($js['gate']) ? trim((string)$js['gate']) : null;

if ($code==='') jerr('Falta code');
if ($evento_id<=0) jerr('Falta evento_id');

/* Migraciones suaves: asegurar columnas de uso en tickets */
if (!has_col($conexion,'tickets','used_at')) { @ $conexion->query("ALTER TABLE tickets ADD COLUMN used_at DATETIME NULL AFTER qr_path"); }
if (!has_col($conexion,'tickets','used_by')) { @ $conexion->query("ALTER TABLE tickets ADD COLUMN used_by INT NULL AFTER used_at"); }
if (!has_col($conexion,'tickets','used_gate')) { @ $conexion->query("ALTER TABLE tickets ADD COLUMN used_gate VARCHAR(60) NULL AFTER used_by"); }
if (!has_col($conexion,'tickets','code')) { jerr('Estructura tickets inválida (falta code)'); }

$conexion->begin_transaction();
try{
  // Traer ticket con FOR UPDATE, chequeando evento y pedido
  $sql = "SELECT t.id, t.code, t.used_at, t.tipo_id, t.evento_id,
                 p.estado AS pedido_estado, p.comprador_nombre,
                 tt.nombre AS tipo_nombre
          FROM tickets t
          LEFT JOIN pedidos p ON p.id = t.pedido_id
          LEFT JOIN tickets_tipos tt ON tt.id = t.tipo_id
          WHERE t.code = ? AND t.evento_id = ?
          FOR UPDATE";
  $st = $conexion->prepare($sql);
  if (!$st) throw new Exception('Error interno (prep).');
  $st->bind_param('si',$code,$evento_id);
  $st->execute();
  $tk = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$tk) { throw new Exception('Ticket inexistente para este evento.'); }

  // Pedido aprobado/pagado?
  $estado = (string)($tk['pedido_estado'] ?? '');
  if (!in_array($estado,['aprobado','pagado'],true)) {
    echo json_encode(['status'=>'invalid','message'=>'Pedido no aprobado aún']); $conexion->rollback(); exit;
  }

  // Ya usado?
  if (!empty($tk['used_at'])) {
    echo json_encode([
      'status'=>'used',
      'used_at'=>$tk['used_at'],
      'tipo'=>$tk['tipo_nombre'] ?? '',
      'comprador'=>$tk['comprador_nombre'] ?? ''
    ]);
    $conexion->commit(); exit;
  }

  // Marcar como usado ahora
  $now = date('Y-m-d H:i:s');
  $used_by = (int)($_SESSION['evento_usuario_id'] ?? 0);
  $sqlU = "UPDATE tickets SET used_at=?, used_by=?, used_gate=? WHERE code=? AND evento_id=? AND used_at IS NULL";
  $u = $conexion->prepare($sqlU);
  if (!$u) throw new Exception('Error interno (prep upd).');
  $u->bind_param('sissi',$now,$used_by,$gate,$code,$evento_id);
  $u->execute();
  $aff = $u->affected_rows; $u->close();

  if ($aff <= 0) {
    // otra terminal lo marcó justo antes
    echo json_encode(['status'=>'used','used_at'=>$now, 'tipo'=>$tk['tipo_nombre']??'', 'comprador'=>$tk['comprador_nombre']??'']);
    $conexion->commit(); exit;
  }

  $conexion->commit();
  echo json_encode([
    'status'=>'ok',
    'tipo'=>$tk['tipo_nombre'] ?? '',
    'comprador'=>$tk['comprador_nombre'] ?? '',
    'used_at'=>$now
  ]);

}catch(Exception $e){
  $conexion->rollback();
  jerr($e->getMessage(), 200);
}
