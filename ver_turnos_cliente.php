<?php
/* ============================================================
   ver_turnos_cliente.php — Listado y Reservar/Cancelar
   FECHA por defecto = HOY · Menú + estilos unificados
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function nombreDiaEs(string $fechaYmd): string {
  $map = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
  return $map[date('l', strtotime($fechaYmd))] ?? 'Lunes';
}
function table_exists(mysqli $cx, string $name): bool {
  $n = $cx->real_escape_string($name);
  $rs = $cx->query("SHOW TABLES LIKE '{$n}'");
  return ($rs instanceof mysqli_result) && $rs->num_rows > 0;
}
function column_exists(mysqli $cx, string $table, string $col): bool {
  if (!table_exists($cx, $table)) return false;
  $t = $cx->real_escape_string($table);
  $c = $cx->real_escape_string($col);
  $rs = $cx->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($rs instanceof mysqli_result) && $rs->num_rows > 0;
}

/* ===== CSRF token ===== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* ===== Parámetros UI ===== */
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$dt || $dt->format('Y-m-d') !== $fecha) $fecha = date('Y-m-d');
$dia_seleccionado = nombreDiaEs($fecha);

/* ===== Membresía (informativa) ===== */
$membresia = null;
if (table_exists($conexion,'membresias')) {
  if ($st = $conexion->prepare("SELECT clases_disponibles, fecha_vencimiento
                                FROM membresias
                                WHERE cliente_id=? AND fecha_vencimiento>=CURDATE()
                                ORDER BY fecha_vencimiento ASC LIMIT 1")) {
    $st->bind_param('i', $cliente_id);
    $st->execute();
    $membresia = $st->get_result()->fetch_assoc();
    $st->close();
  }
}

/* ===== Reservas del cliente (fecha elegida) ===== */
$reservas = [];
if (table_exists($conexion,'reservas_clientes')) {
  $hasGym = column_exists($conexion,'reservas_clientes','gimnasio_id');
  $sql = "SELECT id, turno_id FROM reservas_clientes WHERE cliente_id=? ".($hasGym?"AND gimnasio_id=? ":"")."AND fecha_reserva=?";
  if ($st = $conexion->prepare($sql)) {
    if ($hasGym) $st->bind_param('iis',$cliente_id,$gimnasio_id,$fecha); else $st->bind_param('is',$cliente_id,$fecha);
    $st->execute(); $rs=$st->get_result();
    if ($rs instanceof mysqli_result) {
      while($r=$rs->fetch_assoc()) $reservas[(int)$r['turno_id']] = ['id'=>(int)$r['id']];
    }
    $st->close();
  }
}

/* ===== Excepciones de cierre (global/profe) ===== */
$cerradoGlobal=false; $cerradosPorProfe=[];
if (table_exists($conexion,'turnos_profesor_excepciones')) {
  if ($st=$conexion->prepare("SELECT profesor_id, cerrado FROM turnos_profesor_excepciones WHERE gimnasio_id=? AND fecha=?")){
    $st->bind_param('is',$gimnasio_id,$fecha); $st->execute();
    $rs=$st->get_result();
    if ($rs instanceof mysqli_result) {
      while($ex=$rs->fetch_assoc()){
        $pid=(int)$ex['profesor_id']; $cerr=((int)$ex['cerrado']===1);
        if($pid===0 && $cerr) $cerradoGlobal=true;
        if($pid>0 && $cerr) $cerradosPorProfe[$pid]=true;
      }
    }
    $st->close();
  }
}

/* ===== Lista blanca (opcional) ===== */
$hayListaBlanca=false; $permitidos=[];
if (table_exists($conexion,'turnos_permitidos_fecha')) {
  if ($st=$conexion->prepare("SELECT 1 FROM turnos_permitidos_fecha
                              WHERE gimnasio_id=? AND fecha=? AND profesor_id=0
                                AND hora_inicio='00:00:00' AND hora_fin='00:00:00' LIMIT 1")){
    $st->bind_param('is',$gimnasio_id,$fecha); $st->execute();
    $rs=$st->get_result(); $hayListaBlanca = ($rs instanceof mysqli_result) && $rs->num_rows>0;
    $st->close();
  }
  if ($hayListaBlanca) {
    if ($sp=$conexion->prepare("SELECT profesor_id, hora_inicio, hora_fin
                                FROM turnos_permitidos_fecha
                                WHERE gimnasio_id=? AND fecha=? AND profesor_id<>0")){
      $sp->bind_param('is',$gimnasio_id,$fecha); $sp->execute();
      $rp=$sp->get_result();
      if ($rp instanceof mysqli_result) {
        while($p=$rp->fetch_assoc()){
          $permitidos[(int)$p['profesor_id']][$p['hora_inicio'].'_'.$p['hora_fin']] = true;
        }
      }
      $sp->close();
    }
  }
}

/* ===== Base semanal del día ===== */
$baseRows=[];
if ($st=$conexion->prepare("SELECT td.*, p.nombre, p.apellido
                            FROM turnos_disponibles td
                            JOIN profesores p ON p.id=td.profesor_id
                            WHERE td.gimnasio_id=? AND LOWER(TRIM(td.dia))=LOWER(?)
                            ORDER BY td.hora_inicio")){
  $st->bind_param('is',$gimnasio_id,$dia_seleccionado);
  $st->execute(); $rb=$st->get_result();
  if ($rb instanceof mysqli_result) $baseRows=$rb->fetch_all(MYSQLI_ASSOC);
  $st->close();
}
if ($hayListaBlanca && $baseRows){
  $fil=[]; foreach($baseRows as $t){ $pid=(int)$t['profesor_id']; $k=$t['hora_inicio'].'_'.$t['hora_fin'];
    if(isset($permitidos[$pid][$k])) $fil[]=$t; } $baseRows=$fil;
}

/* ===== Habilitado simple por cierres ===== */
function turno_habilitado_simple(array $t, bool $cerradoGlobal, array $cerradosPorProfe): array {
  $pid = (int)$t['profesor_id'];
  if ($cerradoGlobal) return [false,'Día cerrado por feriado'];
  if (!empty($cerradosPorProfe[$pid])) return [false,'Profesor cerrado por excepción'];
  return [true,''];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Turnos Disponibles</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- MISMA hoja unificada que el panel -->
  <link rel="stylesheet" href="/multi_gimnasio/estilo_unificado.css?v=20251006">

  <style>
    /* ===== Overrides para que SIEMPRE se vean las letras del menú ===== */
    .mc-top, .mc-top *, .mc-drawer *, .mc-tabs *, .mc-item, .mc-item * {
      -webkit-text-fill-color: currentColor !important;
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
    }
    /* Barra superior */
    .mc-top{ background:#111 !important; border-bottom:1px solid #444 !important; }
    .mc-bar .mc-title{ color: gold !important; font-weight: 800 !important; }
    .mc-bar .mc-link{ color: gold !important; }
    .mc-bar .mc-btn{ background:#ffd600 !important; color:#000 !important; }
    .mc-bar .mc-link:hover{ background:#222 !important; }

    /* Drawer / items */
    .mc-item{ background:#222 !important; border:1px solid #444 !important; color:gold !important; }
    .mc-item:hover{ background:#333 !important; }

    /* Tabs inferiores */
    .mc-tabs{ background:#111 !important; border-top:1px solid #444 !important; }
    .mc-tabs a{ color: gold !important; }
    .mc-tabs a.active{ background:#333 !important; border-color:#444 !important; color:#fff !important; }

    /* ===== Ajustes locales de la página ===== */
    .note{background:#fff7e6;border:1px solid #ffd591;padding:.6rem .8rem;border-radius:.5rem;margin:.75rem 0;}
    .reservar, .cancelar { padding:.45rem .7rem; border:0; border-radius:.5rem; cursor:pointer; }
    .reservar{ background:#2563eb; color:#fff;}
    .cancelar{ background:#6b7280; color:#fff;}
    table{width:100%;border-collapse:collapse}
    th,td{padding:.6rem;border-bottom:1px solid #444;text-align:left}
    .contenedor{ max-width: 900px; margin: 20px auto; }
  </style>
</head>
<body>

<?php include __DIR__.'/menu_cliente.php'; ?>

<div class="contenedor">
  <h2>📅 Turnos del día: <?= h($dia_seleccionado) ?> (<?= h($fecha) ?>)</h2>

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
