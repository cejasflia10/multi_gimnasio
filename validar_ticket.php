<?php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { echo json_encode(['ok'=>false,'msg'=>'Sin BD']); exit; }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}

/* Migraciones suaves para control de acceso */
if (!has_col($conexion,'tickets','used_at'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_at DATETIME NULL"); }
if (!has_col($conexion,'tickets','used_by'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_by INT NULL"); }
if (!has_col($conexion,'tickets','used_gate')) { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_gate VARCHAR(60) NULL"); }

$evento_id = (int)($_POST['evento_id']??0);
$code = trim((string)($_POST['code']??''));
if ($evento_id<=0 || $code===''){ echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit; }

/* Buscar ticket + estado del pedido */
$sql="SELECT t.id,t.code,t.evento_id,t.used_at,tt.nombre AS tipo,p.estado
      FROM tickets t
      JOIN pedidos p ON p.id=t.pedido_id
      LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
      WHERE t.code=? AND t.evento_id=? LIMIT 1";
$st=$conexion->prepare($sql);
if(!$st){ echo json_encode(['ok'=>false,'msg'=>'Error interno']); exit; }
$st->bind_param('si',$code,$evento_id);
$st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();

if(!$row){ echo json_encode(['ok'=>false,'msg'=>'Ticket inexistente para este evento']); exit; }
if(!in_array((string)$row['estado'],['aprobado','pagado'],true)){ echo json_encode(['ok'=>false,'msg'=>'Pedido no aprobado/pagado']); exit; }
if(!empty($row['used_at'])){ echo json_encode(['ok'=>false,'msg'=>'Ticket ya utilizado']); exit; }

/* Marcar uso */
$gate = isset($_SESSION['evento_usuario_id']) ? 'gate-'.(int)$_SESSION['evento_usuario_id'] : 'gate';
$now = date('Y-m-d H:i:s');
$st=$conexion->prepare("UPDATE tickets SET used_at=?, used_by=?, used_gate=? WHERE id=? LIMIT 1");
$uid = (int)($_SESSION['evento_usuario_id'] ?? 0);
$st->bind_param('sisi',$now,$uid,$gate,$row['id']);
$ok = $st->execute(); $st->close();
if(!$ok){ echo json_encode(['ok'=>false,'msg'=>'No se pudo marcar el uso']); exit; }

echo json_encode(['ok'=>true,'msg'=>'Ingreso habilitado','tipo'=>$row['tipo']??null]);
