<?php
/*******************************************************
 * editar_evento.php — completo con Cloudinary (Cloudy)
 *******************************************************/
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>false,'httponly'=>true,'samesite'=>'Lax',
  ]);
  session_start();
}

require_once __DIR__ . '/conexion.php';

/* =========================================================
   Cloudinary (opcional) — “Cloudy”
   ========================================================= */
// Opción 1 (recomendada): setear CLOUDINARY_URL en el entorno
//   cloudinary://API_KEY:API_SECRET@CLOUD_NAME
// Opción 2: activar constantes:
const CLOUD_ENABLED     = false;              // ← poné true si usás las constantes
const CLOUD_NAME        = '';                 // ej. "ddrugdsqe"
const CLOUD_API_KEY     = '';                 // ej. "65784174747786"
const CLOUD_API_SECRET  = '';                 // ej. "*************"

function cloud_inited(){ static $i=false; return $i; }
function cloud_init(): void {
  static $inited=false; if ($inited) return; $inited=true;
  $vendor1 = __DIR__.'/vendor/autoload.php';
  $vendor2 = dirname(__DIR__).'/vendor/autoload.php';
  if (file_exists($vendor1)) require_once $vendor1;
  elseif (file_exists($vendor2)) require_once $vendor2;
}
function cloud_configured(): bool {
  $url = getenv('CLOUDINARY_URL');
  if ($url && is_string($url) && preg_match('~^cloudinary://[^:]+:[^@]+@[^/]+$~',$url)) return true;
  if (CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) return true;
  return false;
}
/** Sube un archivo local a Cloudinary. Devuelve secure_url o null. */
function cloud_upload(string $abs_path, string $folder, string $public_id): ?string {
  if (!cloud_configured()) return null;
  try {
    cloud_init();
    if (!getenv('CLOUDINARY_URL') && CLOUD_ENABLED) {
      \Cloudinary\Configuration\Configuration::instance([
        'cloud'=>['cloud_name'=>CLOUD_NAME,'api_key'=>CLOUD_API_KEY,'api_secret'=>CLOUD_API_SECRET],
        'url'=>['secure'=>true],
      ]);
    }
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $res = $uploader->upload($abs_path, [
      'folder'        => $folder,
      'public_id'     => $public_id,
      'resource_type' => 'auto',
      'overwrite'     => true,
    ]);
    return $res['secure_url'] ?? null;
  } catch (\Throwable $e) {
    // Podés loguear $e->getMessage()
    return null;
  }
}

/* ===== Conexión ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ===== CSRF (token + cookie) ===== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token']=bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf_token'];
if (!isset($_COOKIE['csrf_cookie']) || $_COOKIE['csrf_cookie'] !== $CSRF) {
  setcookie('csrf_cookie',$CSRF,0,'/','',false,true);
}
function csrf_ok(): bool {
  $t = $_POST['csrf'] ?? '';
  if (!$t || empty($_SESSION['csrf_token'])) return false;
  if (!hash_equals($_SESSION['csrf_token'],$t)) return false;
  if (!isset($_COOKIE['csrf_cookie']) || !hash_equals($_COOKIE['csrf_cookie'],$t)) return false;
  return true;
}

/* ===== Upload seguro + Cloudinary =====
   $subdir: "media_eventos" o "flyers_eventos"
   $cloudFolder: ej "multi_gimnasio/eventos/<id>"
*/
function save_event_asset(string $field, string $subdir, int $evento_id, string $cloudFolder): ?string {
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
  $err = $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($err !== UPLOAD_ERR_OK) return null;

  $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $orig = basename((string)($_FILES[$field]['name'] ?? 'archivo'));
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $allow= ['jpg','jpeg','png','webp','gif','pdf'];
  if (!in_array($ext,$allow,true)) $ext='jpg';

  $base = pathinfo($orig, PATHINFO_FILENAME);
  $base = preg_replace('/[^\p{L}\p{N}\-_]+/u','-', $base);
  $base = trim($base,'-_') ?: 'archivo';

  $dirAbs = __DIR__ . DIRECTORY_SEPARATOR . $subdir;
  if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);  // ← crea la carpeta si falta
  if (!is_dir($dirAbs)) return null;

  $uniq = date('Ymd_His') . '_' . mt_rand(1000,9999);
  $file = $base . '_' . $uniq . '.' . $ext;

  $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $file;
  $destRel = $subdir . '/' . $file;

  if (!@move_uploaded_file($tmp, $destAbs)) {
    // reintento con nombre simple si Windows/XAMPP jode con caracteres
    $file2 = 'file_' . $uniq . '.' . $ext;
    $destAbs2 = $dirAbs . DIRECTORY_SEPARATOR . $file2;
    $destRel2 = $subdir . '/' . $file2;
    if (!@move_uploaded_file($tmp, $destAbs2)) return null;
    @chmod($destAbs2, 0644);
    $destAbs = $destAbs2; $destRel = $destRel2; $file = $file2;
  } else {
    @chmod($destAbs, 0644);
  }

  // Si Cloudinary está configurado, subimos y devolvemos la URL
  $publicId = $field . '_' . $base . '_' . $uniq;
  $cloudUrl = cloud_upload($destAbs, $cloudFolder, $publicId);
  if ($cloudUrl) return $cloudUrl;

  // Si no hay Cloudinary, devolvemos la ruta relativa local
  return $destRel;
}

/* ===== Mapeo de columnas flexibles ===== */
function evento_cols_map(mysqli $db): array {
  $t='eventos_deportivos';
  $pick=function(array $cands) use($db,$t){ foreach($cands as $c) if (has_col($db,$t,$c)) return $c; return null; };
  return [
    '_table'=>$t,
    'id'      =>$pick(['id','evento_id']),
    'nombre'  =>$pick(['nombre','titulo','title']),
    'fecha'   =>$pick(['fecha','fecha_evento','dia']),
    'lugar'   =>$pick(['lugar','sede','ubicacion']),
    'desc'    =>$pick(['descripcion','description','detalle','resumen']),
    'logo'    =>$pick(['logo','logo_url','imagen_logo','logoEvento']),
    'portada' =>$pick(['portada','banner','imagen_portada','imagen','bg_url','fondo']),
    'flyer'   =>$pick(['flyer','flyer_url','poster']),
  ];
}

/* ===== Parámetro id ===== */
$evento_id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($evento_id <= 0) { http_response_code(400); exit('Falta parámetro ?id=...'); }

$cols = evento_cols_map($conexion);
if (!$cols['id']) { http_response_code(500); exit('No se detecta columna ID en la tabla de eventos.'); }

/* ===== Cargar evento ===== */
function load_evento(mysqli $db, array $cols, int $id): array {
  $tb=$cols['_table'];
  $sql="SELECT * FROM `{$tb}` WHERE `{$cols['id']}`=? LIMIT 1";
  $st=$db->prepare($sql); if(!$st) return [];
  $st->bind_param('i',$id); $st->execute();
  $r=$st->get_result(); $row=($r&&$r->num_rows)?$r->fetch_assoc():[];
  $st->close(); return $row?:[];
}
$evento = load_evento($conexion, $cols, $evento_id);

/* ===== POST: Guardar cambios ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) {
    $_SESSION['flash_error']='CSRF inválido. Recargá la página e intentá nuevamente.';
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  $updates=[]; $types=''; $vals=[];
  if ($cols['nombre']) { $updates[]="`{$cols['nombre']}`=?"; $types.='s'; $vals[]=post('nombre'); }
  if ($cols['fecha'])  { $updates[]="`{$cols['fecha']}`=?";  $types.='s'; $vals[]=post('fecha'); }
  if ($cols['lugar'])  { $updates[]="`{$cols['lugar']}`=?";  $types.='s'; $vals[]=post('lugar'); }
  if ($cols['desc'])   { $updates[]="`{$cols['desc']}`=?";   $types.='s'; $vals[]=post('descripcion'); }

  // carpetas locales + carpeta cloud
  $cloudFolder = 'multi_gimnasio/eventos/'.$evento_id;

  $logo    = save_event_asset('logo_evento',    'media_eventos',  $evento_id, $cloudFolder);
  $portada = save_event_asset('portada_evento', 'media_eventos',  $evento_id, $cloudFolder);
  $flyer   = save_event_asset('flyer',          'flyers_eventos', $evento_id, $cloudFolder);

  if ($logo    && $cols['logo'])    { $updates[]="`{$cols['logo']}`=?";    $types.='s'; $vals[]=$logo; }
  if ($portada && $cols['portada']) { $updates[]="`{$cols['portada']}`=?"; $types.='s'; $vals[]=$portada; }
  if ($flyer   && $cols['flyer'])   { $updates[]="`{$cols['flyer']}`=?";   $types.='s'; $vals[]=$flyer; }

  if ($updates) {
    $tb=$cols['_table'];
    $sql="UPDATE `{$tb}` SET ".implode(', ',$updates)." WHERE `{$cols['id']}`=?";
    $types.='i'; $vals[]=$evento_id;
    $st=$conexion->prepare($sql);
    if(!$st){
      $_SESSION['flash_error']='No se pudo preparar UPDATE: '.$conexion->error;
      header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }
    $bind=[$types]; foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
    call_user_func_array([$st,'bind_param'],$bind);
    if($st->execute()){ $_SESSION['flash_ok']='✅ Evento actualizado.'; }
    else { $_SESSION['flash_error']='No se pudo actualizar: '.$st->error; }
    $st->close();
  } else {
    $_SESSION['flash_ok']='No hubo cambios para guardar.';
  }

  header('Location: '.$_SERVER['REQUEST_URI']); exit;
}

/* ===== Valores actuales para el formulario ===== */
$nombreActual  = $cols['nombre']  ? ($evento[$cols['nombre']]  ?? '') : '';
$fechaActual   = $cols['fecha']   ? ($evento[$cols['fecha']]   ?? '') : '';
$lugarActual   = $cols['lugar']   ? ($evento[$cols['lugar']]   ?? '') : '';
$descActual    = $cols['desc']    ? ($evento[$cols['desc']]    ?? '') : '';
$logoActual    = $cols['logo']    ? ($evento[$cols['logo']]    ?? '') : '';
$portadaActual = $cols['portada'] ? ($evento[$cols['portada']] ?? '') : '';
$flyerActual   = $cols['flyer']   ? ($evento[$cols['flyer']]   ?? '') : '';

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Evento #<?= (int)$evento_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff;
      --card:#0f1720; --bd:#1f2a33; --line:#22313f; --accent:#0e7ad1;
      --ok:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
      --bad:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:980px;margin:0 auto;padding:14px}
    h1,h2,h3{margin:.2rem 0 .6rem}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:12px;margin-top:10px}
    label{font-size:12px;color:#cfe7ff}
    input,textarea,select{width:100%;padding:10px;border-radius:10px;border:1px solid var(--line);background:#111a24;color:#e6eef4;font-size:15px}
    textarea{min-height:120px;resize:vertical}
    .grid{display:grid;grid-template-columns:1fr;gap:10px}
    @media(min-width:720px){ .grid{grid-template-columns:repeat(2,1fr)} }
    .btn{display:inline-block;padding:12px 14px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;cursor:pointer}
    .alert{padding:10px 12px;border-radius:10px;margin:10px 0}
    .ok{background:var(--ok);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{background:var(--bad);border:1px solid var(--badbd);color:var(--badt)}
    .previews{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .prev{background:#0b1115;border:1px solid var(--bd);border-radius:10px;padding:10px;text-align:center}
    .prev img,.prev iframe{max-width:100%;border-radius:8px}
    .hint{font-size:12px;color:#9bb6d1}
  </style>
</head>
<body>
  <div class="wrap">
    <h2>🛠️ Editar Evento #<?= (int)$evento_id ?></h2>

    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="alert ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" action="<?= h($_SERVER['REQUEST_URI']) ?>">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">

      <div class="card">
        <h3>Datos principales</h3>
        <div class="grid">
          <div>
            <label>Nombre / Título</label>
            <input type="text" name="nombre" value="<?= h($nombreActual) ?>" placeholder="Nombre del evento">
          </div>
          <div>
            <label>Fecha</label>
            <input type="date" name="fecha" value="<?= h(preg_match('/^\d{4}-\d{2}-\d{2}$/',$fechaActual)?$fechaActual:'') ?>">
          </div>
          <div>
            <label>Lugar / Sede</label>
            <input type="text" name="lugar" value="<?= h($lugarActual) ?>" placeholder="Ej: Polideportivo…">
          </div>
          <div>
            <label>Descripción</label>
            <textarea name="descripcion" placeholder="Detalles, reglas, etc."><?= h($descActual) ?></textarea>
          </div>
        </div>
      </div>

      <div class="card">
        <h3>Imágenes</h3>
        <div class="grid">
          <div>
            <label>Logo del evento (IMG/PDF)</label>
            <input type="file" name="logo_evento" accept="image/*,application/pdf">
            <div class="hint">Se guarda en <code>media_eventos/</code> y, si Cloudinary está activo, se sube a <code>multi_gimnasio/eventos/<?= (int)$evento_id ?></code></div>
          </div>
          <div>
            <label>Portada / Banner (IMG/PDF)</label>
            <input type="file" name="portada_evento" accept="image/*,application/pdf">
            <div class="hint">Se guarda en <code>media_eventos/</code> (o URL de Cloudinary).</div>
          </div>
          <div>
            <label>Flyer (IMG/PDF)</label>
            <input type="file" name="flyer" accept="image/*,application/pdf">
            <div class="hint">Se guarda en <code>flyers_eventos/</code> (o URL de Cloudinary).</div>
          </div>
        </div>

        <div class="previews" style="margin-top:10px">
          <div class="prev">
            <div class="hint">Logo actual</div>
            <?php if ($logoActual): ?>
              <?php if (preg_match('/\.pdf$/i',$logoActual)): ?>
                <iframe src="<?= h($logoActual) ?>" width="100%" height="160"></iframe>
              <?php else: ?>
                <img src="<?= h($logoActual) ?>" alt="Logo">
              <?php endif; ?>
            <?php else: ?><div class="hint">—</div><?php endif; ?>
          </div>
          <div class="prev">
            <div class="hint">Portada actual</div>
            <?php if ($portadaActual): ?>
              <?php if (preg_match('/\.pdf$/i',$portadaActual)): ?>
                <iframe src="<?= h($portadaActual) ?>" width="100%" height="160"></iframe>
              <?php else: ?>
                <img src="<?= h($portadaActual) ?>" alt="Portada">
              <?php endif; ?>
            <?php else: ?><div class="hint">—</div><?php endif; ?>
          </div>
          <div class="prev">
            <div class="hint">Flyer actual</div>
            <?php if ($flyerActual): ?>
              <?php if (preg_match('/\.pdf$/i',$flyerActual)): ?>
                <iframe src="<?= h($flyerActual) ?>" width="100%" height="160"></iframe>
              <?php else: ?>
                <img src="<?= h($flyerActual) ?>" alt="Flyer">
              <?php endif; ?>
            <?php else: ?><div class="hint">—</div><?php endif; ?>
          </div>
        </div>
      </div>

      <div style="margin-top:10px">
        <button class="btn" type="submit">💾 Guardar cambios</button>
      </div>
    </form>
  </div>
</body>
</html>
