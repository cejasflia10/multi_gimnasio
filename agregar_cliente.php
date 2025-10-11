<?php
// form_agregar_cliente.php
session_start();
require_once 'conexion.php';
require_once 'phpqrcode/qrlib.php';
include 'menu_horizontal.php';

$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : null;
$rol = $_SESSION['rol'] ?? null;

if (!$gimnasio_id && $rol !== 'admin') {
    die("<div style='padding:16px; color:#ef4444'>Acceso denegado.</div>");
}

/**
 * Determina el gimnasio seleccionado para filtrar disciplinas:
 * - Si es admin y viene ?gimnasio_id se usa ese (permite preview en admin).
 * - Si no admin se usa el gimnasio de sesión.
 */
$sel_gimnasio = null;
if ($rol === 'admin' && isset($_GET['gimnasio_id']) && intval($_GET['gimnasio_id']) > 0) {
    $sel_gimnasio = (int)$_GET['gimnasio_id'];
} elseif ($rol !== 'admin') {
    $sel_gimnasio = $gimnasio_id;
}

/* Obtener disciplinas (si $sel_gimnasio se cargan solo de ese gimnasio) */
$disciplinas = [];
if ($sel_gimnasio) {
    $sql = "SELECT id, nombre FROM disciplinas WHERE gimnasio_id = ? AND activo = 1 ORDER BY nombre";
    if ($st = $conexion->prepare($sql)) {
        $st->bind_param('i', $sel_gimnasio);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) $disciplinas[] = $row;
        $st->close();
    } else {
        error_log("Error preparando disciplinas por gim: " . $conexion->error);
    }
} else {
    // Admin sin gym seleccionado -> traer todas (para que el select muestre "gym — disciplina")
    $sql = "SELECT d.id, d.nombre, d.gimnasio_id, g.nombre AS gimnasio_nombre
            FROM disciplinas d
            LEFT JOIN gimnasios g ON g.id = d.gimnasio_id
            WHERE d.activo = 1
            ORDER BY g.nombre, d.nombre";
    if ($res = $conexion->query($sql)) {
        while ($row = $res->fetch_assoc()) $disciplinas[] = $row;
    }
}

/* Si admin, cargar gimnasios */
$gimnasios = [];
if ($rol === 'admin') {
    if ($res = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre")) {
        while ($g = $res->fetch_assoc()) $gimnasios[] = $g;
    }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Agregar Cliente</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- Igual que el index -->
  <link rel="stylesheet" href="estilo_unificado.css">

  <style>
    /* ================== FIX VISIBILIDAD PARA MENÚ HORIZONTAL ==================
       Fuerza colores legibles sin importar reglas globales (gradientes, a{} dorado, etc.)
    ============================================================================*/
    .menu-horizontal, .menu-horizontal *{
      color: var(--ink) !important;
      -webkit-text-fill-color: var(--ink) !important;
      text-shadow: none !important;
      filter: none !important;
      mix-blend-mode: normal !important;
      background: none !important;
      opacity: 1 !important;
    }
    .menu-horizontal{
      position: sticky; top: 0; z-index: 2147483646;
      background: #ffffff !important;
      border-bottom: 1px solid var(--stroke);
    }
    .menu-horizontal a{
      color: var(--brand) !important;
      font-weight: 800 !important;
      border-radius: 8px !important;
      padding: 8px 12px !important;
      text-decoration: none !important;
      white-space: nowrap !important;
    }
    .menu-horizontal a:hover{
      background: rgba(251,191,36,.12) !important; /* amarillo suave */
      color: var(--ink) !important;
      -webkit-text-fill-color: var(--ink) !important;
    }

    /* ================== ESTILO DE ESTA PÁGINA (alineado al index) =============*/
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
    .contenedor-wide{ max-width:1100px; margin:0 auto; }

    /* Caja principal como card del index */
    .card-form{
      background: var(--card);
      border: 1px solid var(--stroke);
      border-radius: 18px;
      box-shadow: var(--shadow);
      padding: 16px;
    }

    h2{
      margin: 0 0 12px 0;
      font-weight: 900;
      letter-spacing: .4px;
      background: linear-gradient(90deg, var(--brand), var(--brand-2), var(--brand-3));
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }

    .grid{ display:grid; gap: 12px; grid-template-columns: 1fr; }
    .row{ display:grid; gap: 12px; grid-template-columns: 1fr; }
    .row.two{ grid-template-columns: 1fr; }

    @media (min-width: 700px){
      .grid{ grid-template-columns: 1fr 360px; align-items: start; }
      .row.two{ grid-template-columns: 1fr 1fr; }
    }

    /* Inputs coherentes con el index (ya define base; aquí afinamos) */
    label{
      display:block; margin:6px 0; font-weight:700; color: var(--brand); font-size: .9rem;
    }
    .small-muted{ color:var(--mut); font-size: .9rem; }
    .helper{ color: var(--mut); font-size: .9rem; margin-top: 6px; }

    /* Botón principal con look del index */
    .btn-primary{
      display:inline-block;
      background: linear-gradient(180deg,#fff,#f7fafc);
      border:1px solid var(--stroke);
      border-radius:12px;
      color:var(--ink);
      padding:12px 16px;
      font-weight:800; cursor:pointer;
    }
    .btn-primary:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }

    /* Aside informativo */
    aside.aside{
      background:#fff;
      border:1px solid var(--stroke);
      border-radius:18px;
      box-shadow: var(--shadow);
      padding:16px;
      height: fit-content;
    }

    /* Mensaje de error del DNI */
    .mensaje-error{ color:#b91c1c; display:none; font-size:.9rem; margin-top:6px; }
  </style>
</head>
<body>

<div class="wrap">
  <div class="contenedor-wide">
    <div class="card-form">
      <a class="link-inline" href="index.php">← Volver al Menú</a>
      <h2>Agregar Cliente</h2>

      <div class="grid">
        <!-- formulario -->
        <form id="formCliente" class="" action="guardar_cliente.php" method="POST" onsubmit="return validarDNI()" autocomplete="off" novalidate>
          <div class="row two">
            <div>
              <label for="apellido">Apellido</label>
              <input id="apellido" name="apellido" type="text" required>
            </div>
            <div>
              <label for="nombre">Nombre</label>
              <input id="nombre" name="nombre" type="text" required>
            </div>
          </div>

          <label for="dni">DNI</label>
          <input id="dni" name="dni" type="text" required oninput="verificarDNI(this.value)">
          <div id="mensajeDNI" class="mensaje-error">Este DNI ya está registrado en este gimnasio.</div>

          <div class="row two" style="margin-top:8px">
            <div>
              <label for="fecha_nacimiento">Fecha de nacimiento</label>
              <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" required onchange="calcularEdad()">
            </div>
            <div>
              <label for="edad">Edad</label>
              <input id="edad" name="edad" type="text" readonly>
            </div>
          </div>

          <label for="domicilio">Domicilio</label>
          <input id="domicilio" name="domicilio" type="text">

          <div class="row two">
            <div>
              <label for="telefono">Teléfono</label>
              <input id="telefono" name="telefono" type="text">
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email">
            </div>
          </div>

          <label for="disciplina_select">Disciplina</label>
          <select id="disciplina_select" name="disciplina_id" required>
            <option value="">Seleccionar</option>
            <?php if ($rol === 'admin' && !$sel_gimnasio): ?>
              <?php foreach ($disciplinas as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= h($d['gimnasio_nombre'] . ' — ' . $d['nombre']) ?></option>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($disciplinas as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= h($d['nombre']) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <div class="helper">Si no ves la disciplina correcta, verificá que esté creada para este gimnasio.</div>

          <?php if ($rol === 'admin'): ?>
            <label for="gimnasio_id">Gimnasio</label>
            <select id="gimnasio_id" name="gimnasio_id">
              <option value="">Seleccionar</option>
              <?php foreach ($gimnasios as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= ($sel_gimnasio && $sel_gimnasio == $g['id']) ? 'selected' : '' ?>><?= h($g['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="small-muted">Al cambiar el gimnasio se recargarán las disciplinas.</div>
          <?php else: ?>
            <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
          <?php endif; ?>

          <div style="margin-top:12px">
            <button class="btn-primary" type="submit">Guardar Cliente</button>
          </div>
        </form>

        <!-- info / ayuda (columna derecha en desktop) -->
        <aside class="aside">
          <h3 style="margin-top:0">Notas</h3>
          <p class="small-muted">Este formulario está optimizado para dispositivos móviles y tablets. El administrador puede cambiar el gimnasio y visualizar las disciplinas disponibles para ese establecimiento.</p>
          <p class="small-muted">La verificación de DNI se hace por gimnasio para evitar duplicados por sede.</p>
        </aside>
      </div>
    </div>
  </div>
</div>

<script>
/* Cálculo edad */
function calcularEdad(){
  const fecha = document.getElementById('fecha_nacimiento').value;
  if (!fecha) { document.getElementById('edad').value = ''; return; }
  const hoy = new Date();
  const nac = new Date(fecha);
  let edad = hoy.getFullYear() - nac.getFullYear();
  const m = hoy.getMonth() - nac.getMonth();
  if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
  document.getElementById('edad').value = edad;
}

/* Verificar DNI por gimnasio */
let dniValido = true;
function verificarDNI(dni){
  const msg = document.getElementById('mensajeDNI');
  if (!dni || dni.trim().length < 5) { msg.style.display = 'none'; dniValido = true; return; }
  let gid = '';
  const gsel = document.getElementById('gimnasio_id');
  if (gsel) gid = gsel.value;
  fetch('verificar_dni.php?dni=' + encodeURIComponent(dni) + '&gimnasio_id=' + encodeURIComponent(gid), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (data && data.existe) { msg.style.display = 'block'; dniValido = false; }
      else { msg.style.display = 'none'; dniValido = true; }
    })
    .catch(e => { console.error(e); msg.style.display = 'none'; dniValido = true; });
}
function validarDNI(){
  if (!dniValido) { alert('El DNI ya existe para este gimnasio.'); return false; }
  return true;
}

/* Admin: al cambiar gimnasio pedir disciplinas */
document.addEventListener('DOMContentLoaded', function(){
  const gSel = document.getElementById('gimnasio_id');
  const discSel = document.getElementById('disciplina_select');
  if (!gSel) return;
  gSel.addEventListener('change', function(){
    const gid = this.value || '';
    discSel.innerHTML = '<option value="">Cargando...</option>';
    fetch('disciplinas_por_gimnasio.php?gimnasio_id=' + encodeURIComponent(gid), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(list => {
        discSel.innerHTML = '<option value="">Seleccionar</option>';
        if (Array.isArray(list)) {
          list.forEach(item => {
            const o = document.createElement('option');
            o.value = item.id;
            o.textContent = item.nombre;
            discSel.appendChild(o);
          });
        }
      })
      .catch(err => {
        console.error('Error cargando disciplinas', err);
        discSel.innerHTML = '<option value="">Seleccionar</option>';
      });
  });
  if (gSel.value) gSel.dispatchEvent(new Event('change'));
});
</script>
</body>
</html>
