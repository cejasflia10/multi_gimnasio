<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$mensaje = "";
$nombre = "";
$disciplina = "";
$clases_restantes = "";
$fecha_vencimiento = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);

    $stmt = $conexion->prepare("SELECT id, nombre, apellido, clases_restantes, disciplina, fecha_vencimiento FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $id_cliente = $cliente["id"];
        $nombre = $cliente["apellido"] . " " . $cliente["nombre"];
        $disciplina = $cliente["disciplina"];
        $clases_restantes = $cliente["clases_restantes"];
        $fecha_vencimiento = $cliente["fecha_vencimiento"];

        $fecha_actual = date("Y-m-d");

        if ($fecha_actual > $fecha_vencimiento) {
            $mensaje = "⚠️ Plan vencido.";
        } elseif ($clases_restantes <= 0) {
            $mensaje = "⚠️ No tiene clases disponibles.";
        } else {
            $conexion->query("INSERT INTO asistencias (id_cliente, fecha_hora) VALUES ($id_cliente, NOW())");
            $conexion->query("UPDATE clientes SET clases_restantes = clases_restantes - 1 WHERE id = $id_cliente");
            $mensaje = "✅ Asistencia registrada correctamente.";
        }
    } else {
        $mensaje = "❌ Cliente no encontrado.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Asistencia QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0; padding: 0;
            font-family: Arial, sans-serif;
            background-color: #000;
            color: #ffd700;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            height: 100vh;
        }
        img.logo {
            width: 200px;
            margin-top: 20px;
        }
        form {
            margin-top: 20px;
            text-align: center;
        }
        input[type="text"] {
            font-size: 24px;
            padding: 10px;
            width: 80vw;
            max-width: 400px;
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        button {
            background-color: #ffd700;
            color: #000;
            font-size: 18px;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .resultado {
            margin-top: 30px;
            font-size: 20px;
            text-align: center;
        }
        .info-cliente {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <img src="logo.png" alt="Logo" class="logo">

    <form method="POST">
        <input type="text" name="dni" placeholder="Escaneá o escribí el DNI" autofocus required>
        <br>
        <button type="submit">Registrar Asistencia</button>
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="resultado">
            <p><?= $mensaje ?></p>
            <?php if ($mensaje === "✅ Asistencia registrada correctamente.") : ?>
                <div class="info-cliente">
                    <p><strong>Nombre:</strong> <?= $nombre ?></p>
                    <p><strong>Disciplina:</strong> <?= $disciplina ?></p>
                    <p><strong>Clases restantes:</strong> <?= $clases_restantes - 1 ?></p>
                    <p><strong>Vence:</strong> <?= $fecha_vencimiento ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>
