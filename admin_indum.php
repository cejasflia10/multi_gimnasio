<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__ . '/menu_horizontal.php';

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

/* ====== Helpers de subida ====== */
function subir_imagen_cloud($tmp_name, $folder, $opts = []){
  global $use_fallback;
  if ($use_fallback) {
    return cloudy_upload_fallback($tmp_name, $folder, array_merge(['resource_type'=>'image'], $opts));
  }
  return cloudy_upload($tmp_name, $folder, array_merge(['resource_type'=>'image'], $opts));
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
      [$foto_url,$foto_pid] = subir_imagen_cloud($_FILES['foto']['tmp_name'], "indumentaria/productos/gym_$gimnasio_id");
    }

    $sql = "INSERT INTO ind_productos
            (gimnasio_id,categoria,titulo,descripcion,precio,activo,foto_url,foto_public_id)
            VALUES (?,?,?,?,?,?,?,?)";
    $st = must_prepare($conexion,$sql);
    $st->bind_param('isssdiss', $gimnasio_id,$categoria,$titulo,$descripcion,$precio,$activo,$foto_url,$foto_pid);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1'); exit;
  }

  /* === Editar producto === */
  if ($f==='edit_producto') {
    $pid         = (int)($_POST['producto_id']??0);
    $categoria   = $_POST['categoria'] ?? 'remera';
    $titulo      = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio      = (float)($_POST['precio'] ?? 0);
    $activo      = isset($_POST['activo']) ? 1 : 0;

    // Traer foto actual
    $st = must_prepare($conexion, "SELECT foto_url, foto_public_id FROM ind_productos WHERE id=? AND gimnasio_id=? LIMIT 1");
    $st->bind_param('ii',$pid,$gimnasio_id); $st->execute();
    $curr = $st->get_result()->fetch_assoc(); $st->close();
    $foto_url = $curr['foto_url'] ?? null;
    $foto_pid = $curr['foto_public_id'] ?? null;

    // Reemplazar foto si suben una nueva
    if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error']===UPLOAD_ERR_OK && $cloud_ok) {
      [$foto_url,$foto_pid] = subir_imagen_cloud($_FILES['foto']['tmp_name'], "indumentaria/productos/gym_$gimnasio_id");
      // Nota: no borramos la anterior en Cloudinary para no romper pedidos antiguos.
    }

    $sql = "UPDATE ind_productos
            SET categoria=?, titulo=?, descripcion=?, precio=?, activo=?, foto_url=?, foto_public_id=?
            WHERE id=? AND gimnasio_id=?";
    $st = must_prepare($conexion,$sql);
    $st->bind_param('sssdisiii', $categoria,$titulo,$descripcion,$precio,$activo,$foto_url,$foto_pid,$pid,$gimnasio_id);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1'); exit;
  }

  /* === Eliminar producto (y talles asociados) === */
  if ($f==='del_producto') {
    $pid = (int)($_POST['producto_id']??0);
    // Borramos talles (si no hay FK con cascade)
    $st = must_prepare($conexion, "DELETE FROM ind_talles WHERE producto_id=?");
    $st->bind_param('i',$pid); $st->execute(); $st->close();
    // Borramos producto
    $st = must_prepare($conexion, "DELETE FROM ind_productos WHERE id=? AND gimnasio_id=?");
    $st->bind_param('ii',$pid,$gimnasio_id); $st->execute(); $st->close();
    header('Location: admin_indum.php?ok=1'); exit;
  }

  /* === Alta/actualización de talle === */
  if ($f==='add_talle') {
    $producto_id = (int)($_POST['producto_id']??0);
    $talle       = trim($_POST['talle']??'');
    $stock       = (int)($_POST['stock']??0);

    $foto_u=null; $foto_pid=null;
    if (!empty($_FILES['foto_talle']['name']) && $_FILES['foto_talle']['error']===UPLOAD_ERR_OK && $cloud_ok) {
      [$foto_u,$foto_pid] = subir_imagen_cloud($_FILES['foto_talle']['tmp_name'], "indumentaria/talles/gym_$gimnasio_id/prod_$producto_id");
    }

    $sql="INSERT INTO ind_talles (producto_id,talle,stock,foto_talle_url,foto_talle_public_id)
          VALUES (?,?,?,?,?)
          ON DUPLICATE KEY UPDATE stock=VALUES(stock),
                                  foto_talle_url=COALESCE(VALUES(foto_talle_url), foto_talle_url),
                                  foto_talle_public_id=COALESCE(VALUES(foto_talle_public_id), foto_talle_public_id)";
    $st = must_prepare($conexion,$sql);
    $st->bind_param('isiss',$producto_id,$talle,$stock,$foto_u,$foto_pid);
    $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1&pid='.$producto_id.'#talles'); exit;
  }

  /* === Editar talle (sólo stock/foto) === */
  if ($f==='edit_talle') {
    $tid   = (int)($_POST['talle_id']??0);
    $stock = (int)($_POST['stock']??0);

    // Traer registro
    $st = must_prepare($conexion, "SELECT producto_id, foto_talle_url, foto_talle_public_id FROM ind_talles WHERE id=? LIMIT 1");
    $st->bind_param('i',$tid); $st->execute();
    $curr = $st->get_result()->fetch_assoc(); $st->close();

    if ($curr) {
      $producto_id = (int)$curr['producto_id'];
      $foto_u = $curr['foto_talle_url'];
      $foto_pid = $curr['foto_talle_public_id'];

      if (!empty($_FILES['foto_talle']['name']) && $_FILES['foto_talle']['error']===UPLOAD_ERR_OK && $cloud_ok) {
        [$foto_u,$foto_pid] = subir_imagen_cloud($_FILES['foto_talle']['tmp_name'], "indumentaria/talles/gym_$gimnasio_id/prod_$producto_id");
      }

      $sql = "UPDATE ind_talles SET stock=?, foto_talle_url=?, foto_talle_public_id=? WHERE id=?";
      $st = must_prepare($conexion, $sql);
      $st->bind_param('issi', $stock, $foto_u, $foto_pid, $tid);
      $st->execute(); $st->close();

      header('Location: admin_indum.php?ok=1&pid='.$producto_id.'#talles'); exit;
    } else {
      header('Location: admin_indum.php?err=notfound'); exit;
    }
  }

  /* === Eliminar talle === */
  if ($f==='del_talle') {
    $tid = (int)($_POST['talle_id']??0);

    // recuperar product id para volver al ancla
    $st = must_prepare($conexion, "SELECT producto_id FROM ind_talles WHERE id=? LIMIT 1");
    $st->bind_param('i',$tid); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    $pid_back = (int)($r['producto_id']??0);

    $st = must_prepare($conexion, "DELETE FROM ind_talles WHERE id=?");
    $st->bind_param('i',$tid); $st->execute(); $st->close();

    header('Location: admin_indum.php?ok=1&pid='.$pid_back.'#talles'); exit;
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
$edit_id=(int)($_GET['edit']??0);
$talles=[];
if ($pid_sel>0){
  $st = must_prepare($conexion, "SELECT * FROM ind_talles WHERE producto_id=? ORDER BY talle");
  $st->bind_param('i',$pid_sel); $st->execute();
  $talles=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}
$prod_edit=null;
if ($edit_id>0){
  $st = must_prepare($conexion, "SELECT * FROM ind_productos WHERE id=? AND gimnasio_id=? LIMIT 1");
  $st->bind_param('ii',$edit_id,$gimnasio_id); $st->execute();
  $prod_edit=$st->get_result()->fetch_assoc(); $st->close();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Indumentaria</title>
<style>
 :root{ --bg:#0f1320; --card:#141a2a; --border:#24314d; --muted:#9fb0d3; --brand:#3b82f6; }
 body{font-family:system-ui,Segoe UI,Roboto,Arial;background:var(--bg);color:#fff;margin:0}
 .wrap{max-width:1100px;margin:24px auto;padding:16px}
 .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;margin-top:16px}
 label{display:block;margin:.5rem 0 .25rem}
 input,select,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #2a3550;background:#0d1322;color:#fff}
 .row{display:flex;gap:12px;flex-wrap:wrap}
 .btn{padding:10px 14px;border-radius:10px;border:0;background:var(--brand);color:#fff;cursor:pointer;font-weight:700}
 .btn.out{background:transparent;border:1px solid var(--brand);color:var(--brand)}
 .btn.warn{background:#ef4444}
 .mini{font-size:12px;color:var(--muted)}
 .table-wrap{width:100%;overflow:auto;border:1px solid var(--border);border-radius:10px}
 table{width:100%;border-collapse:collapse;min-width:800px}
 th,td{padding:10px;border-bottom:1px solid #22304d;text-align:left}
 img.thumb{max-width:72px;border-radius:8px;border:1px solid var(--border)}
 .note{background:#0b1220;border-left:4px solid var(--brand);padding:10px 12px;border-radius:10px;margin-top:8px}
 .ok{color:#34d399}.bad{color:#fca5a5}
 .actions{display:flex;gap:8px;flex-wrap:wrap}
 /* Responsive */
 @media (max-width: 720px){
   .wrap{padding:12px}
   .row > * { flex:1 1 100% }
   .btn{width:100%}
   table{min-width:640px}
   img.thumb{max-width:56px}
 }
</style>
<script>
function confDel(msg){ return confirm(msg||'¿Eliminar? Esta acción no se puede deshacer.'); }
</script>
</head>
<body>
<div class="wrap">
  <h1>🛍️ Administración — Indumentaria</h1>

  <!-- Estado de Cloudinary -->
  <div class="note">
    <?php if($cloud_ok): ?>
      <span class="ok">Cloudinary habilitado <?=($cloud_mode==='curl_fallback'?'(modo: cURL sin SDK)':'(modo: '.$cloud_mode.')')?></span>
    <?php else: ?>
      <span class="bad">Cloudinary NO habilitado</span>
      <?= $cloud_reason ? ' — Motivo: '.h($cloud_reason) : '' ?>
      <?= $cloud_hint   ? ' — Sugerencia: '.h($cloud_hint)   : '' ?>
    <?php endif; ?>
  </div>

  <!-- Alta / Edición de producto -->
  <div class="card">
    <h2><?= $prod_edit ? 'Editar producto #'.(int)$prod_edit['id'] : 'Nuevo producto' ?></h2>
    <form method="post" enctype="multipart/form-data" class="row">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="__form" value="<?= $prod_edit ? 'edit_producto' : 'add_producto' ?>">
      <?php if($prod_edit): ?>
        <input type="hidden" name="producto_id" value="<?= (int)$prod_edit['id'] ?>">
      <?php endif; ?>

      <div style="flex:1">
        <label>Categoría</label>
        <select name="categoria">
          <?php
            $catv = $prod_edit['categoria'] ?? 'remera';
            $cats = ['remera'=>'Remera/Musculosa','short'=>'Short/Pantalón','otro'=>'Otro'];
            foreach($cats as $val=>$txt){
              $sel = ($val===$catv)?'selected':'';
              echo "<option value=\"$val\" $sel>$txt</option>";
            }
          ?>
        </select>
      </div>
      <div style="flex:2"><label>Título</label><input name="titulo" required value="<?=h($prod_edit['titulo'] ?? '')?>"></div>
      <div style="flex:3"><label>Descripción</label><input name="descripcion" value="<?=h($prod_edit['descripcion'] ?? '')?>"></div>
      <div style="flex:1"><label>Precio</label><input type="number" step="0.01" name="precio" required value="<?=h($prod_edit['precio'] ?? '')?>"></div>
      <div style="flex:1">
        <label><?= $prod_edit ? 'Reemplazar foto (opcional)' : 'Foto (opcional)' ?></label>
        <input type="file" name="foto" accept="image/*">
        <?php if($prod_edit && !empty($prod_edit['foto_url'])): ?>
          <div class="mini">Actual: <br><img class="thumb" src="<?=h($prod_edit['foto_url'])?>"></div>
        <?php endif; ?>
      </div>
      <label style="display:flex;gap:8px;align-items:center">
        <input type="checkbox" name="activo" <?= ($prod_edit ? ($prod_edit['activo']?'checked':'') : 'checked') ?>> Activo
      </label>
      <div class="actions">
        <button class="btn"><?= $prod_edit ? 'Guardar cambios' : 'Guardar producto' ?></button>
        <?php if($prod_edit): ?>
          <a class="btn out" href="admin_indum.php">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Listado de productos -->
  <div class="card">
    <h2>Productos</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Foto</th><th>Título</th><th>Cat</th><th>Precio</th><th>Activo</th><th>Acciones</th></tr></thead>
        <tbody>
          <?php if(empty($prods)): ?>
            <tr><td colspan="7" class="mini">Sin productos aún</td></tr>
          <?php else: foreach($prods as $p): ?>
            <tr>
              <td><?=$p['id']?></td>
              <td><?php if($p['foto_url']): ?><img class="thumb" src="<?=h($p['foto_url'])?>"><?php endif; ?></td>
              <td><?=h($p['titulo'])?></td>
              <td><?=h($p['categoria'])?></td>
              <td>$<?=number_format($p['precio'],2,',','.')?></td>
              <td><?=$p['activo']?'Sí':'No'?></td>
              <td class="actions">
                <a class="btn out" href="?edit=<?=$p['id']?>">Editar</a>
                <a class="btn out" href="?pid=<?=$p['id']?>#talles">Talles</a>
                <form method="post" onsubmit="return confDel('¿Eliminar este producto? También se eliminarán sus talles.');">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="__form" value="del_producto">
                  <input type="hidden" name="producto_id" value="<?=$p['id']?>">
                  <button class="btn warn">Eliminar</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="__form" value="toggle_activo">
                  <input type="hidden" name="producto_id" value="<?=$p['id']?>">
                  <label class="mini" style="display:inline-flex;align-items:center;gap:6px;margin:0 6px">
                    <input type="checkbox" name="activo" <?=$p['activo']?'checked':''?>> activo
                  </label>
                  <button class="btn">Aplicar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Talles -->
  <?php if($pid_sel>0): ?>
  <div class="card" id="talles">
    <h2>Talles — Producto #<?=$pid_sel?></h2>
    <form method="post" enctype="multipart/form-data" class="row" style="align-items:end">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="__form" value="add_talle">
      <input type="hidden" name="producto_id" value="<?=$pid_sel?>">
      <div style="flex:1"><label>Talle</label><input name="talle" placeholder="XS / S / M / L / 38 / 40" required></div>
      <div style="flex:1"><label>Stock</label><input type="number" name="stock" value="0" required></div>
      <div style="flex:2"><label>Foto guía (opcional)</label><input type="file" name="foto_talle" accept="image/*"></div>
      <div><button class="btn">Agregar / Actualizar</button></div>
    </form>

    <div class="table-wrap" style="margin-top:12px">
      <table>
        <thead><tr><th>#</th><th>Talle</th><th>Stock</th><th>Foto</th><th>Editar</th><th>Eliminar</th></tr></thead>
        <tbody>
          <?php if(empty($talles)): ?>
            <tr><td colspan="6" class="mini">Sin talles aún</td></tr>
          <?php else: foreach($talles as $t): ?>
            <tr>
              <td><?=$t['id']?></td>
              <td><?=h($t['talle'])?></td>
              <td><?=$t['stock']?></td>
              <td><?php if($t['foto_talle_url']): ?><img class="thumb" src="<?=h($t['foto_talle_url'])?>"><?php endif; ?></td>
              <td>
                <form method="post" enctype="multipart/form-data" class="row" style="align-items:center">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="__form" value="edit_talle">
                  <input type="hidden" name="talle_id" value="<?=$t['id']?>">
                  <div style="min-width:120px">
                    <label class="mini">Stock</label>
                    <input type="number" name="stock" value="<?=$t['stock']?>" style="max-width:120px">
                  </div>
                  <div style="min-width:200px">
                    <label class="mini">Reemplazar foto</label>
                    <input type="file" name="foto_talle" accept="image/*">
                  </div>
                  <button class="btn">Guardar</button>
                </form>
              </td>
              <td>
                <form method="post" onsubmit="return confDel('¿Eliminar este talle?');">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="__form" value="del_talle">
                  <input type="hidden" name="talle_id" value="<?=$t['id']?>">
                  <button class="btn warn">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
