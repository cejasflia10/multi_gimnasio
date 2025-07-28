<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// ✅ Verificar si el cliente pertenece al gimnasio
$stmt = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=?");
$stmt->bind_param("ii", $cliente_id, $gimnasio_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado al gimnasio.</div>";
    exit;
}

// ✅ Si el cliente no completó datos físicos, mostrar formulario
if ($cliente['datos_completos'] == 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_datos_fisicos'])) {
        $peso = $_POST['peso'] ?? '';
        $altura = $_POST['altura'] ?? '';
        $remera = $_POST['talle_remera'] ?? '';
        $pantalon = $_POST['talle_pantalon'] ?? '';
        $calzado = $_POST['talle_calzado'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $fecha = date('Y-m-d');

        $stmt = $conexion->prepare("INSERT INTO datos_fisicos 
            (cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones, gimnasio_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isssssssi",
            $cliente_id,
            $fecha,
            $peso,
            $altura,
            $remera,
            $pantalon,
            $calzado,
            $observaciones,
            $gimnasio_id
        );
        $stmt->execute();

        $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id=$cliente_id AND gimnasio_id=$gimnasio_id");

        header("Location: panel_cliente.php");
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Completar Datos Físicos</title>
        <style>
            body { background:black; color:gold; font-family:Arial; text-align:center; }
            .formulario { max-width:400px; margin:auto; background:#111; padding:20px; border-radius:10px; border:1px solid gold; }
            input, textarea { width:100%; padding:8px; margin-bottom:10px; }
            button { background:gold; border:none; padding:10px; font-weight:bold; cursor:pointer; width:100%; }
        </style>
    </head>
    <body>
        <h2>📋 Completar Datos Físicos</h2>
        <div class="formulario">
            <form method="POST">
                <input type="hidden" name="guardar_datos_fisicos" value="1">

                <label>Peso (kg):</label>
                <input type="text" name="peso" required>

                <label>Altura (cm):</label>
                <input type="text" name="altura" required>

                <label>Talle Remera:</label>
                <input type="text" name="talle_remera">

                <label>Talle Pantalón:</label>
                <input type="text" name="talle_pantalon">

                <label>Talle Calzado:</label>
                <input type="text" name="talle_calzado">

                <label>Observaciones:</label>
                <textarea name="observaciones"></textarea>

                <button type="submit">Guardar Datos</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit; // Evita que se cargue el resto del panel
}

// ✅ Si ya completó datos físicos, se muestra el panel normal
$cliente_nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
$hoy = date('Y-m-d');

// ✅ Consultar reservas del día
$reservas_hoy = $conexion->query("
    SELECT rc.*, 
           c.apellido AS cliente_apellido, c.nombre AS cliente_nombre,
           p.apellido AS profesor_apellido, p.nombre AS profesor_nombre
    FROM reservas_clientes rc
    JOIN clientes c ON rc.cliente_id = c.id
    JOIN profesores p ON rc.profesor_id = p.id
    WHERE rc.fecha_reserva = '$hoy' AND rc.gimnasio_id = $gimnasio_id
    ORDER BY rc.hora_inicio
");

// ✅ Consultar membresía activa
$alerta_membresia = '';
$membresia = $conexion->query("
    SELECT clases_restantes, fecha_vencimiento 
    FROM membresias 
    WHERE cliente_id = $cliente_id 
    ORDER BY fecha_vencimiento DESC 
    LIMIT 1
")->fetch_assoc();

if ($membresia) {
    $clases = intval($membresia['clases_restantes']);
    $vencimiento = $membresia['fecha_vencimiento'];
    $dias_restantes = (strtotime($vencimiento) - strtotime($hoy)) / 86400;

    if ($clases <= 2 || $dias_restantes <= 3) {
        $alerta_membresia = "⚠️ ¡Atención! Te quedan <strong>$clases clase(s)</strong> y tu plan vence en <strong>$dias_restantes día(s)</strong>.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: black; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; margin-top: 30px; }
        .alerta { background-color: #ffcc00; color: black; padding: 15px; border-radius: 8px; max-width: 600px; margin: 20px auto; font-weight: bold; text-align: center; }
        .datos { background: #111; padding: 20px; border-radius: 10px; max-width: 600px; margin: auto; border: 1px solid gold; }
        .foto { text-align: center; margin: 20px auto; }
        .foto img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 2px solid gold; }
        .form-foto { text-align: center; margin-top: 10px; }
        .btn-qr { padding: 10px 20px; background-color: #222; color: gold; border: 1px solid gold; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-qr:hover { background-color: #333; }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>

<?php if ($alerta_membresia): ?>
    <div class="alerta"><?= $alerta_membresia ?></div>
<?php endif; ?>

<div class="foto">
    <?php
    $foto = $cliente['foto'] ?? '';
    $ruta_foto = "fotos_clientes/" . $foto;

    if (!empty($foto) && file_exists($ruta_foto)) {
        echo "<img src='$ruta_foto' alt='Foto del cliente'>";
    } else {
        echo "<img src='fotos_clientes/default.png' alt='Sin foto' style='opacity:0.7;'>";
    }
    ?>
</div>

<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>

<div style="text-align:center; margin-top: 30px;">
    <h3 style="color: gold;">📲 Tu código QR personal</h3>
    <a class="btn-qr" href="generar_qr_individual.php?id=<?= $cliente['id'] ?>" target="_blank">Generar QR</a>
</div>

<div class="form-foto">
    <form method="POST" enctype="multipart/form-data">
        <label for="nueva_foto" style="color:#FFD700;">📸 Subí tu foto (o tomá con la cámara)</label><br><br>
        <input type="file" name="nueva_foto" accept="image/*" capture="user" required><br><br>
        <button type="submit" style="padding:5px 15px; background:#FFD700; border:none; border-radius:5px;">Cargar foto</button>
    </form>
</div>

<!-- Reservas del Día -->
<div style="margin-top: 30px; background:#222; padding:15px; border-radius:10px;">
    <h3 style="color:gold;">📆 Reservas del Día</h3>
    <?php
    if ($reservas_hoy && $reservas_hoy->num_rows > 0) {
        while ($res = $reservas_hoy->fetch_assoc()) {
            echo "<p style='color:white; margin:5px 0;'>
                🕒 {$res['hora_inicio']}<br>
                👤 Cliente: {$res['cliente_apellido']} {$res['cliente_nombre']}<br>
                👨‍🏫 Prof.: {$res['profesor_apellido']} {$res['profesor_nombre']}
            </p>";
        }
    } else {
        echo "<p style='color:gray;'>No hay reservas registradas para hoy.</p>";
    }
    ?>
</div>

</body>
</html>

<?php
// ✅ Subida de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nueva_foto'])) {
    if ($_FILES['nueva_foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp = $_FILES['nueva_foto']['tmp_name'];
        $nombre_archivo = 'cliente_' . $cliente_id . '_' . time() . '.jpg';
        $ruta_destino = 'fotos_clientes/' . $nombre_archivo;

        if (move_uploaded_file($foto_tmp, $ruta_destino)) {
            $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = $cliente_id AND gimnasio_id=$gimnasio_id");
            echo "<script>location.href='panel_cliente.php';</script>";
            exit;
        } else {
            echo "<p style='color:red; text-align:center;'>Error al guardar la imagen.</p>";
        }
    }
}
?>
