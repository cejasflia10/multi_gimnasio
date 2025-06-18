<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Cliente</title>
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
        <h2>Registro de Cliente</h2>

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

        <!-- RFID oculto para completar internamente -->
        <input type="hidden" name="rfid_uid" value="">

        <label for="disciplina">Disciplina:</label>
        <select name="disciplina" id="disciplina" required>
            <option value="">Seleccione una disciplina</option>
            <option value="Boxeo">Boxeo</option>
            <option value="Kickboxing">Kickboxing</option>
            <option value="MMA">MMA</option>
        </select>

        <label for="academia">Academia:</label>
        <select name="academia" id="academia" required>
            <option value="">Seleccione una academia</option>
            <option value="Fight Academy Scorpions">Fight Academy Scorpions</option>
            <option value="Team La Gitana">Team La Gitana</option>
            <!-- Agregar más academias si es necesario -->
        </select>

        <button type="submit">Registrar</button>
    </form>
</body>
</html>
