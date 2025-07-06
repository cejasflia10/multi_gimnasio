<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$cliente_id = $_SESSION['cliente_id'] ?? 0;
if (!$cliente_id) {
    die("Acceso denegado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Hábitos Alimentarios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📋 Ficha de Hábitos y Alimentación</h2>

    <form action="guardar_habitos.php" method="POST">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">

        <label>Edad:</label>
        <input type="number" name="edad" required>

        <label>Objetivo principal:</label>
        <select name="objetivo">
            <option value="Bajar de peso">Bajar de peso</option>
            <option value="Subir masa">Subir masa</option>
            <option value="Mejorar rendimiento">Mejorar rendimiento</option>
            <option value="Otro">Otro</option>
        </select>

        <label>Motivación:</label>
        <select name="motivacion">
            <option value="Nada motivado">Nada motivado</option>
            <option value="Un poco motivado">Un poco motivado</option>
            <option value="Motivado">Motivado</option>
            <option value="Muy motivado">Muy motivado</option>
        </select>

        <label>¿Dormís al menos 7 hs por noche?</label>
        <select name="duerme7hs">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Trabajás o estudiás más de 8 hs al día?</label>
        <select name="trabaja8hs">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Fumás?</label>
        <select name="fuma">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Tomás alcohol más de 2 veces por semana?</label>
        <select name="alcohol">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Tomás al menos 1 litro de agua por día?</label>
        <select name="agua">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Entrenás más de 2 veces por semana?</label>
        <select name="entrena">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Cuántas veces por semana entrenás?</label>
        <input type="number" name="entrenos_por_semana">

        <label>¿Cuántas horas por entrenamiento?</label>
        <input type="number" step="0.1" name="horas_por_entreno">

        <label>¿Cuántas comidas hacés por día?</label>
        <select name="comidas_por_dia">
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4 o más</option>
        </select>

        <label>¿Te salteás comidas?</label>
        <select name="se_salta_comidas">
            <option value="Nunca">Nunca</option>
            <option value="A veces">A veces</option>
            <option value="Siempre">Siempre</option>
        </select>

        <label>¿Qué tan saludable sentís que comés?</label>
        <select name="saludable">
            <option value="Muy poco">Muy poco</option>
            <option value="Regular">Regular</option>
            <option value="Bastante">Bastante</option>
            <option value="Muy">Muy</option>
        </select>

        <label>¿Tomás gaseosas o jugos azucarados?</label>
        <select name="gaseosas">
            <option value="Nunca">Nunca</option>
            <option value="A veces">A veces</option>
            <option value="Todos los días">Todos los días</option>
        </select>

        <label>¿Comés frutas o verduras todos los días?</label>
        <select name="frutas_verduras">
            <option value="1">Sí</option>
            <option value="0">No</option>
        </select>

        <label>¿Consumís fritos o ultra procesados?</label>
        <select name="fritos">
            <option value="Nunca">Nunca</option>
            <option value="A veces">A veces</option>
            <option value="Muy seguido">Muy seguido</option>
        </select>

        <label>Notas personales:</label>
        <textarea name="notas" rows="4"></textarea>

        <button type="submit">💾 Guardar ficha</button>
    </form>
</div>
</body>
</html>
