<?php
/* ============================================================
   ver_turnos_cliente.php — Listado y Reservar/Cancelar
   MISMO look & feel que panel_cliente.php (menú .mnu-* + glass)
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
if ($cliente_id===0 || $gimnasio_id===0){ header('Location: cliente_acceso.php'); exit; }

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
  if (!table_exists($cx,$table)) return false;
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
                                WHERE cliente_id=? AND gimnasio_id=? AND fecha_vencimiento>=CURDATE()
                                ORDER BY fecha_vencimiento ASC LIMIT 1")) {
    $st->bind_param('ii',$cliente_id,$gimnasio_id);
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
    if ($rs instanceof mysqli_result) while($r=$rs->fetch_assoc()) $reservas[(int)$r['turno_id']] = ['id'=>(int)$r['id']];
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
  $fil=[]; foreach($baseRows as $t){ $pid=(int)$t['profesor_id']; $k=$t['hora_inicio'].'_'.$t['hora_fin']; if(isset($permitidos[$pid][$k])) $fil[]=$t; }
  $baseRows=$fil;
}

/* ===== Habilitado simple por cierres ===== */
function turno_habilitado_simple(array $t, bool $cerradoGlobal, array $cerradosPorProfe): array {
  $pid = (int)$t['profesor_id'];
  if ($cerradoGlobal) return [false,'Día cerrado por feriado'];
  if (!empty($cerradosPorProfe[$pid])) return [false,'Profesor cerrado por excepción'];
  return [true,''];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Ver Turnos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ============== MENÚ UNIFICADO (copiado de panel_cliente.php) ============== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;      /* dorado */
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);
    }
    .mnu-bar{
      position:sticky; top:0; z-index:1000;
      display:flex; align-items:center; gap:12px;
      padding:10px 14px; background:var(--mnu-bg-bar);
      -webkit-backdrop-filter: blur(10px) saturate(1.05);
      backdrop-filter: blur(10px) saturate(1.05);
      border-bottom:1px solid var(--mnu-border);
    }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; }
    .mnu-btn--ghost{ background:transparent; color:var(--mnu-fg); border:1px solid var(--mnu-border); }

    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:var(--mnu-fg); text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }

    @media (max-width:920px){ .mnu-inline{ display:none !important; } }

    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{
      position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px;
      background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border);
      box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease;
      z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px;
    }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }

    /* legibilidad anti background-clip/text-fill heredados */
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{
      color:#fff !important; -webkit-text-fill-color:#fff !important;
      text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important;
    }

    /* ================== ESTILOS GENERALES (idénticos al panel) ================== */
    :root{
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg);
           color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .card{ padding:18px }
    .muted{ color:var(--muted) }

    /* ====== PROPIOS de esta página (tabla responsive) ====== */
    .vtc-filter{ display:flex; align-items:center; gap:10px; margin-bottom:10px }
    .vtc-filter input[type="date"]{ background:#0f1115; color:var(--fg); border:1px solid var(--border); padding:8px 10px; border-radius:12px }
    table{ width:100%; border-collapse:collapse }
    th,td{ padding:.6rem; border-bottom:1px solid var(--border); text-align:left }
    .btn{ display:inline-block; padding:.45rem .75rem; border-radius:.6rem; border:0; cursor:pointer; font-weight:800; }
    .btn-primary{ background:#2563eb; color:#fff; }
    .btn-secondary{ background:#6b7280; color:#fff; }

    @media (max-width:660px){
      thead{ display:none; }
      table tr{ display:block; border:1px solid var(--border); border-radius:12px; padding:.6rem; margin-bottom:.6rem; }
      table td{ display:block; border-bottom:none; padding:.35rem 0; }
      table td[data-lbl]::before{ content: attr(data-lbl) ": "; font-weight:700; margin-right:4px; opacity:.85; }
    }
  </style>
</head>
<body>

  <!-- ===== Menú Unificado (igual al panel) ===== -->
  <header>
    <div class="mnu-bar">
      <button class="mnu-btn mnu-open">☰ Menú</button>
      <div class="mnu-title">Panel Cliente</div>
      <div class="mnu-spacer"></div>
      <a class="mnu-btn mnu-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
    </div>

    <!-- Tabs inline (PC) -->
    <nav class="mnu-inline">
      <a class="mnu-tab" href="panel_cliente.php">🏠 Inicio</a>
      <a class="mnu-tab" href="ver_turnos_cliente.php">📅 Ver Turnos</a>
      <a class="mnu-tab" href="ver_mis_pagos.php">💳 Mis Pagos</a>
      <a class="mnu-tab" href="pago_online.php">⚡ Pago Online</a>
      <a class="mnu-tab" href="form_progreso.php">📈 Ver Progreso</a>
      <a class="mnu-tab" href="evolucion_cliente.php">📊 Evolución</a>
      <a class="mnu-tab" href="tienda_indumentaria.php">🛍️ Indumentaria</a>
      <a class="mnu-tab" href="asistente_ia.php">🤖 Asistente IA</a>
      <a class="mnu-tab" href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
      <a class="mnu-tab" href="qr_maquinas.php">🧰 QR de Máquinas</a>
    </nav>

    <!-- Drawer (móvil) -->
    <div class="mnu-backdrop" id="mnu-backdrop"></div>
    <aside class="mnu-drawer" id="mnu-drawer">
      <div class="mnu-head">
        <button class="mnu-close" id="mnu-close">✕</button>
        <div class="mnu-title">Menú</div>
      </div>
      <ul class="mnu-list">
        <li><a class="mnu-item" href="panel_cliente.php"><span class="mnu-item__icon">🏠</span><span class="mnu-item__text">Inicio</span></a></li>
        <li><a class="mnu-item" href="ver_turnos_cliente.php"><span class="mnu-item__icon">📅</span><span class="mnu-item__text">Ver Turnos</span></a></li>
        <li><a class="mnu-item" href="ver_mis_pagos.php"><span class="mnu-item__icon">💳</span><span class="mnu-item__text">Mis Pagos</span></a></li>
        <li><a class="mnu-item" href="pago_online.php"><span class="mnu-item__icon">⚡</span><span class="mnu-item__text">Pago Online</span></a></li>
        <li><a class="mnu-item" href="form_progreso.php"><span class="mnu-item__icon">📈</span><span class="mnu-item__text">Ver Progreso</span></a></li>
        <li><a class="mnu-item" href="evolucion_cliente.php"><span class="mnu-item__icon">📊</span><span class="mnu-item__text">Evolución</span></a></li>
        <li><a class="mnu-item" href="tienda_indumentaria.php"><span class="mnu-item__icon">🛍️</span><span class="mnu-item__text">Indumentaria</span></a></li>
        <li><a class="mnu-item" href="asistente_ia.php"><span class="mnu-item__icon">🤖</span><span class="mnu-item__text">Asistente IA</span></a></li>
        <li><a class="mnu-item" href="cena_fin_anio.php"><span class="mnu-item__icon">🍽️</span><span class="mnu-item__text">Cena Fin de Año</span></a></li>
        <li><a class="mnu-item" href="qr_maquinas.php"><span class="mnu-item__icon">🧰</span><span class="mnu-item__text">QR de Máquinas</span></a></li>
        <li><a class="mnu-item" href="cliente_acceso.php?logout=1"><span class="mnu-item__icon">🚪</span><span class="mnu-item__text">Salir</span></a></li>
      </ul>
    </aside>
  </header>

  <div class="container">
    <section class="glass card">
      <h2>📅 Turnos del día: <?= h($dia_seleccionado) ?> (<?= h($fecha) ?>)</h2>

      <form method="GET" class="vtc-filter">
        <label class="muted">🗓</label>
        <input type="date" id="fecha" name="fecha" value="<?= h($fecha) ?>" onchange="this.form.submit()">
      </form>

      <?php if (!empty($_GET['err'])): ?>
        <div class="glass card" style="padding:10px;background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.35);color:#fecaca;margin-bottom:10px">
          <strong><?= h($_GET['err']) ?></strong>
        </div>
      <?php elseif (!empty($_GET['ok'])): ?>
        <div class="glass card" style="padding:10px;background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.35);color:#bbf7d0;margin-bottom:10px">
          <strong><?= h($_GET['ok']) ?></strong>
        </div>
      <?php endif; ?>

      <div class="glass card" style="padding:10px;margin-bottom:12px">
        🎫 Clases disponibles: <strong><?= (int)($membresia['clases_disponibles'] ?? 0) ?></strong>
      </div>

      <?php if (!empty($baseRows)): ?>
        <table>
          <thead>
          <tr><th>Hora</th><th>Profesor</th><th>Acción</th></tr>
          </thead>
          <tbody>
          <?php foreach ($baseRows as $t):
            $tid = (int)$t['id'];
            $reservado   = isset($reservas[$tid]);
            $reserva_id  = $reservado ? $reservas[$tid]['id'] : null;
            [$habilitado, $motivoBloqueo] = turno_habilitado_simple($t, $cerradoGlobal, $cerradosPorProfe);
          ?>
            <tr>
              <td data-lbl="Hora"><?= h(substr($t['hora_inicio'],0,5)) ?> - <?= h(substr($t['hora_fin'],0,5)) ?></td>
              <td data-lbl="Profesor"><?= h($t['apellido'].' '.$t['nombre']) ?></td>
              <td data-lbl="Acción">
                <?php if ($reservado): ?>
                  <form method="POST" action="cancelar_reserva.php" style="display:inline">
                    <?php if (!empty($reserva_id)): ?>
                      <input type="hidden" name="reserva_id" value="<?= (int)$reserva_id ?>">
                    <?php endif; ?>
                    <input type="hidden" name="turno_id" value="<?= $tid ?>">
                    <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <button type="submit" class="btn btn-secondary">Cancelar</button>
                  </form>
                <?php else: ?>
                  <?php if ($habilitado): ?>
                    <form method="POST" action="reservar_turno.php" style="display:inline">
                      <input type="hidden" name="turno_id" value="<?= $tid ?>">
                      <input type="hidden" name="fecha"    value="<?= h($fecha) ?>">
                      <input type="hidden" name="csrf"     value="<?= h($csrf) ?>">
                      <button type="submit" class="btn btn-primary" name="reservar" value="1">Reservar</button>
                    </form>
                  <?php else: ?>
                    <span title="<?= h($motivoBloqueo) ?>">⛔ No disponible</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="text-align:center;margin:0">No hay turnos disponibles para <?= h($dia_seleccionado) ?> (<?= h($fecha) ?>).</p>
      <?php endif; ?>
    </section>
  </div>

  <script>
    // ===== Menú (abrir/cerrar + bloquear scroll) =====
    (function(){
      const drawer = document.getElementById('mnu-drawer');
      const backdrop = document.getElementById('mnu-backdrop');
      const openBtn = document.querySelector('.mnu-open');
      const closeBtn = document.getElementById('mnu-close');
      const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
      function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); lock(true); }
      function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); lock(false); }
      openBtn?.addEventListener('click', open);
      closeBtn?.addEventListener('click', close);
      backdrop?.addEventListener('click', close);
      window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
    })();
  </script>
</body>
</html>
