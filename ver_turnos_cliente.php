<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);

// ===== CSRF token por sesión (se renueva si no existe) =====
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ==== helpers ====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$en2es = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];

function nombreDiaEs(string $fechaYmd): string {
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}
function proximaFechaDelDia(string $diaEs, string $desdeYmd): string {
  // 0=Domingo..6=Sábado
  $es2num = ['Domingo'=>0,'Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6];
  $target = $es2num[$diaEs] ?? 1;
  $ts = strtotime($desdeYmd);
  for ($i=0; $i<7; $i++) {
    $cand = strtotime("+$i day", $ts);
    if ((int)date('w',$cand) === $target) return date('Y-m-d',$cand);
  }
  return $desdeYmd;
}
function norm_hora($h){ $h = trim((string)$h); return $h==='' ? null : substr($h,0,8); }

// ==== UI params: día y fecha (como ya venías usando) ====
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
  WHERE cliente_id = {$cliente_id} AND fecha_vencimiento >= CURDATE()
  ORDER BY fecha_inicio DESC LIMIT 1
")->fetch_assoc();

$reservas = [];
$res_q = $conexion->query("
  SELECT turno_id FROM reservas_clientes 
  WHERE cliente_id = {$cliente_id} AND gimnasio_id = {$gimnasio_id}
");
while ($r = $res_q->fetch_assoc()) { $reservas[(int)$r['turno_id']] = true; }

// ==== menú ====
require_once __DIR__.'/menu_cliente.php';

/* ============================================================
   EXCEPCIONES (modo simple: solo cierres bloquean)
   ============================================================ */
$stmtExc = $conexion->prepare("
  SELECT e.profesor_id, e.cerrado
  FROM turnos_profesor_excepciones e
  WHERE e.gimnasio_id = ? AND e.fecha = ?
");
$stmtExc->bind_param("is", $gimnasio_id, $fecha);
$stmtExc->execute();
$rowsExc = $stmtExc->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtExc->close();

$cerradoGlobal = false;
$cerradosPorProfe = [];
foreach ($rowsExc as $ex) {
  $pid  = (int)$ex['profesor_id'];
  $cerr = ((int)$ex['cerrado'] === 1);
  if ($pid === 0 && $cerr) $cerradoGlobal = true;
  if ($pid > 0  && $cerr) $cerradosPorProfe[$pid] = true;
}

/* ============================================================
   LISTA BLANCA (activa solo con bandera pid=0 00:00–00:00)
   ============================================================ */
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

$permitidos = [];
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

/* ============================================================
   Base semanal del día
   ============================================================ */
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

// Si hay lista blanca, filtramos SOLO a lo permitido
if ($hayListaBlanca) {
  $filtrados = [];
  foreach ($baseRows as $t) {
    $pid = (int)$t['profesor_id'];
    $clave = $t['hora_inicio'].'_'.$t['hora_fin'];
    if (isset($permitidos[$pid][$clave])) { $filtrados[] = $t; }
  }
  $baseRows = $filtrados;
}

/* ============================================================
   Reglas de habilitación por turno (solo cierre explícito)
   ============================================================ */
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

  <!-- Filtros (solo quité el texto confuso) -->
  <form method="GET" style="margin-bottom:12px;display:flex;gap:.5rem;align-items:end;flex-wrap:wrap">
    <div>
      <label for="dia">Día:</label>
      <select name="dia" id="dia" onchange="this.form.submit()">
        <?php foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $d):
          $sel = $d==$dia_seleccionado?'selected':''; ?>
          <option value="<?= h($d) ?>" <?= $sel ?>><?= h($d) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
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
        $reservado = isset($reservas[$tid]);
        [$habilitado, $motivoBloqueo] = turno_habilitado_simple($t, $cerradoGlobal, $cerradosPorProfe);
      ?>
        <tr>
          <td><?= h(substr($t['hora_inicio'],0,5)) ?> - <?= h(substr($t['hora_fin'],0,5)) ?></td>
          <td><?= h($t['apellido'].' '.$t['nombre']) ?></td>
          <td>
            <?php if ($reservado): ?>
              <form method="POST" action="cancelar_reserva.php">
                <input type="hidden" name="turno_id" value="<?= $tid ?>">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <button type="submit" class="cancelar btn">Cancelar</button>
              </form>
            <?php else: ?>
              <?php if ($habilitado): ?>
                <form method="POST" action="reservar_turno.php">
                  <input type="hidden" name="turno_id" value="<?= $tid ?>">
                  <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                  <!-- 🔒 TOKEN anti auto-llamadas -->
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <!-- 👇 Solo si el usuario CLICKEA este botón se procesa -->
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
