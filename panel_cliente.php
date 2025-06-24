<?php
session_start();
include 'conexion.php';

if (!isset($_GET['dni'])) {
    die("DNI no proporcionado.");
}

$dni = $_GET['dni'];

// Buscar cliente
$query = "SELECT * FROM clientes WHERE dni = '$dni'";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Cliente no encontrado.");
}

$cliente = $resultado->fetch_assoc();

// Manejar foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $foto = $_FILES['foto'];
    $nombre_archivo = "fotos/cliente_" . $cliente['id'] . ".jpg";

    if (move_uploaded_file($foto['tmp_name'], $nombre_archivo)) {
        $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = " . $cliente['id']);
        $cliente['foto'] = $nombre_archivo;
    }
}

// Asistencias del cliente
$asistencias = $conexion->query("SELECT fecha, hora FROM asistencias WHERE cliente_id = " . $cliente['id'] . " ORDER BY fecha DESC, hora DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px gold;
        }
        h2, h3 { text-align: center; }
        p, td {
            font-size: 16px;
            margin-bottom: 10px;
        }
        .qr-container, .foto {
            text-align: center;
            margin: 20px 0;
        }
        .qr-container img, .foto img {
            width: 180px;
            border: 3px solid gold;
            border-radius: 10px;
        }
        .btn-descargar, button {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
            text-decoration: none;
            margin-top: 10px;
            display: inline-block;
            border: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid gold;
            padding: 6px;
            text-align: center;
        }
        @media (max-width: 600px) {
            .container { margin: 10px; padding: 15px; }
            .btn-descargar { padding: 8px 16px; font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Bienvenido <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <div class="foto">
        <img src="<?= $cliente['foto'] ?: 'fotos/default.jpg' ?>" alt="Foto de perfil">
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="foto" accept="image/*" required><br>
            <button type="submit">Actualizar Foto</button>
        </form>
    </div>

    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
    <p><strong>Obra Social:</strong> <?= $cliente['obra_social'] ?? 'No especificada' ?></p>

    <div class="qr-container">
        <h3>Tu Código QR</h3>
        <?php
        $qr_path = "qr/" . $cliente['dni'] . ".png";
        if (file_exists($qr_path)) {
            echo "<img src='$qr_path' alt='QR del cliente'>";
        } else {
            echo "<p>Tu QR aún no ha sido generado.</p>";
            echo "<a href='generar_qr_individual.php?id=" . $cliente['id'] . "' class='btn-descargar'>Generar QR</a>";
        }
        ?>
    </div>

    <h3>Últimas asistencias</h3>
    <table>
        <tr><th>Fecha</th><th>Hora</th></tr>
        <?php while ($a = $asistencias->fetch_assoc()) { ?>
            <tr><td><?= $a['fecha'] ?></td><td><?= $a['hora'] ?></td></tr>
        <?php } ?>
    </table>
</div>

</body>
</html>
