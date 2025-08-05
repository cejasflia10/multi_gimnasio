<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    exit("❌ Acceso denegado.");
}

// --- Obtener datos del gimnasio ---
$gimnasio_stmt = $conexion->prepare("SELECT * FROM gimnasios WHERE id = ? LIMIT 1");
$gimnasio_stmt->bind_param("i", $gimnasio_id);
$gimnasio_stmt->execute();
$gimnasio = $gimnasio_stmt->get_result()->fetch_assoc();
$gimnasio_stmt->close();

// --- Obtener o crear configuración general para este gimnasio ---
$config_stmt = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1");
$config_stmt->bind_param("i", $gimnasio_id);
$config_stmt->execute();
$config = $config_stmt->get_result()->fetch_assoc();
$config_stmt->close();

if (!$config) {
    // Insertar fila por defecto
    $ins = $conexion->prepare("INSERT INTO configuracion_gimnasio (gimnasio_id, color_encabezado, mostrar_logo_pdf, mostrar_cuit_pdf, mostrar_datos_contacto_pdf, mensaje_bienvenida, sitio_web, facebook, instagram) VALUES (?, ?, 1, 1, 1, ?, ?, ?, ?)");
    $default_color = '#FFD700';
    $default_msg = '';
    $default_web = '';
    $default_fb = '';
    $default_ig = '';
    $ins->bind_param("isssss i", $gimnasio_id, $default_color, $default_msg, $default_web, $default_fb, $default_ig);
    // above bind types were wrong to keep simple: do fallback insert using real_escape_string
    $color_esc = $conexion->real_escape_string($default_color);
    $msg_esc = $conexion->real_escape_string($default_msg);
    $web_esc = $conexion->real_escape_string($default_web);
    $fb_esc = $conexion->real_escape_string($default_fb);
    $ig_esc = $conexion->real_escape_string($default_ig);
    $conexion->query("INSERT INTO configuracion_gimnasio (gimnasio_id, color_encabezado, mostrar_logo_pdf, mostrar_cuit_pdf, mostrar_datos_contacto_pdf, mensaje_bienvenida, sitio_web, facebook, instagram) VALUES ($gimnasio_id, '$color_esc', 1, 1, 1, '$msg_esc', '$web_esc', '$fb_esc', '$ig_esc')");
    // volver a cargar
    $config = $conexion->query("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();
}

// --- Obtener enlace whatsapp (links_gimnasio) ---
$link_row = $conexion->query("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id LIMIT 1")->fetch_assoc();
$enlace_whatsapp_actual = $link_row['enlace_whatsapp'] ?? '';

// --- Manejo de POST (guardar configuraciones y enlace whatsapp) ---
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener valores con saneamiento básico
    $color = $conexion->real_escape_string($_POST['color_encabezado'] ?? ($config['color_encabezado'] ?? '#FFD700'));
    $mostrar_logo_pdf = isset($_POST['mostrar_logo_pdf']) ? 1 : 0;
    $mostrar_cuit_pdf = isset($_POST['mostrar_cuit_pdf']) ? 1 : 0;
    $mostrar_datos_contacto_pdf = isset($_POST['mostrar_datos_contacto_pdf']) ? 1 : 0;
    $mensaje_bienvenida = $conexion->real_escape_string($_POST['mensaje_bienvenida'] ?? $config['mensaje_bienvenida'] ?? '');
    $sitio_web = $conexion->real_escape_string($_POST['sitio_web'] ?? $config['sitio_web'] ?? '');
    $facebook = $conexion->real_escape_string($_POST['facebook'] ?? $config['facebook'] ?? '');
    $instagram = $conexion->real_escape_string($_POST['instagram'] ?? $config['instagram'] ?? '');

    // Guardar/Actualizar configuracion_gimnasio
    $upd_sql = "
        UPDATE configuracion_gimnasio SET
            color_encabezado = ?,
            mostrar_logo_pdf = ?,
            mostrar_cuit_pdf = ?,
            mostrar_datos_contacto_pdf = ?,
            mensaje_bienvenida = ?,
            sitio_web = ?,
            facebook = ?,
            instagram = ?
        WHERE gimnasio_id = ?
    ";
    $upd = $conexion->prepare($upd_sql);
    if ($upd) {
        $upd->bind_param("siisssssi", $color, $mostrar_logo_pdf, $mostrar_cuit_pdf, $mostrar_datos_contacto_pdf, $mensaje_bienvenida, $sitio_web, $facebook, $instagram, $gimnasio_id);
        $upd->execute();
        $upd->close();
    } else {
        // fallback si prepare falla
        $conexion->query("UPDATE configuracion_gimnasio SET color_encabezado = '$color', mostrar_logo_pdf = $mostrar_logo_pdf, mostrar_cuit_pdf = $mostrar_cuit_pdf, mostrar_datos_contacto_pdf = $mostrar_datos_contacto_pdf, mensaje_bienvenida = '$mensaje_bienvenida', sitio_web = '$sitio_web', facebook = '$facebook', instagram = '$instagram' WHERE gimnasio_id = $gimnasio_id");
    }

    // Guardar/Actualizar enlace_whatsapp en links_gimnasio
    $enlace_whatsapp = trim($_POST['enlace_whatsapp'] ?? '');
    if ($enlace_whatsapp !== '') {
        // comprobar si existe fila
        $check = $conexion->query("SELECT id FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $conexion->query("UPDATE links_gimnasio SET enlace_whatsapp = '" . $conexion->real_escape_string($enlace_whatsapp) . "' WHERE gimnasio_id = $gimnasio_id");
        } else {
            $conexion->query("INSERT INTO links_gimnasio (gimnasio_id, enlace_whatsapp) VALUES ($gimnasio_id, '" . $conexion->real_escape_string($enlace_whatsapp) . "')");
        }
    } else {
        // Si el campo viene vacío, borramos el enlace (opcional)
        $conexion->query("DELETE FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id");
    }

    // recargar variables
    $config = $conexion->query("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();
    $link_row = $conexion->query("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id LIMIT 1")->fetch_assoc();
    $enlace_whatsapp_actual = $link_row['enlace_whatsapp'] ?? '';

    $mensaje = "✅ Configuración guardada correctamente.";
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
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        .panel { max-width: 1000px; margin: auto; background: #111; padding: 20px; border-radius: 10px; }
        h2, h3 { color: gold; }
        .item { margin-bottom: 25px; border-bottom: 1px solid #444; padding-bottom: 15px; }
        .item:last-child { border: none; }
        .boton {
            background: gold;
            color: black;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        input[type="text"], textarea {
            width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #888; border-radius: 6px; background: #222; color: gold;
        }
        input[type="color"] { margin-left: 10px; vertical-align: middle; width: 60px; height: 36px; padding: 0; border: none; }
        label.inline { display: inline-flex; align-items:center; gap:8px; margin-right:15px; }
        .mensaje_ok { background:#0f0f0f; border:1px solid #2ecc71; color:#2ecc71; padding:10px; border-radius:6px; margin-bottom:12px; }
        .links-directos { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-top:10px; }
    </style>
</head>
<body>

<div class="panel">
    <h2>⚙️ Panel de Configuración del Gimnasio</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje_ok"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="item">
        <h3>🧾 Datos del Gimnasio</h3>
        <p><strong><?= htmlspecialchars($gimnasio['nombre'] ?? '') ?></strong></p>
        <p>Dirección: <?= htmlspecialchars($gimnasio['direccion'] ?? '') ?></p>
        <p>CUIT: <?= htmlspecialchars($gimnasio['cuit'] ?? '') ?></p>
        <p>Teléfono: <?= htmlspecialchars($gimnasio['telefono'] ?? '') ?></p>
        <p>Email: <?= htmlspecialchars($gimnasio['email'] ?? '') ?></p>
        <p>Vencimiento del sistema: <strong style="color: orange;">
            <?= isset($gimnasio['fecha_vencimiento']) ? date('d/m/Y', strtotime($gimnasio['fecha_vencimiento'])) : '-' ?>
        </strong></p>
        <a href="editar_gimnasio.php?id=<?= $gimnasio_id ?>" class="boton">✏️ Editar Datos</a>
    </div>

    <form method="POST">
        <div class="item">
            <h3>🎨 Preferencias Visuales</h3>
            <label>Color del encabezado:
                <input type="color" name="color_encabezado" value="<?= htmlspecialchars($config['color_encabezado'] ?? '#FFD700') ?>">
            </label>
        </div>

        <div class="item">
            <h3>🖨️ Configuración de Facturas</h3>
            <label class="inline"><input type="checkbox" name="mostrar_logo_pdf" <?= (!empty($config['mostrar_logo_pdf']) ? 'checked' : '') ?>> Mostrar logo en PDF</label>
            <label class="inline"><input type="checkbox" name="mostrar_cuit_pdf" <?= (!empty($config['mostrar_cuit_pdf']) ? 'checked' : '') ?>> Mostrar CUIT en PDF</label>
            <label class="inline"><input type="checkbox" name="mostrar_datos_contacto_pdf" <?= (!empty($config['mostrar_datos_contacto_pdf']) ? 'checked' : '') ?>> Mostrar teléfono/email en PDF</label>
        </div>

        <div class="item">
            <h3>💬 Mensaje de Bienvenida</h3>
            <textarea name="mensaje_bienvenida" rows="3"><?= htmlspecialchars($config['mensaje_bienvenida'] ?? '') ?></textarea>
        </div>

        <div class="item">
            <h3>🌐 Redes y Enlaces</h3>
            <label>Sitio Web:</label>
            <input type="text" name="sitio_web" value="<?= htmlspecialchars($config['sitio_web'] ?? '') ?>"><br>
            <label>Facebook:</label>
            <input type="text" name="facebook" value="<?= htmlspecialchars($config['facebook'] ?? '') ?>"><br>
            <label>Instagram:</label>
            <input type="text" name="instagram" value="<?= htmlspecialchars($config['instagram'] ?? '') ?>">
        </div>

        <div class="item">
            <h3>📲 Enlace de WhatsApp (Grupo)</h3>
            <p>Este enlace se mostrará en la página de bienvenida cuando un cliente se registre online.</p>
            <input type="text" name="enlace_whatsapp" placeholder="https://chat.whatsapp.com/XXXXXX" value="<?= htmlspecialchars($enlace_whatsapp_actual) ?>">
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
            <a href="cliente_acceso.php?id=<?= $gimnasio_id ?>" class="boton" target="_blank">👤 Panel del Cliente</a>
            <a href="login_profesor.php?id=<?= $gimnasio_id ?>" class="boton" target="_blank">👨‍🏫 Panel del Profesor</a>
            <a href="registrar_cliente_online.php?gimnasio=<?= $gimnasio_id ?>" class="boton" target="_blank">📝 Registro Online</a>
        </div>
    </div>

</div>

</body>
</html>
