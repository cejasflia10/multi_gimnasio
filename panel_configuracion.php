<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    exit("❌ Acceso denegado.");
}

// Obtener datos del gimnasio
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();

// Obtener o insertar configuración
$config = $conexion->query("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$config) {
    $conexion->query("INSERT INTO configuracion_gimnasio (gimnasio_id) VALUES ($gimnasio_id)");
    $config = [
        'color_encabezado' => '#FFD700',
        'mostrar_logo_pdf' => 1,
        'mostrar_cuit_pdf' => 1,
        'mostrar_datos_contacto_pdf' => 1,
        'mensaje_bienvenida' => '',
        'sitio_web' => '',
        'facebook' => '',
        'instagram' => ''
    ];
}

// Guardar configuración si se envió formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $color = $_POST['color_encabezado'] ?? '#FFD700';
    $logo_pdf = isset($_POST['mostrar_logo_pdf']) ? 1 : 0;
    $cuit_pdf = isset($_POST['mostrar_cuit_pdf']) ? 1 : 0;
    $contacto_pdf = isset($_POST['mostrar_datos_contacto_pdf']) ? 1 : 0;
    $mensaje = $conexion->real_escape_string($_POST['mensaje_bienvenida'] ?? '');
    $web = $conexion->real_escape_string($_POST['sitio_web'] ?? '');
    $fb = $conexion->real_escape_string($_POST['facebook'] ?? '');
    $ig = $conexion->real_escape_string($_POST['instagram'] ?? '');

    $conexion->query("UPDATE configuracion_gimnasio SET
        color_encabezado = '$color',
        mostrar_logo_pdf = $logo_pdf,
        mostrar_cuit_pdf = $cuit_pdf,
        mostrar_datos_contacto_pdf = $contacto_pdf,
        mensaje_bienvenida = '$mensaje',
        sitio_web = '$web',
        facebook = '$fb',
        instagram = '$ig'
        WHERE gimnasio_id = $gimnasio_id");

    header("Location: panel_configuracion.php?ok=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Configuración</title>
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
        input[type="color"] {
            margin-left: 10px;
        }
    </style>
</head>

<body>

<div class="panel">
    <h2>⚙️ Panel de Configuración del Gimnasio</h2>

    <?php if (isset($_GET['ok'])): ?>
        <p style="color: lightgreen;"><strong>✔️ Configuración guardada correctamente.</strong></p>
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
        <label><input type="checkbox" name="mostrar_logo_pdf" <?= $config['mostrar_logo_pdf'] ? 'checked' : '' ?>> Mostrar logo en PDF</label><br>
        <label><input type="checkbox" name="mostrar_cuit_pdf" <?= $config['mostrar_cuit_pdf'] ? 'checked' : '' ?>> Mostrar CUIT en PDF</label><br>
        <label><input type="checkbox" name="mostrar_datos_contacto_pdf" <?= $config['mostrar_datos_contacto_pdf'] ? 'checked' : '' ?>> Mostrar teléfono/email en PDF</label>
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
        <button type="submit" class="boton">💾 Guardar Configuración</button>
    </div>
    </form>

    <div class="item">
        <h3>📤 Exportar Información</h3>
        <a href="exportar_clientes.php" class="boton">👥 Exportar Clientes</a>
        <a href="exportar_ventas.php" class="boton">💵 Exportar Ventas</a>
        <a href="exportar_membresias.php" class="boton">🏋️ Exportar Membresías</a>
        <a href="exportar_productos.php" class="boton">🛍️ Exportar Productos</a>
    </div>

    <div class="item">
        <h3>🔐 Seguridad</h3>
        <a href="cambiar_password.php" class="boton">🔒 Cambiar Contraseña</a>
    </div>
</div>
    <div class="item">
        <h3>🔗 Enlaces Directos del Gimnasio</h3>
        <div style="text-align: center;">
            <a href="cliente_acceso.php?id=<?= $gimnasio_id ?>" class="boton" target="_blank">👤 Panel del Cliente</a>
            <a href="login_profesor.php?id=<?= $gimnasio_id ?>" class="boton" target="_blank">👨‍🏫 Panel del Profesor</a>
            <a href="registrar_cliente_online.php?id=<?= $gimnasio_id ?>" class="boton" target="_blank">📝 Registro Online</a>
        </div>
    </div>

</body>
</html>
