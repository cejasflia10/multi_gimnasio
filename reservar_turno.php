<?php
/* ============================================================
   reservar_turno.php — crea una reserva para el cliente logueado
   Requisitos:
     - conexion.php define $conexion (mysqli)
     - Sesión con $_SESSION['cliente_id']
     - Tabla turnos (opcional col. capacidad)
     - Tabla reservas: id (AI), turno_id, cliente_id, estado, creado_en
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
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $r && $r->num_rows > 0;
}

/* ---------- Validaciones ---------- */
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('❌ Método no permitido'); }

$cliente_id = (int)($_SESSION['cliente_id'] ?? 0);
$turno_id   = (int)($_POST['turno_id']      ?? 0);

if ($cliente_id <= 0) back_to_panel('Debés iniciar sesión.');
if ($turno_id   <= 0) back_to_panel('Turno no válido.');

/* ---------- Lógica ---------- */
$conexion->begin_transaction();
try {
  // Turno existe
  $stmt = $conexion->prepare("SELECT * FROM turnos WHERE id = ? LIMIT 1");
  if (!$stmt) throw new Exception('Error al preparar consulta de turno.');
  $stmt->bind_param('i', $turno_id);
  $stmt->execute();
  $turno = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$turno) throw new Exception('El turno no existe.');

  // Evitar doble reserva misma persona
  $stmt = $conexion->prepare("SELECT id FROM reservas WHERE turno_id = ? AND cliente_id = ? AND estado = 'activa' LIMIT 1");
  if (!$stmt) throw new Exception('Error al verificar duplicado.');
  $stmt->bind_param('ii', $turno_id, $cliente_id);
  $stmt->execute();
  $dup = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($dup) throw new Exception('Ya tenés una reserva activa para este turno.');

  // Cupo
  if (col_exists($conexion, 'turnos', 'capacidad')) {
    $stmt = $conexion->prepare("SELECT COUNT(*) c FROM reservas WHERE turno_id = ? AND estado = 'activa'");
    if (!$stmt) throw new Exception('Error al contar reservas.');
    $stmt->bind_param('i', $turno_id);
    $stmt->execute();
    $ocupadas = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    $capacidad = (int)($turno['capacidad'] ?? 0);
    if ($capacidad > 0 && $ocupadas >= $capacidad) {
      throw new Exception('No hay lugares disponibles en este turno.');
    }
  } else {
    // 1 a 1
    $stmt = $conexion->prepare("SELECT id FROM reservas WHERE turno_id = ? AND estado = 'activa' LIMIT 1");
    if (!$stmt) throw new Exception('Error al verificar disponibilidad.');
    $stmt->bind_param('i', $turno_id);
    $stmt->execute();
    $ya = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ya) throw new Exception('Este turno ya fue reservado.');
  }

  // Insert
  $stmt = $conexion->prepare("INSERT INTO reservas (turno_id, cliente_id, estado, creado_en) VALUES (?, ?, 'activa', NOW())");
  if (!$stmt) throw new Exception('Error al preparar alta.');
  $stmt->bind_param('ii', $turno_id, $cliente_id);
  if (!$stmt->execute()) throw new Exception('No se pudo crear la reserva.');
  $stmt->close();

  $conexion->commit();
  back_to_panel('Reserva creada ✅', true);

} catch (Throwable $e) {
  $conexion->rollback();
  back_to_panel('❌ ' . $e->getMessage());
}
