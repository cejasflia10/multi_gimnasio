<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "<div style='color:red; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

$peso_antes = floatval($_POST['peso_antes'] ?? 0);
$peso_despues = floatval($_POST['peso_despues'] ?? 0);
$altura_cm = floatval($_POST['altura'] ?? 0);
$duracion = intval($_POST['duracion'] ?? 0);
$esfuerzo = $_POST['esfuerzo'] ?? 'medio';
$enfermedades = trim($_POST['enfermedades'] ?? '');
$calorias_quemadas = intval($_POST['calorias_quemadas'] ?? 0);
$fecha = date('Y-m-d');
$hora = date('H:i:s');

// Calcular IMC
$altura_m = $altura_cm / 100;
$peso_actual = $peso_despues > 0 ? $peso_despues : $peso_antes;
$imc = $altura_m > 0 ? round($peso_actual / ($altura_m * $altura_m), 2) : 0;

// Objetivo basado en IMC
$objetivo = '';
if ($imc < 18.5) {
    $objetivo = 'subir de peso';
} elseif ($imc > 25) {
    $objetivo = 'bajar de peso';
} else {
    $objetivo = 'mantener peso';
}

// Guardar datos en tabla (crea si no existe antes)
$conexion->query("CREATE TABLE IF NOT EXISTS progreso_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    gimnasio_id INT,
    fecha DATE,
    hora TIME,
    peso_antes DECIMAL(5,2),
    peso_despues DECIMAL(5,2),
    altura_cm DECIMAL(5,2),
    duracion INT,
    esfuerzo VARCHAR(10),
    enfermedades TEXT,
    calorias INT,
    imc DECIMAL(5,2),
    objetivo VARCHAR(50)
)");

$stmt = $conexion->prepare("INSERT INTO progreso_cliente
    (cliente_id, gimnasio_id, fecha, hora, peso_antes, peso_despues, altura_cm, duracion, esfuerzo, enfermedades, calorias, imc, objetivo)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissddidssids", 
    $cliente_id, $gimnasio_id, $fecha, $hora, 
    $peso_antes, $peso_despues, $altura_cm, $duracion, 
    $esfuerzo, $enfermedades, $calorias_quemadas, $imc, $objetivo
);
$stmt->execute();
$stmt->close();

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Progreso Registrado</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial;
            padding: 30px;
            text-align: center;
        }
        .recuadro {
            background: #111;
            padding: 20px;
            margin: auto;
            border-radius: 10px;
            border: 1px solid gold;
            max-width: 600px;
        }
        .recomendacion {
            background: #222;
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            color: #90ee90;
        }
        .boton {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background: gold;
            color: black;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="recuadro">
    <h2>✅ Progreso registrado con éxito</h2>
    <p><strong>IMC:</strong> <?= $imc ?> - Objetivo: <strong><?= $objetivo ?></strong></p>
    <p><strong>Calorías estimadas quemadas:</strong> <?= $calorias_quemadas ?> kcal</p>

    <div class="recomendacion">
        <?php
        echo "<h3>🍽️ Recomendación Semanal</h3>";

        if ($objetivo === 'bajar de peso') {
            echo "<p>- Dieta rica en vegetales, proteínas magras, bajo en harinas.<br>
            - Evitar azúcares simples y frituras.<br>
            - Tomar 2-3 L de agua por día.</p>";
        } elseif ($objetivo === 'subir de peso') {
            echo "<p>- Incorporar frutos secos, batidos post entrenamiento.<br>
            - Dieta rica en proteínas y carbohidratos buenos.<br>
            - Comer cada 3 horas.</p>";
        } else {
            echo "<p>- Seguir con alimentación balanceada.<br>
            - Buen consumo de frutas, verduras y proteínas.<br>
            - Mantener actividad física regular.</p>";
        }

        if (stripos($enfermedades, 'diab') !== false) {
            echo "<p><strong>⚠️ Nota por diabetes:</strong> evitar harinas y azúcares. Preferir alimentos integrales y controlar índice glucémico.</p>";
        }

        if (stripos($enfermedades, 'hiperten') !== false) {
            echo "<p><strong>⚠️ Nota por hipertensión:</strong> reducir sodio, evitar embutidos y grasas saturadas. Consumir frutas frescas.</p>";
        }
        ?>
    </div>

    <a href="panel_cliente.php" class="boton">Volver al Panel</a>
</div>

</body>
</html>
