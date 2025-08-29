<?php
/* ===== DEBUG (desactivar en producción) ===== */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
  exit("❌ Acceso denegado.");
}

/* ---------- Datos del gimnasio ---------- */
$gimnasio = [];
if ($st = $conexion->prepare("SELECT * FROM gimnasios WHERE id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  $gimnasio = $res ? $res->fetch_assoc() : [];
  $st->close();
}

/* ---------- Configuración (crear si no existe) ---------- */
$config = [];
if ($st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  $config = $res ? $res->fetch_assoc() : null;
  $st->close();
}

if (!$config) {
  // valores por defecto
  $default_color = '#FFD700';
  $default_msg   = '';
  $default_web   = '';
  $default_fb    = '';
  $default_ig    = '';

  // gimnasio_id (i) + 5 strings (s) = 'isssss'
  $ins = $conexion->prepare("
    INSERT INTO configuracion_gimnasio
      (gimnasio_id, color_encabezado, mostrar_logo_pdf, mostrar_cuit_pdf, mostrar_datos_contacto_pdf, mensaje_bienvenida, sitio_web, facebook, instagram)
    VALUES (?, ?, 1, 1, 1, ?, ?, ?, ?)
  ");
  if (!$ins) { die("Error prepare INSERT config: ".$conexion->error); }
  $ins->bind_param('isssss', $gimnasio_id, $default_color, $default_msg, $default_web, $default_fb, $default_ig);
  if (!$ins->execute()) { die("Error exec INSERT config: ".$ins->error); }
  $ins->close();

  // recargar config
  if ($st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
    $st->bind_param('i', $gimnasio_id);
    $st->execute();
    $res = $st->get_result();
    $config = $res ? $res->fetch_assoc() : [];
    $st->close();
  }
}

/* ---------- Enlace WhatsApp ---------- */
$enlace_whatsapp_actual = '';
if ($st = $conexion->prepare("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $st->bind_result($enlace_whatsapp_actual);
  $st->fetch();
  $st->close();
}

/* ---------- Guardar POST ---------- */
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $color  = trim($_POST['color_encabezado'] ?? ($config['color_encabezado'] ?? '#FFD700'));
  $logo   = isset($_POST['mostrar_logo_pdf']) ? 1 : 0;
  $cuit   = isset($_POST['mostrar_cuit_pdf']) ? 1 : 0;
  $contact= isset($_POST['mostrar_datos_contacto_pdf']) ? 1 : 0;

  $mensaje_bienvenida = trim($_POST['mensaje_bienvenida'] ?? ($config['mensaje_bienvenida'] ?? ''));
  $sitio_web = trim($_POST['sitio_web'] ?? ($config['sitio_web'] ?? ''));
  $facebook  = trim($_POST['facebook']  ?? ($config['facebook']  ?? ''));
  $instagram = trim($_POST['instagram'] ?? ($config['instagram'] ?? ''));

  // UPDATE: s (color) + i + i + i + s + s + s + s + i (id) = 'siiissssi'
  $upd = $conexion->prepare("
    UPDATE configuracion_gimnasio
       SET color_encabezado=?,
           mostrar_logo_pdf=?,
           mostrar_cuit_pdf=?,
           mostrar_datos_contacto_pdf=?,
           mensaje_bienvenida=?,
           sitio_web=?,
           facebook=?,
           instagram=?
     WHERE gimnasio_id=?
  ");
  if (!$upd) { die("Error prepare UPDATE config: ".$conexion->error); }
  $upd->bind_param('siiissssi', $color, $logo, $cuit, $contact, $mensaje_bienvenida, $sitio_web, $facebook, $instagram, $gimnasio_id);
  if (!$upd->execute()) { die("Error exec UPDATE config: ".$upd->error); }
  $upd->close();

  // WhatsApp: upsert manual
  $enlace_whatsapp = trim($_POST['enlace_whatsapp'] ?? '');
  if ($enlace_whatsapp !== '') {
    // ¿existe?
    $exists = 0;
    if ($ck = $conexion->prepare("SELECT 1 FROM links_gimnasio WHERE gimnasio_id=? LIMIT 1")) {
      $ck->bind_param('i', $gimnasio_id);
      $ck->execute();
      $ck->store_result();
      $exists = $ck->num_rows > 0 ? 1 : 0;
      $ck->close();
    }
    if ($exists) {
      $st = $conexion->prepare("UPDATE links_gimnasio SET enlace_whatsapp=? WHERE gimnasio_id=?");
      $st->bind_param('si', $enlace_whatsapp, $gimnasio_id);
      $st->execute();
      $st->close();
    } else {
      $st = $conexion->prepare("INSERT INTO links_gimnasio (gimnasio_id, enlace_whatsapp) VALUES (?,?)");
      $st->bind_param('is', $gimnasio_id, $enlace_whatsapp);
      $st->execute();
      $st->close();
    }
    $enlace_whatsapp_actual = $enlace_whatsapp;
  } else {
    // si viene vacío, eliminar fila
    if ($del = $conexion->prepare("DELETE FROM links_gimnasio WHERE gimnasio_id=?")) {
      $del->bind_param('i', $gimnasio_id);
      $del->execute();
      $del->close();
    }
    $enlace_whatsapp_actual = '';
  }

  // recargar config
  if ($st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
    $st->bind_param('i', $gimnasio_id);
    $st->execute();
    $res = $st->get_result();
    $config = $res ? $res->fetch_assoc() : $config;
    $st->close();
  }

  $mensaje = "✅ Configuración guardada correctamente.";
}

/* helper */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_venc($v){
  if (empty($v) || $v === '0000-00-00') return 'Sin fecha';
  $t = strtotime($v);
  return $t ? date('d/m/Y', $t) : 'Sin fecha';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Configuración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body { background:#000; color:gold; font-family:Arial,sans-serif; padding:20px; }
    .panel { max-width:1000px; margin:auto; background:#111; padding:20px; border-radius:10px; }
    h2,h3 { color:gold; }
    .item { margin-bottom:25px; border-bottom:1px solid #444; padding-bottom:15px; }
    .item:last-child { border:none; }
    .boton { background:gold; color:black; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:bold; border:none; cursor:pointer; }
    input[type="text"], textarea { width:100%; padding:8px; margin-top:5px; border:1px solid #888; border-radius:6px; background:#222; color:gold; }
    input[type="color"] { margin-left:10px; vertical-align:middle; width:60px; height:36px; padding:0; border:none; }
    label.inline { display:inline-flex; align-items:center; gap:8px; margin-right:15px; }
    .mensaje_ok { background:#0f0f0f; border:1px solid #2ecc71; color:#2ecc71; padding:10px; border-radius:6px; margin-bottom:12px; }
    .links-directos { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
  </style>
</head>
<body>

<div class="panel">
  <h2>⚙️ Panel de Configuración del Gimnasio</h2>

  <?php if (!empty($mensaje)): ?>
    <div class="mensaje_ok"><?= h($mensaje) ?></div>
  <?php endif; ?>

  <div class="item">
    <h3>🧾 Datos del Gimnasio</h3>
    <p><strong><?= h($gimnasio['nombre'] ?? '') ?></strong></p>
    <p>Dirección: <?= h($gimnasio['direccion'] ?? '') ?></p>
    <p>CUIT: <?= h($gimnasio['cuit'] ?? '') ?></p>
    <p>Teléfono: <?= h($gimnasio['telefono'] ?? '') ?></p>
    <p>Email: <?= h($gimnasio['email'] ?? '') ?></p>
    <p>Vencimiento del sistema: <strong style="color:orange;"><?= fmt_venc($gimnasio['fecha_vencimiento'] ?? '') ?></strong></p>
    <a href="editar_gimnasio.php?id=<?= (int)$gimnasio_id ?>" class="boton">✏️ Editar Datos</a>
  </div>

  <form method="POST">
    <div class="item">
      <h3>🎨 Preferencias Visuales</h3>
      <label>Color del encabezado:
        <input type="color" name="color_encabezado" value="<?= h($config['color_encabezado'] ?? '#FFD700') ?>">
      </label>
    </div>

    <div class="item">
      <h3>🖨️ Configuración de Facturas</h3>
      <label class="inline"><input type="checkbox" name="mostrar_logo_pdf" <?= !empty($config['mostrar_logo_pdf']) ? 'checked' : '' ?>> Mostrar logo en PDF</label>
      <label class="inline"><input type="checkbox" name="mostrar_cuit_pdf" <?= !empty($config['mostrar_cuit_pdf']) ? 'checked' : '' ?>> Mostrar CUIT en PDF</label>
      <label class="inline"><input type="checkbox" name="mostrar_datos_contacto_pdf" <?= !empty($config['mostrar_datos_contacto_pdf']) ? 'checked' : '' ?>> Mostrar teléfono/email en PDF</label>
    </div>

    <div class="item">
      <h3>💬 Mensaje de Bienvenida</h3>
      <textarea name="mensaje_bienvenida" rows="3"><?= h($config['mensaje_bienvenida'] ?? '') ?></textarea>
    </div>

    <div class="item">
      <h3>🌐 Redes y Enlaces</h3>
      <label>Sitio Web:</label>
      <input type="text" name="sitio_web" value="<?= h($config['sitio_web'] ?? '') ?>"><br>
      <label>Facebook:</label>
      <input type="text" name="facebook" value="<?= h($config['facebook'] ?? '') ?>"><br>
      <label>Instagram:</label>
      <input type="text" name="instagram" value="<?= h($config['instagram'] ?? '') ?>">
    </div>

    <div class="item">
      <h3>📲 Enlace de WhatsApp (Grupo)</h3>
      <p>Este enlace se mostrará en la página de bienvenida cuando un cliente se registre online.</p>
      <input type="text" name="enlace_whatsapp" placeholder="https://chat.whatsapp.com/XXXXXX" value="<?= h($enlace_whatsapp_actual) ?>">
    </div>

    <div class="item">
      <button type="submit" class="boton">💾 Guardar Configuración</button>
    </div>
  </form>

  <div class="item">
    <h3>📤 Exportar Información</h3>
    <div class="links-directos">
      <a href="exportar_clientes.php" class="boton">👥 Exportar Clientes</a>
      <a href="exportar_ventas.php" class="boton">💵 Exportar Ventas</a>
      <a href="exportar_membresias.php" class="boton">🏋️ Exportar Membresías</a>
      <a href="exportar_productos.php" class="boton">🛍️ Exportar Productos</a>
    </div>
  </div>

  <div class="item">
    <h3>🔐 Seguridad</h3>
    <a href="cambiar_password.php" class="boton">🔒 Cambiar Contraseña</a>
  </div>

  <div class="item">
    <h3>🔗 Enlaces Directos del Gimnasio</h3>
    <div class="links-directos">
      <a href="cliente_acceso.php?id=<?= (int)$gimnasio_id ?>" class="boton" target="_blank">👤 Panel del Cliente</a>
      <a href="login_profesor.php?id=<?= (int)$gimnasio_id ?>" class="boton" target="_blank">👨‍🏫 Panel del Profesor</a>
      <a href="registrar_cliente_online.php?gimnasio=<?= (int)$gimnasio_id ?>" class="boton" target="_blank">📝 Registro Online</a>
    </div>
  </div>

</div>

</body>
</html>
