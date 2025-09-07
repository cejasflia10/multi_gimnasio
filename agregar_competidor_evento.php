<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

/* ===== Seguridad conexión ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

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
  // Acepta imágenes y PDF (para comprobantes)
  if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  $tmp  = $_FILES[$field]['tmp_name'];
  $name = basename((string)$_FILES[$field]['name']);
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $permitidos = ['jpg','jpeg','png','webp','pdf'];
  if (!in_array($ext, $permitidos, true)) $ext = 'jpg';
  $dir = __DIR__ . '/uploads/evento_' . $evento_id;
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $dest = $dir . '/' . $field . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
  if (!@move_uploaded_file($tmp, $dest)) return null;
  return 'uploads/evento_' . $evento_id . '/' . basename($dest);
}
function cat_tecnica_por_total(int $total): string {
  if ($total >= 10) return 'A';
  if ($total >= 5)  return 'B';
  if ($total >= 1)  return 'C';
  return 'N';
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

/* ===== Duplicado por (evento_id, dni) ===== */
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $t = 'competidores_evento';
  $hasDni = has_col($db,$t,'dni');
  $hasEid = has_col($db,$t,'evento_id');
  if (!$hasDni) return false;
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

/* ===== Inserción segura: solo columnas existentes (devuelve insert_id) ===== */
function insertar_competidor(mysqli $db, array $row): int {
  $t = 'competidores_evento';
  $cols = []; $vals = []; $types = '';
  $cands = [
    'evento_id'=>'i','apellido'=>'s','nombre'=>'s','dni'=>'s','fecha_nacimiento'=>'s','edad'=>'i','sexo'=>'s',
    'escuela_nombre'=>'s','escuela_logo'=>'s','foto_competidor'=>'s',
    'pago_inscripcion'=>'i','alias_transferencia'=>'s','comprobante_url'=>'s','telefono_organizador'=>'s',
    'modalidad_id'=>'i','disciplina_id'=>'i','categoria_tecnica_id'=>'i','division_id'=>'i','categoria_peso_id'=>'i',
    'wins'=>'i','losses'=>'i','draws'=>'i','no_contest'=>'i',
    'categoria_tecnica'=>'s','division'=>'s'
  ];
  foreach ($cands as $c => $tp) {
    if (has_col($db, $t, $c)) { $cols[] = "`$c`"; $vals[] = $row[$c] ?? null; $types .= $tp; }
  }
  if (!$cols) { http_response_code(500); exit('❌ No hay columnas compatibles en competidores_evento.'); }
  $ph  = rtrim(str_repeat('?,', count($cols)), ',');
  $sql = "INSERT INTO `{$t}` (".implode(',', $cols).") VALUES ($ph)";
  $st  = $db->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ SQL prepare: '.$db->error); }
  $bind = [$types]; foreach ($vals as $k => $v) { $bind[] = &$vals[$k]; }
  call_user_func_array([$st,'bind_param'],$bind);
  if (!$st->execute()) { http_response_code(500); exit('❌ exec(insert): '.$st->error); }
  $st->close();
  return (int)$db->insert_id;
}

/* =========================================================
   evento_id contextual (POST → GET → REFERER → SESSION)
   ========================================================= */
$evento_id_post = isset($_POST['evento_id']) && ctype_digit((string)$_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$evento_id_get  = isset($_GET['evento_id'])  && ctype_digit((string)$_GET['evento_id'])  ? (int)$_GET['evento_id']  : 0;
$evento_id_ref  = 0;
if (empty($evento_id_post) && empty($evento_id_get) && !empty($_SERVER['HTTP_REFERER'])) {
  $ref = parse_url($_SERVER['HTTP_REFERER']);
  if (!empty($ref['query'])) {
    parse_str($ref['query'], $qref);
    if (!empty($qref['evento_id']) && ctype_digit((string)$qref['evento_id'])) { $evento_id_ref = (int)$qref['evento_id']; }
  }
}
if ($evento_id_post > 0)      { $_SESSION['evento_id_actual'] = $evento_id_post; }
elseif ($evento_id_get > 0)   { $_SESSION['evento_id_actual'] = $evento_id_get; }
elseif ($evento_id_ref > 0)   { $_SESSION['evento_id_actual'] = $evento_id_ref; }

$evento_id_ctx = (int)$_SESSION['evento_id_actual'];
$evento_presente = $evento_id_ctx > 0;

/* ===== POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $evento_id = $evento_id_ctx;
  if ($evento_id <= 0) {
    $_SESSION['flash_error']='Falta evento_id. Abrí el formulario desde el evento.';
    header('Location: '.$_SERVER['PHP_SELF'].($evento_id_ctx>0?'?evento_id='.$evento_id_ctx:'')); exit;
  }

  // Datos base
  $apellido  = post('apellido');
  $nombre    = post('nombre');
  $dni       = preg_replace('/\D+/', '', post('dni'));
  $fecha_nac = post('fecha_nacimiento');
  $edad      = toIntOrNull(post('edad'));
  if ($edad === null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nac)) {
    $hoy = new DateTime('now'); $nac = DateTime::createFromFormat('Y-m-d', $fecha_nac);
    if ($nac) {
      $diff = $hoy->diff($nac);
      $edad = max(0, (int)$diff->y);
    }
  }
  $sexo      = post('sexo');
  $escuela_nombre = post('escuela_nombre');

  // Pagos
  $habilitar_pago   = (post('habilitar_pago') !== '');
  $pago_inscripcion = $habilitar_pago ? 1 : 0;
  $alias_transferencia = $habilitar_pago ? post('alias_transferencia') : '';
  $telefono_organizador= $habilitar_pago ? post('telefono_organizador') : '';
  $comprobante_url  = null;

  // Selecciones
  $modalidad_id_in         = toIntOrNull(post('modalidad_id'));
  $disciplina_id_in        = toIntOrNull(post('disciplina_id'));
  $categoria_tecnica_id_in = toIntOrNull(post('categoria_tecnica_id'));
  $division_id_in          = toIntOrNull(post('division_id'));
  $categoria_peso_id_in    = toIntOrNull(post('categoria_peso_id'));

  // Ranking
  $wins = max(0, (int)toIntOrNull(post('wins')));
  $loss = max(0, (int)toIntOrNull(post('losses')));
  $draw = max(0, (int)toIntOrNull(post('draws')));
  $nc   = max(0, (int)toIntOrNull(post('no_contest')));
  $total = $wins + $loss + $draw + $nc;

  // Validaciones mínimas
  if ($apellido === '' || $nombre === '' || $dni === '') {
    $_SESSION['flash_error']='Apellido, Nombre y DNI son obligatorios.';
    header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
  }
  if ($fecha_nac !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nac)) {
    $_SESSION['flash_error']='Fecha de nacimiento inválida (YYYY-MM-DD).';
    header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
  }
  if (existe_dni_evento($conexion, $evento_id, $dni)) {
    $_SESSION['flash_error'] = 'Ese DNI ya está inscripto en este evento.';
    header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
  }

  // Normalizar FKs
  $modalidad_id         = fk_ensure_id($conexion, 'modalidades_evento',         $modalidad_id_in);
  $disciplina_id        = fk_ensure_id($conexion, 'disciplinas_evento',         $disciplina_id_in);
  $categoria_tecnica_id = fk_ensure_id($conexion, 'categorias_tecnicas_evento', $categoria_tecnica_id_in);
  $division_id          = fk_ensure_id($conexion, 'divisiones_evento',          $division_id_in);
  $categoria_peso_id    = fk_ensure_id($conexion, 'categorias_peso_evento',     $categoria_peso_id_in);

  // Subidas
  $escuela_logo    = save_upload('escuela_logo', $evento_id);
  $foto_competidor = save_upload('foto_competidor', $evento_id);
  if ($habilitar_pago) {
    $comprobante_url = save_upload('comprobante_pago', $evento_id);
  }

  // Strings alternativos
  $cat_tec_str = cat_tecnica_por_total($total);
  $div_str = null;
  if ($edad !== null) {
    if ($edad < 12) $div_str='Infantil';
    elseif ($edad < 18) $div_str='Juvenil';
    elseif ($edad < 26) $div_str='Adultos';
    elseif ($edad < 46) $div_str='Masters';
    else $div_str='Veteranos';
  }

  // Fila a insertar
  $row = [
    'evento_id'            => $evento_id,
    'apellido'             => $apellido,
    'nombre'               => $nombre,
    'dni'                  => $dni,
    'fecha_nacimiento'     => ($fecha_nac !== '') ? $fecha_nac : null,
    'edad'                 => $edad,
    'sexo'                 => $sexo !== '' ? $sexo : null,
    'escuela_nombre'       => ($escuela_nombre !== '') ? $escuela_nombre : null,
    'escuela_logo'         => $escuela_logo ?: null,
    'foto_competidor'      => $foto_competidor ?: null,

    'pago_inscripcion'     => $pago_inscripcion,
    'alias_transferencia'  => $alias_transferencia ?: null,
    'comprobante_url'      => $comprobante_url ?: null,
    'telefono_organizador' => $telefono_organizador ?: null,

    'modalidad_id'         => $modalidad_id,
    'disciplina_id'        => $disciplina_id,
    'categoria_tecnica_id' => $categoria_tecnica_id,
    'division_id'          => $division_id,
    'categoria_peso_id'    => $categoria_peso_id,

    'wins'                 => $wins, 'losses'=>$loss, 'draws'=>$draw, 'no_contest'=>$nc,
    'categoria_tecnica'    => $cat_tec_str,
    'division'             => $div_str
  ];

  $insert_id = insertar_competidor($conexion, $row);

  // Mensajes y link WA
  $_SESSION['ok_msg'] = '✅ Competidor guardado correctamente.';
  if ($habilitar_pago && $telefono_organizador && ($alias_transferencia || $comprobante_url)) {
    $tel = preg_replace('/\D+/','', $telefono_organizador);
    $msg = "Comprobante de inscripción%0ACompetidor: ".rawurlencode("$apellido $nombre")." (ID $insert_id)%0AAlias: ".rawurlencode($alias_transferencia ?: '—')."%0AComprobante: ".rawurlencode($comprobante_url ?: '—');
    $_SESSION['wa_link'] = $tel ? ("https://wa.me/".$tel."?text=".$msg) : null;
  } else {
    $_SESSION['wa_link'] = null;
  }

  header('Location: '.$_SERVER['PHP_SELF'].'?evento_id='.$evento_id); exit;
}

/* ===== Vista (GET) ===== */
$evento_id = $evento_id_ctx;
$wa_link = $_SESSION['wa_link'] ?? null;
unset($_SESSION['wa_link']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de Competidor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
      --card:#0f1720; --bd:#1f2a33; --line:#22313f; --accent:#0e7ad1; --ok:#0f251b; --okbd:#164b31; --oktx:#b6f3d1; --bad:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    *{box-sizing:border-box}
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    .wrap{max-width:980px;margin:0 auto;padding:14px}
    h1,h2,h3{margin:.2rem 0 .6rem}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:12px;margin-top:10px}

    /* Mobile-first: grids compactos */
    .grid, .grid-3, .row{
      display:grid; gap:10px;
      grid-template-columns:1fr;
    }
    /* Campos cortos en dos por fila cuando hay espacio */
    @media (min-width:520px){
      .grid{grid-template-columns:repeat(2,1fr)}
      .row{grid-template-columns:repeat(2,1fr)}
    }
    @media (min-width:880px){
      .grid-3{grid-template-columns:repeat(3,1fr)}
    }

    label{font-size:12px;color:#cfe7ff;letter-spacing:.2px}
    input,select{
      width:100%;padding:10px;border-radius:10px;
      border:1px solid var(--line);background:#111a24;color:var(--fg);
      font-size:15px; line-height:1.15;
    }
    input[type="file"]{padding:8px}

    /* Inputs numéricos compactos (sin flechas) */
    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button{ -webkit-appearance:none; margin:0; }
    input[type=number]{ -moz-appearance:textfield; }

    .btn{display:inline-block;padding:12px 14px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;cursor:pointer}
    .btn[disabled]{opacity:.6;cursor:not-allowed}
    .alert{padding:10px 12px;border-radius:10px;margin:10px 0}
    .alert.ok{background:var(--ok);border:1px solid var(--okbd);color:var(--oktx)}
    .alert.bad{background:var(--bad);border:1px solid var(--badbd);color:var(--badt)}
    .mut{color:var(--mut);font-size:.9rem}
    .hint{font-size:12px;color:#9bb6d1}
    .warn{color:#ffce7a;font-size:.85rem;margin-top:6px;min-height:18px}
    .toggle{display:flex;align-items:center;gap:8px;margin:6px 0}
    .whats{display:inline-block;padding:10px 12px;border-radius:10px;border:1px solid #1f5c3a;background:#123221;color:#b6f3d1;text-decoration:none}

    /* Grupos visuales más apretados en mobile */
    @media (max-width:420px){
      .wrap{padding:10px}
      input,select{padding:9px;font-size:14.5px}
      .btn{width:100%}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h2>🏅 Registro de Competidor</h2>

    <?php if (!empty($_SESSION['ok_msg'])): ?>
      <div class="alert ok"><?= htmlspecialchars($_SESSION['ok_msg'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['ok_msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert bad"><?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!$evento_presente): ?>
      <div class="alert bad">Falta <b>evento_id</b>. Abrí este formulario desde el enlace del evento.</div>
    <?php endif; ?>

    <?php if (!empty($wa_link)): ?>
      <div class="card" style="border-color:#1f5c3a">
        <b>📨 Enviar comprobante por WhatsApp</b><br>
        <a class="whats" href="<?= htmlspecialchars($wa_link,ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener">Abrir WhatsApp con mensaje</a>
        <div class="hint" style="margin-top:6px">Adjuntá el archivo del comprobante en WhatsApp si hace falta.</div>
      </div>
    <?php endif; ?>

    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES) ?>?evento_id=<?= (int)$evento_id ?>" method="POST" enctype="multipart/form-data" id="form_comp">
      <input type="hidden" name="evento_id" id="evento_id" value="<?= $evento_presente ? htmlspecialchars((string)$evento_id, ENT_QUOTES, 'UTF-8') : '' ?>">

      <div class="card">
        <h3 style="margin-bottom:8px">Datos personales</h3>
        <div class="grid">
          <div>
            <label>Apellido</label>
            <input type="text" name="apellido" required autocomplete="family-name">
          </div>
          <div>
            <label>Nombre</label>
            <input type="text" name="nombre" required autocomplete="given-name">
          </div>
          <div>
            <label>DNI</label>
            <input type="text" name="dni" id="dni" required inputmode="numeric" pattern="\d+" maxlength="12" placeholder="Solo números">
            <div id="dni_msg" class="warn"></div>
          </div>
          <div>
            <label>Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required>
          </div>
          <div>
            <label>Edad</label>
            <input type="number" name="edad" id="edad" readonly required inputmode="numeric">
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
            <input type="text" name="escuela_nombre" required autocomplete="organization">
          </div>

          <!-- Foto/Logo con opción de cámara -->
          <div>
            <label>Logo de la Escuela (IMG/PDF)</label>
            <input type="file" id="escuela_logo" name="escuela_logo" accept="image/*,application/pdf">
            <div class="toggle">
              <input type="checkbox" id="cam_logo" onchange="toggleCapture('escuela_logo', this.checked)">
              <label for="cam_logo" class="mut">Usar cámara directa</label>
            </div>
          </div>
          <div>
            <label>Foto del Competidor</label>
            <input type="file" id="foto_competidor" name="foto_competidor" accept="image/*">
            <div class="toggle">
              <input type="checkbox" id="cam_foto" onchange="toggleCapture('foto_competidor', this.checked)">
              <label for="cam_foto" class="mut">Usar cámara directa</label>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:8px">Ranking</h3>
        <div class="grid-3">
          <div>
            <label>Ganadas (W)</label>
            <input type="number" min="0" name="wins" id="wins" value="0" inputmode="numeric">
          </div>
          <div>
            <label>Perdidas (L)</label>
            <input type="number" min="0" name="losses" id="losses" value="0" inputmode="numeric">
          </div>
          <div>
            <label>Empates (D)</label>
            <input type="number" min="0" name="draws" id="draws" value="0" inputmode="numeric">
          </div>
        </div>
        <div class="grid-3" style="margin-top:6px">
          <div>
            <label>Sin decisión (NC)</label>
            <input type="number" min="0" name="no_contest" id="no_contest" value="0" inputmode="numeric">
          </div>
          <div>
            <label>Total</label>
            <input type="number" id="total_fights" value="0" readonly inputmode="numeric">
          </div>
          <div>
            <label>Categoría técnica (auto)</label>
            <select name="categoria_tecnica_id" id="categoria_tecnica_id">
              <option value="1">A</option>
              <option value="2">B</option>
              <option value="3">C</option>
              <option value="4" selected>N</option>
            </select>
            <div class="hint">A: ≥10 • B: 5–9 • C: 1–4 • N: 0</div>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:8px">Inscripción</h3>
        <div class="grid">
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
            <label>División (auto por edad)</label>
            <select name="division_id" id="division_id" required>
              <option value="1">Infantil</option>
              <option value="2">Juvenil</option>
              <option value="3">Adultos</option>
              <option value="4">Masters</option>
              <option value="5">Veteranos</option>
            </select>
          </div>
          <div>
            <label>Categoría por Peso</label>
            <select name="categoria_peso_id" id="categoria_peso_id" required>
              <option value="">Seleccione edad y sexo primero</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:8px">Pagos</h3>
        <div class="toggle">
          <input type="checkbox" id="habilitar_pago" name="habilitar_pago" onchange="togglePagoBox()">
          <label for="habilitar_pago">Habilitar pago de inscripción</label>
        </div>
        <div id="pago_box" style="display:none">
          <div class="grid">
            <div>
              <label>Alias de transferencia</label>
              <input type="text" name="alias_transferencia" id="alias_transferencia" placeholder="alias.banco.cuenta" autocomplete="off">
            </div>
            <div>
              <label>WhatsApp del organizador</label>
              <input type="text" name="telefono_organizador" id="telefono_organizador" placeholder="54926xxxxxxxx" inputmode="numeric" maxlength="15" autocomplete="tel">
              <div class="hint">Formato internacional sin signos (ej: 5492665xxxxx).</div>
            </div>
          </div>
          <div class="grid" style="margin-top:6px">
            <div>
              <label>Comprobante (imagen o PDF)</label>
              <input type="file" name="comprobante_pago" id="comprobante_pago" accept="image/*,application/pdf">
              <div class="hint">Se guarda y luego podés enviarlo por WhatsApp.</div>
            </div>
            <div>
              <label>Monto de inscripción ($)</label>
              <input type="number" name="pago_inscripcion" step="0.01" value="0.00" inputmode="decimal">
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top:10px">
        <button type="submit" class="btn" id="btn_submit" <?= (!$evento_presente?'disabled':'') ?>>✅ Guardar Competidor</button>
      </div>
    </form>
  </div>

  <script>
  /* ==== Edad, División auto y categorías por peso ==== */
  function calcularEdad() {
    const fechaNac = document.getElementById("fecha_nacimiento").value;
    if (!fechaNac) return;
    const hoy = new Date(), nac = new Date(fechaNac);
    let edad = hoy.getFullYear() - nac.getFullYear();
    const m = hoy.getMonth() - nac.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
    document.getElementById("edad").value = Math.max(0, edad);

    // División auto
    const divSel = document.getElementById("division_id");
    if (edad < 12) divSel.value = "1";
    else if (edad < 18) divSel.value = "2";
    else if (edad < 26) divSel.value = "3";
    else if (edad < 46) divSel.value = "4";
    else divSel.value = "5";

    cargarCategoriasPeso();
  }
  function cargarCategoriasPeso() {
    const edad = document.getElementById("edad").value;
    const sexo = document.getElementById("sexo").value;
    if (!edad || !sexo) return;
    fetch('obtener_categorias_por_peso.php?edad=' + encodeURIComponent(edad) + '&sexo=' + encodeURIComponent(sexo))
      .then(res => res.text())
      .then(html => { document.getElementById("categoria_peso_id").innerHTML = html; })
      .catch(err => console.error("Error al cargar categorías:", err));
  }
  document.getElementById("sexo")?.addEventListener("change", cargarCategoriasPeso);

  /* ==== Ranking: total + categoría técnica auto ==== */
  function recalcRanking() {
    const w = parseInt(document.getElementById('wins').value||0,10);
    const l = parseInt(document.getElementById('losses').value||0,10);
    const d = parseInt(document.getElementById('draws').value||0,10);
    const n = parseInt(document.getElementById('no_contest').value||0,10);
    const tot = w + l + d + n;
    document.getElementById('total_fights').value = tot;
    const sel = document.getElementById('categoria_tecnica_id');
    let cat = '4'; // N
    if (tot>=10) cat='1'; else if (tot>=5) cat='2'; else if (tot>=1) cat='3'; else cat='4';
    sel.value = cat;
  }
  ['wins','losses','draws','no_contest'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', recalcRanking);
  });
  recalcRanking();

  /* ==== Validación en vivo del DNI por evento ==== */
  const dniInput = document.getElementById('dni');
  const dniMsg   = document.getElementById('dni_msg');
  const btnSubmit= document.getElementById('btn_submit');
  const eventoId = document.getElementById('evento_id')?.value || '';

  function setSubmitEnabled(enabled){ if (btnSubmit) btnSubmit.disabled = !enabled; }
  async function validarDNI() {
    dniMsg.textContent = '';
    setSubmitEnabled(true);
    const dni = dniInput?.value.trim();
    if (!dni || !eventoId) return;
    try {
      const r = await fetch('validar_dni_evento.php?evento_id='+encodeURIComponent(eventoId)+'&dni='+encodeURIComponent(dni));
      if (!r.ok) return;
      const data = await r.json(); // {exists: bool}
      if (data.exists) { dniMsg.textContent = '⚠️ Este DNI ya está inscripto en este evento.'; setSubmitEnabled(false); }
    } catch(e) { console.error(e); }
  }
  dniInput?.addEventListener('input', e => { e.target.value = (e.target.value||'').replace(/\D+/g,''); });
  dniInput?.addEventListener('blur', validarDNI);
  dniInput?.addEventListener('change', validarDNI);

  /* ==== Cámara directa opcional para inputs de imagen ==== */
  function toggleCapture(inputId, on){
    const el = document.getElementById(inputId);
    if (!el) return;
    if (on) { el.setAttribute('capture','environment'); }
    else    { el.removeAttribute('capture'); }
  }
  window.toggleCapture = toggleCapture;

  /* ==== Pago toggle ==== */
  function togglePagoBox(){
    const on = document.getElementById('habilitar_pago').checked;
    document.getElementById('pago_box').style.display = on ? '' : 'none';
  }
  window.togglePagoBox = togglePagoBox;
  </script>
</body>
</html>
