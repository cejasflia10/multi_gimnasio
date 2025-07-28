<?php
session_start();
include 'conexion.php';
include 'menu_eventos.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';
    $lugar = trim($_POST['lugar'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $flyer = $_FILES['flyer']['name'] ?? '';

    if ($titulo && $fecha && $hora && $lugar) {
        $ruta_flyer = '';
        if (!empty($flyer)) {
            $ruta_flyer = 'flyers_eventos/' . basename($flyer);
            move_uploaded_file($_FILES['flyer']['tmp_name'], $ruta_flyer);
        }

        $stmt = $conexion->prepare("INSERT INTO eventos_deportivos (titulo, descripcion, fecha, hora, lugar, flyer, video) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $titulo, $descripcion, $fecha, $hora, $lugar, $ruta_flyer, $video);
        
        if ($stmt->execute()) {
            $mensaje = "✅ Evento creado correctamente.";
        } else {
            $mensaje = "❌ Error al guardar el evento.";
        }
    } else {
        $mensaje = "⚠️ Completa todos los campos obligatorios.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Evento Deportivo</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: 'Segoe UI', sans-serif;
        }

        .contenedor {
            width: 80%;
            max-width: 1200px;
            margin: 40px auto;
            background-color: #111;
            padding: 35px;
            border-radius: 12px;
            border: 2px solid gold;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
        }

        h2, h3 {
            color: gold;
            margin-bottom: 25px;
        }

        form label {
            display: block;
            margin-top: 15px;
            font-weight: 600;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        input[type="file"],
        textarea {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border-radius: 6px;
            border: 1px solid #555;
            background-color: #1a1a1a;
            color: gold;
            font-size: 16px;
        }

        textarea {
            resize: vertical;
        }

        .boton {
            margin-top: 25px;
            padding: 12px 24px;
            background: linear-gradient(to right, gold, #d4af37);
            color: black;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s ease;
            font-size: 16px;
        }

        .boton:hover {
            background: linear-gradient(to right, #ffe600, gold);
            transform: scale(1.05);
        }

        .boton-volver {
            text-decoration: none;
            padding: 12px 20px;
            background-color: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 8px;
            margin-left: 15px;
            transition: 0.3s ease;
        }

        .boton-volver:hover {
            background-color: gold;
            color: black;
        }

        .acciones {
            margin-top: 40px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .boton-accion {
            flex: 1 1 200px;
            text-align: center;
            background-color: #222;
            color: gold;
            padding: 15px 20px;
            border: 2px solid gold;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .boton-accion:hover {
            background-color: gold;
            color: #111;
            transform: scale(1.05);
        }

        .mensaje {
            background-color: #222;
            padding: 10px 20px;
            border-left: 5px solid gold;
            margin-bottom: 20px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🎯 Crear Evento Deportivo</h2>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Título del Evento:</label>
        <input type="text" name="titulo" required>

        <label>Descripción:</label>
        <textarea name="descripcion" rows="4"></textarea>

        <label>Fecha:</label>
        <input type="date" name="fecha" required>

        <label>Hora de Inicio:</label>
        <input type="time" name="hora" required>

        <label>Lugar:</label>
        <input type="text" name="lugar" required>

        <label>Flyer del Evento (imagen):</label>
        <input type="file" name="flyer" accept="image/*">

        <label>Video Promocional (YouTube o enlace directo):</label>
        <input type="text" name="video" placeholder="https://youtube.com/...">

        <button type="submit" class="boton">✅ Crear Evento</button>
        <a href="index.php" class="boton-volver">⬅ Volver</a>
    </form>

    <div class="acciones">
        <a href="ver_evento.php" class="boton-accion">📅 Ver Eventos</a>
        <a href="ver_tipos_entrada.php" class="boton-accion">🎫 Cargar Tipos de Entradas</a>
        <a href="vender_entrada.php" class="boton-accion">🛒 Vender Entradas</a>
        <a href="ver_entradas_vendidas.php" class="boton-accion">📥 Ver Entradas Vendidas</a>
        <a href="ver_inscriptos.php" class="boton-accion">📋 Ver Inscriptos</a>
        <a href="reporte_ganancias.php" class="boton-accion">💲 Ver Ganancias</a>
        <a href="informe_evento_pdf.php" class="boton-accion">🖨️ Generar Informe PDF</a>
    </div>
</div>
</body>
</html>
