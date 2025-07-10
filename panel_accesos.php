<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$mensaje = "";

// Al guardar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = isset($_POST['acceso_dni']) ? 1 : 0;
    $qr = isset($_POST['acceso_qr']) ? 1 : 0;
    $rfid = isset($_POST['acceso_rfid']) ? 1 : 0;
    $huella = isset($_POST['acceso_huella']) ? 1 : 0;

    $existe = $conexion->query("SELECT * FROM configuracion_accesos WHERE gimnasio_id = $gimnasio_id")->num_rows;

    if ($existe) {
        $conexion->query("UPDATE configuracion_accesos SET acceso_dni = $dni, acceso_qr = $qr, acceso_rfid = $rfid, acceso_huella = $huella WHERE gimnasio_id = $gimnasio_id");
    } else {
        $conexion->query("INSERT INTO configuracion_accesos (gimnasio_id, acceso_dni, acceso_qr, acceso_rfid, acceso_huella)
                          VALUES ($gimnasio_id, $dni, $qr, $rfid, $huella)");
    }

    $mensaje = "<p style='color:lime;'>✅ Configuración actualizada correctamente.</p>";
}

// Obtener valores actuales
$config = $conexion->query("SELECT * FROM configuracion_accesos WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Accesos</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { background: #222; padding: 20px; border-radius: 10px; max-width: 500px; margin: auto; }
        label { display: block; margin: 15px 0; }
        input[type="checkbox"] { transform: scale(1.5); margin-right: 10px; }
        button { background: gold; color: black; padding: 10px 20px; font-weight: bold; border: none; cursor: pointer; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>🔐 Panel de Accesos Permitidos</h2>
    <?= $mensaje ?>
    <form method="POST">
        <label><input type="checkbox" name="acceso_dni" <?= ($config['acceso_dni'] ?? 1) ? 'checked' : '' ?>> Acceso por DNI</label>
        <label><input type="checkbox" name="acceso_qr" <?= ($config['acceso_qr'] ?? 1) ? 'checked' : '' ?>> Acceso por QR</label>
        <label><input type="checkbox" name="acceso_rfid" <?= ($config['acceso_rfid'] ?? 0) ? 'checked' : '' ?>> Acceso por RFID</label>
        <label><input type="checkbox" name="acceso_huella" <?= ($config['acceso_huella'] ?? 0) ? 'checked' : '' ?>> Acceso por Huella</label>

        <button type="submit">💾 Guardar cambios</button>
    </form>
</div>

</body>
</html>
