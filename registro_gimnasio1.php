<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;
$logo_path = "img/gimnasio" . $gimnasio_id . ".png";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente - Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            text-align: center;
        }
        .form-container {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
            margin: 40px auto;
        }
        input, select {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid gold;
            border-radius: 5px;
            background-color: #333;
            color: white;
        }
        h1 {
            color: gold;
            margin-top: 20px;
        }
        button {
            padding: 10px 20px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php if (file_exists($logo_path)) : ?>
        <div>
            <img src="<?= $logo_path ?>" alt="Logo Gimnasio" style="max-height: 100px;">
        </div>
    <?php endif; ?>

    <h1>Registro de Cliente - Gimnasio <?= $gimnasio_id ?></h1>

    <div class="form-container">
        <form action="guardar_cliente.php" method="POST">
            <input type="text" name="apellido" placeholder="Apellido" required>
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="dni" placeholder="DNI" required>
            <input type="date" name="fecha_nacimiento" placeholder="Fecha de Nacimiento" required>
            <input type="text" name="domicilio" placeholder="Domicilio">
            <input type="text" name="telefono" placeholder="Teléfono">
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="rfid_uid" placeholder="RFID" required>
            <input type="date" name="fecha_vencimiento" placeholder="Fecha de Vencimiento" required>
            <select name="disciplina_id" required>
                <option value="">Seleccione disciplina</option>
                <option value="1">Boxeo</option>
                <option value="2">Kickboxing</option>
                <option value="3">MMA</option>
            </select>
            <button type="submit">Registrar</button>
        </form>
    </div>
</body>
</html>
