<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<h2 style='color:red; text-align:center;'>❌ ID de factura inválido</h2>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Generada</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .contenedor {
            background-color: #111;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            margin: 50px auto;
            color: gold;
            text-align: center;
        }
        .boton, .boton-volver {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            text-decoration: none;
        }
        .boton:hover, .boton-volver:hover {
            background-color: #e0c000;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>✅ ¡Factura generada correctamente!</h2>
    <p>Factura Nº: <strong><?= $id ?></strong></p>

    <a href="factura_pdf.php?id=<?= $id ?>" target="_blank" class="boton">📄 Descargar PDF</a>
    <a href="factura_pdf.php?id=<?= $id ?>&imprimir=1" target="_blank" class="boton">🖨️ Imprimir</a>
    <br><br>
    <a href="ventas_productos.php" class="boton-volver">🔙 Volver a Ventas</a>
</div>
</body>
</html>
