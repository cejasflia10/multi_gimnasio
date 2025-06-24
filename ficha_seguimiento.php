<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$cliente_id = $_GET['id'] ?? 0;
if (!$cliente_id) {
    die("ID de cliente no proporcionado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimiento Alimenticio Semanal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        form {
            max-width: 700px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select, textarea {
            width: 100%;
            padding: 6px;
            border-radius: 4px;
            margin-top: 4px;
            border: none;
        }
        button {
            margin-top: 20px;
            background: gold;
            color: black;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<h2>Ficha Semanal de Acompañamiento Alimenticio</h2>
<form action="guardar_seguimiento.php" method="POST">
    <input type="hidden" name="cliente_id" value="<?php echo $cliente_id; ?>">

    <label>Semana N°:</label>
    <input type="number" name="semana" required>

    <label>Fecha de inicio:</label>
    <input type="date" name="fecha_inicio" required>

    <label>Peso al inicio de la semana (kg):</label>
    <input type="number" step="0.1" name="peso_inicio" required>

    <label>Peso al final de la semana (kg):</label>
    <input type="number" step="0.1" name="peso_fin">

    <label>Satisfacción con la comida:</label>
    <select name="satisfaccion">
        <option>Muy insatisfecho</option>
        <option>Insatisfecho</option>
        <option>Neutral</option>
        <option>Satisfecho</option>
        <option>Muy satisfecho</option>
    </select>

    <label>Adherencia al plan:</label>
    <select name="adherencia">
        <option>No seguí el plan</option>
        <option>Lo seguí algo</option>
        <option>Lo seguí bastante</option>
        <option>Lo seguí muy bien</option>
    </select>

    <label>Dificultades encontradas:</label>
    <textarea name="dificultades"></textarea>

    <label>Recomendaciones diarias y comidas:</label>
    <textarea name="desayuno" placeholder="Desayuno"></textarea>
    <textarea name="almuerzo" placeholder="Almuerzo"></textarea>
    <textarea name="merienda" placeholder="Merienda"></textarea>
    <textarea name="cena" placeholder="Cena"></textarea>

    <label>Plan semanal (Lunes a Domingo):</label>
    <textarea name="lunes" placeholder="Lunes"></textarea>
    <textarea name="martes" placeholder="Martes"></textarea>
    <textarea name="miercoles" placeholder="Miércoles"></textarea>
    <textarea name="jueves" placeholder="Jueves"></textarea>
    <textarea name="viernes" placeholder="Viernes"></textarea>
    <textarea name="sabado" placeholder="Sábado"></textarea>
    <textarea name="domingo" placeholder="Domingo"></textarea>

    <label>Seguimiento diario (¿cumplió con las 4 comidas, evitó extras?):</label>
    <textarea name="seguimient