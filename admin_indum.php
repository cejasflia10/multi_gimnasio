<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

/* ===== CLAVES CLOUDINARY (tal cual me las diste) ===== */
if (!defined('CLOUD_ENABLED'))      define('CLOUD_ENABLED', true);
if (!defined('CLOUD_NAME'))         define('CLOUD_NAME', 'ddfugds9b');
if (!defined('CLOUD_API_KEY'))      define('CLOUD_API_KEY', '657814174747186');
if (!defined('CLOUD_API_SECRET'))   define('CLOUD_API_SECRET', 'TKo5BRiKCEjxSLFzn2DLbz_ji4c');
if (!defined('CLOUD_FOLDER_ROOT'))  define('CLOUD_FOLDER_ROOT', 'ROOT');

/* ===== Inicializador de Cloudinary (no modificado) ===== */
require_once __DIR__.'/cloudy_boot_constants.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0){ header('Location: login.php'); exit; }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function must_prepare(mysqli $db, string $sql){
  $st = $db->prepare($sql);
  if (!$st) die('❌ SQL prepare error: '.$db->error.'<br><code>'.$sql.'</code>');
  return $st;
}

/* ==== Cloudinary init + fallback (sin Composer) ==== */
$CLOUDY      = cloudy_constants_init();
$cloud_ok    = (bool)($CLOUDY['ok'] ?? false);
$cloud_mode  = $CLOUDY['mode']  ?? 'n/a';
$cloud_reason= $CLOUDY['reason']?? null;
$cloud_hint  = $CLOUDY['hint']  ?? null;

/* Si falta el SDK, habilitamos el FALLBACK cURL sin tocar tu init */
$use_fallback = false;
if (!$cloud_ok && $cloud_reason === 'sdk_missing') {
  require_once __DIR__.'/cloudy_uploader_fallback.php'; // ← archivo fallback sin Composer
  $use_fallback = true;
  $cloud_ok   = true;
  $cloud_mode = 'curl_fallback';
}

/* ===== ACCIONES ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__form'])) {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) { http_response_code(400); exit('❌ CSRF'); }
  $f = $_POST['__form'];

  /* === Alta de producto === */
  if ($f==='add_producto') {
    $categoria   = $_POST['categoria'] ?? 'remera';
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio      = (float)($_POST['precio'] ?? 0);
    $activo      = isset($_POST['activo']) ? 1 : 0;

    $foto_url=null; $foto_pid=null;
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error']===UPLOAD_ERR_OK && $cloud_ok) {
      if ($use_fallback) {
        [$foto_url,$foto_pid] = cloudy_upload_fallback(
          $_FILES['foto']['tmp_name'],
          "indumentaria/productos/gym_$gimnasio_id",
          ['resource_type'=>'image']
        );
      } else {
        [$foto_url,$foto_pid] = cloudy_upload(
          $_FILES['foto']['tmp_name'],
          "indumentaria/productos/gym_$gimnasio_id",
          ['resource_type'=>'image']
        );
      }
    }

    $sql = "INSERT INTO ind_productos
            (gimnasio_id,categoria,titulo,descripcion,precio,activo,foto_url,foto_public_id)
            VALUES (?,?,?,?,?,?,?,?)";
    $st = must_prepare($conexion,$sql);
    $st->bind_param('isssdiss', $gimnasio_id,$categoria,$titulo,$descripcion,$precio,$activo,$foto_url,$foto_pid);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1'); exit;
  }

  /* === Alta/actualización de talle === */
  if ($f==='add_talle') {
    $producto_id = (int)($_POST['producto_id']??0);
    $talle       = trim($_POST['talle']??'');
    $stock       = (int)($_POST['stock']??0);

    $foto_u=null; $foto_pid=null;
    if (!empty($_FILES['foto_talle']['name']) && $_FILES['foto_talle']['error']===UPLOAD_ERR_OK && $cloud_ok) {
      if ($use_fallback) {
        [$foto_u,$foto_pid] = cloudy_upload_fallback(
          $_FILES['foto_talle']['tmp_name'],
          "indumentaria/talles/gym_$gimnasio_id/prod_$producto_id",
          ['resource_type'=>'image']
        );
      } else {
        [$foto_u,$foto_pid] = cloudy_upload(
          $_FILES['foto_talle']['tmp_name'],
          "indumentaria/talles/gym_$gimnasio_id/prod_$producto_id",
          ['resource_type'=>'image']
        );
      }
    }

    $sql="INSERT INTO ind_talles (producto_id,talle,stock,foto_talle_url,foto_talle_public_id)
          VALUES (?,?,?,?,?)
          ON DUPLICATE KEY UPDATE stock=VALUES(stock),
                                  foto_talle_url=VALUES(foto_talle_url),
                                  foto_talle_public_id=VALUES(foto_talle_public_id)";
    $st = must_prepare($conexion,$sql);
    $st->bind_param('isiss',$producto_id,$talle,$stock,$foto_u,$foto_pid);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1&pid='.$producto_id.'#talles'); exit;
  }

  /* === Activar/Desactivar producto === */
  if ($f==='toggle_activo') {
    $pid   = (int)($_POST['producto_id']??0);
    $activo= isset($_POST['activo']) ? 1 : 0;

    $st = must_prepare($conexion, "UPDATE ind_productos SET activo=? WHERE id=? AND gimnasio_id=?");
    $st->bind_param('iii',$activo,$pid,$gimnasio_id);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1'); exit;
  }
}

/* ===== DATA ===== */
$prods=[];
$st = must_prepare($conexion, "SELECT * FROM ind_productos WHERE gimnasio_id=? ORDER BY id DESC");
$st->bind_param('i',$gimnasio_id); $st->execute();
$prods=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$pid_sel=(int)($_GET['pid']??0);
$talles=[];
if ($pid_sel>0){
  $st = must_prepare($conexion, "SELECT * FROM ind_talles WHERE producto_id=? ORDER BY talle");
  $st->bind_param('i',$pid_sel); $st->execute();
  $talles=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Indumentaria</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,Arial;background:#0f1320;color:#fff;margin:0}
 .wrap{max-width:1080px;margin:24px auto;padding:16px}
 .card{background:#141a2a;border:1px solid #24314d;border-radius:14px;padding:16px;margin-top:16px}
 label{display:block;margin:.5rem 0 .25rem}
 input,select,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #2a3550;background:#0d1322;color:#fff}
 .row{display:flex;gap:12px;flex-wrap:wrap}
 .btn{padding:10px 14px;border-radius:10px;border:0;background:#3b82f6;color:#fff;cursor:pointer;font-weight:700}
 .mini{font-size:12px;color:#9fb0d3}
 table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #22304d}
 img{max-width:80px;border-radius:8px}
 .note{background:#0b1220;border-left:4px solid #3b82f6;padding:10px 12px;border-radius:10px;margin-top:8px}
 .ok{color:#34d399}.bad{color:#fca5a5}
</style>
</head>
<body>
<div class="wrap">
  <h1>🛍️ Administración — Indumentaria</h1>

  <div class="card">
    <h2>Nuevo producto</h2>
    <form method="post" enctype="multipart/form-data" class="row">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="__form" value="add_producto">
      <div style="flex:1">
        <label>Categoría</label>
        <select name="categoria">
          <option value="remera">Remera/Musculosa</option>
          <option value="short">Short/Pantalón</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div style="flex:2"><label>Título</label><input name="titulo" required></div>
      <div style="flex:3"><label>Descripción</label><input name="descripcion"></div>
      <div style="flex:1"><label>Precio</label><input type="number" step="0.01" name="precio" required></div>
      <div style="flex:1"><label>Foto (opcional)</label><input type="file" name="foto" accept="image/*"></div>
      <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="activo" checked> Activo</label>
      <div><button class="btn">Guardar producto</button></div>
    </form>
    <div class="note">
      <?php if($cloud_ok): ?>
        <span class="ok">
          Cloudinary habilitado <?=($cloud_mode==='curl_fallback'?'(modo: cURL sin SDK)':'(modo: '.$cloud_mode.')')?>
        </span>
      <?php else: ?>
        <span class="bad">Cloudinary NO habilitado</span>
        <?= $cloud_reason ? ' — Motivo: '.h($cloud_reason) : '' ?>
        <?= $cloud_hint   ? ' — Sugerencia: '.h($cloud_hint)   : '' ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Productos</h2>
    <table>
      <thead><tr><th>#</th><th>Foto</th><th>Título</th><th>Cat</th><th>Precio</th><th>Activo</th><th>Acción</th><th>Talles</th></tr></thead>
      <tbody>
        <?php if(empty($prods)): ?>
          <tr><td colspan="8" class="mini">Sin productos aún</td></tr>
        <?php else: foreach($prods as $p): ?>
          <tr>
            <td><?=$p['id']?></td>
            <td><?php if($p['foto_url']): ?><img src="<?=h($p['foto_url'])?>"><?php endif; ?></td>
            <td><?=h($p['titulo'])?></td>
            <td><?=h($p['categoria'])?></td>
            <td>$<?=number_format($p['precio'],2,',','.')?></td>
            <td><?=$p['activo']?'Sí':'No'?></td>
            <td>
              <form method="post" class="row" style="align-items:center">
                <input type="hidden" name="csrf" value="<?=$csrf?>">
                <input type="hidden" name="__form" value="toggle_activo">
                <input type="hidden" name="producto_id" value="<?=$p['id']?>">
                <label class="mini"><input type="checkbox" name="activo" <?=$p['activo']?'checked':''?>> activo</label>
                <button class="btn">Aplicar</button>
              </form>
            </td>
            <td><a class="btn" href="?pid=<?=$p['id']?>#talles">Editar talles</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if($pid_sel>0): ?>
  <div class="card" id="talles">
    <h2>Talles — Producto #<?=$pid_sel?></h2>
    <form method="post" enctype="multipart/form-data" class="row" style="align-items:end">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="__form" value="add_talle">
      <input type="hidden" name="producto_id" value="<?=$pid_sel?>">
      <div style="flex:1"><label>Talle</label><input name="talle" placeholder="XS / S / M / L / 38 / 40" required></div>
      <div style="flex:1"><label>Stock</label><input type="number" name="stock" value="0" required></div>
      <div style="flex:2"><label>Foto guía de talle (opcional)</label><input type="file" name="foto_talle" accept="image/*"></div>
      <div><button class="btn">Guardar talle</button></div>
    </form>

    <table style="margin-top:12px">
      <thead><tr><th>#</th><th>Talle</th><th>Stock</th><th>Foto</th></tr></thead>
      <tbody>
        <?php if(empty($talles)): ?>
          <tr><td colspan="4" class="mini">Sin talles aún</td></tr>
        <?php else: foreach($talles as $t): ?>
          <tr>
            <td><?=$t['id']?></td>
            <td><?=h($t['talle'])?></td>
            <td><?=$t['stock']?></td>
            <td><?php if($t['foto_talle_url']): ?><img src="<?=h($t['foto_talle_url'])?>"><?php endif; ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
