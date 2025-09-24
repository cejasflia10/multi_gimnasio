<?php
/* ============================================================
   cancelar_reserva.php — cancela una reserva del cliente
   Requisitos:
     - conexion.php define $conexion (mysqli)
     - Sesión con $_SESSION['cliente_id']
     - Tabla reservas: id, cliente_id, estado, (opcional cancelado_en)
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* ---------- Helpers ---------- */
function back_to_panel(string $msg, bool $ok=false): void {
  $_SESSION['flash_error'] = $ok ? null : $msg;
  $_SESSION['flash_ok']    = $ok ? $msg : null;

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
  if ($base === '/' || $base === '\\') $base = '';

  header('Location: ' . $scheme . '://' . $host . $base . '/panel_cliente.php');
  exit;
}

/* ---------- Validaciones ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('❌ Método no permitido'); }

$cliente_id = (int)($_SESSION['cliente_id'] ?? 0);
$reserva_id = (int)($_POST['reserva_id']  ?? 0);

if ($cliente_id <= 0) back_to_panel('Debés iniciar sesión.');
if ($reserva_id <= 0) back_to_panel('Reserva no válida.');

/* ---------- Lógica ---------- */
$conexion->begin_transaction();
try {
  // Es tu reserva
  $stmt = $conexion->prepare("SELECT id, estado FROM reservas WHERE id = ? AND cliente_id = ? LIMIT 1");
  if (!$stmt) throw new Exception('Error al buscar reserva.');
  $stmt->bind_param('ii', $reserva_id, $cliente_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$res) throw new Exception('No se encontró la reserva o no te pertenece.');

  if ($res['estado'] === 'cancelada') {
    $conexion->commit();
    back_to_panel('La reserva ya estaba cancelada.', true);
  }

  // Soft delete por estado (si preferís borrar, usa DELETE)
  $sql = col_exists($conexion, 'reservas', 'cancelado_en')
       ? "UPDATE reservas SET estado = 'cancelada', cancelado_en = NOW() WHERE id = ? LIMIT 1"
       : "UPDATE reservas SET estado = 'cancelada' WHERE id = ? LIMIT 1";
  $stmt = $conexion->prepare($sql);
  if (!$stmt) throw new Exception('Error al preparar cancelación.');
  $stmt->bind_param('i', $reserva_id);
  if (!$stmt->execute()) throw new Exception('No se pudo cancelar la reserva.');
  $stmt->close();

  $conexion->commit();
  back_to_panel('Reserva cancelada ✅', true);

} catch (Throwable $e) {
  $conexion->rollback();
  back_to_panel('❌ ' . $e->getMessage());
}

/* ---------- Util ---------- */
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
}
