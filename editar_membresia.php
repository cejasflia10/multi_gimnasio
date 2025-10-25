<?php
// guardar_edicion_membresia.php — Edita clases y vencimiento de una membresía
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hcol(mysqli $db, string $table, string $col): bool {
  $table = $db->real_escape_string($table);
  $col   = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
  return ($res && $res->num_rows > 0);
}
function valid_date($s): bool {
  if (!$s) return false;
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return $d && $d->format('Y-m-d') === $s;
}

/* Método */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido'); }

/* Entrada */
$id          = (int)($_POST['id'] ?? 0);
$gimnasio_id = (int)($_POST['gimnasio_id'] ?? ($_SESSION['gimnasio_id'] ?? 0));
$nuevas_clases = (int)($_POST['clases_disponibles'] ?? 0); // el form manda este name
$nuevo_vto     = (string)($_POST['fecha_vencimiento'] ?? '');

if ($id <= 0 || $gimnasio_id <= 0) { http_response_code(400); exit('Datos inválidos'); }
if ($nuevas_clases < 0) $nuevas_clases = 0;
if (!valid_date($nuevo_vto)) { http_response_code(400); exit('Fecha de vencimiento inválida'); }

try {
  $conexion->begin_transaction();

  // Traer membresía (lock)
  $stmt = $conexion->prepare("
    SELECT id, cliente_id, plan_id, fecha_inicio, fecha_vencimiento,
           clases_disponibles, IFNULL(clases_restantes, NULL) AS clases_restantes
    FROM membresias
    WHERE id = ? AND gimnasio_id = ?
    FOR UPDATE
  ");
  if (!$stmt) throw new Exception("Prepare select: ".$conexion->error);
  $stmt->bind_param("ii", $id, $gimnasio_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $mem = $res->fetch_assoc();
  $stmt->close();

  if (!$mem) { throw new Exception("Membresía no encontrada"); }

  $old_vto    = (string)$mem['fecha_vencimiento'];
  $old_cd     = is_null($mem['clases_disponibles']) ? null : (int)$mem['clases_disponibles'];
  $old_cr     = is_null($mem['clases_restantes'])   ? null : (int)$mem['clases_restantes'];

  // Validación: no permitir vencimiento anterior a inicio
  if (valid_date($mem['fecha_inicio']) && $nuevo_vto < $mem['fecha_inicio']) {
    throw new Exception("La fecha de vencimiento no puede ser anterior a la fecha de inicio");
  }

  // Detectar columnas presentes
  $has_cd = hcol($conexion, 'membresias', 'clases_disponibles');
  $has_cr = hcol($conexion, 'membresias', 'clases_restantes');

  // Armar SET dinámico
  $sets = [];
  $types = '';
  $vals  = [];

  // Siempre se actualiza el vencimiento
  $sets[] = "fecha_vencimiento = ?";
  $types .= 's';
  $vals[]  = $nuevo_vto;

  // Si existen ambas, las dejamos consistentes con el valor ingresado
  if ($has_cd) { $sets[] = "clases_disponibles = ?"; $types .= 'i'; $vals[] = $nuevas_clases; }
  if ($has_cr) { $sets[] = "clases_restantes   = ?"; $types .= 'i'; $vals[] = $nuevas_clases; }

  if (!$sets) { throw new Exception("No hay columnas editables en la tabla membresias"); }

  $sql = "UPDATE membresias SET ".implode(', ', $sets)." WHERE id = ? AND gimnasio_id = ?";
  $stmtU = $conexion->prepare($sql);
  if (!$stmtU) throw new Exception("Prepare update: ".$conexion->error);

  $types .= 'ii';
  $vals[] = $id;
  $vals[] = $gimnasio_id;

  // bind dinámico
  $refs = []; $refs[] = &$types;
  foreach ($vals as $k => $v) { $refs[] = &$vals[$k]; }
  if (!call_user_func_array([$stmtU, 'bind_param'], $refs)) {
    throw new Exception("bind_param update falló");
  }
  if (!$stmtU->execute()) { throw new Exception("Exec update: ".$stmtU->error); }
  $stmtU->close();

  // Auditar cambios si existe historial
  if (hcol($conexion, 'membresias_historial', 'membresia_id')) {
    $stmtH = $conexion->prepare("
      INSERT INTO membresias_historial
        (membresia_id, gimnasio_id, fecha, cambio, old_vencimiento, new_vencimiento, old_clases, new_clases, user_id)
      VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)
    ");
    if ($stmtH) {
      $cambio = 'edicion';
      $old_cls = ($has_cr ? $old_cr : $old_cd);
      $user_id = (int)($_SESSION['usuario_id'] ?? 0);
      $stmtH->bind_param(
        "iisssiii",
        $id, $gimnasio_id, $cambio,
        $old_vto, $nuevo_vto,
        $old_cls, $nuevas_clases, $user_id
      );
      $stmtH->execute();
      $stmtH->close();
    }
  }

  // Si tenés turnos fijos y querés extender el rango automáticamente cuando se extiende el vto:
  // (opcional, solo si existen columnas/tabla)
  if ($nuevo_vto > $old_vto) {
    foreach (['clientes_fijos','turnos_personalizados'] as $tf) {
      $q = $conexion->query("SHOW TABLES LIKE '".$conexion->real_escape_string($tf)."'");
      if ($q && $q->num_rows > 0) {
        $has_mem = hcol($conexion, $tf, 'membresia_id');
        $has_cli = hcol($conexion, $tf, 'cliente_id');
        $has_gim = hcol($conexion, $tf, 'gimnasio_id');
        $has_hst = hcol($conexion, $tf, 'hasta');
        if ($has_hst && ($has_mem || $has_cli)) {
          $cond = [];
          if ($has_gim) $cond[] = "gimnasio_id = ".(int)$gimnasio_id;
          if ($has_mem) $cond[] = "membresia_id = ".(int)$id;
          else          $cond[] = "cliente_id = ".(int)$mem['cliente_id'];
          $where = implode(' AND ', $cond);
          $conexion->query("UPDATE `$tf` SET `hasta` = '".$conexion->real_escape_string($nuevo_vto)."' WHERE $where");
        }
      }
    }
  }

  $conexion->commit();
  header("Location: ver_membresias.php?edit_ok=1", true, 303);
  exit;

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  echo "Error al editar la membresía: ".h($e->getMessage());
}
