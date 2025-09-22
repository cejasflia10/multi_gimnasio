<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

/* =========================================================
   Cloudinary (opcional) — “Cloudy”
   ========================================================= */
// Opción 1 (recomendada): usar variable de entorno CLOUDINARY_URL
//    cloudinary://API_KEY:API_SECRET@CLOUD_NAME
// Opción 2: activar constantes acá (activadas con tus datos):
const CLOUD_ENABLED    = true;                 // ← activado
const CLOUD_NAME       = 'ddfugds9b';          // ← tu cloud name
const CLOUD_API_KEY    = '657814174747186';    // ← tu API key
const CLOUD_API_SECRET = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c'; // ← tu API secret

$CLOUD_ERR = null;

function cloud_init(): void {
  static $inited=false; if ($inited) return; $inited=true;
  $enabled = CLOUD_ENABLED || getenv('CLOUDINARY_URL');
  if (!$enabled) return;
  $autoload1 = __DIR__.'/vendor/autoload.php';
  $autoload2 = dirname(__DIR__).'/vendor/autoload.php';
  if (file_exists($autoload1)) require_once $autoload1;
  elseif (file_exists($autoload2)) require_once $autoload2;
}
function cloud_configured(): bool {
  $url = getenv('CLOUDINARY_URL');
  if ($url && preg_match('~cloudinary://.+@.+~',$url)) return true;
  if (CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) return true;
  return false;
}
/** Sube archivo local a Cloudinary y devuelve secure_url (o null si falla) */
function cloud_upload(string $abs_path, string $folder, string $public): ?string {
  global $CLOUD_ERR;
  cloud_init();
  if (!cloud_configured()) return null;
  try {
    if (!getenv('CLOUDINARY_URL') && CLOUD_ENABLED) {
      \Cloudinary\Configuration\Configuration::instance([
        'cloud'=>[
          'cloud_name'=>CLOUD_NAME,
          'api_key'=>CLOUD_API_KEY,
          'api_secret'=>CLOUD_API_SECRET
        ],
        'secure'=>true
      ]);
    }
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $res = $uploader->upload($abs_path, [
      'folder'        => $folder,
      'public_id'     => $public,
      'overwrite'     => true,
      'resource_type' => 'auto'
    ]);
    return $res['secure_url'] ?? null;
  } catch (\Throwable $e) {
    $CLOUD_ERR = $e->getMessage();
    return null;
  }
}

/* ===== Seguridad conexión ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function is_image_path($p): bool {
  if (!$p) return false;
  $ext = strtolower(pathinfo((string)$p, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','webp','gif'], true);
}

/** Guarda archivo local; intenta Cloudinary y guarda estado de subida por campo en $_SESSION['upload_status'] */
function save_upload(string $field, int $evento_id): ?string {
  if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  $tmp = $_FILES[$field]['tmp_name'];
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $name = basename((string)$_FILES[$field]['name']);
  $ext0 = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $permitidos = ['jpg','jpeg','png','webp','gif','pdf'];
  $ext = in_array($ext0,$permitidos,true) ? $ext0 : 'jpg';

  $base = preg_replace('/[^\p{L}\p{N}\-_]+/u','-', pathinfo($name, PATHINFO_FILENAME));
  $base = trim($base,'-_') ?: 'archivo';

  // 1) Guardar local (backup)
  $dir = __DIR__.'/uploads/evento_'.$evento_id;
  if (!is_dir($dir)) @mkdir($dir,0775,true);
  $filename = $field.'_'.date('Ymd_His').'_'.mt_rand(1000,9999).'.'.$ext;
  $abs_dest = $dir.'/'.$filename;
  if (!@move_uploaded_file($tmp,$abs_dest)) return null;
  $final_url = 'uploads/evento_'.$evento_id.'/'.$filename;

  // 2) Intentar Cloudinary (si está configurado)
  $cloud_attempted = false;
  $cloud_ok = false;
  if (cloud_configured()) {
    $cloud_attempted = true;
    $url = cloud_upload($abs_dest, 'multi_gimnasio/evento_'.$evento_id, $field.'_'.$base.'_'.date('Ymd_His'));
    if ($url) { $cloud_ok = true; $final_url = $url; }
  }

  // 3) Guardar estado para feedback bajo el input
  if (!isset($_SESSION['upload_status'])) $_SESSION['upload_status'] = [];
  if ($cloud_attempted) {
    $_SESSION['upload_status'][$field] = [
      'status'   => $cloud_ok ? 'ok' : 'fail',
      'filename' => $name,
    ];
  } else {
    unset($_SESSION['upload_status'][$field]);
  }

  return $final_url;
}

function cat_tecnica_por_total(int $t): string { return $t>=10?'A':($t>=5?'B':($t>=1?'C':'N')); }

/* ===== FK helpers ===== */
function fk_first_id(mysqli $db, string $table): ?int {
  $res = $db->query("SELECT id FROM `$table` ORDER BY id ASC LIMIT 1");
  if ($res && $row=$res->fetch_assoc()) return (int)$row['id'];
  return null;
}
function fk_ensure_id(mysqli $db, string $table, ?int $id): ?int {
  $id = $id ?? 0;
  if ($id>0) {
    if ($st=$db->prepare("SELECT 1 FROM `$table` WHERE id=? LIMIT 1")){
      $st->bind_param('i',$id); $st->execute();
      $ok = ($r=$st->get_result()) && $r->num_rows>0; $st->close();
      if ($ok) return $id;
    }
  }
  return fk_first_id($db,$table);
}

/* === Buscar id por nombre (para Muay Thai sin romper nada) === */
function cat_id_by_nombre(mysqli $db, string $table, string $nombre): ?int {
  if (!has_col($db,$table,'nombre') || !has_col($db,$table,'id')) return null;
  if ($st=$db->prepare("SELECT id FROM `$table` WHERE nombre=? LIMIT 1")){
    $st->bind_param('s',$nombre); $st->execute();
    $r=$st->get_result(); $id = ($r&&$r->num_rows)?(int)$r->fetch_assoc()['id']:null; $st->close();
    return $id;
  }
  return null;
}

/* ===== Duplicado por (evento_id, dni) ===== */
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $t = 'competidores_evento';
  if (!has_col($db,$t,'dni')) return false;
  if (has_col($db,$t,'evento_id')) {
    $st=$db->prepare("SELECT 1 FROM `$t` WHERE evento_id=? AND dni=? LIMIT 1");
    $st->bind_param('is',$evento_id,$dni);
  } else {
    $st=$db->prepare("SELECT 1 FROM `$t` WHERE dni=? LIMIT 1");
    $st->bind_param('s',$dni);
  }
  $st->execute(); $r=$st->get_result(); $existe = $r && $r->num_rows>0; $st->close();
  return $existe;
}

/* ===== Inserción segura ===== */
function insertar_competidor(mysqli $db, array $row): int {
  $t='competidores_evento'; $cols=[];$vals=[];$types='';
  $cands=[
    'evento_id'=>'i','apellido'=>'s','nombre'=>'s','dni'=>'s','fecha_nacimiento'=>'s','edad'=>'i','sexo'=>'s',
    'escuela_nombre'=>'s','escuela_logo'=>'s','foto_competidor'=>'s',
    'provincia'=>'s','localidad'=>'s','provincia_id'=>'i','localidad_id'=>'i',
    'pago_inscripcion'=>'s','alias_transferencia'=>'s','comprobante_url'=>'s','telefono_organizador'=>'s',
    'modalidad_id'=>'i','disciplina_id'=>'i','categoria_tecnica_id'=>'i','division_id'=>'i','categoria_peso_id'=>'i',
    'wins'=>'i','losses'=>'i','draws'=>'i','no_contest'=>'i',
    'categoria_tecnica'=>'s','division'=>'s'
  ];
  foreach($cands as $c=>$tp){ if(has_col($db,$t,$c)){ $cols[]="`$c`"; $vals[]=$row[$c]??null; $types.=$tp; } }
  if(!$cols) { http_response_code(500); exit('❌ Tabla sin columnas esperadas.'); }
  $ph=rtrim(str_repeat('?,',count($cols)),',');
  $sql="INSERT INTO `$t`(".implode(',',$cols).") VALUES($ph)";
  $st=$db->prepare($sql); if(!$st){ http_response_code(500); exit('❌ SQL: '.$db->error); }
  $bind=[$types]; foreach($vals as $k=>&$v){ $bind[]=&$v; }
  call_user_func_array([$st,'bind_param'],$bind);
  if(!$st->execute()){ http_response_code(500); exit('❌ Exec: '.$st->error); }
  $st->close(); return (int)$db->insert_id;
}

/* ========== Metadata del evento (NOMBRE, LOGO y FONDO/FLYER) ========== */
function get_evento_meta(mysqli $db, int $evento_id): array {
  $row=[]; if($evento_id>0){
    if($st=$db->prepare("SELECT * FROM `eventos_deportivos` WHERE id=? LIMIT 1")){
      $st->bind_param('i',$evento_id); $st->execute();
      $res=$st->get_result(); if($res && $res->num_rows) $row=$res->fetch_assoc();
      $st->close();
    }
  }
  $nombre_keys=['nombre','titulo','title'];

  $logo_cloud=['logo_cloud','logoUrlCloud','logo_cdn'];
  $logo_local=['logo','logo_url','imagen_logo','logoEvento'];

  $flyer_cloud=['flyer_cloud','poster_cloud','flyer_cdn'];
  $flyer_local=['flyer','flyer_url','poster'];
  $bg_cloud=['portada_cloud','banner_cloud','imagen_portada_cloud','bg_cdn'];
  $bg_local=['portada','banner','imagen_portada','imagen','bg_url','fondo'];

  $nombre='Evento'; foreach($nombre_keys as $k) if(!empty($row[$k])){ $nombre=(string)$row[$k]; break; }

  $logo=null; foreach($logo_cloud as $k) if(!empty($row[$k])){ $logo=(string)$row[$k]; break; }
  if(!$logo){ foreach($logo_local as $k) if(!empty($row[$k])){ $logo=(string)$row[$k]; break; } }

  $bg=null;
  foreach($flyer_cloud as $k) if(!empty($row[$k])){ $bg=(string)$row[$k]; break; }
  if(!$bg){ foreach($flyer_local as $k) if(!empty($row[$k])){ $bg=(string)$row[$k]; break; } }
  if(!$bg){ foreach($bg_cloud as $k) if(!empty($row[$k])){ $bg=(string)$row[$k]; break; } }
  if(!$bg){ foreach($bg_local as $k) if(!empty($row[$k])){ $bg=(string)$row[$k]; break; } }

  if($bg && !is_image_path($bg)) $bg=null;

  return ['nombre'=>$nombre,'logo'=>$logo?:null,'bg'=>$bg?:null,'raw'=>$row];
}

/* =========================================================
   evento_id contextual (POST → GET → REFERER → SESSION)
   ========================================================= */
$evento_id_post = isset($_POST['evento_id']) && ctype_digit((string)$_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
$evento_id_get  = isset($_GET['evento_id'])  && ctype_digit((string)$_GET['evento_id'])  ? (int)$_GET['evento_id']  : 0;
$evento_id_ref  = 0;
if (!$evento_id_post && !$evento_id_get && !empty($_SERVER['HTTP_REFERER'])) {
  $ref = parse_url($_SERVER['HTTP_REFERER']);
  if (!empty($ref['query'])) {
    parse_str($ref['query'], $qref);
    if (!empty($qref['evento_id']) && ctype_digit((string)$qref['evento_id'])) $evento_id_ref=(int)$qref['evento_id'];
  }
}
if ($evento_id_post>0)      $_SESSION['evento_id_actual']=$evento_id_post;
elseif ($evento_id_get>0)   $_SESSION['evento_id_actual']=$evento_id_get;
elseif ($evento_id_ref>0)   $_SESSION['evento_id_actual']=$evento_id_ref;

$evento_id_ctx  = (int)($_SESSION['evento_id_actual'] ?? 0);
$evento_presente= $evento_id_ctx>0;

/* ===== Cargar meta del evento ===== */
$ev        = $evento_presente ? get_evento_meta($conexion,$evento_id_ctx) : ['nombre'=>null,'logo'=>null,'bg'=>null];
$EV_NOMBRE = $ev['nombre'] ?: 'Evento';
$EV_LOGO   = $ev['logo']   ?: null;
$EV_BG     = $ev['bg']     ?: null;

/* URL canónica para compartir (con evento_id) */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path   = strtok($_SERVER['PHP_SELF'] ?? '/agregar_competidor_evento.php','?');
$share_url = $scheme.'://'.$host.$path.($evento_presente ? ('?evento_id='.$evento_id_ctx) : '');

/* ===== Estado de Cloudinary para mostrar en UI ===== */
$CLOUDY_ON = cloud_configured();

/* ===== POST: guardar competidor ===== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $evento_id = $evento_id_ctx;
  if ($evento_id <= 0) {
    $_SESSION['flash_error']='Falta evento_id. Abrí el formulario desde el evento o usá el link compartido.';
    header('Location: '.$path.($evento_id_ctx>0?'?evento_id='.$evento_id_ctx:'')); exit;
  }

  // Datos base
  $apellido  = post('apellido');
  $nombre    = post('nombre');
  $dni       = preg_replace('/\D+/', '', post('dni'));
  $fecha_nac = post('fecha_nacimiento');
  $edad      = toIntOrNull(post('edad'));
  if ($edad===null && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha_nac)) {
    $hoy=new DateTime('now'); $nac=DateTime::createFromFormat('Y-m-d',$fecha_nac);
    if($nac){ $diff=$hoy->diff($nac); $edad=max(0,(int)$diff->y); }
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

  // Subidas (van a Cloudinary si está activo)
  $escuela_logo    = save_upload('escuela_logo', $evento_id);
  $foto_competidor = save_upload('foto_competidor', $evento_id);
  if ($habilitar_pago) { $comprobante_url = save_upload('comprobante_pago',$evento_id); }

  // Strings
  $cat_tec_str = cat_tecnica_por_total($total);
  $div_str=null;
  if ($edad!==null) {
    if ($edad<12) $div_str='Infantil';
    elseif ($edad<18) $div_str='Juvenil';
    elseif ($edad<26) $div_str='Adultos';
    elseif ($edad<46) $div_str='Masters';
    else $div_str='Veteranos';
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
    $tel=preg_replace('/\D+/','',$telefono_organizador);
    $msg="Comprobante de inscripción%0ACompetidor: ".rawurlencode("$apellido $nombre")." (ID $insert_id)%0AAlias: ".rawurlencode($alias_transferencia?:'—')."%0AComprobante: ".rawurlencode($comprobante_url?:'—');
    $_SESSION['wa_link'] = $tel ? ("https://wa.me/".$tel."?text=".$msg) : null;
  } else { $_SESSION['wa_link']=null; }

  header('Location: '.$path.'?evento_id='.$evento_id); exit;
}

/* ===== Vista (GET) ===== */
$evento_id = $evento_id_ctx;
$wa_link = $_SESSION['wa_link'] ?? null;
unset($_SESSION['wa_link']);

/* Feedback de subidas Cloudinary (verde/rojo debajo de inputs) */
$upload_status = $_SESSION['upload_status'] ?? [];
unset($_SESSION['upload_status']);

/* ID real de Muay Thai (si existe), o 7 como backup */
$idMuay = cat_id_by_nombre($conexion,'modalidades_evento','Muay Thai') ?? 7;
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

    .grid, .grid-3, .row{ display:grid; gap:10px; grid-template-columns:1fr; }
    @media (min-width:520px){ .grid{grid-template-columns:repeat(2,1fr)} .row{grid-template-columns:repeat(2,1fr)} }
    @media (min-width:880px){ .grid-3{grid-template-columns:repeat(3,1fr)} }

    label{font-size:12px;color:#cfe7ff;letter-spacing:.2px}
    input,select{
      width:100%;padding:10px;border-radius:10px;border:1px solid var(--line);background:#111a24;color:var(--fg);
      font-size:15px; line-height:1.15;
    }
    input[type="file"]{padding:8px}
    input[readonly], select[disabled]{ opacity:.85; background:#0e1620; cursor:not-allowed; }

    .btn{display:inline-block;padding:12px 14px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;cursor:pointer}
    .btn[disabled]{opacity:.6;cursor:not-allowed}
    .alert{padding:10px 12px;border-radius:10px;margin:10px 0}
    .alert.ok{background:var(--ok);border:1px solid var(--okbd);color:var(--oktx)}
    .alert.bad{background:var(--bad);border:1px solid var(--badbd);color:var(--badt)}
    .alert.info{background:#14202b;border:1px solid #2b4154;color:#cfe7ff}

    .hero{
      position:relative;border:1px solid var(--bd);border-radius:16px;overflow:hidden;margin-bottom:10px;
      background:#101820;min-height:120px;display:flex;align-items:flex-end;padding:12px;
    }
    .hero .row{position:relative;z-index:2;display:flex;align-items:center;gap:12px;}
    .hero .logo{
      width:64px;height:64px;border-radius:12px;object-fit:contain;background:#0b1115;border:1px solid #1d2a33;padding:6px;
      box-shadow:0 8px 30px rgba(0,0,0,.35);
    }
    .hero .title{font-size:22px;font-weight:700;letter-spacing:.3px;text-shadow:0 2px 10px rgba(0,0,0,.5);}
    .share{display:flex;gap:8px;align-items:center;margin:8px 0 0}
    .share input{flex:1}

    /* Mensajes de estado de Cloudinary bajo inputs */
    .note-ok  { margin-top:6px; font-size:.9rem; color:#b6f3d1; }
    .note-bad { margin-top:6px; font-size:.9rem; color:#ffb4b4; }
    .mut { color:#cfe7ff; opacity:.9; }
  </style>
<?php if ($EV_BG): ?>
  <style>
    body::before{
      content:""; position:fixed; inset:0; z-index:-1;
      background-image:url('<?= h($EV_BG) ?>');
      background-size:cover; background-position:center; background-attachment:fixed;
      filter:brightness(.35) saturate(1.05); transform:scale(1.02);
    }
    .wrap{backdrop-filter:saturate(1.1)}
  </style>
<?php endif; ?>
</head>
<body>
  <div class="wrap">

    <!-- Header / Hero del evento -->
    <div class="hero">
      <div class="row">
        <?php if ($EV_LOGO): ?>
          <img class="logo" src="<?= h($EV_LOGO) ?>" alt="Logo evento">
        <?php else: ?>
          <div class="logo" style="display:flex;align-items:center;justify-content:center;font-weight:800;">🏆</div>
        <?php endif; ?>
        <div class="title"><?= h($EV_NOMBRE) ?></div>
      </div>
    </div>

    <h2>🏅 Registro de Competidor</h2>

    <!-- Banner de estado de Cloudinary -->
    <?php if ($CLOUDY_ON): ?>
      <div class="alert ok">☁️ Cloudinary <b>ACTIVO</b>: los archivos se subirán a la nube.</div>
    <?php else: ?>
      <div class="alert info">☁️ Cloudinary <b>INACTIVO</b>: los archivos se guardarán <b>localmente</b>. (Podés activar <code>CLOUDINARY_URL</code> o las constantes).</div>
    <?php endif; ?>

    <!-- Link para COMPARTIR con evento_id -->
    <div class="card" style="margin-top:6px">
      <b>🔗 Compartir formulario de inscripción</b>
      <div class="share">
        <input type="text" id="share_url" readonly value="<?= h($share_url) ?>">
        <button type="button" class="btn" onclick="copyShare()">Copiar</button>
      </div>
      <div style="color:#cfe7ff;font-size:.9rem;margin-top:6px">
        Enviá este enlace. Abre el formulario ya asociado al evento #<?= (int)$evento_id ?>.
      </div>
    </div>

    <?php if (!empty($_SESSION['ok_msg'])): ?>
      <div class="alert ok"><?= h($_SESSION['ok_msg']); unset($_SESSION['ok_msg']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!$evento_presente): ?>
      <div class="alert bad">Falta <b>evento_id</b>. Abrí este formulario desde el enlace del evento o usá el botón “Compartir formulario”.</div>
    <?php endif; ?>

    <?php if (!empty($wa_link)): ?>
      <div class="card" style="border-color:#1f5c3a">
        <b>📨 Enviar comprobante por WhatsApp</b><br>
        <a class="btn" style="background:#123221;border-color:#1f5c3a" href="<?= h($wa_link) ?>" target="_blank" rel="noopener">Abrir WhatsApp</a>
      </div>
    <?php endif; ?>

    <form action="<?= h($path) ?>?evento_id=<?= (int)$evento_id ?>" method="POST" enctype="multipart/form-data" id="form_comp">
      <input type="hidden" name="evento_id" id="evento_id" value="<?= $evento_presente ? h((string)$evento_id) : '' ?>">

      <div class="card">
        <h3 style="margin-bottom:8px">Datos personales</h3>
        <div class="grid">
          <div><label>Apellido</label><input type="text" name="apellido" required autocomplete="family-name"></div>
          <div><label>Nombre</label><input type="text" name="nombre" required autocomplete="given-name"></div>
          <div>
            <label>DNI</label>
            <input type="text" name="dni" id="dni" required inputmode="numeric" pattern="\d+" maxlength="12" placeholder="Solo números">
            <div id="dni_msg" class="alert" style="display:none"></div>
          </div>
          <div><label>Fecha de Nacimiento</label><input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required></div>
          <div><label>Edad</label><input type="number" name="edad" id="edad" readonly required inputmode="numeric"></div>
          <div>
            <label>Sexo</label>
            <select name="sexo" id="sexo" required>
              <option value="">Seleccionar</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option>
            </select>
          </div>

          <div>
            <label>Provincia</label>
            <select name="provincia" id="provincia" required onchange="onProvinciaChange()">
              <option value="">Seleccionar provincia</option>
            </select>
            <input type="hidden" name="provincia_id" id="provincia_id">
          </div>
          <div>
            <label>Localidad</label>
            <input list="dl_localidades" name="localidad" id="localidad" placeholder="Escribí tu localidad" autocomplete="address-level2" required>
            <datalist id="dl_localidades"></datalist>
            <input type="hidden" name="localidad_id" id="localidad_id">
          </div>

          <div><label>Escuela / Gimnasio</label><input type="text" name="escuela_nombre" required autocomplete="organization"></div>

          <div>
            <label>Logo de la Escuela (IMG/PDF)</label>
            <input type="file" id="escuela_logo" name="escuela_logo" accept="image/*,application/pdf">
            <div class="<?= $CLOUDY_ON ? 'note-ok' : 'note-bad' ?>">
              <?= $CLOUDY_ON ? 'Cloudinary activo: se subirá a la nube.' : 'Cloudinary inactivo: se guardará localmente.' ?>
            </div>
            <?php if (!empty($upload_status['escuela_logo'])): ?>
              <?php if ($upload_status['escuela_logo']['status']==='ok'): ?>
                <div class="note-ok">☁️ Subido correctamente.</div>
              <?php else: ?>
                <div class="note-bad">☁️ Fallo al subir. Vuelva a intentar.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div>
            <label>Foto del Competidor</label>
            <input type="file" id="foto_competidor" name="foto_competidor" accept="image/*">
            <div class="<?= $CLOUDY_ON ? 'note-ok' : 'note-bad' ?>">
              <?= $CLOUDY_ON ? 'Cloudinary activo: se subirá a la nube.' : 'Cloudinary inactivo: se guardará localmente.' ?>
            </div>
            <?php if (!empty($upload_status['foto_competidor'])): ?>
              <?php if ($upload_status['foto_competidor']['status']==='ok'): ?>
                <div class="note-ok">☁️ Subido correctamente.</div>
              <?php else: ?>
                <div class="note-bad">☁️ Fallo al subir. Vuelva a intentar.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:8px">Ranking</h3>
        <div class="grid-3">
          <div><label>Ganadas (W)</label><input type="number" min="0" name="wins" id="wins" value="0" inputmode="numeric"></div>
          <div><label>Perdidas (L)</label><input type="number" min="0" name="losses" id="losses" value="0" inputmode="numeric"></div>
          <div><label>Empates (D)</label><input type="number" min="0" name="draws" id="draws" value="0" inputmode="numeric"></div>
        </div>
        <div class="grid-3" style="margin-top:6px">
          <div><label>Sin decisión (NC)</label><input type="number" min="0" name="no_contest" id="no_contest" value="0" inputmode="numeric"></div>
          <div><label>Total</label><input type="number" id="total_fights" value="0" readonly inputmode="numeric"></div>
          <div>
            <label>Categoría técnica (automática)</label>
            <select id="categoria_tecnica_id_view" disabled>
              <option value="1">A</option><option value="2">B</option><option value="3">C</option><option value="4" selected>N</option>
            </select>
            <input type="hidden" name="categoria_tecnica_id" id="categoria_tecnica_id" value="4">
            <div class="mut" style="font-size:.85rem;margin-top:4px">A: ≥10 • B: 5–9 • C: 1–4 • N: 0</div>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 style="margin-bottom:8px">Inscripción</h3>
        <div class="grid">
          <div>
            <label>Modalidad</label>
            <select name="modalidad_id" id="modalidad_id" required>
              <option value="1">Exhibición</option><option value="2">Boxeo</option><option value="3">Full Contact</option>
              <option value="4">Low Kick</option><option value="5">K1</option><option value="6">MMA</option>
              <option value="<?= (int)$idMuay ?>">Muay Thai</option>
            </select>
          </div>
          <div>
            <label>Disciplina</label>
            <select name="disciplina_id" id="disciplina_id" required>
              <option value="1">Exhibiciones</option><option value="2">Amateurs</option><option value="3">Proam</option><option value="4">Pro</option>
            </select>
          </div>
          <div>
            <label>División (automática por edad)</label>
            <select id="division_id_view" disabled>
              <option value="1">Infantil</option><option value="2">Juvenil</option><option value="3">Adultos</option>
              <option value="4">Masters</option><option value="5">Veteranos</option>
            </select>
            <input type="hidden" name="division_id" id="division_id" value="">
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
        <div class="grid">
          <div><label>Alias de transferencia</label><input type="text" name="alias_transferencia" id="alias_transferencia" placeholder="alias.banco.cuenta"></div>
          <div>
            <label>WhatsApp del organizador</label>
            <input type="text" name="telefono_organizador" id="telefono_organizador" placeholder="54926xxxxxxxx" inputmode="numeric" maxlength="15">
            <div class="mut" style="font-size:.85rem;margin-top:4px">Formato internacional sin signos (ej: 5492665xxxxx)</div>
          </div>
          <div>
            <label>Comprobante (imagen o PDF)</label>
            <input type="file" name="comprobante_pago" id="comprobante_pago" accept="image/*,application/pdf">
            <div class="<?= $CLOUDY_ON ? 'note-ok' : 'note-bad' ?>">
              <?= $CLOUDY_ON ? 'Cloudinary activo: se subirá a la nube.' : 'Cloudinary inactivo: se guardará localmente.' ?>
            </div>
            <?php if (!empty($upload_status['comprobante_pago'])): ?>
              <?php if ($upload_status['comprobante_pago']['status']==='ok'): ?>
                <div class="note-ok">☁️ Subido correctamente.</div>
              <?php else: ?>
                <div class="note-bad">☁️ Fallo al subir. Vuelva a intentar.</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div>
            <label>Monto de inscripción ($)</label>
            <input type="number" name="pago_inscripcion" step="0.01" value="0.00" inputmode="decimal">
          </div>
        </div>
      </div>

      <div style="margin-top:10px">
        <button type="submit" class="btn" id="btn_submit" <?= (!$evento_presente?'disabled':'') ?>>✅ Guardar Competidor</button>
      </div>
    </form>
  </div>

  <script>
  /* ==== Copiar link de compartir ==== */
  function copyShare(){
    const el = document.getElementById('share_url');
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(el.value);
    } else {
      el.select(); el.setSelectionRange(0, 99999);
      try { document.execCommand('copy'); } catch(e){}
    }
  }

  /* ==== Provincias y Localidades (Argentina) ==== */
  const PROVINCIAS=["Buenos Aires","CABA","Catamarca","Chaco","Chubut","Córdoba","Corrientes","Entre Ríos","Formosa","Jujuy","La Pampa","La Rioja","Mendoza","Misiones","Neuquén","Río Negro","Salta","San Juan","San Luis","Santa Cruz","Santa Fe","Santiago del Estero","Tierra del Fuego","Tucumán"];
  const PROV_IDX={}; PROVINCIAS.forEach((p,i)=>PROV_IDX[p]=i+1);
  function cargarProvincias(){const sel=document.getElementById('provincia'); sel.innerHTML='<option value="">Seleccionar provincia</option>'; PROVINCIAS.forEach(p=>{const o=document.createElement('option'); o.value=p; o.textContent=p; sel.appendChild(o);});}
  function onProvinciaChange(){const p=document.getElementById('provincia').value; document.getElementById('provincia_id').value=PROV_IDX[p]||''; const dl=document.getElementById('dl_localidades'); dl.innerHTML='';}
  cargarProvincias();

  /* ==== Edad, División auto y categorías por peso ==== */
  function calcularEdad(){
    const f=document.getElementById("fecha_nacimiento").value; if(!f)return;
    const hoy=new Date(), nac=new Date(f);
    let e=hoy.getFullYear()-nac.getFullYear();
    const m=hoy.getMonth()-nac.getMonth();
    if(m<0||(m===0&&hoy.getDate()<nac.getDate())) e--;
    document.getElementById("edad").value=Math.max(0,e);
    let d="3"; if(e<12)d="1"; else if(e<18)d="2"; else if(e<26)d="3"; else if(e<46)d="4"; else d="5";
    document.getElementById("division_id_view").value=d; document.getElementById("division_id").value=d; cargarCategoriasPeso();
  }
  function cargarCategoriasPeso(){
    const e=document.getElementById("edad").value; const s=document.getElementById("sexo").value; if(!e||!s)return;
    fetch('obtener_categorias_por_peso.php?edad='+encodeURIComponent(e)+'&sexo='+encodeURIComponent(s))
      .then(r=>r.text()).then(html=>{document.getElementById("categoria_peso_id").innerHTML=html;}).catch(()=>{});
  }
  document.getElementById("sexo")?.addEventListener("change",cargarCategoriasPeso);

  /* ==== Ranking ==== */
  function recalcRanking(){
    const w=+document.getElementById('wins').value||0,
          l=+document.getElementById('losses').value||0,
          d=+document.getElementById('draws').value||0,
          n=+document.getElementById('no_contest').value||0;
    const tot=w+l+d+n;
    document.getElementById('total_fights').value=tot;
    let cat='4'; if(tot>=10)cat='1'; else if(tot>=5)cat='2'; else if(tot>=1)cat='3';
    document.getElementById('categoria_tecnica_id_view').value=cat;
    document.getElementById('categoria_tecnica_id').value=cat;
  }
  ['wins','losses','draws','no_contest'].forEach(id=> document.getElementById(id)?.addEventListener('input',recalcRanking)); recalcRanking();

  /* ==== DNI en vivo ==== */
  const dniInput=document.getElementById('dni'),
        dniMsg=document.getElementById('dni_msg'),
        btnSubmit=document.getElementById('btn_submit'),
        eventoId=document.getElementById('evento_id')?.value||'';
  function setSubmitEnabled(x){ if(btnSubmit) btnSubmit.disabled=!x; }
  async function validarDNI(){
    dniMsg.style.display='none'; setSubmitEnabled(true);
    const dni=dniInput?.value.trim(); if(!dni||!eventoId)return;
    try{
      const r=await fetch('validar_dni_evento.php?evento_id='+encodeURIComponent(eventoId)+'&dni='+encodeURIComponent(dni));
      if(!r.ok)return;
      const data=await r.json();
      if(data.exists){
        dniMsg.textContent='⚠️ Este DNI ya está inscripto en este evento.';
        dniMsg.className='alert bad'; dniMsg.style.display='block'; setSubmitEnabled(false);
      }
    }catch{}
  }
  dniInput?.addEventListener('input',e=>{e.target.value=(e.target.value||'').replace(/\D+/g,'');});
  dniInput?.addEventListener('blur',validarDNI);
  dniInput?.addEventListener('change',validarDNI);

  // Inicializar división oculta
  document.getElementById('division_id').value=document.getElementById('division_id_view').value||'';
  </script>
</body>
</html>
