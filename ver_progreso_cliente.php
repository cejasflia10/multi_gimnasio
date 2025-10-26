<?php
// evolucion_cliente.php — Historial de progreso físico (MENÚ REUSABLE + estilo unificado)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { echo "Acceso denegado."; exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function table_exists(mysqli $cx, string $name): bool {
  $n = $cx->real_escape_string($name);
  $rs = $cx->query("SHOW TABLES LIKE '{$n}'");
  return ($rs instanceof mysqli_result) && $rs->num_rows > 0;
}

/* 
   Intentamos usar tu esquema actual:
   - progreso_fisico (p): fecha, peso, altura, observaciones, profesor_id, cliente_id, gimnasio_id
   - profesores (pr): id, nombre, apellido
*/
$rows = [];
$err  = '';

if (!table_exists($conexion, 'progreso_fisico')) {
  $err = 'No existe la tabla de progreso físico.';
} else {
  $sql = "
    SELECT p.fecha,
           p.peso,
           p.altura,
           p.observaciones,
           COALESCE(CONCAT(pr.apellido, ', ', pr.nombre), CONCAT('ID ', p.profesor_id)) AS profesor
    FROM progreso_fisico p
    LEFT JOIN profesores pr ON pr.id = p.profesor_id
    WHERE p.cliente_id = ? AND (p.gimnasio_id = ? OR p.gimnasio_id IS NULL)
    ORDER BY p.fecha DESC, p.id DESC
  ";
  if ($st = $conexion->prepare($sql)) {
    $st->bind_param('ii', $cliente_id, $gimnasio_id);
    if ($st->execute()) {
      $res = $st->get_result();
      if ($res instanceof mysqli_result) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
      }
    } else { $err = 'No se pudieron obtener los registros (ejecución).'; }
    $st->close();
  } else { $err = 'No se pudieron obtener los registros (prepare).'; }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>📊 Evolución | Mi Progreso Físico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ================== MENÚ UNIFICADO ================== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);

      /* Base panel */
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; }
    .mnu-btn--ghost{ background:transparent; color:var(--mnu-fg); border:1px solid var(--mnu-border); }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:var(--mnu-fg); text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important; }

    /* ================== BASE / GLASS ================== */
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .card{ padding:18px }
    h1{ margin:10px 0 14px; }
    .muted{ color:var(--muted) }

    /* ================== TABLA (responsive) ================== */
    table{ width:100%; border-collapse:collapse; }
    thead th{ text-align:left; padding:.7rem; border-bottom:1px solid var(--border); background:rgba(255,255,255,.03); }
    tbody td{ padding:.7rem; border-bottom:1px solid var(--border); vertical-align:top; }
    tr:hover td{ background:rgba(255,255,255,.03); }
    .obs{ color:#dbe3f0; opacity:.95; }
    .chip{ display:inline-block; padding:.2rem .5rem; border-radius:999px; border:1px solid var(--border); background:#0f1115; font-size:.85rem; }

    @media (max-width:720px){
      thead{ display:none; }
      table tr{ display:block; border:1px solid var(--border); border-radius:12px; padding:.6rem; margin-bottom:.6rem; }
      table td{ display:block; border-bottom:none; padding:.35rem 0; }
      table td[data-lbl]::before{ content: attr(data-lbl) ": "; font-weight:700; opacity:.85; margin-right:4px; }
    }

    .alert{ padding:10px 12px; border-radius:12px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); color:#fecaca; }
    .headline{ display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
  </style>
</head>
<body>

  <?php
    // ===== Menú REUSABLE (activa pestaña Evolución) =====
    require_once __DIR__ . '/menu_cliente.php';
    render_menu_cliente('evolucion_cliente');
  ?>

  <div class="container">
    <section class="glass card">
      <div class="headline">
        <h1>📊 Mi Progreso Físico</h1>
        <a class="chip" href="registrar_progreso.php" title="Cargar un nuevo registro">+ Nuevo registro</a>
      </div>

      <?php if ($err !== ''): ?>
        <div class="alert" style="margin-bottom:10px"><?= h($err) ?></div>
      <?php endif; ?>

      <?php if (!empty($rows)): ?>
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Peso</th>
              <th>Altura</th>
              <th>Profesor</th>
              <th>Observaciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $p): ?>
              <tr>
                <td data-lbl="Fecha"><?= h($p['fecha'] ?? '—') ?></td>
                <td data-lbl="Peso"><?= h(number_format((float)($p['peso'] ?? 0), 1, ',', '.')) ?> kg</td>
                <td data-lbl="Altura"><?= h(number_format((float)($p['altura'] ?? 0), 0, ',', '.')) ?> cm</td>
                <td data-lbl="Profesor"><?= h($p['profesor'] ?? '—') ?></td>
                <td data-lbl="Observaciones" class="obs"><?= nl2br(h((string)($p['observaciones'] ?? ''))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php elseif ($err === ''): ?>
        <p class="muted" style="text-align:center;margin:0">Todavía no hay registros de progreso físico.</p>
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
