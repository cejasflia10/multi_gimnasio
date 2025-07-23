<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; text-align:center;'>❌ Acceso denegado</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Progreso Físico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .formulario {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid gold;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: gold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #555;
            border-radius: 5px;
            margin-top: 5px;
            background: #222;
            color: gold;
        }
        .output {
            background: #222;
            padding: 10px;
            border-radius: 5px;
            color: lightgreen;
            margin-top: 10px;
            font-weight: bold;
        }
        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 8px;
        }
    </style>
    <script>
        function calcularCalorias() {
            const duracion = parseFloat(document.getElementById('duracion').value) || 0;
            const esfuerzo = document.getElementById('esfuerzo').value;
            let calorias = 0;

            if (esfuerzo === 'bajo') calorias = duracion * 4;
            else if (esfuerzo === 'medio') calorias = duracion * 7;
            else if (esfuerzo === 'alto') calorias = duracion * 10;

            document.getElementById('resultado_calorias').innerText = calorias.toFixed(0) + " kcal estimadas";
            document.getElementById('calorias_quemadas').value = calorias.toFixed(0);
        }
    </script>
</head>
<body>

<div class="contenedor">
    <h2>📈 Registrar Progreso Físico</h2>

    <form method="POST" action="guardar_progreso.php" oninput="calcularCalorias()" class="formulario">
        <label for="peso_antes">Peso antes del entrenamiento (kg):</label>
        <input type="number" name="peso_antes" id="peso_antes" step="0.1" required>

        <label for="peso_despues">Peso después del entrenamiento (kg):</label>
        <input type="number" name="peso_despues" id="peso_despues" step="0.1" required>

        <label for="altura">Altura (cm):</label>
        <input type="number" name="altura" id="altura" required>

        <label for="duracion">Duración del entrenamiento (minutos):</label>
        <input type="number" name="duracion" id="duracion" required>

        <label for="esfuerzo">Nivel de esfuerzo físico:</label>
        <select name="esfuerzo" id="esfuerzo" required>
            <option value="bajo">Bajo</option>
            <option value="medio" selected>Medio</option>
            <option value="alto">Alto</option>
        </select>

        <label for="enfermedades">Condiciones médicas (ej: diabetes, hipertensión):</label>
        <input type="text" name="enfermedades" id="enfermedades" placeholder="Opcional">

        <div class="output">
            🔥 Calorías quemadas estimadas: <span id="resultado_calorias">0 kcal</span>
        </div>

        <input type="hidden" name="calorias_quemadas" id="calorias_quemadas" value="0">

        <button type="submit">Guardar Progreso</button>
    </form>
</div>

</body>
</html>
