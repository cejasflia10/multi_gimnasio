<?php
// menu_escuelas.php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Helper simple */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/*
  Convención sugerida para el login de escuelas:
  $_SESSION['escuela_id']      = ID de la escuela logueada
  $_SESSION['escuela_nombre']  = Nombre de la escuela
  (Si todavía no lo usás así, no rompe nada: simplemente no mostrará las partes "logueado")
*/
$escuelaId     = !empty($_SESSION['escuela_id'])     ? (int)$_SESSION['escuela_id'] : null;
$escuelaNombre = !empty($_SESSION['escuela_nombre']) ? (string)$_SESSION['escuela_nombre'] : null;
?>
<style>
  .menu-escuelas {
    background:#020617;
    color:#e5e7eb;
    border-bottom:1px solid #111827;
    position:relative;
    z-index:50;
  }
  .menu-escuelas-inner{
    max-width:1100px;
    margin:0 auto;
    padding:8px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }

  .me-brand{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:700;
    font-size:15px;
    color:#facc15;
  }
  .me-brand span.small{
    display:block;
    font-size:11px;
    font-weight:500;
    color:#9ca3af;
  }

  .me-nav{
    display:flex;
    align-items:center;
    gap:6px;
  }

  .me-nav a{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:7px 11px;
    border-radius:999px;
    border:1px solid #1f2937;
    background:#020617;
    color:#e5e7eb;
    text-decoration:none;
    font-size:13px;
    white-space:nowrap;
    transition:background .2s, transform .1s, border-color .2s;
  }
  .me-nav a:hover{
    background:#0f172a;
    border-color:#374151;
    transform:translateY(-1px);
  }

  .me-badge-escuela{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:4px 10px;
    border-radius:999px;
    background:rgba(34,197,94,.12);
    color:#bbf7d0;
    font-size:11px;
    border:1px solid rgba(34,197,94,.4);
  }

  .me-toggle{
    display:none;
    background:#020617;
    border:1px solid #1f2937;
    color:#e5e7eb;
    border-radius:999px;
    padding:6px 10px;
    font-size:14px;
    cursor:pointer;
  }

  @media (max-width:768px){
    .menu-escuelas-inner{
      flex-wrap:wrap;
      align-items:flex-start;
    }
    .me-toggle{
      display:inline-flex;
      align-items:center;
      gap:6px;
      margin-left:auto;
    }
    .me-nav{
      display:none;
      flex-direction:column;
      align-items:flex-start;
      width:100%;
      margin-top:6px;
    }
    .me-nav.show{
      display:flex;
    }
    .me-nav a{
      width:100%;
      justify-content:flex-start;
    }
  }
</style>

<nav class="menu-escuelas" role="navigation" aria-label="Menú escuelas / ranking">
  <div class="menu-escuelas-inner">
    <div class="me-brand">
      🥊 Ranking Oficial
      <span class="small">Escuelas &amp; Competidores</span>
    </div>

    <button class="me-toggle" type="button" aria-expanded="false" aria-controls="me-nav">
      ☰ Menú
    </button>

    <div class="me-nav" id="me-nav">
      <!-- Siempre visibles (públicos) -->
      <a href="ranking_competidores.php">🏆 Ranking</a>
      <a href="ver_competidor_publico.php">🔍 Buscar competidor</a>

      <?php if (!$escuelaId): ?>
        <!-- Opciones cuando NO hay escuela logueada -->
        <a href="escuela_login.php">🔐 Ingresar escuela</a>
        <a href="escuela_registro.php">🏫 Registrar escuela</a>
      <?php else: ?>
        <!-- Opciones cuando SÍ hay escuela logueada -->
        <span class="me-badge-escuela">
          🏫 <?= h($escuelaNombre ?: 'Mi escuela') ?>
        </span>
        <a href="agregar_pelea_externa.php">➕ Registrar pelea de mi escuela</a>
        <!-- Si luego creás un panel, podés usar: panel_escuela.php -->
        <!-- <a href="panel_escuela.php">📂 Panel de mi escuela</a> -->
        <a href="escuela_logout.php">🚪 Cerrar sesión</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
(function(){
  const nav   = document.getElementById('me-nav');
  const btn   = document.querySelector('.me-toggle');
  if (!nav || !btn) return;

  btn.addEventListener('click', function(){
    const show = nav.classList.toggle('show');
    btn.setAttribute('aria-expanded', show ? 'true' : 'false');
  });
})();
</script>
