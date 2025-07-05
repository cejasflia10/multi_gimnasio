<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$dni_cliente = $cliente['dni'] ?? '';

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// Verificar si el cliente pertenece al gimnasio
$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$cliente) {
    echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado al gimnasio.</div>";
    exit;
}

$cliente_nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 30px;
        }
        .datos {
            background: #111;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
            border: 1px solid gold;
        }
        .foto {
            text-align: center;
            margin: 20px auto;
        }
        .foto img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid gold;
        }
        .form-foto {
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>

<div class="foto">
    <?php
    $foto = $cliente['foto'];
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
    <a class="btn-qr" href="generar_qr_individual.php?id=<?= $cliente['id'] ?>" target="_blank">
        Generar QR
    </a>
</div>

<style>
.btn-qr {
    padding: 10px 20px;
    background-color: #222;
    color: gold;
    border: 1px solid gold;
    border-radius: 5px;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
}
.btn-qr:hover {
    background-color: #333;
}
</style>

</div>

<div class="form-foto">
    <form method="POST" enctype="multipart/form-data">
        <label for="nueva_foto" style="color:#FFD700;">📸 Subí tu foto (o tomá con la cámara)</label><br><br>
        <input type="file" name="nueva_foto" accept="image/*" capture="user" required><br><br>
        <button type="submit" style="padding:5px 15px; background:#FFD700; border:none; border-radius:5px;">Cargar foto</button>
    </form>
</div>
<h2 style="color:gold; text-align:center; margin-top:30px;">📌 Turnos de Hoy con Reservas</h2>
<div style="max-width: 800px; margin: auto; background: #111; padding: 20px; border-radius: 10px; color: white;">
    <table style="width:100%; border-collapse: collapse; color: white;">
        <thead>
            <tr style="background-color: #333;">
                <th style="padding:10px; border:1px solid #444;">Horario</th>
                <th style="padding:10px; border:1px solid #444;">Disciplina</th>
                <th style="padding:10px; border:1px solid #444;">Profesor</th>
                <th style="padding:10px; border:1px solid #444;">Reservas</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $fecha_hoy = date('Y-m-d');
            $turnos = $conexion->query("
                SELECT tp.horario_inicio, tp.horario_fin, tp.disciplina, p.apellido, p.nombre, COUNT(r.id) AS cantidad_reservas
                FROM turnos_profesor tp
                JOIN reservas r ON r.turno_id = tp.id AND r.fecha = '$fecha_hoy'
                JOIN profesores p ON tp.id_profesor = p.id
                WHERE tp.gimnasio_id = $gimnasio_id
                GROUP BY tp.id
                ORDER BY tp.horario_inicio
            ");

            while ($t = $turnos->fetch_assoc()) {
                $horario = $t['horario_inicio'] . ' - ' . $t['horario_fin'];
                $disciplina = $t['disciplina'];
                $profesor = $t['apellido'] . ' ' . $t['nombre'];
                $cantidad = $t['cantidad_reservas'];
                echo "<tr>
                        <td style='padding:10px; border:1px solid #444;'>$horario</td>
                        <td style='padding:10px; border:1px solid #444;'>$disciplina</td>
                        <td style='padding:10px; border:1px solid #444;'>$profesor</td>
                        <td style='padding:10px; border:1px solid #444;'>$cantidad</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

</body>
</html>

<?php
// Subida de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['nueva_foto'])) {
    if ($_FILES['nueva_foto']['error'] === UPLOAD_ERR_OK) {
        $foto_tmp = $_FILES['nueva_foto']['tmp_name'];
        $nombre_archivo = 'cliente_' . $cliente_id . '_' . time() . '.jpg';
        $ruta_destino = 'fotos_clientes/' . $nombre_archivo;

        if (move_uploaded_file($foto_tmp, $ruta_destino)) {
            $conexion->query("UPDATE clientes SET foto = '$nombre_archivo' WHERE id = $cliente_id");
            echo "<script>location.href='panel_cliente.php';</script>";
            exit;
        } else {
            echo "<p style='color:red; text-align:center;'>Error al guardar la imagen.</p>";
        }
    }
}

?>
