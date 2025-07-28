<!-- menu_eventos.php -->
<style>
  .menu-eventos {
    background-color: #111;
    padding: 10px;
    position: relative;
  }

  .menu-eventos .menu {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .menu-eventos .menu li {
    position: relative;
    margin: 5px;
  }

  .menu-eventos .menu a {
    display: block;
    padding: 8px 12px;
    background-color: #222;
    color: gold;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s;
    white-space: nowrap;
    font-size: 14px;
  }

  .menu-eventos .menu a:hover {
    background-color: #333;
  }

  .menu-toggle {
    display: none;
    background: #222;
    color: gold;
    border: none;
    padding: 10px;
    font-size: 16px;
    border-radius: 6px;
    margin-bottom: 10px;
    cursor: pointer;
  }

  .submenu {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background-color: #222;
    border-radius: 6px;
    padding: 0;
    min-width: 180px;
    z-index: 999;
  }

  .has-submenu:hover .submenu {
    display: block;
  }

  .submenu li {
    width: 100%;
  }

  .submenu a {
    padding-left: 20px;
    border-bottom: 1px solid #333;
  }

  /* Móvil */
  @media screen and (max-width: 768px) {
    .menu-toggle {
      display: block;
      margin: auto;
    }

    .menu-eventos .menu {
      display: none;
      flex-direction: column;
      align-items: center;
    }

    .menu-eventos .menu.show {
      display: flex;
    }

    .has-submenu > .submenu {
      position: static;
      display: none;
    }

    .has-submenu.show-submenu > .submenu {
      display: block;
    }
  }
</style>

<nav class="menu-eventos">
  <button class="menu-toggle">☰ Menú</button>
  <ul class="menu">
    <li><a href="panel_eventos.php">🏠 Panel de Eventos</a></li>

    <li class="has-submenu">
      <a href="#">🏆 Competencias ▾</a>
      <ul class="submenu">
        <li><a href="agregar_competidor_evento.php">👤 Registrar Competidor</a></li>
        <li><a href="ver_competidores_evento.php">📋 Ver Competidores</a></li>
        <li><a href="organizar_pelea.php">🥊 Organizar Peleas</a></li>
        <li><a href="ver_peleas_evento.php">📊 Ver Peleas</a></li>
        <li><a href="combate_en_vivo.php">📺 En Vivo</a></li>
        <li><a href="resultados_combates.php">🥇 Resultados</a></li>
      </ul>
    </li>

     <!-- 🔒 Solo para fightacademy y lucianoc -->
    <?php if (isset($_SESSION['usuario']) && 
        ($_SESSION['usuario'] === 'fightacademy' || $_SESSION['usuario'] === 'lucianoc')): ?>
        <li><a href="ver_usuarios_evento.php">👥 Usuarios Evento</a></li>
    <?php endif; ?>
    <li class="has-submenu">
      <a href="#">⚖️ Panel Jueces ▾</a>
      <ul class="submenu">
        <li><a href="login_juez.php">👨‍⚖️ Ingreso Juez</a></li>
        <li><a href="crear_juez.php">➕ Crear Juez</a></li>
      </ul>
    </li>

    <li class="has-submenu">
      <a href="#">⚙️ Configuraciones ▾</a>
      <ul class="submenu">
        <li><a href="crear_evento.php">🗓️ Crear Evento</a></li>
        <li><a href="ver_eventos.php">📅 Ver Eventos</a></li>
        <li><a href="tipos_entradas.php">🎟️ Tipos de Entrada</a></li>
      </ul>
    </li>

    <li><a href="panel_general.php">🔙 Volver</a></li>
    <li><a href="logout_eventos.php">🚪 Cerrar Sesión</a></li>
  </ul>
</nav>

<script>
  document.querySelector('.menu-toggle').addEventListener('click', function () {
    document.querySelector('.menu').classList.toggle('show');
  });

  document.querySelectorAll('.has-submenu > a').forEach(function (element) {
    element.addEventListener('click', function (e) {
      if (window.innerWidth <= 768) {
        e.preventDefault();
        this.parentElement.classList.toggle('show-submenu');
      }
    });
  });
</script>
