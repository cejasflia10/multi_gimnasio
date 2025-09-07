<?php
// brand_admin.php — Panel simple para subir/actualizar el banner global
if (session_status()===PHP_SESSION_NONE) session_start();

// Guardia mínima (ajústala a tu sistema)
if (empty($_SESSION['evento_usuario_id']) && ($_SESSION['user_rol'] ?? '')!=='admin') {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'brand_admin.php';
  header('Location: login_evento.php?return_to='.urlencode($return_to));
  exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$BASE_DIR = __DIR__;
$DIR      = $BASE_DIR.'/assets/brand';
$CFG      = $DIR.'/brand_config.json';
@mkdir($DIR, 0775, true);

$ok=''; $err='';
$cfg = ['filename'=>'','link'=>'index.php','height'=>'64px'];

// Cargar config actual
if (is_file($CFG)) {
  $raw = @file_get_contents($CFG);
  if ($raw!==false) {
    $j = json_decode($raw,true);
    if (is_array($j)) $cfg = array_merge($cfg,$j);
  }
}

// Procesar POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
  $link   = trim((string)($_POST['link'] ?? 'index.php'));
  $height = trim((string)($_POST['height'] ?? '64px'));

  // Validación básica del alto (solo números + px/%/rem/vh/vw)
  if ($height !== '' && !preg_match('~^\d+(\.\d+)?(px|%|rem|vh|vw)$~i', $height)) {
    $err = 'Altura inválida. Ej: 64px, 3rem, 10vh';
  }

  // Upload opcional
  if (!$err && !empty($_FILES['banner']) && ($_FILES['banner']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['banner']['tmp_name'];
    $name = (string)($_FILES['banner']['name'] ?? '');
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $okExt = ['png','jpg','jpeg','webp','gif','svg'];

    if (!in_array($ext, $okExt, true)) {
      $err = 'Formato no permitido. Usa PNG, JPG, WEBP, GIF o SVG.';
    } else {
      // Borramos anteriores brand_header.*
      foreach (glob($DIR.'/brand_header.*') as $old) { @unlink($old); }
      // Guardamos como brand_header.EXT
      $dest = $DIR.'/brand_header.'.$ext;
      if (@move_uploaded_file($tmp, $dest)) {
        // para SVG/JPG/PNG conviene 0644
        @chmod($dest, 0644);
        $cfg['filename'] = 'brand_header.'.$ext;
        $ok = 'Imagen subida correctamente.';
      } else {
        $err = 'No se pudo guardar la imagen (permisos?).';
      }
    }
  }

  if (!$err) {
    // Actualizar link/height aunque no haya imagen nueva
    if ($link   !== '') $cfg['link']   = $link;
    if ($height !== '') $cfg['height'] = $height;

    // Persistir config
    $saveOk = @file_put_contents($CFG, json_encode($cfg, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    if ($saveOk===false) {
      $err = 'No se pudo guardar la configuración.';
    } else {
      if (!$ok) $ok = 'Configuración actualizada.';
    }
  }
}

// Helpers para mostrar imagen actual
$imgUrl = '';
if (!empty($cfg['filename']) && is_file($DIR.'/'.$cfg['filename'])) {
  $imgUrl = 'assets/brand/'.$cfg['filename'].'?t='.time();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Configurar banner global</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{--bg:#0b1115;--fg:#e6eef4;--mut:#bcd8ff;--card:#0f1720;--bd:#1f2a33;--good:#0f251b;--goodbd:#164b31;--bad:#2a1414;--badbd:#5e2626}
  html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  .wrap{max-width:860px;margin:18px auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
  label{display:block;margin:.2rem 0 .3rem}
  input[type="text"],input[type="url"],input[type="file"]{width:100%;padding:.6rem .7rem;border-radius:10px;border:1px solid #28313a;background:#0c1620;color:#e6eef4}
  .btn{display:inline-block;padding:.6rem 1rem;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none;cursor:pointer}
  .mut{color:var(--mut)}
  .ok{background:var(--good);border:1px solid var(--goodbd);padding:10px;border-radius:10px;margin:10px 0}
  .bad{background:var(--bad);border:1px solid var(--badbd);padding:10px;border-radius:10px;margin:10px 0}
  img.preview{max-width:100%;height:auto;border-radius:10px;border:1px solid #223}
</style>
</head>
<body>
  <div class="wrap">
    <?php @include __DIR__.'/menu_eventos.php'; ?>

    <h2 style="margin:0 0 10px">🖼️ Banner global del sitio</h2>
    <p class="mut" style="margin-top:-6px">Esta imagen aparecerá en todas las páginas que incluyan <code>brand_strip.php</code>.</p>

    <?php if($ok): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

    <div class="card">
      <form method="post" enctype="multipart/form-data" class="row">
        <div style="flex:1 1 320px">
          <label for="banner">Nueva imagen (PNG/JPG/WEBP/GIF/SVG)</label>
          <input id="banner" type="file" name="banner" accept=".png,.jpg,.jpeg,.webp,.gif,.svg,image/*">
          <small class="mut">Se guardará en <code>assets/brand/</code> como <code>brand_header.EXT</code>.</small>
        </div>
        <div style="flex:1 1 240px">
          <label for="link">Enlace al hacer click</label>
          <input id="link" type="url" name="link" placeholder="https://tusitio.com" value="<?= h($cfg['link']) ?>">
          <small class="mut">Puedes usar rutas internas como <code>index.php</code>.</small>
        </div>
        <div style="flex:0 0 180px">
          <label for="height">Altura (ej: 64px, 3rem, 10vh)</label>
          <input id="height" type="text" name="height" value="<?= h($cfg['height']) ?>">
        </div>
        <div style="flex:1 1 100%;display:flex;gap:8px;align-items:center">
          <button class="btn" type="submit">💾 Guardar</button>
          <a class="btn" href="brand_admin.php">↻ Recargar</a>
          <a class="btn" href="eventos_disponibles.php">Ir al sitio</a>
        </div>
      </form>
    </div>

    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Vista previa actual</h3>
      <?php if($imgUrl): ?>
        <img class="preview" src="<?= h($imgUrl) ?>" alt="Banner actual">
        <div class="mut" style="margin-top:6px">
          Archivo: <code><?= h($cfg['filename']) ?></code> · Link: <code><?= h($cfg['link']) ?></code> · Altura: <code><?= h($cfg['height']) ?></code>
        </div>
      <?php else: ?>
        <div class="mut">No hay imagen configurada aún.</div>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Cómo mostrarlo en páginas</h3>
      <pre style="white-space:pre-wrap;background:#0b1320;border:1px solid #1e2a3a;border-radius:10px;padding:10px;overflow:auto"><code>&lt;?php @include __DIR__.'/brand_strip.php'; ?&gt;</code></pre>
    </div>
  </div>
</body>
</html>
