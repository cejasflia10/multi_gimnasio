<?php
// menu_eventos.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Helpers con guardas para evitar "Cannot redeclare" */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('safe_back_url')) {
  function safe_back_url(string $default = 'panel_eventos.php'): string {
    $cand = '';
    if (!empty($_GET['return_to']))           $cand = (string)$_GET['return_to'];
    elseif (!empty($_SESSION['return_to']))   $cand = (string)$_SESSION['return_to'];
    elseif (!empty($_SERVER['HTTP_REFERER'])) $cand = (string)$_SERVER['HTTP_REFERER'];

    if ($cand === '') return $default;

    // Si es URL absoluta, permitir solo mismo host
    if (preg_match('#^https?://#i', $cand)) {
      $curHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
      $u = parse_url($cand);
      $host = strtolower((string)($u['host'] ?? ''));
      if ($host !== $curHost) return $default;
      $path = (string)($u['path'] ?? '/');
      $q    = isset($u['query']) ? ('?'.$u['query']) : '';
      return $path.$q;
    }

    // Rutas relativas seguras
    if ($cand[0] === '/') return $cand;
    if (preg_match('/^[A-Za-z0-9_\-]+\.php(?:\?.*)?$/', $cand)) return $cand;

    return $default;
  }
}
$backUrl = safe_back_url();
?>
<style>
  .menu-eventos { background:#111; padding:10px; position:relative; border-bottom:1px solid #222; }
  .menu-eventos .menu {
    display:flex; flex-wrap:wrap; justify-content:center; gap:6px;
    list-style:none; margin:0; padding:0;
  }
  .menu-eventos .menu li { position:relative; }
  .menu-eventos .menu a{
    display:block; padding:8px 12px; background:#222; color:gold; text-decoration:none;
    border-radius:6px; border:1px solid #2a2a2a; font-size:14px; white-space:nowrap; transition:background .2s;
  }
  .menu-eventos .menu a:hover{ background:#333; }

  .menu-toggle{
    display:none; background:#222; color:gold; border:1px solid #2a2a2a;
    padding:10px 12px; font-size:16px; border-radius:6px; margin:0 auto 10px; cursor:pointer;
  }

  .has-submenu > a::after{ content:" ▾"; font-weight:700; }
  .submenu{
    display:none; position:absolute; top:calc(100% + 6px); left:0; min-width:200px;
    background:#1a1a1a; border:1px solid #2a2a2a; border-radius:8px; padding:6px; z-index:999;
    box-shadow:0 6px 20px rgba(0,0,0,.35);
  }
  .submenu li{ width:100%; }
  .submenu a{ display:block; padding:8px 12px; border-radius:6px; }
  .submenu a + a{ margin-top:4px; }

  @media (hover:hover){ .has-submenu:hover > .submenu{ display:block; } }

  @media (max-width:768px){
    .menu-toggle{ display:block; }
    .menu-eventos .menu{ display:none; flex-direction:column; align-items:center; }
    .menu-eventos .menu.show{ display:flex; }
    .has-submenu{ width:100%; }
    .has-submenu > .submenu{
      position:static; display:none; width:100%; box-shadow:none; border:0; padding:4px 0 0; background:transparent;
    }
    .has-submenu.show-submenu > .submenu{ display:block; }
    .has-submenu > a{ width:100%; text-align:center; }
    .submenu a{ background:#222; width:100%; }
  }
</style>

<nav class="menu-eventos" role="navigation" aria-label="Menú de eventos" id="menu-eventos">
  <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-eventos-list">☰ Menú</button>
  <ul class="menu" id="menu-eventos-list">
    <li><a href="<?= h($backUrl) ?>">🔙 Volver</a></li>
    <li><a href="panel_eventos.php">🏠 Panel de Eventos</a></li>

    <li class="has-submenu">
      <a href="#" tabindex="0" aria-haspopup="true" aria-expanded="false">🏆 Competencias</a>
      <ul class="submenu" role="menu">
        <li><a href="agregar_competidor_evento.php">👤 Registrar Competidor</a></li>
        <li><a href="ver_competidores_evento.php">📋 Ver Competidores</a></li>
        <li><a href="organizar_pelea.php">🥊 Organizar Peleas</a></li>
        <li><a href="ver_peleas_evento.php">📊 Ver Peleas</a></li>
        <li><a href="combate_en_vivo.php">📺 En Vivo</a></li>
        <li><a href="resultados_combates.php">🥇 Resultados</a></li>
        <li><a href="ranking_competidores.php">📊 score</a></li>
        <li><a href="recibir_competidores.php">📊 Caga de competidores </a></li>

      </ul>
    </li>

    <li><a href="ver_usuarios_evento.php">👥 Usuarios Evento</a></li>

    <li class="has-submenu">
      <a href="#" tabindex="0" aria-haspopup="true" aria-expanded="false">⚖️ Panel Jueces</a>
      <ul class="submenu" role="menu">
        <li><a href="login_juez.php">👨‍⚖️ Ingreso Juez</a></li>
        <li><a href="crear_juez.php">➕ Crear Juez</a></li>
      </ul>
    </li>

    <li class="has-submenu">
      <a href="#" tabindex="0" aria-haspopup="true" aria-expanded="false">⚙️ Configuraciones</a>
      <ul class="submenu" role="menu">
        <li><a href="crear_evento.php">🗓️ Crear Evento</a></li>
        <li><a href="ver_eventos.php">📅 Ver Eventos</a></li>
        <li><a href="tipos_entradas.php">🎟️ Tipos de Entrada</a></li>
        <!-- Link para subir la imagen de marca (header) -->
        <li><a href="upload_brand_image.php">🖼️ Subir imagen de marca (header)</a></li>
        <li><a href="config_catalogos_evento.php"> ⚙️ Catálogos</a></li>
  </a>
</li>

      </ul>
    </li>

    <li><a href="logout_eventos.php">🚪 Cerrar Sesión</a></li>
  </ul>
</nav>

<script>
(function(){
  const nav = document.getElementById('menu-eventos');
  if (!nav) return;
  const toggle = nav.querySelector('.menu-toggle');
  const list   = nav.querySelector('.menu');

  if (toggle && list) {
    toggle.addEventListener('click', function () {
      const show = list.classList.toggle('show');
      toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
    });
  }
  nav.querySelectorAll('.has-submenu > a').forEach(function (trigger) {
    trigger.addEventListener('click', function (e) {
      if (window.innerWidth <= 768) {
        e.preventDefault();
        const li = this.parentElement;
        const expanded = li.classList.toggle('show-submenu');
        this.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      }
    });
    trigger.addEventListener('keydown', function (e) {
      if (window.innerWidth <= 768 && (e.key === 'Enter' || e.key === ' ')) {
        e.preventDefault();
        this.click();
      }
    });
  });
})();
</script>
