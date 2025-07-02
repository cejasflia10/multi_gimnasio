<?php
session_start();
include 'conexion.php';



if (session_status() === PHP_SESSION_NONE) 


$cliente_id = $_SESSION['cliente_id'] ?? null;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

if (!$cliente_id || !$gimnasio_id) {
    echo "Acceso denegado.";
    exit;
}

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

// Si se solicitó generar QR
$mostrar_qr = false;
$qr_temp_path = '';
if (isset($_POST['generar_qr'])) {
    require_once 'phpqrcode/qrlib.php';
    $dni = $cliente['dni'];
    $qr_temp_path = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    QRcode::png($dni, $qr_temp_path, QR_ECLEVEL_L, 4);
    $mostrar_qr = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
        }
        .foto-cliente {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid gold;
            margin-top: 15px;
        }
        .cuadro-datos {
            border: 2px solid gold;
            padding: 10px;
            margin: 20px auto;
            max-width: 400px;
            border-radius: 10px;
            text-align: left;
        }
        .btn {
            padding: 10px 20px;
            background: gold;
            color: black;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .qr-section {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <h2>👋 Bienvenido <?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></h2>

    <?php if (!empty($cliente['foto']) && file_exists($cliente['foto'])): ?>
        <img src="<?= $cliente['foto'] ?>" alt="Foto del Cliente" class="foto-cliente">
    <?php else: ?>
        <div style="width:150px;height:150px;border:3px solid gold;border-radius:50%;display:flex;align-items:center;justify-content:center;color:gold;margin:auto;">Sin foto</div>
    <?php endif; ?>

    <div class="cuadro-datos">
        <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
        <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
        <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
        <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
    </div>

    <form action="" method="post" enctype="multipart/form-data">
        <label>Subir o cambiar mi foto:</label><br><br>
        <input type="file" name="foto" required><br><br>
        <button type="submit" formaction="subir_foto_cliente.php" class="btn">Subir Foto</button>
    </form>

    <div class="qr-section">
        <h3>📲 Tu código QR personal</h3>
        <form method="post">
            <button type="submit" name="generar_qr" class="btn">Generar QR</button>
        </form>

        <?php if ($mostrar_qr && file_exists($qr_temp_path)): ?>
            <div style="margin-top:15px;">
                <img src="data:image/png;base64,<?= base64_encode(file_get_contents($qr_temp_path)) ?>" alt="QR Cliente" style="width:180px;height:180px;border:4px solid gold;border-radius:10px;background:#fff;padding:10px;">
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
