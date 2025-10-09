<?php
// menu_horizontal.php (responsive: desktop horizontal / mobile drawer)
// Usa $rol si querés ocultar/mostrar items por rol.
if (session_status() === PHP_SESSION_NONE) session_start();
$rol = $_SESSION['rol'] ?? '';

// Ruta actual para "activo"
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$basename = strtolower(trim(basename($uri), '/'));

function item($href, $label, $icon, $active) {
  $isActive = $active ? ' data-active="1"' : '';
  return [
    'href' => $href,
    'label' => $label,
    'icon' => $icon,
    'active' => $active,
    'li' => "<li><a href=\"{$href}\" class=\"nav-link".($active?' active':'')."\"{$isActive} aria-current=\"".($active?'page':'false')."\">{$icon}<span>{$label}</span></a></li>"
  ];
}

// Define tu menú (agregá/quitá lo que necesites)
$items = [];
$items[] = item('index.php',        'Panel',        '🏠', in_array($basename, ['', 'index.php', 'panel.php']));
$items[] = item('clientes.php',     'Clientes',     '🧑‍🤝‍🧑', $basename === 'clientes.php');
$items[] = item('membresias.php',   'Membresías',   '💳', $basename === 'membresias.php');
$items[] = item('reservas.php',     'Reservas',     '📅', $basename === 'reservas.php');
$items[] = item('disciplinas.php',  'Disciplinas',  '🥋', $basename === 'disciplinas.php');
$items[] = item('reportes.php',     'Reportes',     '📊', $basename === 'reportes.php');

// Ejemplo por rol (admin)
if ($rol === 'admin') {
  $items[] = item('config.php',     'Config',       '⚙️', $basename === 'config.php');
}

$items[] = item('salir.php',        'Salir',        '🚪', $basename === 'salir.php');

?>
<style>
  /* ====== Reset mínimo del componente (scoped por #main-nav) ====== */
  #main-nav, #main-nav * { box-sizing: border-box; }
  #main-nav { --bg:#0b0f16; --ink:#eaf1ff; --mut:#8fa2ba; --stroke:rgba(255,255,255,.12);
              --brand:#ffd166; --hover:rgba(255,255,255,.06); --shadow:0 8px 24px rgba(0,0,0,.25);
              --h:56px; --z: 9999; font-family: system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif; }

  /* ====== Desktop navbar (visible ≥ 992px) ====== */
  #main-nav .deskbar {
    position: sticky; top: 0; z-index: var(--z);
    display: none;
    background: linear-gradient(180deg, #101827f2, #0b1220f2);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--stroke);
    box-shadow: var(--shadow);
    height: var(--h);
  }
  #main-nav .desk-inner {
    max-width: 1200px; margin: 0 auto; height: 100%;
    display: grid; grid-template-columns: 220px 1fr 220px; align-items: center; gap: 12px; padding: 0 12px;
  }
  #main-nav .brand {
    display:flex; align-items:center; gap:10px; color:var(--ink); font-weight: 700;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  #main-nav .brand .dot { width:8px; height:8px; border-radius:999px; background: var(--brand); box-shadow:0 0 10px rgba(255,209,102,.8); }
  #main-nav .desk-links { display:flex; gap:6px; align-items:center; justify-content:center; overflow:auto hidden; scrollbar-width:none; }
  #main-nav .desk-links::-webkit-scrollbar{ display:none; }
  #main-nav .desk-links li{ list-style:none; }
  #main-nav .nav-link {
    display:flex; align-items:center; gap:8px; color:var(--ink); text-decoration:none; padding:8px 12px; border-radius:10px;
    border:1px solid transparent;
  }
  #main-nav .nav-link:hover { background: var(--hover); border-color: var(--stroke); }
  #main-nav .nav-link.active { color:#111; background: linear-gradient(180deg,#ffd166,#ffb703); border-color: transparent; }
  #main-nav .nav-link span{ font-size:.95rem; }
  #main-nav .nav-link:focus-visible{ outline:2px solid #ffb703; outline-offset:2px; }

  #main-nav .rightbox{ display:flex; justify-content:flex-end; gap:10px; align-items:center; }
  #main-nav .chip{
    padding:6px 10px; border:1px solid var(--stroke); border-radius:999px; color:var(--ink);
    background: linear-gradient(180deg, #16243a, #0b1220);
    font-size:.85rem;
  }

  /* ====== Mobile topbar + drawer (visible < 992px) ====== */
  #main-nav .mobar {
    position: sticky; top: 0; z-index: var(--z);
    display: flex; align-items:center; justify-content:space-between;
    height: var(--h);
    background: linear-gradient(180deg, #101827f2, #0b1220f2);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--stroke);
    padding: 0 10px;
  }
  #main-nav .mo-title { color:var(--ink); font-weight:800; letter-spacing:.3px; display:flex; align-items:center; gap:8px; }
  #main-nav .mo-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:40px; height:40px; border-radius:10px; border:1px solid var(--stroke);
    background: linear-gradient(180deg, #142033, #0b1220); color:var(--ink);
  }
  #main-nav .mo-btn:active { transform: scale(.98); }

  /* Drawer */
  #main-nav .drawer {
    position: fixed; inset: 0 0 0 auto; width: 86vw; max-width: 360px; background:#0d1423; border-left:1px solid var(--stroke);
    transform: translateX(100%); transition: transform .25s ease; z-index: calc(var(--z) + 1);
    box-shadow: -24px 0 40px rgba(0,0,0,.35);
    display:flex; flex-direction:column; height:100dvh;
  }
  #main-nav .drawer.open { transform: translateX(0); }
  #main-nav .scrim {
    position: fixed; inset: 0; background: rgba(0,0,0,.45); backdrop-filter: blur(2px);
    opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: var(--z);
  }
  #main-nav .scrim.show { opacity: 1; pointer-events: auto; }

  #main-nav .drawer-head{
    display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px; border-bottom:1px solid var(--stroke);
    color:var(--ink);
  }
  #main-nav .drawer-list{ padding:10px; overflow:auto; }
  #main-nav .drawer-list ul{ margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
  #main-nav .drawer-list li{ list-style:none; }
  #main-nav .drawer-list .nav-link{
    display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; text-decoration:none; color:var(--ink);
    border:1px solid transparent;
  }
  #main-nav .drawer-list .nav-link:hover{ background: var(--hover); border-color: var(--stroke); }
  #main-nav .drawer-list .nav-link.active{ color:#111; background: linear-gradient(180deg,#ffd166,#ffb703); }

  /* ====== Visibilidad por breakpoint ====== */
  @media (min-width: 992px){
    #main-nav .deskbar{ display:block; }
    #main-nav .mobar, #main-nav .drawer, #main-nav .scrim { display:none !important; }
  }
  @media (max-width: 991.98px){
    #main-nav .deskbar{ display:none !important; }
    #main-nav .mobar{ display:flex; }
  }
</style>

<nav id="main-nav" aria-label="Principal">
  <!-- ===== Mobile Topbar ===== -->
  <div class="mobar">
    <button class="mo-btn" type="button" aria-label="Abrir menú" id="btn-open">☰</button>
    <div class="mo-title">🏋️ Panel</div>
    <a class="mo-btn" href="index.php" aria-label="Ir al inicio">🏠</a>
  </div>

  <!-- Drawer + Scrim -->
  <div class="scrim" id="nav-scrim" hidden></div>
  <aside class="drawer" id="nav-drawer" aria-hidden="true" aria-label="Menú móvil">
    <div class="drawer-head">
      <strong>Menú</strong>
      <button class="mo-btn" type="button" aria-label="Cerrar menú" id="btn-close">✕</button>
    </div>
    <div class="drawer-list">
      <ul>
        <?php foreach ($items as $it) echo $it['li']; ?>
      </ul>
    </div>
  </aside>

  <!-- ===== Desktop Navbar ===== -->
  <div class="deskbar">
    <div class="desk-inner">
      <div class="brand">
        <span class="dot" aria-hidden="true"></span>
        <span>Panel General</span>
      </div>
      <ul class="desk-links" role="menubar" aria-label="Navegación">
        <?php foreach ($items as $it) echo $it['li']; ?>
      </ul>
      <div class="rightbox">
        <span class="chip"><?= htmlspecialchars($_SESSION['usuario'] ?? 'Usuario') ?></span>
      </div>
    </div>
  </div>
</nav>

<script>
(function(){
  const drawer = document.getElementById('nav-drawer');
  const scrim  = document.getElementById('nav-scrim');
  const openB  = document.getElementById('btn-open');
  const closeB = document.getElementById('btn-close');

  function open(){
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden','false');
    scrim.hidden = false;
    void scrim.offsetWidth; // reflow
    scrim.classList.add('show');
    // focus al primer link
    const first = drawer.querySelector('a.nav-link');
    if(first) first.focus({preventScroll:true});
    // bloquear scroll fondo
    document.documentElement.style.overflow = 'hidden';
  }
  function close(){
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden','true');
    scrim.classList.remove('show');
    setTimeout(()=>{ scrim.hidden = true; }, 200);
    document.documentElement.style.overflow = '';
    openB && openB.focus({preventScroll:true});
  }

  openB && openB.addEventListener('click', open);
  closeB && closeB.addEventListener('click', close);
  scrim  && scrim.addEventListener('click', close);
  document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape' && drawer.classList.contains('open')) close(); });

  // Mejora accesible: marca dinámica aria-current
  document.querySelectorAll('#main-nav a.nav-link').forEach(a=>{
    if (a.classList.contains('active')) a.setAttribute('aria-current','page');
  });
})();
</script>
