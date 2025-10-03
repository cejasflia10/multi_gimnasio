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
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* Mobile-first */
    :root{
      --bg:#0b0b0b; --card:#0f1115; --fg:#eef2f7; --muted:#94a3b8; --accent:#f5c542; --border:rgba(255,255,255,.06);
      --radius:12px;
    }
    *{box-sizing:border-box}
    body{ margin:0; font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--fg); -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale; }
    .contenedor{ max-width:980px; margin:14px auto; padding:14px; }
    a.volver-btn{ display:inline-block; color:var(--muted); margin-bottom:8px; text-decoration:none }
    h2{ margin:6px 0 14px; font-size:20px; text-align:left }
    form.card{ background:var(--card); padding:14px; border-radius:var(--radius); border:1px solid var(--border); box-shadow:0 6px 18px rgba(0,0,0,.4) }
    .grid{ display:grid; gap:10px; grid-template-columns: 1fr; }
    .row{ display:grid; gap:10px; grid-template-columns: 1fr; }
    label{ display:block; margin-bottom:6px; font-weight:700; color:var(--accent); font-size:13px }
    input[type=text], input[type=email], input[type=date], select{ width:100%; padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.04); background:#0b0d10; color:var(--fg); font-size:15px }
    .mensaje-error{ color:#fca5a5; display:none; font-size:13px; margin-top:6px }
    .small-muted{ color:var(--muted); font-size:13px; margin-top:6px }
    .btn{ display:inline-block; margin-top:12px; padding:10px 14px; border-radius:10px; background:var(--accent); color:#111; font-weight:800; border:none; cursor:pointer; width:100% }
    .flex{ display:flex; gap:10px; align-items:center }
    .col-2{ width:50% }
    .helper{ font-size:13px; color:var(--muted); margin-top:6px }

    /* tablet/desktop */
    @media(min-width:700px){
      .grid{ grid-template-columns: 1fr 360px; align-items:start }
      .row.two{ grid-template-columns: 1fr 1fr; }
      .btn{ width:auto; padding:12px 18px; }
    }

    /* accesibilidad focus */
    input:focus, select:focus { outline:3px solid rgba(245,197,66,0.12); box-shadow:0 0 0 3px rgba(245,197,66,0.06); }
  </style>
</head>
<body>
<div class="contenedor">
  <a class="volver-btn" href="index.php">← Volver al Menú</a>
  <h2>Agregar Cliente</h2>

  <div class="grid">
    <!-- formulario -->
    <form id="formCliente" class="card" action="guardar_cliente.php" method="POST" onsubmit="return validarDNI()" autocomplete="off" novalidate>
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

      <button class="btn" type="submit">Guardar Cliente</button>
    </form>

    <!-- info / ayuda (columna derecha en desktop) -->
    <aside style="padding:14px; border-radius:12px; background:#0f1316; border:1px solid var(--border)">
      <h3 style="margin-top:0">Notas</h3>
      <p class="small-muted">Este formulario está optimizado para dispositivos móviles y tablets. El administrador puede cambiar el gimnasio y visualizar las disciplinas disponibles para ese establecimiento.</p>
      <p class="small-muted">La verificación de DNI se hace por gimnasio para evitar duplicados por sede.</p>
    </aside>
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
  // obtener gimnasio actual
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

  // Si ya hay gimnasio seleccionado, disparar change para cargar disciplinas al abrir
  if (gSel.value) gSel.dispatchEvent(new Event('change'));
});
</script>
</body>
</html>
