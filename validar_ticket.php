<?php
// validar_ticket.php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['evento_usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit;
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Sin BD']); exit;
}
@$conexion->set_charset('utf8mb4');

function j($ok,$msg,$extra=[]){ echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }

$evento_id = (int)($_POST['evento_id'] ?? 0);
$code      = trim((string)($_POST['code'] ?? ''));
$gate      = trim((string)($_POST['gate'] ?? 'Acceso principal'));
$uid       = (int)($_SESSION['evento_usuario_id'] ?? 0);

if ($evento_id<=0 || $code==='') j(false,'Datos inválidos');

$sql = "SELECT t.id, t.code, t.used_at, t.tipo_id,
               COALESCE(tt.nombre,'—') AS tipo_nombre,
               p.id AS pedido_id, p.estado
        FROM tickets t
        JOIN pedidos p ON p.id=t.pedido_id
        LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
        WHERE t.code=? AND p.evento_id=? LIMIT 1";
$st = $conexion->prepare($sql);
$st->bind_param('si',$code,$evento_id);
$st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();

if (!$row) j(false,'Código inválido o de otro evento');

$estado = strtolower((string)$row['estado']);
if (!in_array($estado,['aprobado','pagado'],true)) {
  j(false,'Pedido no habilitado (estado: '.$estado.')');
}
if (!empty($row['used_at'])) {
  j(false,'Ticket ya fue usado el '.$row['used_at']);
}

/* Marca de uso con condición para evitar carrera */
$st = $conexion->prepare("UPDATE tickets
                          SET used_at=NOW(), used_by=?, used_gate=?
                          WHERE id=? AND used_at IS NULL");
$st->bind_param('isi',$uid,$gate,$row['id']);
$st->execute();
$aff = $st->affected_rows;
$st->close();

if ($aff<=0) {
  j(false,'No se pudo marcar como usado (posible doble lectura)');
}

j(true,'Entrada válida',[
  'tipo' => $row['tipo_nombre'],
  'pedido_id' => (int)$row['pedido_id']
]);
