<?php
// toggle_turno_fecha.php
if (session_status() === PHP_SESSION_NONE) session_start();
require 'conexion.php';

// mostrar errores y forzar charset
error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conexion->set_charset('utf8mb4');

// autocrear tabla si no existe
$conexion->query("
  CREATE TABLE IF NOT EXISTS turnos_permitidos_fecha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    profesor_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    UNIQUE KEY ux_gym_prof_fecha_hora (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
  )
");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) { header("Location: login.php"); exit; }

$accion      = $_POST['__accion']   ?? '';
$fecha       = $_POST['fecha']      ?? '';
$profesor_id = (int)($_POST['profesor_id'] ?? 0);
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fin    = $_POST['hora_fin']    ?? '';
$habilitar   = (int)($_POST['habilitar'] ?? 0);
$dia         = $_POST['dia'] ?? '';

$redir = "turnos_profesor.php?fecha_bloqueo=" . urlencode($fecha);

try {
  // validar SIEMPRE la fecha
  $dt = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$dt || $dt->format('Y-m-d') !== $fecha) {
    header("Location: {$redir}&err=Fecha%20inv%C3%A1lida"); exit;
  }

  if ($accion === 'toggle_slot') {
    // Validación específica de toggle_slot
    if ($profesor_id<=0 || !$hora_inicio || !$hora_fin) {
      header("Location: {$redir}&err=Datos%20inv%C3%A1lidos"); exit;
    }

    if ($habilitar === 1) {
      // HABILITAR: agregar a la lista blanca (si no existe, se crea con esta franja)
      $stmt = $conexion->prepare("
        INSERT INTO turnos_permitidos_fecha
          (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio)
      ");
      $stmt->bind_param("iisss", $gimnasio_id, $profesor_id, $fecha, $hora_inicio, $hora_fin);
      $stmt->execute();
      $stmt->close();
      header("Location: {$redir}&ok=Franja%20habilitada"); exit;

    } else {
      // DESHABILITAR:
      // si NO hay lista blanca para esa fecha, primero la creamos con TODAS las franjas del día
      $hay = $conexion->prepare("
        SELECT 1 FROM turnos_permitidos_fecha
        WHERE gimnasio_id=? AND fecha=? LIMIT 1
      ");
      $hay->bind_param("is", $gimnasio_id, $fecha);
      $hay->execute();
      $existeLista = (bool)$hay->get_result()->fetch_row();
      $hay->close();

      if (!$existeLista) {
        // 1) averiguar el día de la semana en español a partir de la fecha
        $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
        $diaFecha = $map[date('l', strtotime($fecha))] ?? 'Lunes';

        // 2) traer todas las franjas base de ese día
        $q = $conexion->prepare("
          SELECT profesor_id, hora_inicio, hora_fin
          FROM turnos_disponibles
          WHERE gimnasio_id=? AND LOWER(TRIM(dia))=LOWER(?)
        ");
        $q->bind_param("is", $gimnasio_id, $diaFecha);
        $q->execute();
        $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
        $q->close();

        // 3) insertar todas esas franjas como permitidas (lista blanca)
        if ($rows) {
          $ins = $conexion->prepare("
            INSERT INTO turnos_permitidos_fecha
              (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio)
          ");
          foreach ($rows as $r) {
            $pidAll = (int)$r['profesor_id'];
            $ins->bind_param("iisss", $gimnasio_id, $pidAll, $fecha, $r['hora_inicio'], $r['hora_fin']);
            $ins->execute();
          }
          $ins->close();
        }
      }

      // 4) ahora sí, quitar la franja a deshabilitar de la lista blanca
      $stmt = $conexion->prepare("
        DELETE FROM turnos_permitidos_fecha
        WHERE gimnasio_id=? AND profesor_id=? AND fecha=? AND hora_inicio=? AND hora_fin=?
      ");
      $stmt->bind_param("iisss", $gimnasio_id, $profesor_id, $fecha, $hora_inicio, $hora_fin);
      $stmt->execute();
      $stmt->close();

      header("Location: {$redir}&ok=Franja%20deshabilitada"); exit;
    }
  }

  if ($accion === 'toggle_all') {
    // Validación específica de toggle_all
    if (!$dia) { header("Location: {$redir}&err=Falta%20d%C3%ADa"); exit; }

    // Traer TODOS los turnos_disponibles del día que te interesan
    $q = $conexion->prepare("
      SELECT profesor_id, hora_inicio, hora_fin
      FROM turnos_disponibles
      WHERE gimnasio_id = ? AND LOWER(TRIM(dia)) = LOWER(?)
    ");
    $q->bind_param("is", $gimnasio_id, $dia);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();

    if ($habilitar === 1 || (isset($_POST['modo']) && (int)$_POST['modo'] === 1)) {
      // Habilitar todas: llenar lista blanca con todas las franjas
      $ins = $conexion->prepare("
        INSERT INTO turnos_permitidos_fecha
          (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio)
      ");
      foreach ($rows as $r) {
        $pid = (int)$r['profesor_id'];
        $ins->bind_param("iisss", $gimnasio_id, $pid, $fecha, $r['hora_inicio'], $r['hora_fin']);
        $ins->execute();
      }
      $ins->close();
      header("Location: {$redir}&ok=Se%20habilitaron%20todas%20las%20franjas"); exit;

    } else {
      // Deshabilitar todas: vaciar la lista blanca para esa fecha
      $del = $conexion->prepare("
        DELETE FROM turnos_permitidos_fecha
        WHERE gimnasio_id=? AND fecha=?
      ");
      $del->bind_param("is", $gimnasio_id, $fecha);
      $del->execute();
      $del->close();
      header("Location: {$redir}&ok=Se%20deshabilitaron%20todas%20las%20franjas"); exit;
    }
  }

  // acción no reconocida
  header("Location: {$redir}&err=Acci%C3%B3n%20inv%C3%A1lida"); exit;

} catch (Throwable $e) {
  header("Location: {$redir}&err=" . urlencode($e->getMessage())); exit;
}
