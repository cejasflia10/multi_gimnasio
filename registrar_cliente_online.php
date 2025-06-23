<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener el gimnasio logueado
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre_gimnasio = 'Gimnasio';

if ($gimnasio_id > 0) {
    $resultado = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
    if ($fila = $resultado->fetch_assoc()) {
        $nombre_gimnasio = $fila['nombre'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .form-container {
            background-color: #222;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: gold;
            margin-bottom: 5px;
            font-size: 22px;
        }
        h3 {
            text-align: center;
            color: white;
            margin-top: 0;
            font-size: 16px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: none;
            border-radius: 8px;
            background-color: #333;
            color: white;
        }
        input:invalid {
            border: 2px solid red;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background-color: darkorange;
        }
    </style>
</head>
<body>
    <form class="form-container" action="guardar_cliente_online.php" method="POST">
        <h2><?php echo strtoupper(htmlspecialchars($nombre_gimnasio)); ?></h2>
        <h3>Registro de Cliente</h3>

        <input type="hidden" name="gimnasio_id" value="<?php echo $gimnasio_id; ?>">

        <label for="apellido">Apellido:</label>
        <input type="text" id="apellido" name="apellido" required>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required>

        <label for="dni">DNI:</label>
        <input type="text" id="dni" name="dni" required>

        <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>

        <label for="domicilio">Domicilio:</label>
        <input type="text" id="domicilio" name="domicilio" required>

        <label for="telefono">Teléfono:</label>
        <input type="text" id="telefono" name="telefono" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="disciplina">Disciplina:</label>
        <select name="disciplina" id="disciplina" required>
            <option value="">Seleccione una disciplina</option>
            <option value="Boxeo">Boxeo</option>
            <option value="Kickboxing">Kickboxing</option>
            <option value="MMA">MMA</option>
        </select>

        <button type="submit">Registrar</button>
    </form>
</body>
</html>
