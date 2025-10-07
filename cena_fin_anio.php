<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0) { header('Location: login.php'); exit; }

/* CSRF */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf_token'];

/* Traer evento activo (el más próximo) */
$stmt = $conexion->prepare("
  SELECT id, titulo, fecha, hora, lugar, precio_cubierto, sena_minima, cupo_total, cupo_reservado
  FROM cenas_eventos
  WHERE gimnasio_id = ? AND estado = 'activo'
  ORDER BY fecha ASC, hora ASC
  LIMIT 1
");
$stmt->bind_param('i', $gimnasio_id);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

$cupo_disponible = $evento ? max(0, (int)$evento['cupo_total'] - (int)$evento['cupo_reservado']) : 0;
$precio_cubierto = $evento ? (float)$evento['precio_cubierto'] : 0.0;
$sena_minima     = $evento ? (float)$evento['sena_minima']     : 0.0;

/* Slots 30' 10:00–24:00 */
function build_slots(): array {
  $out=[]; $t=strtotime('10:00'); $end=strtotime('24:00');
  while($t<$end){ $out[]=date('H:i',$t); $t+=30*60; }
  return $out;
}
$slots = build_slots();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cena de Fin de Año</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="estilo_unificado.css">

  <style>
    /* =========================================================
       OVERRIDES DUROS PARA EL MENÚ (barra + drawer + dropdown)
       — Fuerzan fondo oscuro/vidriado y texto blanco.
       — Cubren nombres de clases típicos y genéricos.
       ========================================================= */

    /* Barra superior */
    header, .mc-top, .navbar, nav.site-nav, .topbar {
      background: rgba(15,19,32,.78) !important;
      -webkit-backdrop-filter: blur(10px) saturate(1.1);
      backdrop-filter: blur(10px) saturate(1.1);
      border-bottom: 1px solid rgba(255,255,255,.14) !important;
    }
    header *, .mc-top *, .navbar *, nav.site-nav *, .topbar * {
      color:#fff !important;
      -webkit-text-fill-color:#fff !important;
      text-shadow:none !important;
      background: transparent !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
    }

    /* Contenedores de menú desplegable / lateral / offcanvas */
    .mc-drawer, .mc-menu, .mc-dropdown, .dropdown-menu, .offcanvas, .offcanvas-body,
    aside.menu, nav .menu, [role="menu"], .sidebar, .drawer, .drawer-content {
      background: rgba(10,12,20,.92) !important;
      -webkit-backdrop-filter: blur(12px) saturate(1.1);
      backdrop-filter: blur(12px) saturate(1.1);
      border: 1px solid rgba(255,255,255,.18) !important;
      box-shadow: 0 10px 30px rgba(0,0,0,.4) !important;
    }

    /* Items del menú (links, botones, li) */
    .mc-item, .mc-link, .mc-btn, .dropdown-item, .menu a, .menu li, .drawer a, .sidebar a,
    .mc-item *, .dropdown-item *, .menu a *, .drawer a *, .sidebar a * {
      color:#fff !important;
      -webkit-text-fill-color:#fff !important;
      background: transparent !important;
      border-color: rgba(255,255,255,.18) !important;
    }
    .mc-item, .dropdown-item, .menu a, .drawer a, .sidebar a {
      border:1px solid rgba(255,255,255,.18) !important;
      border-radius:14px !important;
    }
    .mc-item:hover, .dropdown-item:hover, .menu a:hover, .drawer a:hover, .sidebar a:hover {
      background: rgba(255,255,255,.10) !important;
      border-color: rgba(255,255,255,.28) !important;
      color:#fff !important;
    }
    /* Iconos del menú */
    .mc-item .icon, .mc-item svg, .dropdown-item svg, .menu svg, .drawer svg, .sidebar svg {
      color:#fff !important; fill:#fff !important; stroke:#fff !important;
    }

    /* Botón flotante de abrir/cerrar menú (si existe) */
    .mc-bar .mc-btn, .menu-toggle, .nav-toggle {
      background:#ffd600 !important; color:#000 !important; border:none !important;
    }

    /* ===== Estilos mínimos propios de esta pantalla ===== */
    .contenedor{max-width:980px;margin:24px auto;padding:16px}
    .glass.card{background:#141a2a;border:1px solid #222a40;border-radius:14px;padding:20px;box-shadow:0 6px 26px rgba(0,0,0,.25)}
    .muted{color:#c9d1e1}
    .mini{font-size:12px}
    label{display:block;margin:.25rem 0 .35rem;font-weight:600}
    .input{width:100%;padding:10px;border-radius:10px;border:1px solid #2a3550;background:#0d1322;color:#fff}
    .btn{display:inline-block;padding:12px 16px;border-radius:12px;border:0;cursor:pointer;font-weight:700}
    .btn.primary{background:#3b82f6;color:#fff}
    .btn.outline{background:transparent;border:1px solid #3b82f6;color:#3b82f6}
    .chips{display:flex;gap:8px;flex-wrap:wrap;margin:.35rem 0}
    .chip{padding:6px 10px;border:1px solid #3b82f6;border-radius:999px;cursor:pointer;font-size:12px}
    .grid2{display:grid;gap:16px}
    @media (min-width:860px){ .grid2{grid-template-columns:1fr 1fr} }
    select[multiple]{min-height:220px}
    .note{background:#0b1220;border-left:4px solid #3b82f6;padding:10px 12px;border-radius:10px}
    .tot{margin-top:10px;padding:10px;border-radius:10px;background:#0b1220;border:1px solid rgba(255,255,255,.08)}
  </style>
</head>
<body>

<?php include __DIR__.'/menu_cliente.php'; ?>

<div class="contenedor">
  <h2>🍽️ Cena de Fin de Año</h2>

  <?php if (!$evento): ?>
    <div class="glass card">
      <p class="muted">Aún no hay un evento de cena activo configurado para tu gimnasio.</p>
      <p class="muted">Pedile al administrador que lo cargue en <code>cenas_eventos</code>.</p>
    </div>
  <?php else: ?>

    <div class="grid2">
      <div class="glass card">
        <h3 style="margin:0 0 6px"><?= htmlspecialchars($evento['titulo']) ?></h3>
        <p class="muted" style="margin:0 0 10px">
          📅 <?= date('d/m/Y', strtotime($evento['fecha'])) ?> · ⏰ <?= substr($evento['hora'],0,5) ?><br>
          📍 <?= htmlspecialchars($evento['lugar']) ?>
        </p>

        <div class="note" style="margin-bottom:12px">
          <strong>Precio por cubierto:</strong> $<?= number_format($precio_cubierto,2,',','.') ?><br>
          <strong>Seña mínima (por cubierto):</strong> $<?= number_format($sena_minima,2,',','.') ?><br>
          <strong>Cupo disponible:</strong> <?= $cupo_disponible ?><br>
          <small class="mini">
            🎉 Promo: <strong>3–4</strong> cubiertos → <strong>10% OFF</strong> ·
            🎉 <strong>5+</strong> cubiertos → <strong>15% OFF</strong>
          </small>
        </div>

        <?php if ($cupo_disponible<=0): ?>
          <p style="color:#fca5a5"><strong>Sin cupos disponibles por el momento.</strong></p>
        <?php else: ?>
          <form action="guardar_cena_reserva.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="evento_id" value="<?= (int)$evento['id'] ?>">
            <input type="hidden" id="precio_unit" value="<?= $precio_cubierto ?>">
            <input type="hidden" id="sena_unit" value="<?= $sena_minima ?>">

            <label for="cantidad">Cantidad de cubiertos</label>
            <input class="input" type="number" id="cantidad" name="cantidad" min="1" max="<?= $cupo_disponible ?>" value="1" required>

            <label>Seleccioná horarios (podés elegir varios)</label>
            <div class="chips">
              <span class="chip" data-range="12:00-15:00">Almuerzo rápido</span>
              <span class="chip" data-range="16:00-18:00">Merienda</span>
              <span class="chip" data-range="20:00-23:00">Cena típica</span>
              <span class="chip" data-range="10:00-24:00">Todo el día</span>
              <span class="chip" data-clear="1">Limpiar</span>
            </div>
            <select class="input" id="horarios" name="horarios[]" multiple required>
              <?php foreach ($slots as $h): ?>
                <option value="<?= $h ?>"><?= $h ?></option>
              <?php endforeach; ?>
            </select>
            <small class="mini muted">Tip: mantené Ctrl/⌘ para seleccionar varios horarios.</small>

            <label for="nombres">Nombres de acompañantes (opcional)</label>
            <textarea class="input" id="nombres" name="nombres" rows="3" placeholder="Ej: Juan Pérez, Ana López"></textarea>

            <label for="comentario">Comentario (opcional)</label>
            <input class="input" id="comentario" name="comentario" maxlength="200" placeholder="Preferencias, alergias, etc.">

            <label for="pago">Forma de pago</label>
            <select class="input" id="pago" name="pago" required>
              <option value="sena_efectivo">Seña ahora (Efectivo)</option>
              <option value="total_efectivo">Pagar total (Efectivo)</option>
              <option value="sena_transferencia">Seña ahora (Transferencia)</option>
              <option value="total_transferencia">Pagar total (Transferencia)</option>
              <option value="pendiente">Reservar y pagar después</option>
            </select>

            <div id="wrap_comprobante" style="display:none">
              <label for="comprobante">Comprobante (imagen o PDF, máx 6MB)</label>
              <input class="input" type="file" id="comprobante" name="comprobante" accept="image/*,application/pdf">
              <small class="mini muted">Subí el recibo/transferencia. Se guardará en la nube.</small>
            </div>

            <div class="tot" id="tot_view">
              <div>Subtotal: <strong id="subtot">$0,00</strong></div>
              <div id="desc_line" style="display:none">Descuento: <strong id="desc">$0,00</strong></div>
              <div>Total: <strong id="total">$0,00</strong></div>
              <div>Seña mínima estimada: <strong id="sena_estimada">$0,00</strong></div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
              <button class="btn primary" type="submit">Reservar</button>
              <a class="btn outline" href="mis_reservas_cena.php">Ver mis reservas</a>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="glass card">
        <p class="muted">
          Tu reserva queda registrada al instante. Si elegís pagar seña o total, el estado figurará como
          <em>“seña”</em> o <em>“pagado”</em>. Con “reservar y pagar después”, queda <em>“pendiente”</em>.
          Si pagás por <strong>transferencia</strong>, podés adjuntar el <strong>comprobante</strong> acá mismo.
        </p>
        <p class="mini muted">
          * Descuentos automáticos: 3–4 cubiertos 10% OFF; 5+ cubiertos 15% OFF.<br>
          * Podés elegir varios horarios entre 10:00 y 24:00.
        </p>
      </div>
    </div>

  <?php endif; ?>

</div>

<script>
(function(){
  const $ = (id)=>document.getElementById(id);
  const precio = parseFloat(($('precio_unit')?.value)||'0');
  const sena   = parseFloat(($('sena_unit')?.value)||'0');
  const fmt = (n)=> n.toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});

  if ($('cantidad')) {
    function recalc(){
      const q = Math.max(1, parseInt(($('cantidad').value||'1'),10));
      let subtotal = precio * q;
      let desc = 0;
      if (q >= 5) { desc = subtotal * 0.15; }
      else if (q >= 3) { desc = subtotal * 0.10; }
      const total = subtotal - desc;
      $('subtot').textContent = '$'+fmt(subtotal);
      $('desc_line').style.display = desc>0 ? '' : 'none';
      $('desc').textContent = '$'+fmt(desc);
      $('total').textContent = '$'+fmt(total);
      $('sena_estimada').textContent = '$'+fmt(sena*q);
    }
    function toggleComprobante(){
      const v = $('pago').value;
      $('wrap_comprobante').style.display = (v==='sena_transferencia' || v==='total_transferencia') ? '' : 'none';
    }

    /* Chips de rango rápido */
    function parseTime(s){ const [H,M]=s.split(':').map(x=>parseInt(x,10)); return H*60+M; }
    function selectRange(r){
      const [a,b]=r.split('-'); const amin=parseTime(a), bmin=parseTime(b);
      const sel=$('horarios');
      for(let i=0;i<sel.options.length;i++){
        const v=sel.options[i].value; const m=parseTime(v);
        sel.options[i].selected = (m>=amin && m<=bmin);
      }
      sel.dispatchEvent(new Event('change'));
    }
    document.querySelectorAll('.chip').forEach(ch=>{
      ch.addEventListener('click', ()=>{
        if (ch.dataset.clear){
          const sel=$('horarios'); for(let i=0;i<sel.options.length;i++) sel.options[i].selected=false;
          return;
        }
        if (ch.dataset.range) selectRange(ch.dataset.range);
      });
    });

    $('cantidad').addEventListener('input', recalc);
    $('pago').addEventListener('change', toggleComprobante);
    recalc(); toggleComprobante();
  }
})();
</script>
</body>
</html>
