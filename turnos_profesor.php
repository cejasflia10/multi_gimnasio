<?php
ob_start();
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    header("Location: login.php");
    exit();
}

include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

include 'menu_horizontal.php';

$msg = '';
$err = '';

// ======================= INSERTAR TURNOS (MÚLTIPLES DÍAS) =======================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profesor_id'])) {
    $profesor_id  = (int)($_POST["profesor_id"] ?? 0);
    $dias         = $_POST["dias"] ?? [];                 // <- array de días tildados
    $hora_inicio  = trim($_POST["hora_inicio"] ?? '');
    $hora_fin     = trim($_POST["hora_fin"] ?? '');
    $cupo_maximo  = 10;

    // Validaciones básicas
    if ($profesor_id <= 0)               { $err = "Seleccioná un profesor."; }
    elseif (empty($dias))                { $err = "Tildá al menos un día."; }
    elseif (!$hora_inicio || !$hora_fin) { $err = "Completá hora de inicio y fin."; }
    elseif ($hora_inicio >= $hora_fin)   { $err = "La hora de inicio debe ser menor que la de fin."; }

    if ($err === '') {
        // Preparados
        $stmtExiste = $conexion->prepare("
            SELECT COUNT(*) AS c
            FROM turnos_profesor
            WHERE profesor_id = ?
              AND dia = ?
              AND (hora_inicio < ? AND hora_fin > ?)  -- solapamiento
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

        $insertados = 0;
        $saltados   = []; // días con solapamiento

        foreach ($dias as $dia) {
            $dia = trim($dia);
            if ($dia === '') continue;

            // ¿ya existe algo que se solape?
            $stmtExiste->bind_param("isss", $profesor_id, $dia, $hora_fin, $hora_inicio);
            $stmtExiste->execute();
            $res = $stmtExiste->get_result();
            $row = $res->fetch_assoc();
            $haySolape = (int)$row['c'] > 0;

            if ($haySolape) {
                $saltados[] = $dia;
                continue;
            }

            // Insertar en ambas tablas
            $stmtInsertTP->bind_param("isss", $profesor_id, $dia, $hora_inicio, $hora_fin);
            $stmtInsertTP->execute();

            $stmtInsertTD->bind_param("isssii", $profesor_id, $dia, $hora_inicio, $hora_fin, $gimnasio_id, $cupo_maximo);
            $stmtInsertTD->execute();

            $insertados++;
        }

        if ($insertados > 0) {
            $conexion->commit();
            $msg = "Se guardaron $insertados turno(s).";
            if (!empty($saltados)) {
                $msg .= " (Saltados por solapamiento: " . implode(', ', $saltados) . ")";
            }
        } else {
            $conexion->rollback();
            if (!empty($saltados)) {
                $err = "No se guardó nada. Todos los días tildados se solapan: " . implode(', ', $saltados);
            } else {
                $err = "No se pudo guardar. Verificá los datos.";
            }
        }

        $stmtExiste->close();
        $stmtInsertTP->close();
        $stmtInsertTD->close();
    }
}

// ======================= ELIMINAR TURNO =======================
if (isset($_GET['eliminar'])) {
    $id_turno = (int)$_GET['eliminar'];

    // Obtener datos del turno ANTES de borrar
    $stmtDatos = $conexion->prepare("
        SELECT t.profesor_id, t.dia, t.hora_inicio, t.hora_fin
        FROM turnos_profesor t
        JOIN profesores p ON p.id = t.profesor_id
        WHERE t.id = ? AND p.gimnasio_id = ?
    ");
    $stmtDatos->bind_param("ii", $id_turno, $gimnasio_id);
    $stmtDatos->execute();
    $res = $stmtDatos->get_result();

    if ($res && $fila = $res->fetch_assoc()) {
        $profesor_id_turno = (int)$fila['profesor_id'];
        $dia_turno         = $fila['dia'];
        $hora_inicio_turno = $fila['hora_inicio'];
        $hora_fin_turno    = $fila['hora_fin'];

        $conexion->begin_transaction();

        // Borrar de turnos_profesor
        $stmtDelTP = $conexion->prepare("DELETE FROM turnos_profesor WHERE id = ?");
        $stmtDelTP->bind_param("i", $id_turno);
        $stmtDelTP->execute();

        // Borrar de turnos_disponibles
        $stmtDelTD = $conexion->prepare("
            DELETE FROM turnos_disponibles
            WHERE profesor_id = ? AND dia = ? AND hora_inicio = ? AND hora_fin = ? AND gimnasio_id = ?
        ");
        $stmtDelTD->bind_param("isssi", $profesor_id_turno, $dia_turno, $hora_inicio_turno, $hora_fin_turno, $gimnasio_id);
        $stmtDelTD->execute();

        $conexion->commit();

        header("Location: turnos_profesor.php");
        exit();
    } else {
        $err = "Turno no encontrado.";
    }
}

// ======================= LISTADOS =======================
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
  </style>
</head>
<body>
<div class="contenedor">
  <h1>🕓 Turnos de Profesores</h1>

  <?php if ($msg): ?><div class="alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert-err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <form method="POST">
    <div class="fila">
      <select name="profesor_id" required>
        <option value="">Seleccionar Profesor</option>
        <?php while ($row = $result->fetch_assoc()): ?>
          <option value="<?= (int)$row['id'] ?>">
            <?= htmlspecialchars($row['apellido'].' '.$row['nombre']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="fila">
      <div class="dias">
        <?php
          $diasSemana = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
          foreach ($diasSemana as $d):
        ?>
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

  <h2>Turnos Registrados</h2>
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
