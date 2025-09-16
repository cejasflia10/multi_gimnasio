<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  return ($q && $q->num_rows > 0);
}
function hcol(mysqli $db, string $table, string $col): bool {
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
  return ($res && $res->num_rows > 0);
}
function prep(mysqli $db, string $sql, string $label): mysqli_stmt {
  $stmt = $db->prepare($sql);
  if (!$stmt) { throw new Exception("[$label] prepare() falló: ".$db->error." | SQL: ".$sql); }
  return $stmt;
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* Entrada */
$session_gym = (int)($_SESSION['gimnasio_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID no proporcionado.'); }

try {
  $conexion->begin_transaction();

  // 0) Traer la membresía por ID (sin filtrar por gimnasio todavía)
  $stSel = prep($conexion, "SELECT id, cliente_id, gimnasio_id FROM membresias WHERE id = ? LIMIT 1", "membresias.select_by_id");
  $stSel->bind_param("i", $id);
  $stSel->execute();
  $res = $stSel->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stSel->close();

  if (!$row) {
    $conexion->rollback();
    header("Location: ver_membresias.php?mensaje=no_encontrada", true, 303);
    exit;
  }

  $m_cliente_id = (int)$row['cliente_id'];
  $m_gym_id     = (int)$row['gimnasio_id'];

  // 0.b) Validar gimnasio: si la sesión tiene gimnasio y NO coincide -> error con detalle
  if ($session_gym > 0 && $session_gym !== $m_gym_id) {
    $conexion->rollback();
    http_response_code(403);
    exit("No se puede eliminar: gimnasio de sesión ({$session_gym}) ≠ gimnasio de la membresía ({$m_gym_id}).");
  }

  // 1) Borrar adicionales relacionados (tabla correcta: membresias_adicionales)
  if (table_exists($conexion, 'membresias_adicionales')) {
    $stDelAd = prep($conexion, "DELETE FROM membresias_adicionales WHERE membresia_id = ?", "membresias_adicionales.delete_by_membresia");
    $stDelAd->bind_param("i", $id);
    $stDelAd->execute();
    $stDelAd->close();
  }

  // 2) Borrar movimientos de cuenta corriente vinculados por venta_id = membresia_id
  if (table_exists($conexion, 'cc_movimientos')) {
    // si existe columna gimnasio_id (normalmente sí), filtramos por gimnasio también
    if (hcol($conexion, 'cc_movimientos', 'gimnasio_id')) {
      $stDelCC = prep($conexion, "DELETE FROM cc_movimientos WHERE venta_id = ? AND gimnasio_id = ?", "cc_movimientos.delete_by_membresia");
      $stDelCC->bind_param("ii", $id, $m_gym_id);
    } else {
      $stDelCC = prep($conexion, "DELETE FROM cc_movimientos WHERE venta_id = ?", "cc_movimientos.delete_by_membresia");
      $stDelCC->bind_param("i", $id);
    }
    $stDelCC->execute();
    $stDelCC->close();
  }

  // 3) (Opcional) Borrar pagos relacionados si tu esquema los vincula. Dos estrategias:
  // 3.a) Por concepto "Renovación de membresía #ID"
  if (table_exists($conexion, 'pagos') && hcol($conexion, 'pagos', 'concepto')) {
    $sql = "DELETE FROM pagos WHERE concepto = ?";
    // Si existe gimnasio_id en pagos, filtramos también
    if (hcol($conexion, 'pagos', 'gimnasio_id')) $sql .= " AND gimnasio_id = ?";
    $stDelPag = prep($conexion, $sql, "pagos.delete_by_concepto");
    if (strpos($sql, 'gimnasio_id') !== false) {
      $concepto = "Renovación de membresía #{$id}";
      $stDelPag->bind_param("si", $concepto, $m_gym_id);
    } else {
      $concepto = "Renovación de membresía #{$id}";
      $stDelPag->bind_param("s", $concepto);
    }
    $stDelPag->execute();
    $stDelPag->close();
  }
  // 3.b) Por metadata_json que contenga "membresia_id":ID (si existe metadata_json)
  if (table_exists($conexion, 'pagos') && hcol($conexion, 'pagos', 'metadata_json')) {
    $like = '%"membresia_id":'.$id.'%';
    $sql2 = "DELETE FROM pagos WHERE metadata_json LIKE ?";
    if (hcol($conexion, 'pagos', 'gimnasio_id')) $sql2 .= " AND gimnasio_id = ?";
    $stDelMeta = prep($conexion, $sql2, "pagos.delete_by_metadata");
    if (strpos($sql2, 'gimnasio_id') !== false) {
      $stDelMeta->bind_param("si", $like, $m_gym_id);
    } else {
      $stDelMeta->bind_param("s", $like);
    }
    $stDelMeta->execute();
    $stDelMeta->close();
  }
  // (Si usás pagos_membresia, repetí la lógica de arriba cambiando el nombre de la tabla.)

  // 4) Borrar la membresía (ya validamos gimnasio; podemos borrar por id directo)
  $stDel = prep($conexion, "DELETE FROM membresias WHERE id = ?", "membresias.delete_one");
  $stDel->bind_param("i", $id);
  $ok = $stDel->execute();
  $affected = $conexion->affected_rows;
  $err = $conexion->error;
  $stDel->close();

  if (!$ok || $affected < 1) {
    // Revalidar si la fila sigue existiendo
    $stChk = prep($conexion, "SELECT id FROM membresias WHERE id = ? LIMIT 1", "membresias.exists_after_delete");
    $stChk->bind_param("i", $id);
    $stChk->execute();
    $still = $stChk->get_result();
    $exists = ($still && $still->num_rows > 0);
    $stChk->close();

    $conexion->rollback();
    if ($exists) {
      // Puede ser una restricción de FK que no pudimos cubrir; devolvemos detalle técnico
      http_response_code(409);
      exit("No se pudo eliminar la membresía #{$id}. Posible restricción de llave foránea o registros asociados restantes. Detalle MySQL: ".h($err));
    } else {
      // La fila ya no existe (condición de carrera)
      header("Location: ver_membresias.php?mensaje=eliminada", true, 303);
      exit;
    }
  }

  $conexion->commit();
  header("Location: ver_membresias.php?mensaje=eliminada", true, 303);
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al eliminar la membresía: " . h($e->getMessage());
}
