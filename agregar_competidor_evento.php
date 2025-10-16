<?php
/* ================= DEBUG (muestra errores en desarrollo) ================= */
error_reporting(E_ALL);
ini_set('display_errors', 1);
/* ======================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* =========================================================
   Cloudinary (opcional)
   ========================================================= */
const CLOUD_ENABLED    = true;
const CLOUD_NAME       = 'ddfugds9b';
const CLOUD_API_KEY    = '657814174747186';
const CLOUD_API_SECRET = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';

$CLOUD_ERR = null;

function cloud_init(): void {
  static $inited = false;
  if ($inited) return;
  $inited = true;
  $enabled = CLOUD_ENABLED || getenv('CLOUDINARY_URL');
  if (!$enabled) return;
  $autoload1 = __DIR__ . '/vendor/autoload.php';
  $autoload2 = dirname(__DIR__) . '/vendor/autoload.php';
  if (file_exists($autoload1)) require_once $autoload1;
  elseif (file_exists($autoload2)) require_once $autoload2;
}
function cloud_configured(): bool {
  $url = getenv('CLOUDINARY_URL');
  if ($url && preg_match('~cloudinary://.+@.+~', $url)) return true;
  if (CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) return true;
  return false;
}
function cloud_upload(string $abs_path, string $folder, string $public): ?string {
  global $CLOUD_ERR;
  cloud_init();
  if (!cloud_configured()) return null;
  try {
    if (!getenv('CLOUDINARY_URL') && CLOUD_ENABLED) {
      \Cloudinary\Configuration\Configuration::instance([
        'cloud' => [
          'cloud_name' => CLOUD_NAME,
          'api_key'    => CLOUD_API_KEY,
          'api_secret' => CLOUD_API_SECRET
        ],
        'secure' => true
      ]);
    }
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $res = $uploader->upload($abs_path, [
      'folder' => $folder,
      'public_id' => $public,
      'overwrite' => true,
      'resource_type' => 'auto'
    ]);
    return $res['secure_url'] ?? null;
  } catch (\Throwable $e) {
    $CLOUD_ERR = $e->getMessage();
    return null;
  }
}

/* ===== Verificar conexión mysqli ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}' LIMIT 1";
  if ($r = $db->query($sql)) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function is_image_path($p): bool {
  if (!$p) return false;
  $p = (string)$p;
  if (preg_match('~\.(jpe?g|png|webp|gif)(\?.*)?$~i', $p)) return true;
  if (strpos($p, '/image/upload/') !== false) return true;
  if (strpos($p, 'data:image/') === 0) return true;
  return false;
}
function save_upload(string $field, int $evento_id): ?string {
  if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  $tmp = $_FILES[$field]['tmp_name'];
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $name = basename((string)$_FILES[$field]['name']);
  $ext0 = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $permitidos = ['jpg','jpeg','png','webp','gif','pdf'];
  $ext = in_array($ext0, $permitidos, true) ? $ext0 : 'jpg';
  $base = preg_replace('/[^\p{L}\p{N}\-_]+/u','-', pathinfo($name, PATHINFO_FILENAME));
  $base = trim($base,'-_') ?: 'archivo';

  $dir = __DIR__.'/uploads/evento_'.$evento_id;
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $filename = $field.'_'.date('Ymd_His').'_'.mt_rand(1000,9999).'.'.$ext;
  $abs_dest = $dir.'/'.$filename;
  if (!@move_uploaded_file($tmp,$abs_dest)) return null;
  $final_url = 'uploads/evento_'.$evento_id.'/'.$filename;

  $cloud_attempted = false;
  $cloud_ok = false;
  if (cloud_configured()) {
    $cloud_attempted = true;
    $url = cloud_upload($abs_dest, 'multi_gimnasio/evento_'.$evento_id, $field.'_'.$base.'_'.date('Ymd_His'));
    if ($url) { $cloud_ok = true; $final_url = $url; }
  }
  if (!isset($_SESSION['upload_status'])) $_SESSION['upload_status'] = [];
  if ($cloud_attempted) {
    $_SESSION['upload_status'][$field] = ['status' => $cloud_ok ? 'ok' : 'fail', 'filename' => $name];
  } else {
    unset($_SESSION['upload_status'][$field]);
  }
  return $final_url;
}
function cat_tecnica_por_total(int $t): string { return $t>=10 ? 'A' : ($t>=5 ? 'B' : ($t>=1 ? 'C' : 'N')); }
function fk_first_id(mysqli $db, string $table): ?int {
  $res = $db->query("SELECT id FROM `$table` ORDER BY id ASC LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) return (int)$row['id'];
  return null;
}
function fk_ensure_id(mysqli $db, string $table, ?int $id): ?int {
  $id = $id ?? 0;
  if ($id > 0) {
    if ($st = $db->prepare("SELECT 1 FROM `$table` WHERE id=? LIMIT 1")) {
      $st->bind_param('i', $id); $st->execute();
      $r = $st->get_result(); $ok = ($r && $r->num_rows > 0); $st->close();
      if ($ok) return $id;
    }
  }
  return fk_first_id($db,$table);
}
function cat_id_by_nombre(mysqli $db, string $table, string $nombre): ?int {
  if (!has_col($db,$table,'nombre') || !has_col($db,$table,'id')) return null;
  if ($st = $db->prepare("SELECT id FROM `$table` WHERE nombre=? LIMIT 1")) {
    $st->bind_param('s', $nombre); $st->execute();
    $r = $st->get_result(); $id = ($r && $r->num_rows) ? (int)$r->fetch_assoc()['id'] : null; $st->close();
    return $id;
  }
  return null;
}
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $t = 'competidores_evento';
  if (!has_col($db,$t,'dni')) return false;
  if (has_col($db,$t,'evento_id')) {
    $st = $db->prepare("SELECT 1 FROM `$t` WHERE evento_id=? AND dni=? LIMIT 1");
    $st->bind_param('is', $evento_id, $dni);
  } else {
    $st = $db->prepare("SELECT 1 FROM `$t` WHERE dni=? LIMIT 1");
    $st->bind_param('s', $dni);
  }
  $st->execute(); $r = $st->get_result(); $existe = ($r && $r->num_rows > 0); $st->close();
  return $existe;
}
function insertar_competidor(mysqli $db, array $row): int {
  $t='competidores_evento'; $cols=[]; $vals=[]; $types='';
  $cands=[
    'evento_id'=>'i','apellido'=>'s','nombre'=>'s','dni'=>'s','fecha_nacimiento'=>'s','edad'=>'i','sexo'=>'s',
    'escuela_nombre'=>'s','escuela_logo'=>'s','foto_competidor'=>'s',
    'provincia'=>'s','localidad'=>'s','provincia_id'=>'i','localidad_id'=>'i',
    'pago_inscripcion'=>'s','alias_transferencia'=>'s','comprobante_url'=>'s','telefono_organizador'=>'s',
    'modalidad_id'=>'i','disciplina_id'=>'i','categoria_tecnica_id'=>'i','division_id'=>'i','categoria_peso_id'=>'i',
    'wins'=>'i','losses'=>'i','draws'=>'i','no_contest'=>'i',
    'categoria_tecnica'=>'s','division'=>'s'
  ];
  foreach($cands as $c=>$tp) if (has_col($db,$t,$c)) { $cols[]="`$c`"; $vals[]=$row[$c]??null; $types.=$tp; }
  if (!$cols) { http_response_code(500); exit('❌ Tabla sin columnas esperadas.'); }
  $ph = rtrim(str_repeat('?,', count($cols)), ',');
  $sql = "INSERT INTO `$t`(".implode(',', $cols).") VALUES($ph)";
  $st = $db->prepare($sql); if(!$st){ http_response_code(500); exit('❌ SQL: '.$db->error); }
  $refs=[]; $refs[]=&$types; for($i=0;$i<count($vals);$i++) $refs[]=&$vals[$i];
  if (!call_user_func_array([$st,'bind_param'],$refs)) { $err=$st->error?:'bind_param'; $st->close(); http_response_code(500); exit('❌ '.$err); }
  if (!$st->execute()) { $err=$st->error?:'execute'; $st->close(); http_response_code(500); exit('❌ '.$err); }
  $id=(int)$db->insert_id; $st->close(); return $id;
}

/* ========== Metadata del evento (NOMBRE, LOGO y FONDO) ========== */
function get_evento_meta(mysqli $db, int $evento_id): array {
  $row=[];
  if ($evento_id>0){
    if($st=$db->prepare("SELECT * FROM `eventos_deportivos` WHERE id=? LIMIT 1")){
      $st->bind_param('i',$evento_id); $st->execute(); $res=$st->get_result();
      if($res && $res->num_rows) $row=$res->fetch_assoc(); $st->close();
    }
  }
  $nombre='Evento'; foreach(['nombre','titulo','title'] as $k) if(!empty($row[$k])){$nombre=(string)$row[$k]; break;}
  $logo=null; foreach(['logo_cloud','logoUrlCloud','logo_cdn','logo','logo_url','imagen_logo','logoEvento'] as $k) if(!empty($row[$k])){$logo=(string)$row[$k]; break;}
  $cands=['flyer_cloud','poster_cloud','flyer_cdn','flyer','flyer_url','poster','poster_url','flyer_evento','flyer_img','portada_cloud','banner_cloud','imagen_portada_cloud','bg_cdn','portada','banner','imagen_portada','imagen','bg_url','fondo','background','background_image'];
  $bg=null; foreach($cands as $k) if(!empty($row[$k]) && is_image_path($row[$k])){$bg=(string)$row[$k]; break;}
  if(!$bg && $logo && is_image_path($logo)) $bg=$logo;
  if($bg && !preg_match('~^https?://~i',$bg) && strpos($bg,'data:image/')!==0){
    $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http'; $host=$_SERVER['HTTP_HOST']??'localhost'; $bg=rtrim($scheme.'://'.$host,'/').'/'.ltrim($bg,'/');
  }
  return ['nombre'=>$nombre,'logo'=>$logo?:null,'bg'=>$bg?:null,'raw'=>$row];
}

/* =========================================================
   evento_id contextual
   ========================================================= */
$evento_id_post = isset($_POST['evento_id']) && ctype_digit((string)$_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$evento_id_get  = isset($_GET['evento_id'])  && ctype_digit((string)$_GET['evento_id'])  ? (int)$_GET['evento_id']  : 0;
$evento_id_ref  = 0;
if (!$evento_id_post && !$evento_id_get && !empty($_SERVER['HTTP_REFERER'])) {
  $ref = parse_url($_SERVER['HTTP_REFERER']); if(!empty($ref['query'])){ parse_str($ref['query'],$qref); if(!empty($qref['evento_id']) && ctype_digit((string)$qref['evento_id'])) $evento_id_ref=(int)$qref['evento_id']; }
}
if ($evento_id_post>0)      $_SESSION['evento_id_actual']=$evento_id_post;
elseif ($evento_id_get>0)   $_SESSION['evento_id_actual']=$evento_id_get;
elseif ($evento_id_ref>0)   $_SESSION['evento_id_actual']=$evento_id_ref;

$evento_id_ctx  = (int)($_SESSION['evento_id_actual'] ?? 0);
$evento_presente = $evento_id_ctx > 0;

/* ===== Meta del evento ===== */
$ev        = $evento_presente ? get_evento_meta($conexion,$evento_id_ctx) : ['nombre'=>null,'logo'=>null,'bg'=>null];
$EV_NOMBRE = $ev['nombre'] ?: 'Evento';
$EV_LOGO   = $ev['logo']   ?: null;
$EV_BG     = $ev['bg']     ?: null;

/* URL para compartir */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path   = strtok($_SERVER['PHP_SELF'] ?? '/agregar_competidor_evento.php','?');
$share_url = $scheme.'://'.$host.$path.($evento_presente ? ('?evento_id='.$evento_id_ctx) : '');

$CLOUDY_ON = cloud_configured();

/* ================= POST: guardar competidor ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $evento_id = $evento_id_ctx;
  if ($evento_id <= 0) {
    $_SESSION['flash_error'] = 'Falta evento_id. Abrí el formulario desde el evento o usá el link compartido.';
    header('Location: '.$path.($evento_id_ctx>0 ? '?evento_id='.$evento_id_ctx : ''));
    exit;
  }

  // Datos base
  $apellido  = post('apellido');
  $nombre    = post('nombre');
  $dni       = preg_replace('/\D+/', '', post('dni'));
  $fecha_nac = post('fecha_nacimiento');
  $edad      = toIntOrNull(post('edad'));
  if ($edad === null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nac)) {
    $hoy = new DateTime('now'); $nac = DateTime::createFromFormat('Y-m-d', $fecha_nac);
    if ($nac) { $diff = $hoy->diff($nac); $edad = max(0, (int)$diff->y); }
  }
  $sexo = post('sexo');
  $escuela_nombre = post('escuela_nombre');

  // Ubicación
  $provincia    = post('provincia');
  $localidad    = post('localidad');
  $provincia_id = toIntOrNull(post('provincia_id'));
  $localidad_id = toIntOrNull(post('localidad_id'));

  // Pagos
  $habilitar_pago      = (post('habilitar_pago') !== '');
  $monto_inscripcion   = $habilitar_pago ? post('pago_inscripcion') : '0.00';
  if ($monto_inscripcion === '' || !is_numeric(str_replace(',','.', $monto_inscripcion))) $monto_inscripcion='0.00';
  $alias_transferencia = $habilitar_pago ? post('alias_transferencia') : '';
  $telefono_organizador= $habilitar_pago ? post('telefono_organizador') : '';
  $comprobante_url     = null;

  // Selecciones
  $modalidad_id_in         = toIntOrNull(post('modalidad_id'));
  $disciplina_id_in        = toIntOrNull(post('disciplina_id'));
  $categoria_tecnica_id_in = toIntOrNull(post('categoria_tecnica_id'));
  $division_id_in          = toIntOrNull(post('division_id'));
  $categoria_peso_id_in    = toIntOrNull(post('categoria_peso_id'));

  // Ranking
  $wins = max(0,(int)toIntOrNull(post('wins')));
  $loss = max(0,(int)toIntOrNull(post('losses')));
  $draw = max(0,(int)toIntOrNull(post('draws')));
  $nc   = max(0,(int)toIntOrNull(post('no_contest')));
  $total= $wins+$loss+$draw+$nc;

  // Validaciones mínimas
  if ($apellido==='' || $nombre==='' || $dni==='') {
    $_SESSION['flash_error']='Apellido, Nombre y DNI son obligatorios.';
    header('Location: '.$path.'?evento_id='.$evento_id); exit;
  }
  if ($fecha_nac!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_nac)) {
    $_SESSION['flash_error']='Fecha de nacimiento inválida (YYYY-MM-DD).';
    header('Location: '.$path.'?evento_id='.$evento_id); exit;
  }
  if (existe_dni_evento($conexion,$evento_id,$dni)) {
    $_SESSION['flash_error']='Ese DNI ya está inscripto en este evento.';
    header('Location: '.$path.'?evento_id='.$evento_id); exit;
  }

  // Normalizar FKs
  $modalidad_id         = fk_ensure_id($conexion,'modalidades_evento',$modalidad_id_in);
  $disciplina_id        = fk_ensure_id($conexion,'disciplinas_evento',$disciplina_id_in);
  $categoria_tecnica_id = fk_ensure_id($conexion,'categorias_tecnicas_evento',$categoria_tecnica_id_in);
  $division_id          = fk_ensure_id($conexion,'divisiones_evento',$division_id_in);
  $categoria_peso_id    = fk_ensure_id($conexion,'categorias_peso_evento',$categoria_peso_id_in);

  // Logo/Fotografía: usa archivo si subís; si no, conserva URL ingresada
  $escuela_logo_url_in  = post('escuela_logo_url');
  $foto_competidor_url_in = post('foto_competidor_url');

  $escuela_logo_up    = save_upload('escuela_logo', $evento_id);
  $foto_competidor_up = save_upload('foto_competidor', $evento_id);
  if ($habilitar_pago) { $comprobante_url = save_upload('comprobante_pago',$evento_id); }

  $escuela_logo    = $escuela_logo_up    ?: ($escuela_logo_url_in    ?: null);
  $foto_competidor = $foto_competidor_up ?: ($foto_competidor_url_in ?: null);

  // Strings auxiliares
  $cat_tec_str = cat_tecnica_por_total($total);
  $div_str=null;
  if ($edad !== null) {
    if ($edad < 12) $div_str = 'Infantil';
    elseif ($edad < 18) $div_str = 'Juvenil';
    elseif ($edad < 26) $div_str = 'Adultos';
    elseif ($edad < 46) $div_str = 'Masters';
    else $div_str = 'Veteranos';
  }

  // Insert
  $row = [
    'evento_id'=>$evento_id,'apellido'=>$apellido,'nombre'=>$nombre,'dni'=>$dni,
    'fecha_nacimiento'=>($fecha_nac!==''?$fecha_nac:null),'edad'=>$edad,'sexo'=>$sexo?:null,
    'escuela_nombre'=>($escuela_nombre!==''?$escuela_nombre:null),
    'escuela_logo'=>$escuela_logo?:null,'foto_competidor'=>$foto_competidor?:null,
    'provincia'=>$provincia?:null,'localidad'=>$localidad?:null,
    'provincia_id'=>$provincia_id,'localidad_id'=>$localidad_id,
    'pago_inscripcion'=>(string)$monto_inscripcion,'alias_transferencia'=>$alias_transferencia?:null,
    'comprobante_url'=>$comprobante_url?:null,'telefono_organizador'=>$telefono_organizador?:null,
    'modalidad_id'=>$modalidad_id,'disciplina_id'=>$disciplina_id,
    'categoria_tecnica_id'=>$categoria_tecnica_id,'division_id'=>$division_id,'categoria_peso_id'=>$categoria_peso_id,
    'wins'=>$wins,'losses'=>$loss,'draws'=>$draw,'no_contest'=>$nc,
    'categoria_tecnica'=>$cat_tec_str,'division'=>$div_str
  ];
  $insert_id = insertar_competidor($conexion,$row);

  // Mensajes y link WA
  $_SESSION['ok_msg']='✅ Competidor guardado correctamente.';
  if ($habilitar_pago && $telefono_organizador && ($alias_transferencia || $comprobante_url)) {
    $tel = preg_replace('/\D+/','',$telefono_organizador);
    $msg = "Comprobante de inscripción%0ACompetidor: ".rawurlencode("$apellido $nombre")." (ID $insert_id)%0AAlias: ".rawurlencode($alias_transferencia?:'—')."%0AComprobante: ".rawurlencode($comprobante_url?:'—');
    $_SESSION['wa_link'] = $tel ? ("https://wa.me/".$tel."?text=".$msg) : null;
  } else { $_SESSION['wa_link'] = null; }

  header('Location: '.$path.'?evento_id='.$evento_id);
  exit;
}

/* ================= VISTA (GET) ================= */
$evento_id = $evento_id_ctx;
$wa_link = $_SESSION['wa_link'] ?? null; unset($_SESSION['wa_link']);
$upload_status = $_SESSION['upload_status'] ?? []; unset($_SESSION['upload_status']);
$idMuay = cat_id_by_nombre($conexion,'modalidades_evento','Muay Thai') ?? 7;

/* =================== HTML =================== */
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro de Competidor</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
      --card:#0f1720; --bd:#1f2a33; --line:#22313f; --accent:#0e7ad1;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,Arial,Helvetica,sans-serif}
    .wrap{max-width:980px;margin:0 auto;padding:14px}
    .card{background:var(--card);border:1px solid #1a2733;border-radius:12px;padding:12px;margin-top:10px}
    .grid{display:grid;gap:10px;grid-template-columns:1fr}
    @media(min-width:520px){ .grid{grid-template-columns:repeat(2,1fr)} }
    label{font-size:12px;color:#cfe7ff}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #182532;background:#0f1b25;color:var(--fg)}
    .btn{display:inline-block;padding:10px 12px;border-radius:8px;background:var(--accent);color:#fff;border:none;cursor:pointer}
    .alert{padding:10px;border-radius:8px;margin:10px 0}
    .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .info{background:#14202b;border:1px solid #2b4154;color:#cfe7ff}
    .hero{display:flex;align-items:center;gap:12px}
    .logo{width:64px;height:64px;border-radius:10px;object-fit:contain;background:#081016;display:flex;align-items:center;justify-content:center}
    .mut{opacity:.9;color:#9ecbff;font-size:.9rem}

    /* === Autocomplete === */
    .ac-wrap{position:relative}
    .ac-list{
      position:absolute; z-index:40; left:0; right:0; top:100%;
      background:#0b1620; border:1px solid #213245; border-radius:10px; margin-top:4px;
      max-height:260px; overflow:auto; box-shadow:0 10px 25px rgba(0,0,0,.35); display:none
    }
    .ac-item{padding:10px 12px; cursor:pointer; display:flex; gap:8px; align-items:center}
    .ac-item:hover{background:#132235}
    .ac-name{font-weight:700}
    .ac-sub{font-size:12px; color:#9bbad8}
    .ac-empty{padding:10px 12px; color:#9bbad8}

    .preview{display:flex;gap:10px;align-items:center;margin-top:6px}
    .preview img{width:72px;height:72px;border-radius:10px;object-fit:cover;background:#071019;border:1px solid #1a2a3a}
    .mut-btn{font-size:12px;background:none;border:none;color:#7fb6ff;cursor:pointer;padding:0 4px}
    .chip-lock{display:inline-flex; gap:6px; align-items:center; font-size:12px; padding:4px 8px; border-radius:999px; background:#152332; border:1px solid #22364b; color:#b7d2eb}
  </style>
  <?php if ($EV_BG): ?>
    <style>
      body::before{
        content:"";position:fixed;inset:0;z-index:-1;background-image:url('<?= h($EV_BG) ?>');
        background-size:cover;background-position:center;filter:brightness(.35) saturate(1.05);
      }
    </style>
  <?php endif; ?>
</head>
<body>
  <div class="wrap">
    <div class="card hero">
      <div class="logo">
        <?php if ($EV_LOGO): ?>
          <img src="<?= h($EV_LOGO) ?>" alt="logo" style="max-width:100%;max-height:100%;border-radius:8px">
        <?php else: ?>
          <span style="font-size:24px">🏆</span>
        <?php endif; ?>
      </div>
      <div>
        <div style="font-weight:800;font-size:18px"><?= h($EV_NOMBRE) ?></div>
        <div class="mut">Formulario de inscripción</div>
      </div>
    </div>

    <h2 style="margin-top:12px">🏅 Registro de Competidor</h2>

    <?php if ($CLOUDY_ON): ?>
      <div class="alert ok">☁️ Cloudinary <b>ACTIVO</b>: los archivos se subirán a la nube.</div>
    <?php else: ?>
      <div class="alert info">☁️ Cloudinary <b>INACTIVO</b>: los archivos se guardarán localmente.</div>
    <?php endif; ?>

    <div class="card">
      <b>🔗 Compartir formulario</b>
      <div style="display:flex;gap:8px;margin-top:8px">
        <input type="text" id="share_url" style="flex:1;padding:10px;border-radius:8px" readonly value="<?= h($share_url) ?>">
        <button class="btn" onclick="copyShare()">Copiar</button>
      </div>
    </div>

    <?php if (!empty($_SESSION['ok_msg'])): ?>
      <div class="alert ok"><?= h($_SESSION['ok_msg']); unset($_SESSION['ok_msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!$evento_presente): ?>
      <div class="alert bad">Falta <b>evento_id</b>. Abrí este formulario desde el enlace del evento.</div>
    <?php endif; ?>

    <?php if (!empty($wa_link)): ?>
      <div class="card">
        <b>📨 Enviar comprobante por WhatsApp</b>
        <div style="margin-top:8px"><a class="btn" href="<?= h($wa_link) ?>" target="_blank" rel="noopener">Abrir WhatsApp</a></div>
      </div>
    <?php endif; ?>

    <form action="<?= h($path) ?>?evento_id=<?= (int)$evento_id ?>" method="POST" enctype="multipart/form-data" id="form_comp">
      <input type="hidden" name="evento_id" value="<?= $evento_presente ? (int)$evento_id : '' ?>">

      <div class="card">
        <h3>Datos personales</h3>
        <div class="grid">
          <div class="ac-wrap">
            <label>Apellido (buscar en ranking)</label>
            <input type="text" name="apellido" id="apellido" required autocomplete="off" placeholder="Escribí el apellido">
            <div id="ac_list" class="ac-list"></div>
            <div id="lock_info" style="margin-top:6px;display:none" class="chip-lock">Campos bloqueados: Apellido, Nombre y Fecha de nacimiento</div>
          </div>
          <div><label>Nombre</label><input type="text" name="nombre" id="nombre" required></div>
          <div>
            <label>DNI</label>
            <input type="text" name="dni" id="dni" required inputmode="numeric" pattern="\d+" maxlength="12" placeholder="Sólo números">
            <div id="dni_msg" style="display:none;margin-top:6px"></div>
          </div>
          <div><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()"></div>
          <div><label>Edad</label><input type="number" name="edad" id="edad" readonly></div>
          <div>
            <label>Sexo</label>
            <select name="sexo" id="sexo">
              <option value="">Seleccionar</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option>
            </select>
          </div>
          <div><label>Provincia</label><select name="provincia" id="provincia" onchange="onProvinciaChange()"><option>Seleccionar provincia</option></select><input type="hidden" name="provincia_id" id="provincia_id"></div>
          <div><label>Localidad</label>
            <input list="dl_localidades" name="localidad" id="localidad" placeholder="Escribí tu localidad"><datalist id="dl_localidades"></datalist>
            <input type="hidden" name="localidad_id" id="localidad_id">
          </div>
          <div><label>Escuela / Gimnasio</label><input type="text" name="escuela_nombre" id="escuela_nombre"></div>

          <!-- LOGO ESCUELA -->
          <div>
            <label>Logo de la Escuela (subir archivo)</label>
            <input type="file" name="escuela_logo" id="escuela_logo_file" accept="image/*,application/pdf">
            <?php if (!empty($upload_status['escuela_logo'])): ?>
              <div style="margin-top:6px;color:<?= $upload_status['escuela_logo']['status']==='ok' ? '#b6f3d1' : '#ffb4b4' ?>"><?= h($upload_status['escuela_logo']['status']) ?></div>
            <?php endif; ?>
            <div class="preview">
              <img id="escuela_logo_preview" src="" alt="logo preview" style="display:none">
              <button type="button" class="mut-btn" onclick="clearLogo()">Borrar</button>
            </div>
            <label style="margin-top:8px">o URL del logo</label>
            <input type="url" name="escuela_logo_url" id="escuela_logo_url" placeholder="https://...">
          </div>

          <!-- FOTO COMPETIDOR -->
          <div>
            <label>Foto del Competidor (subir archivo)</label>
            <input type="file" name="foto_competidor" id="foto_competidor_file" accept="image/*">
            <?php if (!empty($upload_status['foto_competidor'])): ?>
              <div style="margin-top:6px;color:<?= $upload_status['foto_competidor']['status']==='ok' ? '#b6f3d1' : '#ffb4b4' ?>"><?= h($upload_status['foto_competidor']['status']) ?></div>
            <?php endif; ?>
            <div class="preview">
              <img id="foto_competidor_preview" src="" alt="foto preview" style="display:none">
              <button type="button" class="mut-btn" onclick="clearFoto()">Borrar</button>
            </div>
            <label style="margin-top:8px">o URL de la foto</label>
            <input type="url" name="foto_competidor_url" id="foto_competidor_url" placeholder="https://...">
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Ranking</h3>
        <div class="grid" style="grid-template-columns:repeat(3,1fr);">
          <div><label>Ganadas (W)</label><input type="number" min="0" name="wins" id="wins" value="0"></div>
          <div><label>Perdidas (L)</label><input type="number" min="0" name="losses" id="losses" value="0"></div>
          <div><label>Empates (D)</label><input type="number" min="0" name="draws" id="draws" value="0"></div>
        </div>
        <div class="grid" style="grid-template-columns:repeat(3,1fr);margin-top:8px">
          <div><label>Sin decisión (NC)</label><input type="number" min="0" name="no_contest" id="no_contest" value="0"></div>
          <div><label>Total</label><input type="number" id="total_fights" readonly value="0"></div>
          <div>
            <label>Categoría técnica (automática)</label>
            <select id="categoria_tecnica_id_view" disabled><option value="1">A</option><option value="2">B</option><option value="3">C</option><option value="4" selected>N</option></select>
            <input type="hidden" name="categoria_tecnica_id" id="categoria_tecnica_id" value="4">
            <div class="mut" style="margin-top:6px">A: ≥10 • B: 5–9 • C: 1–4 • N: 0</div>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Inscripción</h3>
        <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
          <div>
            <label>Modalidad</label>
            <select name="modalidad_id">
              <option value="1">Exhibición</option>
              <option value="2">Boxeo</option>
              <option value="3">Full Contact</option>
              <option value="4">Low Kick</option>
              <option value="5">K1</option>
              <option value="6">MMA</option>
              <option value="<?= (int)$idMuay ?>">Muay Thai</option>
            </select>
          </div>
          <div>
            <label>Disciplina</label>
            <select name="disciplina_id">
              <option value="1">Exhibiciones</option>
              <option value="2">Amateurs</option>
              <option value="3">Proam</option>
              <option value="4">Pro</option>
            </select>
          </div>
          <div>
            <label>División (automática)</label>
            <select id="division_id_view" disabled><option>Infantil</option><option>Juvenil</option><option selected>Adultos</option><option>Masters</option><option>Veteranos</option></select>
            <input type="hidden" name="division_id" id="division_id" value="">
          </div>
          <div>
            <label>Categoría por Peso</label>
            <select name="categoria_peso_id" id="categoria_peso_id">
              <option value="">Seleccione edad y sexo primero</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Pagos</h3>
        <div class="grid" style="grid-template-columns:repeat(2,1fr);gap:12px">
          <div><label>Alias de transferencia</label><input type="text" name="alias_transferencia"></div>
          <div><label>WhatsApp organizador</label><input type="text" name="telefono_organizador" maxlength="15" placeholder="54926xxxxxxxx"></div>
          <div>
            <label>Comprobante (IMG/PDF)</label>
            <input type="file" name="comprobante_pago" accept="image/*,application/pdf">
          </div>
          <div><label>Monto inscripción ($)</label><input type="number" name="pago_inscripcion" step="0.01" value="0.00"></div>
        </div>
      </div>

      <div style="margin-top:12px">
        <button type="submit" class="btn" <?= (!$evento_presente ? 'disabled' : '') ?>>✅ Guardar Competidor</button>
      </div>
    </form>
  </div>

  <script>
    function copyShare(){ const el=document.getElementById('share_url'); navigator.clipboard?.writeText(el.value).catch(()=>{ el.select(); document.execCommand('copy'); }); }

    const PROVINCIAS=["Buenos Aires","CABA","Catamarca","Chaco","Chubut","Córdoba","Corrientes","Entre Ríos","Formosa","Jujuy","La Pampa","La Rioja","Mendoza","Misiones","Neuquén","Río Negro","Salta","San Juan","San Luis","Santa Cruz","Santa Fe","Santiago del Estero","Tierra del Fuego","Tucumán"];
    const PROV_IDX={}; PROVINCIAS.forEach((p,i)=>PROV_IDX[p]=i+1);
    function cargarProvincias(){ const sel=document.getElementById('provincia'); sel.innerHTML='<option value="">Seleccionar provincia</option>'; PROVINCIAS.forEach(p=>{ const o=document.createElement('option'); o.value=p; o.textContent=p; sel.appendChild(o); }); }
    function onProvinciaChange(){ const p=document.getElementById('provincia').value; document.getElementById('provincia_id').value=PROV_IDX[p]||''; document.getElementById('dl_localidades').innerHTML=''; }
    cargarProvincias();

    function calcularEdad(){
      const f=document.getElementById('fecha_nacimiento').value; if(!f) return;
      const hoy=new Date(), nac=new Date(f);
      let e=hoy.getFullYear()-nac.getFullYear(); const m=hoy.getMonth()-nac.getMonth();
      if (m<0 || (m===0 && hoy.getDate()<nac.getDate())) e--;
      document.getElementById('edad').value = Math.max(0,e);
      let d = "3"; if(e<12) d="1"; else if(e<18) d="2"; else if(e<26) d="3"; else if(e<46) d="4"; else d="5";
      document.getElementById('division_id_view').selectedIndex = ({"1":0,"2":1,"3":2,"4":3,"5":4}[d]||2);
      document.getElementById('division_id').value = d;
      cargarCategoriasPeso();
    }
    function cargarCategoriasPeso(){
      const e=document.getElementById('edad').value; const s=document.getElementById('sexo').value; if(!e||!s) return;
      fetch('obtener_categorias_por_peso.php?edad='+encodeURIComponent(e)+'&sexo='+encodeURIComponent(s))
        .then(r=>r.text()).then(html=>{ document.getElementById('categoria_peso_id').innerHTML = html; }).catch(()=>{});
    }
    document.getElementById('sexo')?.addEventListener('change', cargarCategoriasPeso);

    function recalcRanking(){
      const w=+document.getElementById('wins').value||0, l=+document.getElementById('losses').value||0, d=+document.getElementById('draws').value||0, n=+document.getElementById('no_contest').value||0;
      const tot=w+l+d+n; document.getElementById('total_fights').value = tot;
      let cat='4'; if(tot>=10) cat='1'; else if(tot>=5) cat='2'; else if(tot>=1) cat='3';
      document.getElementById('categoria_tecnica_id_view').value = cat; document.getElementById('categoria_tecnica_id').value = cat;
    }
    ['wins','losses','draws','no_contest'].forEach(id=> document.getElementById(id)?.addEventListener('input', recalcRanking)); recalcRanking();

    // ================== Previews de imágenes ==================
    const elLogoFile=document.getElementById('escuela_logo_file');
    const elLogoURL =document.getElementById('escuela_logo_url');
    const elLogoPrev=document.getElementById('escuela_logo_preview');
    const elFotoFile=document.getElementById('foto_competidor_file');
    const elFotoURL =document.getElementById('foto_competidor_url');
    const elFotoPrev=document.getElementById('foto_competidor_preview');

    function setPreview(imgEl, src){
      if (src){ imgEl.src=src; imgEl.style.display='block'; } else { imgEl.src=''; imgEl.style.display='none'; }
    }
    elLogoFile?.addEventListener('change', e=>{
      const f=e.target.files?.[0]; if(!f){ setPreview(elLogoPrev,''); return; }
      const reader=new FileReader(); reader.onload=()=> setPreview(elLogoPrev, reader.result); reader.readAsDataURL(f);
      elLogoURL.value=''; // si subís archivo, limpiamos URL
    });
    elLogoURL?.addEventListener('input', e=>{ if(e.target.value) setPreview(elLogoPrev, e.target.value); });

    elFotoFile?.addEventListener('change', e=>{
      const f=e.target.files?.[0]; if(!f){ setPreview(elFotoPrev,''); return; }
      const reader=new FileReader(); reader.onload=()=> setPreview(elFotoPrev, reader.result); reader.readAsDataURL(f);
      elFotoURL.value='';
    });
    elFotoURL?.addEventListener('input', e=>{ if(e.target.value) setPreview(elFotoPrev, e.target.value); });

    function clearLogo(){ elLogoFile.value=''; elLogoURL.value=''; setPreview(elLogoPrev,''); }
    function clearFoto(){ elFotoFile.value=''; elFotoURL.value=''; setPreview(elFotoPrev,''); }

    // DNI validation
    const dniInput = document.getElementById('dni'), dniMsg = document.getElementById('dni_msg'), btnSubmit = document.querySelector('button[type="submit"]');
    const eventoId = document.querySelector('input[name="evento_id"]')?.value || '';
    function setSubmitEnabled(x){ if(btnSubmit) btnSubmit.disabled = !x; }
    async function validarDNI(){
      dniMsg.style.display='none'; setSubmitEnabled(true);
      const dni = (dniInput?.value || '').trim(); if(!dni || !eventoId) return;
      try{
        const res = await fetch('validar_dni_evento.php?evento_id='+encodeURIComponent(eventoId)+'&dni='+encodeURIComponent(dni));
        if (!res.ok) return;
        const data = await res.json();
        if (data.exists) { dniMsg.textContent = '⚠️ Este DNI ya está inscripto en este evento.'; dniMsg.style.display='block'; dniMsg.className='alert bad'; setSubmitEnabled(false); }
      } catch {}
    }
    dniInput?.addEventListener('input', e=> e.target.value = (e.target.value || '').replace(/\D+/g,''));
    dniInput?.addEventListener('blur', validarDNI);

    /* ==============================
       AUTOCOMPLETE desde api_ranking_buscar.php
       ============================== */
    const acList = document.getElementById('ac_list');
    const apeIn  = document.getElementById('apellido');
    const nomIn  = document.getElementById('nombre');
    const fnIn   = document.getElementById('fecha_nacimiento');
    const edadIn = document.getElementById('edad');
    const sexoIn = document.getElementById('sexo');
    const provIn = document.getElementById('provincia');
    const provId = document.getElementById('provincia_id');
    const locIn  = document.getElementById('localidad');
    const escuelaIn = document.getElementById('escuela_nombre');
    const lockInfo = document.getElementById('lock_info');

    let acTimer=null;
    apeIn.addEventListener('input', (e)=>{
      unlockNameBirthIfNeeded(); // si el usuario vuelve a escribir, desbloquea
      const q=(e.target.value||'').trim();
      if (acTimer) clearTimeout(acTimer);
      if (q.length<2){ acList.style.display='none'; return; }
      acTimer=setTimeout(()=> doLookup(q), 220);
    });
    document.addEventListener('click', (ev)=>{ if(!acList.contains(ev.target) && ev.target!==apeIn){ acList.style.display='none'; }});

    async function doLookup(q){
      try{
        const url='api_ranking_buscar.php?q='+encodeURIComponent(q);
        const r = await fetch(url, {headers:{'Accept':'application/json'}});
        const ok = r.ok;
        const data = ok ? await r.json() : [];
        renderAC(Array.isArray(data)?data:[]);
      }catch(e){ renderAC([]); }
    }
    function renderAC(items){
      if (!items.length){
        acList.innerHTML='<div class="ac-empty">Sin coincidencias…</div>';
        acList.style.display='block';
        return;
      }
      acList.innerHTML = items.slice(0,30).map((c,i)=>(
        `<div class="ac-item" data-i="${i}">
          <div class="ac-name">${escapeHtml(c.apellido||'')} ${escapeHtml(c.nombre||'')}</div>
          <div class="ac-sub">DNI: ${escapeHtml(c.dni||'—')} • Nac: ${escapeHtml(c.fecha_nacimiento||'—')} • ${escapeHtml(c.escuela_nombre||'')}</div>
        </div>`
      )).join('');
      acList.style.display='block';
      [...acList.querySelectorAll('.ac-item')].forEach((el,idx)=> el.addEventListener('click',()=> applyCompetidor(items[idx])));
    }
    function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

    function applyCompetidor(c){
      // Completar campos base
      if (c.apellido) apeIn.value = c.apellido;
      if (c.nombre)   nomIn.value = c.nombre;
      if (c.dni)      dniInput.value = String(c.dni).replace(/\D+/g,'');
      if (c.fecha_nacimiento){ fnIn.value = c.fecha_nacimiento; calcularEdad(); }
      if (c.sexo){ sexoIn.value = (c.sexo||'').toLowerCase()==='femenino'?'femenino':((c.sexo||'').toLowerCase()==='masculino'?'masculino':''); }
      if (c.provincia && PROV_IDX[c.provincia]){ provIn.value = c.provincia; provId.value = PROV_IDX[c.provincia]; }
      if (c.localidad){ locIn.value = c.localidad; }
      if (c.escuela_nombre){ escuelaIn.value = c.escuela_nombre; }

      // Ranking si viene
      if (c.wins!=null)   document.getElementById('wins').value = +c.wins||0;
      if (c.losses!=null) document.getElementById('losses').value = +c.losses||0;
      if (c.draws!=null)  document.getElementById('draws').value = +c.draws||0;
      if (c.no_contest!=null) document.getElementById('no_contest').value = +c.no_contest||0;
      recalcRanking();

      // Foto/Logo por URL (si existen en el ranking)
      if (c.escuela_logo_url){ document.getElementById('escuela_logo_url').value = c.escuela_logo_url; setPreview(elLogoPrev, c.escuela_logo_url); elLogoFile.value=''; }
      if (c.foto_competidor_url){ document.getElementById('foto_competidor_url').value = c.foto_competidor_url; setPreview(elFotoPrev, c.foto_competidor_url); elFotoFile.value=''; }

      // Bloquear lo solicitado
      apeIn.readOnly = true;
      nomIn.readOnly = true;
      fnIn.readOnly  = true;
      lockInfo.style.display='inline-flex';

      acList.style.display='none';
      validarDNI();
      cargarCategoriasPeso();
    }

    function unlockNameBirthIfNeeded(){
      if (apeIn.readOnly){
        // Si el usuario vuelve a escribir, desbloquea por si quiere cargar uno nuevo
        apeIn.readOnly = false;
        nomIn.readOnly = false;
        fnIn.readOnly  = false;
        lockInfo.style.display='none';
      }
    }
  </script>
</body>
</html>
