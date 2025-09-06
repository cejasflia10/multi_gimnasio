<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

/* ===== Helpers ===== */
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function save_upload(string $field, int $evento_id): ?string {
  if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  $tmp  = $_FILES[$field]['tmp_name'];
  $name = basename((string)$_FILES[$field]['name']);
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'])) $ext = 'jpg';
  $dir = __DIR__ . '/uploads/evento_' . $evento_id;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $dest = $dir . '/' . $field . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
  if (!@move_uploaded_file($tmp, $dest)) return null;
  return 'uploads/evento_' . $evento_id . '/' . basename($dest);
}

/* ===== FK helpers ===== */
function fk_first_id(mysqli $db, string $table): ?int {
  $res = $db->query("SELECT id FROM `{$table}` ORDER BY id ASC LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) return (int)$row['id'];
  return null;
}
function fk_ensure_id(mysqli $db, string $table, ?int $id): ?int {
  $id = $id ?? 0;
  if ($id > 0) {
    if ($st = $db->prepare("SELECT 1 FROM `{$table}` WHERE id = ? LIMIT 1")) {
      $st->bind_param('i', $id);
      $st->execute();
      $ok = ($r = $st->get_result()) && $r->num_rows > 0;
      $st->close();
      if ($ok) return $id;
    }
  }
  return fk_first_id($db, $table);
}

/* ===== Duplicado por (evento_id, dni) (robusto si faltan columnas) ===== */
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $t = 'competidores_evento';
  $hasDni  = has_col($db,$t,'dni');
  $hasEid  = has_col($db,$t,'evento_id');
  if (!$hasDni) return false; // si no hay columna DNI no podemos validar duplicado
  if ($hasEid) {
    $sql = "SELECT 1 FROM `{$t}` WHERE evento_id=? AND dni=? LIMIT 1";
    $st = $db->prepare($sql);
    if (!$st) return false;
    $st->bind_param('is', $evento_id, $dni);
  } else {
    $sql = "SELECT 1 FROM `{$t}` WHERE dni=? LIMIT 1";
    $st = $db->prepare($sql);
    if (!$st) return false;
    $st->bind_param('s', $dni);
  }
  $st->execute();
  $r = $st->get_result();
  $existe = $r && $r->num_rows > 0;
  $st->close();
  return $existe;
}

/* ===== Inserción segura: solo columnas existentes ===== */
function insertar_competidor(mysqli $db, array $row): bool {
  $t = 'competidores_evento';
  $cols = []; $vals = []; $types = '';

  // columnas candidatas (agregá acá si tu tabla tiene más)
  $cands = [
    'evento_id'           => 'i',
    'apellido'            => 's',
    'nombre'              => 's',
    'dni'                 => 's',
    'fecha_nacimiento'    => 's',
    'edad'                => 's', // si tu columna es INT podés poner 'i' y castear arriba
    'sexo'                => 's',
    'escuela_nombre'      => 's',
    'escuela_logo'        => 's',
    'foto_competidor'     => 's',
    'pago_inscripcion'    => 's', // DECIMAL/NUMERIC igual enlaza bien como string
    'modalidad_id'        => 's',
    'disciplina_id'       => 's',
    'categoria_tecnica_id'=> 's',
    'division_id'         => 's',
    'categoria_peso_id'   => 's',
  ];

  foreach ($cands as $c => $tp) {
    if (has_col($db, $t, $c)) {
      $cols[]  = "`$c`";
      $vals[]  = $row[$c] ?? null;
      $types  .= $tp;
    }
  }

  if (!$cols) { http_response_code(500); exit('❌ No hay columnas compatibles en competidores_evento.'); }

  $ph = rtrim(str_repeat('?,', count($cols)), ',');
  $sql = "INSERT INTO `{$t}` (".implode(',', $cols).") VALUES ($ph)";
  $st  = $db->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ SQL prepare: '.$db->error); }

  // bind por referencia
  $bind = [$types];
  foreach ($vals as $k => $v) { $bind[] = &$vals[$k]; }
  call_user_func_array([$st,'bind_param'],$bind);

  if (!$st->execute()) { http_response_code(500); exit('❌ exec(insert): '.$st->error); }
  $st->close();
  return true;
}

/* ===== evento_id contextual ===== */
$evento_id_get = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id_get > 0) { $_SESSION['evento_id_actual'] = $evento_id_get; }
$evento_id_ses = isset($_SESSION['evento_id_actual']) ? (int)$_SESSION['evento_id_actual'] : 0;
$evento_id_ctx = $evento_id_get > 0 ? $evento_id_get : $evento_id_ses;
$evento_presente = $evento_id_ctx > 0;

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($conexion) || !($conexion instanceof mysqli)) {
    http_response_code(500); exit('❌ No hay conexión a la base de datos.');
  }
  if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
  @$conexion->set_charset('utf8mb4');

  $evento_id = (int)($_POST['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
  if ($evento_id <= 0) {
    $_SESSION['flash_error']='Falta evento_id. Abrí el formulario desde el evento.';
    header('Location: agregar_competidor_evento.php?evento_id='.$evento_id_ctx);
    exit;
  }

  $apellido  = post('apellido');
  $nombre    = post('nombre');
  $dni       = preg_replace('/\D+/', '', post('dni')); // SOLO dígitos
  $fecha_nac = post('fecha_nacimiento');
  $edad      = toIntOrNull(post('edad'));
  $sexo      = post('sexo');
  $escuela_nombre = post('escuela_nombre');
  $pago_inscripcion = post('pago_inscripcion'); if ($pago_inscripcion === '') $pago_inscripcion = '0.00';

  // IDs del bloque principal
  $modalidad_id_in         = toIntOrNull(post('modalidad_id'));
  $disciplina_id_in        = toIntOrNull(post('disciplina_id'));
  $categoria_tecnica_id_in = toIntOrNull(post('categoria_tecnica_id'));
  $division_id_in          = toIntOrNull(post('division_id'));
  $categoria_peso_id_in    = toIntOrNull(post('categoria_peso_id'));

  if ($apellido === '' || $nombre === '' || $dni === '') {
    $_SESSION['flash_error']='Apellido, Nombre y DNI son obligatorios.';
    header('Location: agregar_competidor_evento.php?evento_id='.$evento_id);
    exit;
  }
  if ($fecha_nac !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nac)) {
    $_SESSION['flash_error']='Fecha de nacimiento inválida (YYYY-MM-DD).';
    header('Location: agregar_competidor_evento.php?evento_id='.$evento_id);
    exit;
  }

  // ❗ BLOQUEO POR DNI (por evento)
  if (existe_dni_evento($conexion, $evento_id, $dni)) {
    $_SESSION['flash_error'] = 'Ese DNI ya está inscripto en este evento.';
    header('Location: agregar_competidor_evento.php?evento_id='.$evento_id);
    exit;
  }

  // FKs seguros (si las tablas no existen, devolverán null)
  $modalidad_id         = fk_ensure_id($conexion, 'modalidades_evento',         $modalidad_id_in);
  $disciplina_id        = fk_ensure_id($conexion, 'disciplinas_evento',         $disciplina_id_in);
  $categoria_tecnica_id = fk_ensure_id($conexion, 'categorias_tecnicas_evento', $categoria_tecnica_id_in);
  $division_id          = fk_ensure_id($conexion, 'divisiones_evento',          $division_id_in);
  $categoria_peso_id    = fk_ensure_id($conexion, 'categorias_peso_evento',     $categoria_peso_id_in);

  // Archivos
  $escuela_logo    = save_upload('escuela_logo', $evento_id);
  $foto_competidor = save_upload('foto_competidor', $evento_id);

  // Mapa de valores (algunos pueden no existir en tu tabla; insertar_competidor filtra)
  $row = [
    'evento_id'            => (int)$evento_id,
    'apellido'             => $apellido,
    'nombre'               => $nombre,
    'dni'                  => $dni,
    'fecha_nacimiento'     => ($fecha_nac !== '') ? $fecha_nac : null,
    'edad'                 => isset($edad) ? (string)$edad : null,
    'sexo'                 => $sexo !== '' ? $sexo : null,
    'escuela_nombre'       => ($escuela_nombre !== '') ? $escuela_nombre : null,
    'escuela_logo'         => $escuela_logo ?: null,
    'foto_competidor'      => $foto_competidor ?: null,
    'pago_inscripcion'     => (string)$pago_inscripcion,
    'modalidad_id'         => $modalidad_id !== null ? (string)$modalidad_id : null,
    'disciplina_id'        => $disciplina_id !== null ? (string)$disciplina_id : null,
    'categoria_tecnica_id' => $categoria_tecnica_id !== null ? (string)$categoria_tecnica_id : null,
    'division_id'          => $division_id !== null ? (string)$division_id : null,
    'categoria_peso_id'    => $categoria_peso_id !== null ? (string)$categoria_peso_id : null,
  ];

  insertar_competidor($conexion, $row);
  header('Location: ver_competidores_evento.php?evento_id='.(int)$evento_id);
  exit;
}

/* ===== Vista (GET) ===== */
$evento_id = $evento_id_ctx;
@include __DIR__.'/menu_eventos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Competidor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width: 900px; margin: 0 auto; padding: 16px; }
    .alert { padding:10px 12px;border-radius:8px;margin-bottom:12px }
    .alert.error{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
    .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 680px){ .grid{ grid-template-columns: 1fr; } }
    label { font-weight:600; font-size:14px; }
    input, select { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:8px; }
    fieldset { border:1px solid #e3e3e3; border-radius:10px; padding:12px; margin-top:14px; }
    legend { font-weight:700; padding:0 6px; }
    .row { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px; }
    @media (max-width: 680px){ .row{ grid-template-columns: 1fr; } }
    .btn-principal { display:inline-block; padding:10px 14px; border-radius:10px; background:#1e88e5; color:#fff; border:0; cursor:pointer; }
    .btn-principal[disabled]{ opacity:0.6; cursor:not-allowed; }
    .warn { color:#b26a00; font-size:13px; margin-top:6px; min-height:18px; }
  </style>
</head>
<body>
  <div class="contenedor">
    <h2>🏅 Agregar Competidor al Evento</h2>

    <?php if (isset($_SESSION['flash_error'])): ?>
      <div class="alert error"><?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <?php if (!$evento_presente): ?>
      <div class="alert error">Falta evento_id. Abrí este formulario desde el listado del evento (botón “Agregar competidor”).</div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" id="form_comp">
      <input type="hidden" name="evento_id" id="evento_id" value="<?= $evento_presente ? htmlspecialchars((string)$evento_id, ENT_QUOTES, 'UTF-8') : '' ?>">

      <fieldset <?= !$evento_presente?'disabled':'' ?>}>
        <legend>Datos personales</legend>
        <div class="grid">
          <div>
            <label>Apellido</label>
            <input type="text" name="apellido" required>
          </div>
          <div>
            <label>Nombre</label>
            <input type="text" name="nombre" required>
          </div>
          <div>
            <label>DNI</label>
            <input type="text" name="dni" id="dni" required inputmode="numeric" pattern="\d+">
            <div id="dni_msg" class="warn"></div>
          </div>
          <div>
            <label>Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required>
          </div>
          <div>
            <label>Edad</label>
            <input type="number" name="edad" id="edad" readonly required>
          </div>
          <div>
            <label>Sexo</label>
            <select name="sexo" id="sexo" required>
              <option value="">Seleccionar</option>
              <option value="masculino">Masculino</option>
              <option value="femenino">Femenino</option>
            </select>
          </div>
          <div>
            <label>Escuela / Gimnasio</label>
            <input type="text" name="escuela_nombre" required>
          </div>
          <div>
            <label>Pago inscripción ($)</label>
            <input type="number" name="pago_inscripcion" step="0.01" value="0.00">
          </div>
          <div>
            <label>Logo de la Escuela (JPG/PNG)</label>
            <input type="file" name="escuela_logo" accept="image/*" required>
          </div>
          <div>
            <label>Foto del Competidor</label>
            <input type="file" name="foto_competidor" accept="image/*" required>
          </div>
        </div>
      </fieldset>

      <fieldset <?= !$evento_presente?'disabled':'' ?>}>
        <legend>Inscripción</legend>
        <div class="row">
          <div>
            <label>Modalidad</label>
            <select name="modalidad_id" id="modalidad_id" required>
              <option value="1">Exhibición</option>
              <option value="2">Boxeo</option>
              <option value="3">Full Contact</option>
              <option value="4">Low Kick</option>
              <option value="5">K1</option>
              <option value="6">MMA</option>
            </select>
          </div>
          <div>
            <label>Disciplina</label>
            <select name="disciplina_id" id="disciplina_id" required>
              <option value="1">Exhibiciones</option>
              <option value="2">Amateurs</option>
              <option value="3">Proam</option>
              <option value="4">Pro</option>
            </select>
          </div>
          <div>
            <label>Categoría Técnica</label>
            <select name="categoria_tecnica_id" required>
              <option value="1">A - Más de 11 peleas</option>
              <option value="2">B - 4 a 10 peleas</option>
              <option value="3">C - 1 a 3 peleas</option>
              <option value="4">N - 0 peleas</option>
            </select>
          </div>
          <div>
            <label>División</label>
            <select name="division_id" required>
              <option value="1">Infantil</option>
              <option value="2">Juvenil</option>
              <option value="3">Adulto</option>
              <option value="4">Master</option>
            </select>
          </div>
          <div>
            <label>Categoría por Peso</label>
            <select name="categoria_peso_id" id="categoria_peso_id" required>
              <option value="">Seleccione edad y sexo primero</option>
            </select>
          </div>
        </div>
      </fieldset>

      <p style="margin-top:12px;">
        <button type="submit" class="btn-principal" id="btn_submit" <?= (!$evento_presente?'disabled':'') ?>>✅ Guardar Competidor</button>
        <a href="ver_competidores_evento.php?evento_id=<?= (int)$evento_id ?>" class="btn-principal" style="background:#607d8b">↩ Volver al listado</a>
      </p>
    </form>
  </div>

  <script>
  function calcularEdad() {
    const fechaNac = document.getElementById("fecha_nacimiento").value;
    if (!fechaNac) return;
    const hoy = new Date(), nac = new Date(fechaNac);
    let edad = hoy.getFullYear() - nac.getFullYear();
    const m = hoy.getMonth() - nac.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
    document.getElementById("edad").value = edad;
    cargarCategoriasPeso();
  }
  function cargarCategoriasPeso() {
    const edad = document.getElementById("edad").value;
    const sexo = document.getElementById("sexo").value;
    if (!edad || !sexo) return;
    fetch('obtener_categorias_por_peso.php?edad=' + edad + '&sexo=' + sexo)
      .then(res => res.text())
      .then(html => { document.getElementById("categoria_peso_id").innerHTML = html; })
      .catch(err => console.error("Error al cargar categorías:", err));
  }
  document.getElementById("sexo")?.addEventListener("change", cargarCategoriasPeso);

  // ===== Validación en vivo del DNI SOLO por evento =====
  const dniInput = document.getElementById('dni');
  const dniMsg   = document.getElementById('dni_msg');
  const btnSubmit= document.getElementById('btn_submit');
  const eventoId = document.getElementById('evento_id')?.value || '';

  function setSubmitEnabled(enabled){
    if (!btnSubmit) return;
    btnSubmit.disabled = !enabled;
  }
  async function validarDNI() {
    dniMsg.textContent = '';
    setSubmitEnabled(true);
    const dni = dniInput?.value.trim();
    if (!dni || !eventoId) return;

    try {
      const r = await fetch('validar_dni_evento.php?evento_id='+encodeURIComponent(eventoId)+'&dni='+encodeURIComponent(dni));
      if (!r.ok) return;
      const data = await r.json(); // {exists: bool}
      if (data.exists) {
        dniMsg.textContent = '⚠️ Este DNI ya está inscripto en este evento.';
        setSubmitEnabled(false);
      }
    } catch(e) { console.error(e); }
  }
  dniInput?.addEventListener('input', e => { e.target.value = (e.target.value||'').replace(/\D+/g,''); });
  dniInput?.addEventListener('blur', validarDNI);
  dniInput?.addEventListener('change', validarDNI);
  </script>
</body>
</html>
