<?php
ob_start();
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) { header("Location: login.php"); exit(); }

include 'conexion.php';
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

include 'menu_horizontal.php';

$msg = '';
$err = '';

if (isset($_GET['ok']))  { $msg = strip_tags($_GET['ok']); }
if (isset($_GET['err'])) { $err = strip_tags($_GET['err']); }

/* ======================= Alta múltiple de turnos semanales ======================= */
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST['profesor_id'])
    && isset($_POST['__accion'])
    && $_POST['__accion']==='alta_turnos') {

    $profesor_id  = (int)($_POST["profesor_id"] ?? 0);
    $dias         = $_POST["dias"] ?? [];
    $hora_inicio  = trim($_POST["hora_inicio"] ?? '');
    $hora_fin     = trim($_POST["hora_fin"] ?? '');
    $cupo_maximo  = 10;

    if ($profesor_id <= 0)               { $err = "Seleccioná un profesor."; }
    elseif (empty($dias))                { $err = "Tildá al menos un día."; }
    elseif (!$hora_inicio || !$hora_fin) { $err = "Completá hora de inicio y fin."; }
    elseif ($hora_inicio >= $hora_fin)   { $err = "La hora de inicio debe ser menor que la de fin."; }

    if ($err === '') {
        $stmtExiste = $conexion->prepare("
            SELECT COUNT(*) AS c
            FROM turnos_profesor
            WHERE profesor_id = ?
              AND dia = ?
              AND (hora_inicio < ? AND hora_fin > ?)
        ");

        $stmtInsertTP = $conexion->prepare("
            INSERT INTO turnos_profesor (profesor_id, dia, hora_inicio, hora_fin)
            VALUES (?, ?, ?, ?)
        ");

        $stmtInsertTD = $conexion->prepare("
            INSERT INTO turnos_disponibles (profesor_id, dia, hora_inicio, hora_fin, gimnasio_id, cupo_maximo)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $conexion->begin_transaction();
        $insertados = 0; $saltados = [];

        foreach ($dias as $dia) {
            $dia = trim($dia);
            if ($dia === '') continue;

            $stmtExiste->bind_param("isss", $profesor_id, $dia, $hora_fin, $hora_inicio);
            $stmtExiste->execute();
            $res = $stmtExiste->get_result()->fetch_assoc();
            $haySolape = ((int)$res['c'] > 0);

            if ($haySolape) { $saltados[] = $dia; continue; }

            $stmtInsertTP->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
            $stmtInsertTP->execute();

            $stmtInsertTD->bind_param("isssii", $profesor_id, $dia, $hora_inicio, $hora_fin, $gimnasio_id, $cupo_maximo);
            $stmtInsertTD->execute();

            $insertados++;
        }

        if ($insertados > 0) {
            $conexion->commit();
            $msg = "Se guardaron $insertados turno(s).";
            if ($saltados) { $msg .= " (Saltados: ".implode(', ', $saltados).")"; }
        } else {
            $conexion->rollback();
            $err = $saltados ? "Todos se solapan: ".implode(', ', $saltados) : "No se pudo guardar.";
        }

        $stmtExiste->close();
        $stmtInsertTP->close();
        $stmtInsertTD->close();
    }
}

/* ======================= Eliminar turno semanal ======================= */
if (isset($_GET['eliminar'])) {
    $id_turno = (int)$_GET['eliminar'];

    $stmtDatos = $conexion->prepare("
        SELECT t.profesor_id, t.dia, t.hora_inicio, t.hora_fin
        FROM turnos_profesor t
        JOIN profesores p ON p.id = t.profesor_id
        WHERE t.id = ? AND p.gimnasio_id = ?
    ");
    $stmtDatos->bind_param("ii", $id_turno, $gimnasio_id);
    $stmtDatos->execute();
    $fila = $stmtDatos->get_result()->fetch_assoc();
    $stmtDatos->close();

    if ($fila) {
        $profesor_id_turno = (int)$fila['profesor_id'];
        $dia_turno         = $fila['dia'];
        $hora_inicio_turno = $fila['hora_inicio'];
        $hora_fin_turno    = $fila['hora_fin'];

        $conexion->begin_transaction();

        $stmtDelTP = $conexion->prepare("DELETE FROM turnos_profesor WHERE id = ?");
        $stmtDelTP->bind_param("i", $id_turno);
        $stmtDelTP->execute();
        $stmtDelTP->close();

        $stmtDelTD = $conexion->prepare("
            DELETE FROM turnos_disponibles
            WHERE profesor_id = ? AND dia = ? AND hora_inicio = ? AND hora_fin = ? AND gimnasio_id = ?
        ");
        $stmtDelTD->bind_param("isssi", $profesor_id_turno, $dia_turno, $hora_inicio_turno, $hora_fin_turno, $gimnasio_id);
        $stmtDelTD->execute();
        $stmtDelTD->close();

        $conexion->commit();

        header("Location: turnos_profesor.php"); exit();
    } else {
        $err = "Turno no encontrado.";
    }
}

/* ======================= Toggle/Habilitar franjas por FECHA ======================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__accion']) && $_POST['__accion']==='toggle_slot') {
    $fecha = $_POST['fecha'] ?? '';
    $profesor_id = (int)($_POST['profesor_id'] ?? 0);
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin    = $_POST['hora_fin'] ?? '';
    $habilitar   = (int)($_POST['habilitar'] ?? 0);

    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha || $profesor_id<=0 || !$hora_inicio || !$hora_fin) {
        header("Location: turnos_profesor.php?err=Datos%20inv%C3%A1lidos"); exit;
    }

    if ($habilitar === 1) {
        $stmt = $conexion->prepare("
          INSERT INTO turnos_permitidos_fecha (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
          VALUES (?,?,?,?,?)
          ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio)
        ");
        $stmt->bind_param("iisss", $gimnasio_id, $profesor_id, $fecha, $hora_inicio, $hora_fin);
        $stmt->execute();
        $stmt->close();
        header("Location: turnos_profesor.php?fecha_bloqueo={$fecha}&ok=Franja%20habilitada"); exit;
    } else {
        $stmt = $conexion->prepare("
          DELETE FROM turnos_permitidos_fecha
          WHERE gimnasio_id=? AND profesor_id=? AND fecha=? AND hora_inicio=? AND hora_fin=?
        ");
        $stmt->bind_param("iisss", $gimnasio_id, $profesor_id, $fecha, $hora_inicio, $hora_fin);
        $stmt->execute();
        $stmt->close();
        header("Location: turnos_profesor.php?fecha_bloqueo={$fecha}&ok=Franja%20deshabilitada"); exit;
    }
}

/* ======================= Bulk habilitar/deshabilitar todo el día ======================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__accion']) && $_POST['__accion']==='toggle_all') {
    $fecha = $_POST['fecha'] ?? '';
    $modo  = (int)($_POST['modo'] ?? 0); // 1 = habilitar todas; 0 = deshabilitar todas
    $dia   = $_POST['dia'] ?? '';

    $dt = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha || !$dia) {
        header("Location: turnos_profesor.php?err=Datos%20inv%C3%A1lidos"); exit;
    }

    // Traer TODOS los turnos_disponibles del día:
    $q = $conexion->prepare("
      SELECT td.profesor_id, td.hora_inicio, td.hora_fin
      FROM turnos_disponibles td
      WHERE td.gimnasio_id = ? AND LOWER(TRIM(td.dia)) = LOWER(?)
    ");
    $q->bind_param("is", $gimnasio_id, $dia);
    $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();

    if ($modo === 1) {
        $ins = $conexion->prepare("
          INSERT INTO turnos_permitidos_fecha (gimnasio_id, profesor_id, fecha, hora_inicio, hora_fin)
          VALUES (?,?,?,?,?)
          ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio)
        ");
        foreach ($rows as $r) {
            $pid = (int)$r['profesor_id'];
            $ins->bind_param("iisss", $gimnasio_id, $pid, $fecha, $r['hora_inicio'], $r['hora_fin']);
            $ins->execute();
        }
        $ins->close();
        header("Location: turnos_profesor.php?fecha_bloqueo={$fecha}&ok=Se%20habilitaron%20todas%20las%20franjas"); exit;
    } else {
        $del = $conexion->prepare("
          DELETE FROM turnos_permitidos_fecha
          WHERE gimnasio_id=? AND fecha=?
        ");
        $del->bind_param("is", $gimnasio_id, $fecha);
        $del->execute();
        $del->close();
        header("Location: turnos_profesor.php?fecha_bloqueo={$fecha}&ok=Se%20deshabilitaron%20todas%20las%20franjas"); exit;
    }
}

/* ======================= Listados base ======================= */
$result = $conexion->query("
    SELECT id, apellido, nombre
    FROM profesores
    WHERE gimnasio_id = {$gimnasio_id}
    ORDER BY apellido, nombre
");

$turnos = $conexion->query("
    SELECT t.*, p.apellido, p.nombre
    FROM turnos_profesor t
    JOIN profesores p ON t.profesor_id = p.id
    WHERE p.gimnasio_id = {$gimnasio_id}
    ORDER BY p.apellido, p.nombre,
             FIELD(t.dia,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'),
             t.hora_inicio
");

/* ======================= Vista por fecha con botones ======================= */
function nombreDiaEs($fechaYmd) {
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}

$fecha_bloqueo = $_GET['fecha_bloqueo'] ?? date('Y-m-d');
$dtb = DateTime::createFromFormat('Y-m-d', $fecha_bloqueo);
if (!$dtb || $dtb->format('Y-m-d') !== $fecha_bloqueo) { $fecha_bloqueo = date('Y-m-d'); }
$dia_bloqueo = nombreDiaEs($fecha_bloqueo);

// Base del día desde turnos_disponibles (lo que el cliente vería en un día normal)
$stmtBase = $conexion->prepare("
  SELECT td.id, td.profesor_id, td.hora_inicio, td.hora_fin,
         p.apellido, p.nombre
  FROM turnos_disponibles td
  JOIN profesores p ON p.id = td.profesor_id
  WHERE td.gimnasio_id = ? AND LOWER(TRIM(td.dia)) = LOWER(?)
  ORDER BY p.apellido, p.nombre, td.hora_inicio
");
$stmtBase->bind_param("is", $gimnasio_id, $dia_bloqueo);
$stmtBase->execute();
$base_del_dia = $stmtBase->get_result();
$stmtBase->close();

// Permitidos (lista blanca) para la fecha
$stmtP = $conexion->prepare("
  SELECT profesor_id, hora_inicio, hora_fin
  FROM turnos_permitidos_fecha
  WHERE gimnasio_id = ? AND fecha = ?
");
$stmtP->bind_param("is", $gimnasio_id, $fecha_bloqueo);
$stmtP->execute();
$resP = $stmtP->get_result();
$permitidos = [];
while ($p = $resP->fetch_assoc()) {
  $permitidos[(int)$p['profesor_id']][$p['hora_inicio'].'_'.$p['hora_fin']] = true;
}
$stmtP->close();

$modo_lista_blanca = !empty($permitidos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Turnos de Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .alert-ok{background:#e6ffed;border:1px solid #a7f3d0;padding:.5rem 1rem;border-radius:.5rem;margin:.5rem 0;}
    .alert-err{background:#ffe6e6;border:1px solid #f5a0a0;padding:.5rem 1rem;border-radius:.5rem;margin:.5rem 0;}
    .dias {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.3rem;margin:.5rem 0;}
    .dias label{display:flex;align-items:center;gap:.4rem;padding:.35rem .5rem;background:#1f2937;color:#fff;border-radius:.4rem;}
    form .fila{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin:.5rem 0;}
    button[type=submit]{background:#fbbf24;border:0;padding:.6rem 1rem;border-radius:.5rem;font-weight:600;cursor:pointer;}
    table {width:100%;border-collapse:collapse;}
    th, td {padding:.5rem;border-bottom:1px solid #444;}
    .boton{background:#374151;color:#fff;padding:.35rem .6rem;border-radius:.375rem;text-decoration:none;}
    .seccion {margin-top:1.25rem;padding-top:1rem;border-top:1px dashed #555;}
    .badge{padding:.2rem .45rem;border-radius:.35rem;color:#fff;display:inline-block}
    .ok{background:#10b981}.err{background:#ef4444}
    .btn-mini{background:#1f2937;color:#fff;padding:.25rem .5rem;border-radius:.35rem;border:0;cursor:pointer}
  </style>
</head>
<body>
<div class="contenedor">
  <h1>🕓 Turnos de Profesores</h1>

  <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Alta rápida semanal -->
  <form method="POST">
    <input type="hidden" name="__accion" value="alta_turnos">
    <div class="fila">
      <select name="profesor_id" required>
        <option value="">Seleccionar Profesor</option>
        <?php
        $result = $conexion->query("
          SELECT id, apellido, nombre
          FROM profesores
          WHERE gimnasio_id = {$gimnasio_id}
          ORDER BY apellido, nombre
        ");
        while ($row = $result->fetch_assoc()): ?>
          <option value="<?= (int)$row['id'] ?>">
            <?= htmlspecialchars($row['apellido'].' '.$row['nombre']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="fila">
      <div class="dias">
        <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $d): ?>
          <label><input type="checkbox" name="dias[]" value="<?= $d ?>"> <?= $d ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="fila">
      <label>Inicio: <input type="time" name="hora_inicio" required></label>
      <label>Fin: <input type="time" name="hora_fin" required></label>
    </div>

    <button type="submit">Agregar Turnos</button>
  </form>

  <!-- ======= Panel por FECHA con botones ======= -->
  <div class="seccion">
    <h2>Feriado por fecha (habilitar/deshabilitar franjas)</h2>
    <form method="get" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap;margin-bottom:.75rem">
      <div>
        <label>Fecha:</label>
        <input type="date" name="fecha_bloqueo" value="<?= htmlspecialchars($fecha_bloqueo) ?>" onchange="this.form.submit()">
      </div>
      <div>
        <label>Día detectado:</label>
        <input type="text" value="<?= htmlspecialchars($dia_bloqueo) ?>" readonly>
      </div>
    </form>

      <form method="post" action="toggle_turno_fecha.php" style="margin:.5rem 0">
      <input type="hidden" name="__accion" value="toggle_all">
      <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha_bloqueo) ?>">
      <input type="hidden" name="dia" value="<?= htmlspecialchars($dia_bloqueo) ?>">
      <button class="btn-mini" name="modo" value="1">✅ Habilitar todas las franjas del día</button>
      <button class="btn-mini" name="modo" value="0">⛔ Deshabilitar todas las franjas del día</button>
    </form>

    <?php if ($modo_lista_blanca): ?>
      <p style="background:#fff7e6;border:1px solid #ffd591;padding:.5rem .75rem;border-radius:.5rem;">
        🟠 Modo lista blanca activo para <?= htmlspecialchars($fecha_bloqueo) ?>: solo se habilitan las franjas marcadas aquí.
      </p>
    <?php else: ?>
      <p style="background:#eef2ff;border:1px solid #c7d2fe;padding:.5rem .75rem;border-radius:.5rem;">
        ℹ️ Aún no marcaste franjas para <?= htmlspecialchars($fecha_bloqueo) ?>. Por defecto, todas aparecen habilitadas hasta que elijas.
      </p>
    <?php endif; ?>

    <table>
      <tr>
        <th>Profesor</th>
        <th>Base: Inicio</th>
        <th>Base: Fin</th>
        <th>Estado</th>
      </tr>
      <?php
      $stmtBase2 = $conexion->prepare("
        SELECT td.id, td.profesor_id, td.hora_inicio, td.hora_fin,
               p.apellido, p.nombre
        FROM turnos_disponibles td
        JOIN profesores p ON p.id = td.profesor_id
        WHERE td.gimnasio_id = ? AND LOWER(TRIM(td.dia)) = LOWER(?)
        ORDER BY p.apellido, p.nombre, td.hora_inicio
      ");
      $stmtBase2->bind_param("is", $gimnasio_id, $dia_bloqueo);
      $stmtBase2->execute();
      $rs2 = $stmtBase2->get_result();

      while ($b = $rs2->fetch_assoc()):
        $pid = (int)$b['profesor_id'];
        $clave = $b['hora_inicio'].'_'.$b['hora_fin'];
        $estaPermitida = $modo_lista_blanca ? isset($permitidos[$pid][$clave]) : true;
        $estado = $estaPermitida ? 'Habilitado' : 'BLOQUEADO';
        $cl = $estaPermitida ? 'ok' : 'err';
      ?>
        <tr>
          <td><?= htmlspecialchars($b['apellido'].' '.$b['nombre']) ?></td>
          <td><?= htmlspecialchars(substr($b['hora_inicio'],0,5)) ?></td>
          <td><?= htmlspecialchars(substr($b['hora_fin'],0,5)) ?></td>
          <td>
            <span class="badge <?= $cl ?>" style="margin-right:.5rem"><?= htmlspecialchars($estado) ?></span>

            <?php if ($estaPermitida): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="__accion" value="toggle_slot">
                <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha_bloqueo) ?>">
                <input type="hidden" name="profesor_id" value="<?= (int)$pid ?>">
                <input type="hidden" name="hora_inicio" value="<?= htmlspecialchars($b['hora_inicio']) ?>">
                <input type="hidden" name="hora_fin" value="<?= htmlspecialchars($b['hora_fin']) ?>">
                <input type="hidden" name="habilitar" value="0">
                <button type="submit" class="btn-mini">Deshabilitar</button>
              </form>
            <?php else: ?>
                <form method="post" action="toggle_turno_fecha.php" style="display:inline">
                <input type="hidden" name="__accion" value="toggle_slot">
                <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha_bloqueo) ?>">
                <input type="hidden" name="profesor_id" value="<?= (int)$pid ?>">
                <input type="hidden" name="hora_inicio" value="<?= htmlspecialchars($b['hora_inicio']) ?>">
                <input type="hidden" name="hora_fin" value="<?= htmlspecialchars($b['hora_fin']) ?>">
                <input type="hidden" name="habilitar" value="1">
                <button type="submit" class="btn-mini">Habilitar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile;
      $stmtBase2->close(); ?>
    </table>
  </div>

  <h2 class="seccion">Turnos Registrados</h2>
  <table>
    <tr>
      <th>Profesor</th>
      <th>Día</th>
      <th>Hora Inicio</th>
      <th>Hora Fin</th>
      <th>Acciones</th>
    </tr>
    <?php while ($t = $turnos->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($t['apellido'] . ' ' . $t['nombre']) ?></td>
        <td><?= htmlspecialchars($t['dia']) ?></td>
        <td><?= htmlspecialchars($t['hora_inicio']) ?></td>
        <td><?= htmlspecialchars($t['hora_fin']) ?></td>
        <td>
          <a class="boton" href="editar_turno_profesor.php?id=<?= (int)$t['id'] ?>">✏️ Editar</a>
          <a class="boton" href="turnos_profesor.php?eliminar=<?= (int)$t['id'] ?>" onclick="return confirm('¿Eliminar este turno?')">🗑️ Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>
<?php ob_end_flush(); ?>
