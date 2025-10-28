<?php
/* ============================================================
   registro_online.php — Alta rápida de cliente (MultiGimnasio)
   • NO duplica DNI por gimnasio: si existe en clientes, usa ese registro
   • Si fue eliminado, prellena desde historial (clientes_bajas / clientes_archivo)
   • Si está “soft-deleted” (activo/eliminado/estado), lo reactiva en vez de duplicar
   • Campos extra: fecha_nacimiento, disciplina (id o texto), domicilio
   • Inserta/actualiza sólo columnas que existan en tu BD (dinámico)
   • Redirige a return (o al QR). NO marca ingreso.
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers genéricos ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function qv(mysqli $db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function json_out($arr){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

/* ===== Utilidades de introspección ===== */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $rs = $db->query("SHOW TABLES LIKE '{$t}'");
  return $rs && $rs->num_rows>0;
}
function colset(mysqli $db, string $table): array {
  $out = [];
  $table = $db->real_escape_string($table);
  if ($rs = $db->query("SHOW COLUMNS FROM `{$table}`")) {
    while($c=$rs->fetch_assoc()){ $out[strtolower($c['Field'])] = $c['Field']; }
  }
  return $out;
}
function hascol(array $cols, string $name): ?string {
  $ln = strtolower($name);
  return $cols[$ln] ?? null;
}

/* ===== Disciplinas (selector si hay tabla) =====
   Intentos:
   - disciplinas (con gimnasio_id o id_gimnasio)
   - disciplinas_gimnasio
   Si no hay tabla, devolvemos array vacío -> UI muestra input texto. */
function get_disciplinas(mysqli $db, int $gimnasio_id): array {
  $out = [];
  if ($gimnasio_id<=0) return $out;

  if (table_exists($db,'disciplinas')) {
    $cols = colset($db,'disciplinas');
    $cId   = hascol($cols,'id') ?? 'id';
    $cNom  = hascol($cols,'nombre') ?? (hascol($cols,'titulo') ?? null);
    $cGym1 = hascol($cols,'gimnasio_id');
    $cGym2 = hascol($cols,'id_gimnasio');
    if ($cNom) {
      $where = '1=1';
      if ($cGym1) $where = "`{$cGym1}`={$gimnasio_id}";
      elseif ($cGym2) $where = "`{$cGym2}`={$gimnasio_id}";
      $q = "SELECT `{$cId}` AS id, `{$cNom}` AS nombre
            FROM `disciplinas`
            WHERE {$where}
            ORDER BY 2 ASC";
      if ($rs = $db->query($q)) while($r=$rs->fetch_assoc()) $out[]=$r;
      if ($out) return $out;
    }
  }
  if (table_exists($db,'disciplinas_gimnasio')) {
    $cols = colset($db,'disciplinas_gimnasio');
    $cId   = hascol($cols,'id') ?? 'id';
    $cNom  = hascol($cols,'nombre') ?? (hascol($cols,'titulo') ?? null);
    $cGym1 = hascol($cols,'gimnasio_id') ?? (hascol($cols,'id_gimnasio') ?? null);
    if ($cNom) {
      $where = $cGym1 ? "`{$cGym1}`={$gimnasio_id}" : '1=1';
      $q = "SELECT `{$cId}` AS id, `{$cNom}` AS nombre
            FROM `disciplinas_gimnasio`
            WHERE {$where}
            ORDER BY 2 ASC";
      if ($rs = $db->query($q)) while($r=$rs->fetch_assoc()) $out[]=$r;
    }
  }
  return $out;
}

/* ===== Lógica de negocio ===== */
function find_client_by_dni(mysqli $db, int $gimnasio_id, string $dni): ?array {
  if ($gimnasio_id<=0 || $dni==='') return null;

  $cols = colset($db,'clientes');
  $sel = [
    hascol($cols,'id') ?: 'id',
    hascol($cols,'nombre') ?: 'nombre',
    hascol($cols,'apellido') ?: 'apellido',
    hascol($cols,'dni') ?: 'dni',
    hascol($cols,'telefono') ?: 'telefono',
    hascol($cols,'email') ?: 'email',
  ];

  // Extras si existen
  $cFechaNac = hascol($cols,'fecha_nacimiento') ?? hascol($cols,'fecha_nac');
  $cDom      = hascol($cols,'domicilio') ?? hascol($cols,'direccion');
  $cDiscId   = hascol($cols,'disciplina_id');
  $cDiscTxt  = hascol($cols,'disciplina'); // por si guardás texto

  if ($cFechaNac) $sel[] = "`{$cFechaNac}` AS fecha_nacimiento";
  if ($cDom)      $sel[] = "`{$cDom}` AS domicilio";
  if ($cDiscId)   $sel[] = "`{$cDiscId}` AS disciplina_id";
  if ($cDiscTxt)  $sel[] = "`{$cDiscTxt}` AS disciplina_txt";

  $q = "SELECT ".implode(',',$sel)."
        FROM clientes
        WHERE gimnasio_id={$gimnasio_id} AND dni=".qv($db,$dni)."
        LIMIT 1";
  if ($rs = $db->query($q)) {
    if ($rs->num_rows) {
      $row = $rs->fetch_assoc();

      // Soft delete flags
      $cActivo    = hascol($cols,'activo');
      $cEliminado = hascol($cols,'eliminado');
      $cEstado    = hascol($cols,'estado');
      if ($cActivo)    $row['__activo']    = $row[$cActivo]    ?? null;
      if ($cEliminado) $row['__eliminado'] = $row[$cEliminado] ?? null;
      if ($cEstado)    $row['__estado']    = $row[$cEstado]    ?? null;

      return $row;
    }
  }
  return null;
}

/* Historico por DNI (clientes_bajas o clientes_archivo). Trae extras si existen. */
function find_archived_by_dni(mysqli $db, string $dni): ?array {
  if ($dni==='') return null;
  $cands = [];
  if (table_exists($db,'clientes_bajas'))     $cands[]='clientes_bajas';
  if (table_exists($db,'clientes_archivo'))   $cands[]='clientes_archivo';

  foreach($cands as $t){
    $cols = colset($db,$t);
    $cNom = hascol($cols,'nombre'); $cApe = hascol($cols,'apellido');
    $cDni = hascol($cols,'dni');    $cTel = hascol($cols,'telefono');
    $cEml = hascol($cols,'email');
    $cFechaNac = hascol($cols,'fecha_nacimiento') ?? hascol($cols,'fecha_nac');
    $cDom      = hascol($cols,'domicilio') ?? hascol($cols,'direccion');
    $cDiscId   = hascol($cols,'disciplina_id');
    $cDiscTxt  = hascol($cols,'disciplina');

    $sel = [];
    if ($cNom) $sel[]="`{$cNom}` AS nombre";
    if ($cApe) $sel[]="`{$cApe}` AS apellido";
    if ($cDni) $sel[]="`{$cDni}` AS dni";
    if ($cTel) $sel[]="`{$cTel}` AS telefono";
    if ($cEml) $sel[]="`{$cEml}` AS email";
    if ($cFechaNac) $sel[]="`{$cFechaNac}` AS fecha_nacimiento";
    if ($cDom)      $sel[]="`{$cDom}` AS domicilio";
    if ($cDiscId)   $sel[]="`{$cDiscId}` AS disciplina_id";
    if ($cDiscTxt)  $sel[]="`{$cDiscTxt}` AS disciplina_txt";
    if (!$sel) continue;

    $q = "SELECT ".implode(',',$sel)." FROM `{$t}` WHERE ".($cDni ? "`{$cDni}`" : 'dni')."=".qv($db,$dni)." ORDER BY 1 DESC LIMIT 1";
    if ($rs = $db->query($q)) {
      if ($rs->num_rows) return $rs->fetch_assoc();
    }
  }
  return null;
}

/* Reactivar soft delete, si corresponde */
function maybe_reactivate_client(mysqli $db, int $cliente_id): void {
  $cols = colset($db, 'clientes');
  $cActivo    = hascol($cols,'activo');
  $cEliminado = hascol($cols,'eliminado');
  $cEstado    = hascol($cols,'estado');

  $sets = [];
  if ($cActivo)    $sets[] = "`{$cActivo}`=1";
  if ($cEliminado) $sets[] = "`{$cEliminado}`=0";
  if ($cEstado)    $sets[] = "`{$cEstado}`='activo'";

  if ($sets) {
    $db->query("UPDATE clientes SET ".implode(',', $sets)." WHERE id=".(int)$cliente_id." LIMIT 1");
  }
}

/* ===== Entrada GET/POST ===== */
$gimnasio_id = (int)($_GET['gimnasio_id'] ?? $_GET['g'] ?? 0);
$dni_pref    = preg_replace('/\D+/','', (string)($_GET['dni'] ?? ''));
$return_url  = (string)($_GET['return'] ?? '');

/* === AJAX: ver si DNI ya existe y/o si hay historial === */
if (($_GET['ajax'] ?? '') === 'dni_check') {
  $gid = (int)($_GET['gimnasio_id'] ?? 0);
  $dni = preg_replace('/\D+/','', (string)($_GET['dni'] ?? ''));
  $row = $gid>0 ? find_client_by_dni($conexion, $gid, $dni) : null;
  if ($row) {
    json_out([
      'exists'=>true,
      'archived'=>false,
      'data'=>[
        'id'=>(int)$row['id'],
        'nombre'=>$row['nombre'] ?? '',
        'apellido'=>$row['apellido'] ?? '',
        'dni'=>$row['dni'] ?? '',
        'telefono'=>$row['telefono'] ?? '',
        'email'=>$row['email'] ?? '',
        'fecha_nacimiento'=>$row['fecha_nacimiento'] ?? '',
        'domicilio'=>$row['domicilio'] ?? '',
        'disciplina_id'=> isset($row['disciplina_id']) ? (string)$row['disciplina_id'] : '',
        'disciplina_txt'=> $row['disciplina_txt'] ?? '',
      ]
    ]);
  }
  // Si no existe en clientes, buscamos historial (para prefill)
  $arch = find_archived_by_dni($conexion, $dni);
  if ($arch) {
    json_out([
      'exists'=>false,
      'archived'=>true,
      'data'=>[
        'nombre'=>$arch['nombre'] ?? '',
        'apellido'=>$arch['apellido'] ?? '',
        'dni'=>$arch['dni'] ?? $dni,
        'telefono'=>$arch['telefono'] ?? '',
        'email'=>$arch['email'] ?? '',
        'fecha_nacimiento'=>$arch['fecha_nacimiento'] ?? '',
        'domicilio'=>$arch['domicilio'] ?? '',
        'disciplina_id'=> isset($arch['disciplina_id']) ? (string)$arch['disciplina_id'] : '',
        'disciplina_txt'=> $arch['disciplina_txt'] ?? '',
      ]
    ]);
  }
  json_out(['exists'=>false, 'archived'=>false]);
}

/* Validar gimnasio (o selector) */
$gym = null;
if ($gimnasio_id > 0) {
  $rsG = $conexion->query("SELECT id, nombre, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
  if ($rsG && $rsG->num_rows) { $gym = $rsG->fetch_assoc(); }
}
if (!$gym) {
  $gimnasios = [];
  if ($rs = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre ASC")) {
    while($r=$rs->fetch_assoc()){ $gimnasios[]=$r; }
  }
}

/* Disciplinas */
$disciplinas = get_disciplinas($conexion, $gimnasio_id);

/* === POST: guardar alta === */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $gimnasio_id = (int)($_POST['gimnasio_id'] ?? 0);
  $nombre   = trim((string)($_POST['nombre'] ?? ''));
  $apellido = trim((string)($_POST['apellido'] ?? ''));
  $dni      = preg_replace('/\D+/','', (string)($_POST['dni'] ?? ''));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));

  $fecha_nacimiento = trim((string)($_POST['fecha_nacimiento'] ?? '')); // YYYY-MM-DD esperado
  $domicilio        = trim((string)($_POST['domicilio'] ?? ''));

  // Disciplina: priorizamos select disciplina_id, si no hay opciones, usamos disciplina_txt
  $disciplina_id  = (string)($_POST['disciplina_id'] ?? '');
  $disciplina_txt = trim((string)($_POST['disciplina_txt'] ?? ''));

  if ($gimnasio_id<=0){ $error="Falta seleccionar gimnasio."; }
  elseif ($dni===''){   $error="Falta DNI."; }
  elseif ($nombre===''){ $error="Falta nombre."; }
  else {
    $exist = find_client_by_dni($conexion, $gimnasio_id, $dni);
    $colsC = colset($conexion,'clientes');

    $cFechaNac = hascol($colsC,'fecha_nacimiento') ?? hascol($colsC,'fecha_nac');
    $cDom      = hascol($colsC,'domicilio') ?? hascol($colsC,'direccion');
    $cDiscId   = hascol($colsC,'disciplina_id');
    $cDiscTxt  = hascol($colsC,'disciplina');

    if ($exist) {
      // Si hay soft delete, reactivar
      $cid = (int)$exist['id'];
      if (
        (isset($exist['__activo']) && (string)$exist['__activo']!=='1') ||
        (isset($exist['__eliminado']) && (string)$exist['__eliminado']!=='0') ||
        (isset($exist['__estado']) && strtolower((string)$exist['__estado'])!=='activo')
      ){
        maybe_reactivate_client($conexion, $cid);
      }

      // Actualización SUAVE: completa sólo campos vacíos del registro con lo que envía el usuario
      $sets = [];
      if ($cFechaNac && !($exist['fecha_nacimiento'] ?? '')) {
        if ($fecha_nacimiento) $sets[] = "`{$cFechaNac}`=".qv($conexion,$fecha_nacimiento);
      }
      if ($cDom && !($exist['domicilio'] ?? '')) {
        if ($domicilio) $sets[] = "`{$cDom}`=".qv($conexion,$domicilio);
      }
      if ($cDiscId && empty($exist['disciplina_id']) && $disciplina_id!=='') {
        $sets[] = "`{$cDiscId}`=".(is_numeric($disciplina_id)? (int)$disciplina_id : 0);
      } elseif ($cDiscTxt && !($exist['disciplina_txt'] ?? '') && $disciplina_txt!=='') {
        $sets[] = "`{$cDiscTxt}`=".qv($conexion,$disciplina_txt);
      }
      // También podríamos completar tel/email si el registro está vacío
      if (!($exist['telefono'] ?? '') && $telefono) $sets[] = "`".(hascol($colsC,'telefono')??'telefono')."`=".qv($conexion,$telefono);
      if (!($exist['email']    ?? '') && $email)    $sets[] = "`".(hascol($colsC,'email')??'email')."`=".qv($conexion,$email);

      if ($sets) {
        $conexion->query("UPDATE clientes SET ".implode(', ',$sets)." WHERE id={$cid} LIMIT 1");
      }

    } else {
      // Prefill desde historial si existiera (pero priorizamos lo que envía el usuario)
      $arch = find_archived_by_dni($conexion, $dni);
      if ($arch) {
        $nombre   = $nombre   ?: ($arch['nombre'] ?? '');
        $apellido = $apellido ?: ($arch['apellido'] ?? '');
        $telefono = $telefono ?: ($arch['telefono'] ?? '');
        $email    = $email    ?: ($arch['email'] ?? '');
        $fecha_nacimiento = $fecha_nacimiento ?: ($arch['fecha_nacimiento'] ?? '');
        $domicilio        = $domicilio ?: ($arch['domicilio'] ?? '');
        if ($disciplina_id==='') $disciplina_id = isset($arch['disciplina_id']) ? (string)$arch['disciplina_id'] : '';
        if ($disciplina_txt==='') $disciplina_txt = $arch['disciplina_txt'] ?? '';
      }

      // Armado dinámico de INSERT sólo con columnas que existan
      $cols = ['nombre','apellido','dni','telefono','email','gimnasio_id'];
      $vals = [qv($conexion,$nombre), qv($conexion,$apellido), qv($conexion,$dni), qv($conexion,$telefono), qv($conexion,$email), (int)$gimnasio_id];

      if ($cFechaNac){ $cols[]=$cFechaNac; $vals[]= $fecha_nacimiento? qv($conexion,$fecha_nacimiento) : "NULL"; }
      if ($cDom){      $cols[]=$cDom;      $vals[]= $domicilio? qv($conexion,$domicilio) : "NULL"; }
      if ($cDiscId){   $cols[]=$cDiscId;   $vals[]= ($disciplina_id!==''? (int)$disciplina_id : "NULL"); }
      elseif ($cDiscTxt){ $cols[]=$cDiscTxt; $vals[]= ($disciplina_txt!==''? qv($conexion,$disciplina_txt) : "NULL"); }

      $sql = "INSERT INTO clientes (".implode(',',array_map(fn($c)=>"`{$c}`",$cols)).")
              VALUES (".implode(',', $vals).")";
      if (!$conexion->query($sql)) {
        $error = "No se pudo registrar el cliente. (".$conexion->error.")";
      }
      $cid = (int)$conexion->insert_id;
    }

    if (empty($error)) {
      // Iniciar sesión del cliente y volver (NO marcamos ingreso)
      $_SESSION['cliente_id']  = (int)$cid;
      $_SESSION['gimnasio_id'] = (int)$gimnasio_id;

      $fallback = '/gym_qr_checkin.php?g='.$gimnasio_id;
      $dest = isset($_POST['return']) && $_POST['return']!=='' ? (string)$_POST['return'] : $fallback;
      if (!headers_sent()) { header("Location: ".$dest, true, 302); exit; }
      echo '<meta http-equiv="refresh" content="0;url='.h($dest).'">'; exit;
    }
  }
}

/* ====== UI ====== */
$css = "
:root{--bg:#0c0c0d;--card:#141416;--ink:#eaeaea;--muted:#9aa0a6;--stroke:#222;--accent:#6ea8fe;--ok:#134e4a;--okbd:#065f46;--arch:#1e3a8a;--archbd:#1d4ed8;}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:520px;margin:0 auto;padding:18px}
.card{background:var(--card);border:1px solid #222;border-radius:16px;padding:18px}
.brand{display:flex;gap:12px;align-items:center;justify-content:center;margin-bottom:12px}
.brand img{width:56px;height:56px;object-fit:cover;border-radius:12px;background:#fff;border:1px solid #333}
.brand .name{font-size:22px;font-weight:800;letter-spacing:.2px;color:#fff}
h1{font-size:18px;margin:8px 0 14px;text-align:center;color:#cbd5e1}
label{display:block;margin:8px 0 6px;color:#cbd5e1;font-size:14px}
input,select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #303038;background:#101012;color:#fff;outline:none}
input:focus,select:focus{border-color:var(--accent)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.btn{width:100%;padding:14px 16px;border-radius:12px;border:0;background:var(--accent);color:#000;font-weight:800;font-size:18px;margin-top:14px;cursor:pointer}
.flash{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-size:14px}
.err{background:#5b1111;color:#ffd3d3;border:1px solid #7f1d1d}
.ok{background:var(--ok);border:1px solid #0a7a67;color:#d1fae5}
.arch{background:var(--arch);border:1px solid var(--archbd);color:#dbeafe}
.help{font-size:12px;color:var(--muted);margin-top:10px;text-align:center}
.small{font-size:12px;color:#cbd5e1}
";

function logo_url(?string $logo): ?string {
  if (!$logo) return null;
  if (preg_match('#^(https?:)?//#i', $logo) || (function_exists('str_starts_with') && str_starts_with($logo,'data:'))) return $logo;
  $candidatos = [
    __DIR__ . '/uploads/gimnasios/' . $logo => '/uploads/gimnasios/' . rawurlencode($logo),
    __DIR__ . '/img/' . $logo              => '/img/' . rawurlencode($logo),
    __DIR__ . '/' . ltrim($logo, '/\\')    => '/' . ltrim($logo, '/\\'),
  ];
  foreach ($candidatos as $fs=>$url) if (is_file($fs)) return $url.'?v='.(int)@filemtime($fs);
  return $logo;
}
$logoGym   = null;
$nombreGym = 'Gimnasio';
if (!empty($gym)) {
  $logoGym   = logo_url($gym['logo'] ?? null);
  $nombreGym = $gym['nombre'] ?? 'Gimnasio';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Registro Online · <?= h($nombreGym) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="color-scheme" content="dark">
<style><?= $css ?></style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <?php if ($logoGym): ?><img src="<?= h($logoGym) ?>" alt="Logo <?= h($nombreGym) ?>" loading="lazy" decoding="async"><?php endif; ?>
        <div class="name"><?= h($nombreGym) ?></div>
      </div>
      <h1>Registro de Cliente</h1>

      <?php if (!empty($error)): ?>
        <div class="flash err">⚠️ <?= h($error) ?></div>
      <?php endif; ?>

      <div id="existsBox" class="flash ok" style="display:none"></div>
      <div id="archBox" class="flash arch" style="display:none"></div>

      <form id="frm" method="post" novalidate>
        <input type="hidden" name="return" value="<?= h($return_url) ?>">
        <label>Gimnasio</label>
        <?php if ($gym): ?>
          <input type="hidden" name="gimnasio_id" id="gimnasio_id" value="<?= (int)$gimnasio_id ?>">
          <input value="#<?= (int)$gym['id'] ?> · <?= h($nombreGym) ?>" readonly>
        <?php else: ?>
          <select name="gimnasio_id" id="gimnasio_id" required>
            <option value="">Seleccioná tu gimnasio…</option>
            <?php foreach(($gimnasios ?? []) as $g): ?>
              <option value="<?= (int)$g['id'] ?>" <?= ($g['id']==$gimnasio_id?'selected':'') ?>>#<?= (int)$g['id'] ?> · <?= h($g['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>

        <label>DNI</label>
        <input name="dni" id="dni" inputmode="numeric" pattern="[0-9]*" value="<?= h($dni_pref) ?>" placeholder="Ej: 35123456" required>

        <div class="row">
          <div>
            <label>Nombre</label>
            <input name="nombre" id="nombre" placeholder="Tu nombre" required>
          </div>
          <div>
            <label>Apellido</label>
            <input name="apellido" id="apellido" placeholder="Tu apellido" required>
          </div>
        </div>

        <div class="row">
          <div>
            <label>Fecha de nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" placeholder="YYYY-MM-DD">
          </div>
          <div>
            <label>Domicilio</label>
            <input name="domicilio" id="domicilio" placeholder="Calle y número">
          </div>
        </div>

        <label>Disciplina</label>
        <?php if (!empty($disciplinas)): ?>
          <select name="disciplina_id" id="disciplina_id">
            <option value="">Elegí tu disciplina…</option>
            <?php foreach($disciplinas as $d): ?>
              <option value="<?= h($d['id']) ?>"><?= h($d['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input name="disciplina_txt" id="disciplina_txt" placeholder="Ej: Boxeo, Kickboxing, etc.">
        <?php endif; ?>

        <label>Teléfono (WhatsApp) <span class="small">(opcional)</span></label>
        <input name="telefono" id="telefono" placeholder="Ej: 2664 123456">

        <label>Email <span class="small">(opcional)</span></label>
        <input type="email" name="email" id="email" placeholder="tu@correo.com">

        <button class="btn" type="submit" id="btnSubmit">Registrarme</button>
        <div class="help">Al registrarte aceptás las políticas del gimnasio.</div>
      </form>

      <div class="help" style="margin-top:14px">
        <!-- Recomendación: evitar duplicados reales a nivel BD
        ALTER TABLE clientes ADD UNIQUE KEY ux_gym_dni (gimnasio_id, dni);
        -->
      </div>
    </div>
  </div>

<script>
(function(){
  const $ = sel => document.querySelector(sel);
  const dni = $('#dni'), gid = $('#gimnasio_id');
  const nombre = $('#nombre'), apellido = $('#apellido'), tel = $('#telefono'), email = $('#email');
  const fecha_nac = $('#fecha_nacimiento'), domicilio = $('#domicilio');
  const discSel = $('#disciplina_id'), discTxt = $('#disciplina_txt');
  const box = $('#existsBox'), abox = $('#archBox'), btn = $('#btnSubmit');

  function setReadonly(readonly){
    [nombre, apellido, tel, email, fecha_nac, domicilio].forEach(i => { if(i) i.readOnly = readonly; });
    if (discSel) discSel.disabled = readonly;
    if (discTxt) discTxt.readOnly = readonly;
  }
  function fill(data){
    if (!data) return;
    if (typeof data.nombre   === 'string') nombre.value   = data.nombre;
    if (typeof data.apellido === 'string') apellido.value = data.apellido;
    if (typeof data.telefono === 'string') tel.value      = data.telefono;
    if (typeof data.email    === 'string') email.value    = data.email;
    if (typeof data.fecha_nacimiento === 'string' && fecha_nac) fecha_nac.value = data.fecha_nacimiento;
    if (typeof data.domicilio === 'string' && domicilio) domicilio.value = data.domicilio;

    // Disciplina: si vino disciplina_id y hay select, setear; si vino disciplina_txt y hay input, setear
    if (discSel && data.disciplina_id) discSel.value = String(data.disciplina_id);
    if (discTxt && data.disciplina_txt) discTxt.value = data.disciplina_txt;
  }
  async function check(){
    box.style.display = 'none';
    abox.style.display = 'none';
    setReadonly(false);
    btn.textContent = 'Registrarme';

    const vgid = (gid && gid.value) ? gid.value : '0';
    const vdni = (dni.value||'').replace(/\D+/g,'');
    if (!vgid || !vdni) return;

    try{
      const url = `?ajax=dni_check&gimnasio_id=${encodeURIComponent(vgid)}&dni=${encodeURIComponent(vdni)}`;
      const r = await fetch(url, {credentials:'same-origin'});
      const j = await r.json();

      if (!j) return;

      if (j.exists) {
        fill(j.data);
        setReadonly(true);
        box.innerHTML = '✅ <strong>DNI ya registrado.</strong> Usaremos el registro existente y te vamos a ingresar con esos datos.';
        box.style.display = '';
        btn.textContent = 'Continuar';
        return;
      }

      if (j.archived) {
        fill(j.data);
        setReadonly(false);
        abox.innerHTML = '🗂️ <strong>Datos recuperados del historial.</strong> Revisá y confirmá para volver a registrarte.';
        abox.style.display = '';
        btn.textContent = 'Registrarme';
        return;
      }
    }catch(e){ /* silencioso */ }
  }

  if (dni) dni.addEventListener('blur', check);
  if (gid) gid.addEventListener('change', check);

  // Chequeo inicial si vino DNI por GET
  if (dni && dni.value) { check(); }
})();
</script>
</body>
</html>
