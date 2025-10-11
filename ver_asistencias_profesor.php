<?php
session_start();
require __DIR__.'/conexion.php';
require __DIR__.'/menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}
$gimnasio_id  = (int)$_SESSION['gimnasio_id'];
$filtro_fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_fecha)) {
    $filtro_fecha = date('Y-m-d');
}

/* ===== Consulta principal (PREPARADA) ===== */
$sql = "
    SELECT a.id, a.fecha, a.hora_entrada, a.hora_salida,
           a.profesor_id,
           p.apellido, p.nombre,
           IF(a.hora_entrada IS NOT NULL AND a.hora_salida IS NOT NULL,
              TIMESTAMPDIFF(MINUTE, a.hora_entrada, a.hora_salida), NULL) AS minutos,
           COALESCE(a.hora_entrada, a.hora_salida) AS orden_hora
    FROM asistencias_profesores a
    INNER JOIN profesores p ON a.profesor_id = p.id
    WHERE a.fecha = ? AND a.gimnasio_id = ?
    ORDER BY a.fecha DESC, orden_hora DESC
";
$st = $conexion->prepare($sql);
$st->bind_param('si', $filtro_fecha, $gimnasio_id);
$st->execute();
$res = $st->get_result();

/* Helper seguro */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🕓 Asistencias Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ===== Maqueta alineada al index ===== */
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
    .page-card{
      background:var(--card); border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
    }
    .page-title{
      margin:0 0 12px 0; text-align:center; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* Filtro de fecha */
    .toolbar{
      display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap; margin:10px 0 16px;
    }
    .toolbar input[type="date"], .toolbar button{
      padding:10px 12px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink); font-weight:700; cursor:pointer;
    }
    .toolbar button:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }

    /* Tabla clara tipo index */
    .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ width:100%; min-width:860px; border-collapse:collapse; background:#fff; }
    .tabla thead th{
      position:sticky; top:0; z-index:1; background:#f7fafc; color:#0f172a;
      border-bottom:1px solid var(--stroke); text-align:center;
    }
    .tabla th, .tabla td{
      padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center; white-space:nowrap;
    }
    .tabla tbody tr:hover{ background:#f9fafb; }

    /* Bloque de alumnos por turno */
    .alumnos-turno{
      text-align:left; background:#fff; border-left:4px solid var(--brand);
      padding:10px 12px; border-radius:12px; box-shadow:var(--shadow);
    }
    .alumnos-turno ul{ margin:6px 0 0; padding-left:18px; }
    .muted{ color:#64748b; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h2 class="page-title">🕓 Asistencias de Profesores</h2>

      <form class="toolbar" method="get" role="search">
        <label for="fecha" class="muted">📅 Seleccionar fecha:</label>
        <input id="fecha" type="date" name="fecha" value="<?= h($filtro_fecha) ?>">
        <button type="submit">Filtrar</button>
      </form>

      <div class="table-wrap">
        <table class="tabla" aria-label="Asistencias de profesores">
          <thead>
            <tr>
              <th>Profesor</th>
              <th>Fecha</th>
              <th>Hora Ingreso</th>
              <th>Hora Egreso</th>
              <th>Tiempo Trabajado</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($res && $res->num_rows): ?>
            <?php while($fila = $res->fetch_assoc()): ?>
              <?php
                $prof = trim(($fila['apellido'] ?? '').' '.($fila['nombre'] ?? ''));
                $min  = $fila['minutos'];
                $trab = '-';
                if ($fila['hora_entrada'] && $fila['hora_salida'] && $min !== null){
                  $horas = (int)floor($min/60);
                  $mins  = (int)($min%60);
                  $trab  = $horas.'h '.$mins.'m';
                }
              ?>
              <tr>
                <td><?= h($prof) ?></td>
                <td><?= h($fila['fecha']) ?></td>
                <td><?= h($fila['hora_entrada'] ?? '-') ?></td>
                <td><?= h($fila['hora_salida'] ?? '-') ?></td>
                <td><?= h($trab) ?></td>
              </tr>

              <?php
                // ===== Alumnos del turno (consulta preparada por fila)
                $hora_ini = $fila['hora_entrada'];
                $hora_fin = $fila['hora_salida'] ?: '23:59:59';

                $sqlAlu = "
                  SELECT c.apellido, c.nombre, c.dni, a.hora
                  FROM asistencias a
                  INNER JOIN clientes c ON c.id = a.cliente_id
                  WHERE a.gimnasio_id = ?
                    AND a.fecha = ?
                    AND a.hora BETWEEN ? AND ?
                  ORDER BY a.hora ASC
                ";
                $stAlu = $conexion->prepare($sqlAlu);
                $stAlu->bind_param('isss', $gimnasio_id, $filtro_fecha, $hora_ini, $hora_fin);
                $stAlu->execute();
                $alumnos = $stAlu->get_result();
              ?>
              <tr>
                <td colspan="5">
                  <div class="alumnos-turno">
                    <strong>👥 Alumnos presentes durante el turno:</strong><br>
                    <?php if ($alumnos && $alumnos->num_rows): ?>
                      <ul>
                        <?php while($a = $alumnos->fetch_assoc()): ?>
                          <li>🕒 <?= h($a['hora']) ?> — <?= h($a['apellido'].' '.$a['nombre']) ?> (DNI: <?= h($a['dni']) ?>)</li>
                        <?php endwhile; ?>
                      </ul>
                    <?php else: ?>
                      <span class="muted">Sin alumnos registrados en este turno.</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php $stAlu->close(); ?>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="muted">Sin registros para la fecha seleccionada.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
