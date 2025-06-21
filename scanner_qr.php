<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener el dato escaneado (DNI o RFID desde el QR)
$dato = isset($_GET['dato']) ? trim($_GET['dato']) : '';
$mensaje = '';
$cliente = null;

if (!empty($dato)) {
    $query = "SELECT c.nombre, c.apellido, m.clases_disponibles, m.fecha_vencimiento FROM clientes c
              INNER JOIN membresias m ON c.id = m.cliente_id
              WHERE (c.dni = ? OR c.rfid_uid = ?) AND m.fecha_vencimiento >= CURDATE()
              ORDER BY m.fecha_inicio DESC LIMIT 1";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ss", $dato, $dato);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        // Descontar clase
        $update = $conexion->prepare("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE cliente_id = (SELECT id FROM clientes WHERE dni = ? OR rfid_uid = ?) AND clases_disponibles > 0");
        $update->bind_param("ss", $dato, $dato);
        $update->execute();

        $mensaje = "Ingreso registrado correctamente.";
    } else {
        $mensaje = "Cliente no encontrado o membresía vencida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear QR</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .contenedor {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            border: 2px solid gold;
            border-radius: 10px;
        }
        input[type=text] {
            font-size: 20px;
            padding: 10px;
            width: 80%;
        }
        button {
            padding: 10px 20px;
            margin: 10px;
            background-color: gold;
            border: none;
            color: black;
            font-size: 16px;
            cursor: pointer;
        }
        .datos {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Fight Academy</h1>

    <div class="contenedor">
        <form method="GET" action="">
            <input type="text" name="dato" placeholder="Escanea o ingresa DNI/RFID" autofocus required>
            <br>
            <button type="submit">Verificar</button>
        </form>

        <?php if ($cliente): ?>
        <div class="datos">
            <h2><?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></h2>
            <p><strong>Clases restantes:</strong> <?= $cliente['clases_disponibles'] ?></p>
            <p><strong>Vence:</strong> <?= date("d/m/Y", strtotime($cliente['fecha_vencimiento'])) ?></p>
            <p style="color: lime;">✔️ <?= $mensaje ?></p>
        </div>
        <?php elseif ($mensaje): ?>
        <p style="color: red; font-weight: bold;">❌ <?= $mensaje ?></p>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="scanner_qr.php"><button>Seguir Escaneando</button></a>
            <a href="index.php"><button>Cerrar</button></a>
        </div>
    </div>
</body>
</html>
