<?php
// editar_competidor_evento.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* =========================================================
   Cloudinary (Cloudy) — ACTIVADO + credenciales
   (tal como me lo pasaste)
   ========================================================= */
const CLOUD_ENABLED     = true;                    // ← activado
const CLOUD_NAME        = 'ddfugds9b';
const CLOUD_API_KEY     = '657814174747186';
const CLOUD_API_SECRET  = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';

function cloud_init(): void {
  static $inited=false; if ($inited) return; $inited=true;
  $vendor1 = __DIR__.'/vendor/autoload.php';
  $vendor2 = dirname(__DIR__).'/vendor/autoload.php';
  if (file_exists($vendor1)) require_once $vendor1;
  elseif (file_exists($vendor2)) require_once $vendor2;

  // Config SDK si no hay CLOUDINARY_URL exportada
  if (class_exists('\Cloudinary\Configuration\Configuration')
      && !getenv('CLOUDINARY_URL')
      && CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) {
    \Cloudinary\Configuration\Configuration::instance([
      'cloud'=>[
        'cloud_name'=>CLOUD_NAME,
        'api_key'   =>CLOUD_API_KEY,
        'api_secret'=>CLOUD_API_SECRET
      ],
      'secure'=>true
    ]);
  }
}

/* ================= Utilidades ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0;
  if($q) $q->close();
  return $ok;
}
function cols(mysqli $db, string $t): array {
  $out=[];
  if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){
    while($x=$r->fetch_assoc()){
      $out[strtolower($x['Field'])]=$x['Field'];
    }
    $r->close();
  }
  return $out;
}
function pick(array $cands, array $pool){
  foreach($cands as $c){
    $lc=strtolower($c);
    if(isset($pool[$lc])) return $pool[$lc];
  }
  return null;
}
function parse_float_or_null($s){
  $s = trim((string)($s ?? ''));
  if ($s==='') return null;
  $s = str_replace(',', '.', $s);
  return is_numeric($s) ? (float)$s : null;
}

/**
 * Sube una imagen a Cloudinary usando el SDK (Cloudy).
 * - $fieldName: nombre del input file en $_FILES
 * - $folder: carpeta destino en Cloudinary (ej: 'eventos/competidores')
 * - $errRef: string de errores acumulados (por referencia)
 * Devuelve: URL (secure_url/url) o null si no se sube nada / falla.
 */
function upload_cloudinary_from_files(string $fieldName, string $folder, string &$errRef): ?string {
  if (
    empty($_FILES[$fieldName]) ||
    empty($_FILES[$fieldName]['tmp_name']) ||
    $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK
  ) {
    return null; // no se subió archivo
  }

  cloud_init();

  if (defined('CLOUD_ENABLED') && !CLOUD_ENABLED) {
    $errRef .= ' Cloudinary está desactivado (CLOUD_ENABLED=false).';
    return null;
  }

  $tmpPath = $_FILES[$fieldName]['tmp_name'];

  try {
    $res = null;

    // SDK nuevo (Cloudinary\Cloudinary)
    if (class_exists('\Cloudinary\Cloudinary')) {
      $cloudinary = new \Cloudinary\Cloudinary();
      $opts = [];
      if ($folder !== '') {
        $opts['folder'] = $folder;
      }
      $res = $cloudinary->uploadApi()->upload($tmpPath, $opts);

    // SDK clásico (Cloudinary\Uploader)
    } elseif (class_exists('\Cloudinary\Uploader')) {
      $opts = [];
      if ($folder !== '') {
        $opts['folder'] = $folder;
      }
      $res = \Cloudinary\Uploader::upload($tmpPath, $opts);

    } else {
      $errRef .= ' SDK Cloudinary no encontrado.';
      return null;
    }

    if (!is_array($res)) {
      $errRef .= ' Respuesta inesperada de Cloudinary.';
      return null;
    }

    if (!empty($res['secure_url'])) return $res['secure_url'];
    if (!empty($res['url']))        return $res['url'];

    $errRef .= ' Cloudinary no devolvió URL.';
    return null;

  } catch (\Throwable $e) {
    $errRef .= ' Error Cloudinary: '.$e->getMessage();
    return null;
  }
}

/* ===== Descubrir columnas ===== */
if (!has_table($conexion,'competidores_evento')) exit('❌ Falta tabla competidores_evento');
$cc = cols($conexion,'competidores_evento');

$C_ID        = pick(['id','competidor_id'],$cc);
$C_APELLIDO  = pick(['apellido'],$cc);
$C_NOMBRE    = pick(['nombre'],$cc);
$C_ESC_NOM   = pick(['escuela_nombre','academia','gimnasio','equipo'],$cc);
$C_ESC_LOGO  = pick(['escuela_logo','logo_escuela','logo_academia'],$cc);
$C_FOTO      = pick(['foto_competidor','foto','avatar'],$cc);
$C_EDAD      = pick(['edad'],$cc);
$C_MODAL_ID  = pick(['modalidad_id'],$cc);

$C_SEXO      = pick(['sexo','genero','sexo_biologico','sexo_competidor'],$cc);
$C_EVENTO_ID = pick(['evento_id','event_id','id_evento'],$cc);

$C_PESO_CAT  = pick([
  'categorias_evento_id',
  'categoria_evento_id',
  'categoria_id',
  'categoria_peso_id',
  'categoria_peso_evento_id',
  'peso_cat_id',
  'categoria_peso',
  'categoria',
  'peso_min_id'
], $cc);

$C_PESO_KG = pick([
  'peso_kg','peso','peso_declarado','peso_decl','kg','weight_kg',
  'peso_evento','peso_actual','peso_competidor','peso_inscripcion'
], $cc);

if (!$C_ID) exit('❌ No se detectó columna ID');

/* Si NO existe columna de peso: crearla automáticamente como peso_kg DOUBLE NULL */
if (!$C_PESO_KG) {
  if ($conexion->query("ALTER TABLE `competidores_evento` ADD COLUMN `peso_kg` DOUBLE NULL") === TRUE) {
    $C_PESO_KG = 'peso_kg';
    $cc['peso_kg'] = 'peso_kg';
  }
}

/* Catálogos (opcionales) */
$mods=[];
if (has_table($conexion,'modalidades_evento')) {
  if ($r=$conexion->query("SELECT id,nombre FROM modalidades_evento ORDER BY nombre")){
    while($x=$r->fetch_assoc()){
      $mods[]=['id'=>(int)$x['id'],'nombre'=>$x['nombre']];
    }
    $r->close();
  }
}

/* 👉 CATEGORÍAS DE PESO DESDE categorias_evento (nuevo)
   y fallback a categorias_peso_evento (viejo) */
$pesos=[];
if (has_table($conexion,'categorias_evento')) {
  $sql = "SELECT id,nombre,peso_min,peso_max,genero,edad_min,edad_max
          FROM categorias_evento
          ORDER BY peso_min, peso_max, nombre";
  if ($r = $conexion->query($sql)) {
    while($x = $r->fetch_assoc()){
      $pesos[] = [
        'id'       => (int)$x['id'],
        'nombre'   => $x['nombre'],
        'peso_min' => $x['peso_min'],
        'peso_max' => $x['peso_max'],
        'genero'   => $x['genero'],
        'edad_min' => $x['edad_min'],
        'edad_max' => $x['edad_max'],
      ];
    }
    $r->close();
  }
} elseif (has_table($conexion,'categorias_peso_evento')) {
  if ($r=$conexion->query("SELECT id,nombre FROM categorias_peso_evento ORDER BY nombre")){
    while($x=$r->fetch_assoc()){
      $pesos[] = [
        'id'       => (int)$x['id'],
        'nombre'   => $x['nombre'],
        'peso_min' => null,
        'peso_max' => null,
        'genero'   => '',
        'edad_min' => null,
        'edad_max' => null,
      ];
    }
    $r->close();
  }
}

/* ================= Parámetros ================= */
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;

$evento_id = 0;
if (isset($_GET['evento_id']) && ctype_digit($_GET['evento_id'])) {
  $evento_id = (int)$_GET['evento_id'];
} elseif (isset($_POST['evento_id']) && ctype_digit($_POST['evento_id'])) {
  $evento_id = (int)$_POST['evento_id'];
}

if ($id<=0) exit('❌ Falta id');

/* ================= Leer competidor (para mostrar y fallback) ================= */
$sel = "SELECT ".
       bt($C_ID)." AS id".
       ($C_APELLIDO ? ", ".bt($C_APELLIDO)." AS apellido" : ", NULL AS apellido").
       ($C_NOMBRE   ? ", ".bt($C_NOMBRE)  ." AS nombre"   : ", NULL AS nombre").
       ($C_ESC_NOM  ? ", ".bt($C_ESC_NOM) ." AS escuela"  : ", NULL AS escuela").
       ($C_ESC_LOGO ? ", ".bt($C_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo").
       ($C_FOTO     ? ", ".bt($C_FOTO)    ." AS foto"     : ", NULL AS foto").
       ($C_EDAD     ? ", ".bt($C_EDAD)    ." AS edad"     : ", NULL AS edad").
       ($C_MODAL_ID ? ", ".bt($C_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id").
       ($C_PESO_CAT ? ", ".bt($C_PESO_CAT)." AS peso_cat_id"  : ", NULL AS peso_cat_id").
       ($C_PESO_KG  ? ", ".bt($C_PESO_KG) ." AS peso_kg"      : ", NULL AS peso_kg").
       ($C_SEXO     ? ", ".bt($C_SEXO)    ." AS sexo"         : ", NULL AS sexo").
       ($C_EVENTO_ID ? ", ".bt($C_EVENTO_ID)." AS evento_id_comp" : ", NULL AS evento_id_comp").
       " FROM `competidores_evento` WHERE ".bt($C_ID)."=? LIMIT 1";
$st=$conexion->prepare($sel);
$st->bind_param('i',$id);
$st->execute();
$comp=$st->get_result()->fetch_assoc();
$st->close();
if (!$comp) exit('❌ No encontrado');

/* Si no vino evento_id por GET/POST, intentar sacarlo de la fila del competidor */
if ($evento_id <= 0 && !empty($comp['evento_id_comp'])) {
  $evento_id = (int)$comp['evento_id_comp'];
}

/* ================= Guardar (POST) ================= */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $sets=[]; $vals=[]; $types='';
  $cloudErr = '';

  if ($C_APELLIDO){
    $v=trim((string)($_POST['apellido']??''));
    $sets[]=bt($C_APELLIDO)."=?";
    $vals[]=$v; $types.='s';
  }
  if ($C_NOMBRE){
    $v=trim((string)($_POST['nombre']??''));
    $sets[]=bt($C_NOMBRE)."=?";
    $vals[]=$v; $types.='s';
  }
  if ($C_ESC_NOM){
    $v=trim((string)($_POST['escuela']??''));
    $sets[]=bt($C_ESC_NOM)."=?";
    $vals[]=$v; $types.='s';
  }

  /* ==== Logo academia ==== */
  if ($C_ESC_LOGO){
    $logo_actual_bd = (string)($comp['escuela_logo'] ?? '');
    $logo_url_texto = trim((string)($_POST['escuela_logo']??''));
    $nuevo_logo_url = null;

    // Intentar subir archivo
    $subida_logo = upload_cloudinary_from_files('escuela_logo_file', 'eventos/escuelas', $cloudErr);
    if ($subida_logo) {
      $nuevo_logo_url = $subida_logo;
    } elseif ($logo_url_texto !== '') {
      // Sin archivo pero con URL escrita
      $nuevo_logo_url = $logo_url_texto;
    }

    if ($nuevo_logo_url !== null) {
      // Solo actualizamos si hay algo nuevo
      $sets[]=bt($C_ESC_LOGO)."=?";
      $vals[]=$nuevo_logo_url; $types.='s';
    }
    // Si no hay nada nuevo y texto vacío, NO tocamos el logo (se queda como está)
  }

  /* ==== Foto competidor ==== */
  if ($C_FOTO){
    $foto_actual_bd = (string)($comp['foto'] ?? '');
    $foto_url_texto = trim((string)($_POST['foto']??''));
    $nueva_foto_url = null;

    // Intentar subir archivo
    $subida_foto = upload_cloudinary_from_files('foto_file', 'eventos/competidores', $cloudErr);
    if ($subida_foto) {
      $nueva_foto_url = $subida_foto;
    } elseif ($foto_url_texto !== '') {
      // Sin archivo pero con URL escrita
      $nueva_foto_url = $foto_url_texto;
    }

    if ($nueva_foto_url !== null) {
      // Solo actualizamos si hay algo nuevo
      $sets[]=bt($C_FOTO)."=?";
      $vals[]=$nueva_foto_url; $types.='s';
    }
    // Si no hay nada nuevo y texto vacío, NO tocamos la foto
  }

  if ($C_EDAD){
    $raw=trim((string)($_POST['edad']??''));
    $v=($raw!=='' && ctype_digit($raw))?(int)$raw:null;
    $sets[]=bt($C_EDAD)."=?";
    $vals[]=$v; $types.='i';
  }
  if ($C_MODAL_ID){
    $raw=trim((string)($_POST['modalidad_id']??''));
    $v=($raw!=='' && ctype_digit($raw))?(int)$raw:null;
    $sets[]=bt($C_MODAL_ID)."=?";
    $vals[]=$v; $types.='i';
  }
  if ($C_PESO_CAT){
    $raw=trim((string)($_POST['peso_cat_id']??''));
    $v=($raw!=='' && ctype_digit($raw))?(int)$raw:null;
    $sets[]=bt($C_PESO_CAT)."=?";
    $vals[]=$v; $types.='i';
  }

  /* 👉 SIEMPRE guardar PESO declarado (si viene algo) */
  if ($C_PESO_KG){
    $pval_raw = $_POST['peso_kg'] ?? '';
    if (trim((string)$pval_raw) !== '') {
      $pval = parse_float_or_null($pval_raw);
      $sets[] = bt($C_PESO_KG)."=?";
      $vals[]=$pval; $types.='d';
    }
  }

  if ($cloudErr !== '') {
    $err .= trim($cloudErr);
  }

  if ($sets){
    $sql="UPDATE `competidores_evento` SET ".implode(', ',$sets)." WHERE ".bt($C_ID)."=?";
    $types.='i'; $vals[]=$id;
    if ($st=$conexion->prepare($sql)){
      $st->bind_param($types, ...$vals);
      if ($st->execute()){
        $_SESSION['flash_ok'] = '✅ Cambios guardados.';
        if ($evento_id > 0) {
          header('Location: ver_competidores_evento.php?evento_id='.(int)$evento_id);
        } else {
          header('Location: ver_competidores_evento.php');
        }
        exit;
      } else {
        $err='No se pudo guardar: '.$st->error . ($cloudErr ? ' | '.$cloudErr : '');
      }
      $st->close();
    } else {
      $err='Error preparando UPDATE.' . ($cloudErr ? ' | '.$cloudErr : '');
    }
  } else {
    $err='No hay cambios.' . ($cloudErr ? ' | '.$cloudErr : '');
  }
}

/* ===== Determinar categoría seleccionada ===== */
$selected_cat_id = (int)($comp['peso_cat_id'] ?? 0);

/* Si no hay FK guardada, intentar deducir según peso/edad/sexo */
if (!$selected_cat_id && $pesos){
  $peso_kg_comp = null;
  if (isset($comp['peso_kg']) && $comp['peso_kg'] !== null && $comp['peso_kg'] !== '') {
    $peso_kg_comp = (float)$comp['peso_kg'];
  }
  $edad_comp = null;
  if (isset($comp['edad']) && $comp['edad'] !== null && $comp['edad'] !== '') {
    $edad_comp = (int)$comp['edad'];
  }
  $sexo_comp = strtolower(trim((string)($comp['sexo'] ?? '')));

  foreach($pesos as $p){
    $ok = true;

    $pm = $p['peso_min'];
    $px = $p['peso_max'];
    if ($peso_kg_comp !== null && $pm !== null && $pm !== '' && $px !== null && $px !== ''){
      $peso_min_cat = (float)$pm;
      $peso_max_cat = (float)$px;
      if ($peso_kg_comp < $peso_min_cat || $peso_kg_comp > $peso_max_cat){
        $ok = false;
      }
    }

    $em = $p['edad_min'];
    $ex = $p['edad_max'];
    if ($ok && $edad_comp !== null && $em !== null && $em !== '' && $ex !== null && $ex !== ''){
      $edad_min_cat = (int)$em;
      $edad_max_cat = (int)$ex;
      if ($edad_comp < $edad_min_cat || $edad_comp > $edad_max_cat){
        $ok = false;
      }
    }

    $gen = strtolower(trim((string)$p['genero']));
    if ($ok && $gen !== '' && $gen !== 'mixto' && $sexo_comp !== ''){
      $prim = $sexo_comp[0]; // m / f
      if ($gen[0] !== $prim){
        $ok = false;
      }
    }

    if ($ok){
      $selected_cat_id = (int)$p['id'];
      break;
    }
  }
}

/* ================= Render ================= */
$phUser='assets/placeholder-user.png';
$phGym ='assets/placeholder-gym.png';
$nombre = trim(($comp['apellido']??'').' '.($comp['nombre']??''));
if ($nombre==='') { $nombre = '#'.$id; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>✏️ Editar competidor #<?= (int)$id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .wrap{max-width:900px;margin:24px auto;padding:12px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:14px}
  label{display:block;margin:8px 0 4px;color:#bcd8ff}
  input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
  .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
  .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
  img.pfp{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #2b3c4f}
  .muted{color:#9ecbff;font-size:0.85rem}
</style>
</head>
<body>
<?php if (empty($_SESSION['__JUEZ_MODE__'])) { @include __DIR__.'/menu_eventos.php'; } ?>

<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">✏️ Editar competidor — <?= h($nombre) ?></h2>
    <?php if(!empty($_SESSION['flash_ok'])): ?>
      <div class="ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
    <?php endif; ?>
    <?php if(!empty($err)): ?>
      <div class="bad"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

      <div class="row" style="align-items:center;margin-bottom:8px">
        <img class="pfp" src="<?= h($comp['foto'] ?: $phUser) ?>" alt="foto">
        <img class="pfp" src="<?= h($comp['escuela_logo'] ?: $phGym) ?>" alt="logo">
        <div class="muted">ID: <?= (int)$comp['id'] ?></div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Apellido</label>
          <input name="apellido" value="<?= h($comp['apellido']??'') ?>">
        </div>
        <div style="flex:1">
          <label>Nombre</label>
          <input name="nombre" value="<?= h($comp['nombre']??'') ?>">
        </div>
      </div>

      <div class="row">
        <div style="flex:2">
          <label>Academia</label>
          <input name="escuela" value="<?= h($comp['escuela']??'') ?>">
        </div>
        <div style="flex:1">
          <label>Edad</label>
          <input name="edad" type="number" min="0" step="1" value="<?= h((string)($comp['edad']??'')) ?>">
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>URL Foto competidor</label>
          <input name="foto" value="<?= h($comp['foto'] ?? '') ?>">
          <label style="margin-top:6px;">Foto competidor (subir archivo)</label>
          <input type="file" name="foto_file" accept="image/*">
          <small class="muted">Si subís un archivo o escribís una URL, se actualiza la foto. Si dejás vacío, se mantiene la actual.</small>
        </div>
        <div style="flex:1">
          <label>URL Logo academia</label>
          <input name="escuela_logo" value="<?= h($comp['escuela_logo'] ?? '') ?>">
          <label style="margin-top:6px;">Logo academia (subir archivo)</label>
          <input type="file" name="escuela_logo_file" accept="image/*">
          <small class="muted">Si subís un archivo o escribís una URL, se actualiza el logo. Si dejás vacío, se mantiene el actual.</small>
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Modalidad</label>
          <?php if ($C_MODAL_ID && $mods): ?>
            <select name="modalidad_id">
              <option value="">—</option>
              <?php foreach($mods as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= (int)($comp['modalidad_id']??0)===(int)$m['id']?'selected':''; ?>>
                  <?= h($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($C_MODAL_ID): ?>
            <input value="(sin tabla modalidades_evento)" disabled>
          <?php else: ?>
            <input value="(columna modalidad_id no existe)" disabled>
          <?php endif; ?>
        </div>

        <div style="flex:1">
          <label>Categoría de peso (catálogo)</label>
          <?php if ($C_PESO_CAT && $pesos): ?>
            <select name="peso_cat_id">
              <option value="">—</option>
              <?php foreach($pesos as $p): ?>
                <?php
                  $label = (string)$p['nombre'];
                  $pm = $p['peso_min'];
                  $px = $p['peso_max'];
                  $em = $p['edad_min'];
                  $ex = $p['edad_max'];
                  $gen = trim((string)$p['genero']);
                  $extra = [];
                  if ($pm !== null && $pm !== '' || $px !== null && $px !== '') {
                    $txtPesoMin = ($pm !== null && $pm !== '') ? $pm : '?';
                    $txtPesoMax = ($px !== null && $px !== '') ? $px : '?';
                    $extra[] = $txtPesoMin.'–'.$txtPesoMax.' kg';
                  }
                  if ($em !== null && $em !== '' || $ex !== null && $ex !== '') {
                    $txtEdadMin = ($em !== null && $em !== '') ? $em : '?';
                    $txtEdadMax = ($ex !== null && $ex !== '') ? $ex : '?';
                    $extra[] = $txtEdadMin.'–'.$txtEdadMax.' años';
                  }
                  if ($gen !== '') {
                    $extra[] = $gen;
                  }
                  if (!empty($extra)) {
                    $label .= ' ('.implode(', ', $extra).')';
                  }
                ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$selected_cat_id === (int)$p['id'] ? 'selected' : ''; ?>>
                  <?= h($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($C_PESO_CAT): ?>
            <input value="(sin tabla categorias_evento / categorias_peso_evento)" disabled>
          <?php else: ?>
            <input value="(columna de categoría de peso no existe)" disabled>
          <?php endif; ?>
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Peso declarado (kg)</label>
          <input name="peso_kg" type="number" step="0.1" min="0" value="<?= h((string)($comp['peso_kg'] ?? '')) ?>">
          <small class="muted">
            <?php if ($C_PESO_KG): ?>
              Guardando en <code><?= h($C_PESO_KG) ?></code>.
            <?php else: ?>
              Se creó columna <code>peso_kg</code> (DOUBLE).
            <?php endif; ?>
          </small>
        </div>
      </div>

      <div class="row" style="margin-top:12px">
        <button class="btn" type="submit">💾 Guardar cambios</button>
        <?php if ($evento_id > 0): ?>
          <a class="btn" href="ver_competidores_evento.php?evento_id=<?= (int)$evento_id ?>" style="background:#1b2836;border-color:#2b3c4f">⬅ Volver</a>
        <?php else: ?>
          <a class="btn" href="ver_competidores_evento.php" style="background:#1b2836;border-color:#2b3c4f">⬅ Volver</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
</body>
</html>
