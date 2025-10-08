<?php
// ver_mis_pagos.php — Listado de pagos con MENÚ UNIFICADO (idéntico a panel/ver_turnos)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0 || $gimnasio_id <= 0) { header('Location: cliente_acceso.php'); exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
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

/* Traer pagos (usa gimnasio_id si existe en membresias) */
$pagos = [];
$err   = '';
if (table_exists($conexion, 'membresias')) {
  $hasGym = column_exists($conexion, 'membresias', 'gimnasio_id');
  $sql = "SELECT fecha_inicio, fecha_vencimiento, total, metodo_pago
          FROM membresias
          WHERE cliente_id = ? " . ($hasGym ? "AND gimnasio_id = ? " : "") . "
          ORDER BY fecha_inicio DESC";
  if ($st = $conexion->prepare($sql)) {
    if ($hasGym) { $st->bind_param('ii', $cliente_id, $gimnasio_id); }
    else         { $st->bind_param('i',  $cliente_id); }
    if ($st->execute()) {
      $rs = $st->get_result();
      if ($rs instanceof mysqli_result) while($r = $rs->fetch_assoc()) $pagos[] = $r;
    } else { $err = 'No se pudieron obtener los pagos (ejecución).'; }
    $st->close();
  } else { $err = 'No se pudieron obtener los pagos (prepare).'; }
} else { $err = 'No existe la tabla de membresías.'; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>💳 Mis Pagos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ================== MENÚ UNIFICADO (igual al panel) ================== */
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

    /* Garantía de legibilidad (por si hay text-fill heredado) */
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{
      color:#fff !important; -webkit-text-fill-color:#fff !important;
      text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important;
    }

    /* ================== ESTILOS BASE (idénticos al panel) ================== */
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

    /* ================== TABLA PAGOS (responsive) ================== */
    table{ width:100%; border-collapse:collapse; }
    thead th{ text-align:left; padding:.7rem; border-bottom:1px solid var(--border); background:rgba(255,255,255,.03); }
    tbody td{ padding:.7rem; border-bottom:1px solid var(--border); }
    tr:hover td{ background:rgba(255,255,255,.03); }
    .chip{ display:inline-block; padding:.2rem .5rem; border-radius:999px; border:1px solid var(--border); background:#0f1115; font-size:.85rem; }

    @media (max-width:660px){
      thead{ display:none; }
      table tr{ display:block; border:1px solid var(--border); border-radius:12px; padding:.6rem; margin-bottom:.6rem; }
      table td{ display:block; border-bottom:none; padding:.35rem 0; }
      table td[data-lbl]::before{ content: attr(data-lbl) ": "; font-weight:700; opacity:.85; margin-right:4px; }
    }

    .alert{ padding:10px 12px; border-radius:12px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); color:#fecaca; }
  </style>
</head>
<body>

  <!-- ===== Menú Unificado ===== -->
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
      <h2>💳 Mis Pagos</h2>

      <?php if ($err !== ''): ?>
        <div class="alert"><?= h($err) ?></div>
      <?php endif; ?>

      <?php if (!empty($pagos)): ?>
        <table>
          <thead>
            <tr>
              <th>Fecha Inicio</th>
              <th>Fecha Vencimiento</th>
              <th>Total</th>
              <th>Método de Pago</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pagos as $fila): ?>
              <tr>
                <td data-lbl="Inicio"><?= h($fila['fecha_inicio'] ?? '—') ?></td>
                <td data-lbl="Vencimiento"><?= h($fila['fecha_vencimiento'] ?? '—') ?></td>
                <td data-lbl="Total">$<?= number_format((float)($fila['total'] ?? 0), 2, ',', '.') ?></td>
                <td data-lbl="Método"><span class="chip"><?= h($fila['metodo_pago'] ?? '—') ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif ($err === ''): ?>
        <p class="muted" style="text-align:center;margin:0">No hay pagos registrados.</p>
      <?php endif; ?>
    </section>
  </div>

  <script>
  // ===== Menú (abrir/cerrar + bloquear scroll) =====
  (function(){
    const drawer   = document.getElementById('mnu-drawer');
    const backdrop = document.getElementById('mnu-backdrop');
    const openBtn  = document.querySelector('.mnu-open');
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
