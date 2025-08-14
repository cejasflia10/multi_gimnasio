<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id  = $_SESSION['cliente_id'] ?? 0;

// ==== helpers ====
$en2es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
function nombreDiaEs(string $fechaYmd): string {
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}
function proximaFechaDelDia(string $diaEs, string $desdeYmd): string {
  $es2num = ['Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6,'Domingo'=>0];
  $target = $es2num[$diaEs] ?? 1;
  $ts = strtotime($desdeYmd);
  for ($i=0; $i<7; $i++) {
    $cand = strtotime("+$i day", $ts);
    if ((int)date('w',$cand) === $target) return date('Y-m-d',$cand);
  }
  return $desdeYmd;
}

// ==== UI params: día y fecha ====
$dia_hoy = $en2es[date('l')] ?? 'Lunes';
$dia_seleccionado = $_GET['dia'] ?? $dia_hoy;

$fecha = $_GET['fecha'] ?? ''; // si no mandan fecha, usamos la próxima del día elegido
if ($fecha) {
  $dt = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$dt || $dt->format('Y-m-d') !== $fecha) $fecha = '';
}
if ($fecha === '') {
  $fecha = proximaFechaDelDia($dia_seleccionado, date('Y-m-d'));
}
$dia_seleccionado = nombreDiaEs($fecha); // la fecha manda

// ==== membresía y reservas del cliente ====
$membresia = $conexion->query("
  SELECT * FROM membresias 
  WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE()
  ORDER BY fecha_inicio DESC LIMIT 1
")->fetch_assoc();

$reservas = [];
$res_q = $conexion->query("
  SELECT turno_id FROM reservas_clientes 
  WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id
");
while ($r = $res_q->fetch_assoc()) { $reservas[$r['turno_id']] = true; }

// ==== menú ====
include 'menu_cliente.php';

// ==== EXCEPCIONES por FECHA exacta (feriado / horario reducido) ====
$stmtExc = $conexion->prepare("
  SELECT e.profesor_id, e.cerrado, e.hora_inicio, e.hora_fin,
         p.nombre, p.apellido
  FROM turnos_profesor_excepciones e
  JOIN profesores p ON p.id = e.profesor_id
  WHERE e.gimnasio_id = ? AND e.fecha = ?
");
$stmtExc->bind_param("is", $gimnasio_id, $fecha);
$stmtExc->execute();
$excepciones = $stmtExc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtExc->close();

$excPorProfe = [];
foreach ($excepciones as $ex) {
  $excPorProfe[(int)$ex['profesor_id']] = [
    'cerrado'     => (int)$ex['cerrado'],
    'hora_inicio' => $ex['hora_inicio'],
    'hora_fin'    => $ex['hora_fin'],
    'nombre'      => $ex['nombre'],
    'apellido'    => $ex['apellido'],
  ];
}
$hayFeriado = !empty($excPorProfe);

// ==== LISTA BLANCA por FECHA (franjas permitidas explícitas) ====
$stmtP = $conexion->prepare("
  SELECT profesor_id, hora_inicio, hora_fin
  FROM turnos_permitidos_fecha
  WHERE gimnasio_id = ? AND fecha = ?
");
$stmtP->bind_param("is", $gimnasio_id, $fecha);
$stmtP->execute();
$resP = $stmtP->get_result();
$permitidos = [];
while ($p = $resP->fetch_assoc()) {
  $permitidos[(int)$p['profesor_id']][$p['hora_inicio'].'_'.$p['hora_fin']] = true;
}
$stmtP->close();
$hayListaBlanca = !empty($permitidos);

// ==== Cargar base semanal del día ====
$qBase = $conexion->prepare("
  SELECT td.*, p.nombre, p.apellido
  FROM turnos_disponibles td
  JOIN profesores p ON td.profesor_id = p.id
  WHERE td.gimnasio_id = ? 
    AND LOWER(TRIM(td.dia)) = LOWER(?)
  ORDER BY td.hora_inicio
");
$qBase->bind_param("is", $gimnasio_id, $dia_seleccionado);
$qBase->execute();
$rsBase = $qBase->get_result();

// Si hay lista blanca, filtramos SOLO a lo permitido
if ($hayListaBlanca) {
  $filtrados = [];
  while ($t = $rsBase->fetch_assoc()) {
    $pid = (int)$t['profesor_id'];
    $clave = $t['hora_inicio'].'_'.$t['hora_fin'];
    if (isset($permitidos[$pid][$clave])) { $filtrados[] = $t; }
  }
  // Normalizamos a un "result" con la misma interfaz
  $turnos = new class($filtrados){private $rows; public $num_rows; function __construct($r){$this->rows=$r;$this->num_rows=count($r);} function fetch_assoc(){return array_shift($this->rows);} };
} else {
  // Sin lista blanca: usar el result normal
  $turnos = $rsBase;
}
$qBase->close();

// ==== Regla de habilitación final ====
// - Si hay lista blanca: un turno está habilitado SOLO si está en la lista blanca.
//   También, si existe excepción para su profe ese día y está "cerrado" o fuera de rango, se deshabilita.
// - Si NO hay lista blanca: se habilita por defecto salvo que la excepción lo cierre o lo deje fuera de rango.
function turno_habilitado(array $t, array $excPorProfe, array $permitidos, bool $hayListaBlanca): array {
  $pid = (int)$t['profesor_id'];
  $hin = $t['hora_inicio'];
  $hfi = $t['hora_fin'];

  // 1) Lista blanca primero
  if ($hayListaBlanca) {
    $clave = $hin.'_'.$hfi;
    if (!isset($permitidos[$pid][$clave])) {
      return [false, 'No permitido para esta fecha'];
    }
  }

  // 2) Excepción (feriado)
  if (!empty($excPorProfe)) {
    if (!isset($excPorProfe[$pid])) {
      // Hay feriado cargado pero este profe no tiene excepción explícita:
      // política estricta: bloquear
      return [false, 'Bloqueado por feriado'];
    }
    $ex = $excPorProfe[$pid];
    if ($ex['cerrado'] === 1) {
      return [false, 'Profesor cerrado por feriado'];
    }
    // Dentro de rango especial
    if (!($hin >= $ex['hora_inicio'] && $hfi <= $ex['hora_fin'])) {
      return [false, 'Fuera del horario especial por feriado'];
    }
  }

  return [true, ''];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Turnos Disponibles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .note{background:#fff7e6;border:1px solid #ffd591;padding:.5rem .75rem;border-radius:.5rem;margin:.5rem 0;}
    .btn[disabled]{opacity:.5;cursor:not-allowed;pointer-events:none;}
    .reservar, .cancelar { padding:.35rem .6rem; border:0; border-radius:.375rem; }
    .reservar{ background:#2563eb; color:#fff;}
    .cancelar{ background:#6b7280; color:#fff;}
  </style>
</head>
<body>
<div class="contenedor">

  <h2>📅 Turnos del día: <?= htmlspecialchars($dia_seleccionado) ?> (<?= htmlspecialchars($fecha) ?>)</h2>

  <!-- Filtros -->
  <form method="GET" style="margin-bottom:12px;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
    <div>
      <label for="dia">Día:</label>
      <select name="dia" id="dia" onchange="this.form.submit()">
        <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $d):
          $sel = $d==$dia_seleccionado?'selected':''; ?>
          <option value="<?= $d ?>" <?= $sel ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="fecha">Fecha:</label>
      <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha) ?>" onchange="this.form.submit()">
      <small>Si no elegís fecha, usamos la próxima <?= htmlspecialchars($dia_seleccionado) ?>.</small>
    </div>
  </form>

  <?php if (!empty($_SESSION['aviso_deuda'])): ?>
    <div style="color:red;font-weight:bold;text-align:center;margin-bottom:10px;">
      ⚠️ No tenés clases activas. Se generó una deuda de $1000 en cuenta corriente por esta reserva.
    </div>
    <?php unset($_SESSION['aviso_deuda']); ?>
  <?php endif; ?>

  <?php if ($hayListaBlanca): ?>
    <div class="note">🟠 <strong>Horario especial</strong> para <?= htmlspecialchars($fecha) ?>:
      solo se muestran franjas permitidas para esta fecha.</div>
  <?php elseif ($hayFeriado): ?>
    <div class="note">🟠 <strong>Horario especial por feriado</strong> para <?= htmlspecialchars($fecha) ?>.
      Los turnos fuera del rango especial aparecen deshabilitados.</div>
  <?php endif; ?>

  <p>🎫 Clases disponibles: <strong><?= $membresia['clases_disponibles'] ?? 0 ?></strong></p>

  <?php if ($turnos && $turnos->num_rows > 0): ?>
    <table>
      <tr>
        <th>Hora</th>
        <th>Profesor</th>
        <th>Acción</th>
      </tr>
      <?php while ($t = $turnos->fetch_assoc()):
        $tid = $t['id'];
        $reservado = isset($reservas[$tid]);

        // Habilitación combinada: lista blanca + excepción
        [$habilitado, $motivoBloqueo] = turno_habilitado($t, $excPorProfe, $permitidos, $hayListaBlanca);
      ?>
        <tr>
          <td><?= htmlspecialchars(substr($t['hora_inicio'],0,5)) ?> - <?= htmlspecialchars(substr($t['hora_fin'],0,5)) ?></td>
          <td><?= htmlspecialchars($t['apellido'].' '.$t['nombre']) ?></td>
          <td>
            <?php if ($reservado): ?>
              <form method="POST" action="cancelar_reserva.php">
                <input type="hidden" name="turno_id" value="<?= (int)$tid ?>">
                <button type="submit" class="cancelar btn">Cancelar</button>
              </form>
            <?php else: ?>
              <form method="POST" action="reservar_turno.php" onsubmit="return <?= $habilitado ? 'true' : 'false' ?>;">
                <input type="hidden" name="turno_id" value="<?= (int)$tid ?>">
                <button type="submit" class="reservar btn" <?= $habilitado ? '' : 'disabled' ?> title="<?= htmlspecialchars($motivoBloqueo) ?>">
                  Reservar
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  <?php else: ?>
    <p style="text-align:center;">No hay turnos disponibles para <?= htmlspecialchars($dia_seleccionado) ?> (<?= htmlspecialchars($fecha) ?>).</p>
  <?php endif; ?>
</div>
</body>
</html>
