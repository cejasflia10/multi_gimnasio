<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* Tabla simple de configuración (clave=>valor), si no existe la creamos */
$conexion->query("
  CREATE TABLE IF NOT EXISTS `eventos_config` (
    `clave` VARCHAR(64) NOT NULL PRIMARY KEY,
    `valor` TEXT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Leer valor actual */
function get_brand_path(mysqli $db): ?string {
  $r = $db->query("SELECT valor FROM eventos_config WHERE clave='brand_image_path' LIMIT 1");
  if ($r && $r->num_rows){ $v = (string)$r->fetch_assoc()['valor']; $r->close(); return $v ?: null; }
  return null;
}
$current = get_brand_path($conexion);

/* Manejo POST */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'remove') {
    // borrar archivo físico (si existe) y limpiar config
    if ($current) {
      $abs = __DIR__ . '/' . ltrim($current, '/');
      if (is_file($abs)) @unlink($abs);
    }
    $conexion->query("REPLACE INTO eventos_config (clave, valor) VALUES ('brand_image_path', NULL)");
    $_SESSION['flash_ok'] = 'Imagen eliminada.';
    header('Location: upload_brand_image.php'); exit;
  }

  if ($accion === 'upload') {
    if (empty($_FILES['brand_image']) || ($_FILES['brand_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      $_SESSION['flash_err'] = 'Subí un archivo de imagen válido.'; header('Location: upload_brand_image.php'); exit;
    }

    $tmp  = $_FILES['brand_image']['tmp_name'];
    $name = (string)$_FILES['brand_image']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed_ext = ['jpg','jpeg','png','webp','svg'];
    if (!in_array($ext, $allowed_ext, true)) {
      $_SESSION['flash_err'] = 'Formato no permitido. Usá JPG, PNG, WEBP o SVG.'; header('Location: upload_brand_image.php'); exit;
    }

    // Validación básica de imagen (skip para svg)
    if ($ext !== 'svg') {
      $info = @getimagesize($tmp);
      if (!$info) { $_SESSION['flash_err'] = 'El archivo no parece ser una imagen válida.'; header('Location: upload_brand_image.php'); exit; }
    }

    // Directorio destino
    $dir = __DIR__ . '/uploads/branding';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    // Nombre único
    try { $rand = bin2hex(random_bytes(4)); } catch(Throwable $e){ $rand = uniqid(); }
    $fname = 'brand_'.date('Ymd_His')."_{$rand}.{$ext}";
    $dest  = $dir . '/' . $fname;

    if (!@move_uploaded_file($tmp, $dest)) {
      $_SESSION['flash_err'] = 'No se pudo mover el archivo subido.'; header('Location: upload_brand_image.php'); exit;
    }

    // Guardamos ruta relativa para usarla en la web
    $rel = 'uploads/branding/'.$fname;
    $st = $conexion->prepare("REPLACE INTO eventos_config (clave, valor) VALUES ('brand_image_path', ?)");
    $st->bind_param('s', $rel); $st->execute(); $st->close();

    $_SESSION['flash_ok'] = 'Imagen actualizada correctamente.';
    header('Location: upload_brand_image.php'); exit;
  }
}

/* Flash */
$ok  = !empty($_SESSION['flash_ok'])  ? $_SESSION['flash_ok']  : '';
$err = !empty($_SESSION['flash_err']) ? $_SESSION['flash_err'] : '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Subir imagen de marca (header)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{ --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff; --card:#0f1720; --bd:#1f2a33; --brand:#d4af37; }
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    .wrap{max-width:900px;margin:16px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem .9rem;border-radius:10px;border:1px solid var(--bd);background:#15202b;color:var(--brand);cursor:pointer;text-decoration:none;font-weight:700}
    .btn.gray{background:#1b2733;color:#d6e2ef}
    .btn.red{background:#5b1a1a;color:#fff;border-color:#7a2a2a}
    input[type="file"],button,select{padding:.6rem .7rem;border-radius:10px;border:1px solid var(--bd);background:#0f1720;color:var(--fg)}
    .mut{color:var(--mut)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
    .preview{display:flex;justify-content:center;align-items:center;min-height:120px;border:1px dashed #2a3b4d;border-radius:12px;background:#0b141d}
    .preview img{max-width:100%;max-height:180px;object-fit:contain}
  </style>
</head>
<body>
  <?php @include __DIR__.'/menu_eventos.php'; ?>

  <div class="wrap">
    <div class="row" style="margin-bottom:10px">
      <a class="btn gray" href="panel_eventos.php">← Volver al panel</a>
      <span class="mut">Configuración global de marca (header)</span>
    </div>

    <?php if($ok): ?><div class="ok"><?= h($ok) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

    <div class="card">
      <h2 style="margin:0 0 8px">🖼️ Subir imagen de marca</h2>
      <p class="mut" style="margin:6px 0 12px">
        Formatos aceptados: JPG, PNG, WEBP o SVG. Se guardará en <code>uploads/branding/</code>.
        Luego se mostrará automáticamente en todas las páginas que incluyan <code>brand_header.php</code>.
      </p>

      <div class="preview" style="margin-bottom:12px">
        <?php if ($current): ?>
          <img src="<?= h($current) ?>" alt="Imagen actual">
        <?php else: ?>
          <div class="mut">Sin imagen cargada</div>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" class="row" style="margin-bottom:10px">
        <input type="hidden" name="accion" value="upload">
        <input type="file" name="brand_image" accept=".jpg,.jpeg,.png,.webp,.svg,image/*" required>
        <button class="btn" type="submit">⬆ Subir / Reemplazar</button>
      </form>

      <?php if ($current): ?>
        <form method="post" onsubmit="return confirm('¿Eliminar la imagen actual?');">
          <input type="hidden" name="accion" value="remove">
          <button class="btn red" type="submit">🗑 Eliminar imagen</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Cómo mostrarla en tus páginas</h3>
      <p class="mut" style="margin:6px 0 8px">
        Agregá esta línea al principio del <code>&lt;body&gt;</code> (o donde quieras mostrarla):
      </p>
      <pre style="background:#0b141d;border:1px solid #1f2a33;border-radius:10px;padding:10px;overflow:auto;">
&lt;?php @include __DIR__.'/brand_header.php'; ?&gt;</pre>
    </div>
  </div>
</body>
</html>
