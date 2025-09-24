<?php
/* ============================================================
   ver_turnos_clientes.php — Listado y Reservar/Cancelar
   - La FECHA se completa por defecto con HOY
   - No cambia tu vista ni el flujo
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }

/* ===== CSRF token ===== */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ===== helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$en2es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
function nombreDiaEs(string $fechaYmd): string {
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}

/* ===== Parámetros de UI =====
   FECHA: por defecto HOY (lo que pediste)
   Día mostrado = día de la fecha seleccionada
   ============================================================ */
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$dt || $dt->format('Y-m-d') !== $fecha) {
  $fecha = date('Y-m-d');
}
$dia_seleccionado = nombreDiaEs($fecha);

/* ===== membresía (informativa) ===== */
$membresia = $conexion->query("
  SELECT * FROM membresias 
  WHERE cliente_id = {$cliente_id} AND fecha_vencimiento >= CURDATE()
  ORDER BY fecha_inicio DESC LIMIT 1
")?->fetch_assoc();

/* ===== reservas del cliente (para esa fecha) =====
   Usamos tu tabla reservas_clientes como nos pasaste el esquema
   Guardamos map: turno_id => ['id'=>reserva_id]
   ============================================================ */
$reservas = []; // por turno en esa fecha
$qres = $conexion->prepare("
  SELECT id, turno_id 
  FROM reservas_clientes 
  WHERE cliente_id=? AND gimnasio_id=? AND fecha_reserva=?
");
$qres->bind_param('iis', $cliente_id, $gimnasio_id, $fecha);
$qres->execute();
$rs = $qres->get_result();
while($r=$rs->fetch_assoc()){
  $reservas[(int)$r['turno_id']] = ['id'=>(int)$r['id']];
}
$qres->close();

/* ===== menú ===== */
require_once __DIR__.'/menu_cliente.php';

/* ===== Excepciones (sólo cierres) ===== */
$cerradoGlobal = false;
$cerradosPorProfe = [];
$stmtExc = $conexion->prepare("
  SELECT e.profesor_id, e.cerrado
  FROM turnos_profesor_excepciones e
  WHERE e.gimnasio_id = ? AND e.fecha = ?
");
$stmtExc->bind_param("is", $gimnasio_id, $fecha);
$stmtExc->execute();
$rowsExc = $stmtExc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtExc->close();
foreach ($rowsExc as $ex) {
  $pid  = (int)$ex['profesor_id'];
  $cerr = ((int)$ex['cerrado'] === 1);
  if ($pid === 0 && $cerr) $cerradoGlobal = true;
  if ($pid > 0  && $cerr) $cerradosPorProfe[$pid] = true;
}

/* ===== Lista blanca (si está activa con bandera pid=0 00:00–00:00) ===== */
$hayListaBlanca = false;
$permitidos = [];
$flagLB = $conexion->prepare("
  SELECT 1 FROM turnos_permitidos_fecha
  WHERE gimnasio_id=? AND fecha=? AND profesor_id=0
    AND hora_inicio='00:00:00' AND hora_fin='00:00:00'
  LIMIT 1
");
$flagLB->bind_param("is", $gimnasio_id, $fecha);
$flagLB->execute();
$hayListaBlanca = (bool)$flagLB->get_result()->num_rows;
$flagLB->close();

if ($hayListaBlanca) {
  $stmtP = $conexion->prepare("
    SELECT profesor_id, hora_inicio, hora_fin
    FROM turnos_permitidos_fecha
    WHERE gimnasio_id = ? AND fecha = ? AND profesor_id <> 0
  ");
  $stmtP->bind_param("is", $gimnasio_id, $fecha);
  $stmtP->execute();
  $resP = $stmtP->get_result();
  while ($p = $resP->fetch_assoc()) {
    $permitidos[(int)$p['profesor_id']][$p['hora_inicio'].'_'.$p['hora_fin']] = true;
  }
  $stmtP->close();
}

/* ===== Base semanal del día (según la FECHA elegida) ===== */
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
$baseRows = $rsBase->fetch_all(MYSQLI_ASSOC);
$qBase->close();

if ($hayListaBlanca) {
  $filtrados = [];
  foreach ($baseRows as $t) {
    $pid = (int)$t['profesor_id'];
    $clave = $t['hora_inicio'].'_'.$t['hora_fin'];
    if (isset($permitidos[$pid][$clave])) { $filtrados[] = $t; }
  }
  $baseRows = $filtrados;
}

/* ===== Habilitado simple por cierres ===== */
function turno_habilitado_simple(array $t, bool $cerradoGlobal, array $cerradosPorProfe): array {
  $pid = (int)$t['profesor_id'];
  if ($cerradoGlobal) return [false, 'Día cerrado por feriado'];
  if (!empty($cerradosPorProfe[$pid])) return [false, 'Profesor cerrado por excepción'];
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
    .note{background:#fff7e6;border:1px solid #ffd591;padding:.6rem .8rem;border-radius:.5rem;margin:.75rem 0;}
    .reservar, .cancelar { padding:.35rem .6rem; border:0; border-radius:.375rem; }
    .reservar{ background:#2563eb; color:#fff;}
    .cancelar{ background:#6b7280; color:#fff;}
    table{width:100%;border-collapse:collapse}
    th,td{padding:.5rem;border-bottom:1px solid #444}
  </style>
</head>
<body>
<div class="contenedor">

  <h2>📅 Turnos del día: <?= h($dia_seleccionado) ?> (<?= h($fecha) ?>)</h2>

  <!-- Filtros: fecha por defecto = HOY -->
  <form method="GET" style="margin-bottom:12px;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
    <div>
      <label for="fecha">Fecha:</label>
      <input type="date" id="fecha" name="fecha" value="<?= h($fecha) ?>" onchange="this.form.submit()">
    </div>
  </form>

  <?php if (!empty($_GET['err'])): ?>
    <div class="note" style="border-color:#f87171;background:#fee2e2"><strong><?= h($_GET['err']) ?></strong></div>
  <?php elseif (!empty($_GET['ok'])): ?>
    <div class="note" style="border-color:#34d399;background:#ecfdf5"><strong><?= h($_GET['ok']) ?></strong></div>
  <?php endif; ?>

  <p>🎫 Clases disponibles: <strong><?= (int)($membresia['clases_disponibles'] ?? 0) ?></strong></p>

  <?php if (!empty($baseRows)): ?>
    <table>
      <tr>
        <th>Hora</th>
        <th>Profesor</th>
        <th>Acción</th>
      </tr>
      <?php foreach ($baseRows as $t):
        $tid = (int)$t['id'];
        $reservado   = isset($reservas[$tid]);
        $reserva_id  = $reservado ? $reservas[$tid]['id'] : null;

        [$habilitado, $motivoBloqueo] = turno_habilitado_simple($t, $cerradoGlobal, $cerradosPorProfe);
      ?>
        <tr>
          <td><?= h(substr($t['hora_inicio'],0,5)) ?> - <?= h(substr($t['hora_fin'],0,5)) ?></td>
          <td><?= h($t['apellido'].' '.$t['nombre']) ?></td>
          <td>
            <?php if ($reservado): ?>
              <form method="POST" action="cancelar_reserva.php" style="display:inline">
                <?php if (!empty($reserva_id)): ?>
                  <input type="hidden" name="reserva_id" value="<?= (int)$reserva_id ?>">
                <?php endif; ?>
                <input type="hidden" name="turno_id" value="<?= $tid ?>">
                <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="cancelar btn">Cancelar</button>
              </form>
            <?php else: ?>
              <?php if ($habilitado): ?>
                <form method="POST" action="reservar_turno.php" style="display:inline">
                  <input type="hidden" name="turno_id" value="<?= $tid ?>">
                  <input type="hidden" name="fecha"    value="<?= h($fecha) ?>">
                  <input type="hidden" name="csrf"     value="<?= h($csrf) ?>">
                  <button type="submit" class="reservar btn" name="reservar" value="1">Reservar</button>
                </form>
              <?php else: ?>
                <span title="<?= h($motivoBloqueo) ?>">⛔ No disponible</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php else: ?>
    <p style="text-align:center;">No hay turnos disponibles para <?= h($dia_seleccionado) ?> (<?= h($fecha) ?>).</p>
  <?php endif; ?>
</div>
</body>
</html>
