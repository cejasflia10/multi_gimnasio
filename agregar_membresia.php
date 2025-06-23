<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type="text"], select {
            width: 100%;
            padding: 10px;
            background-color: #222;
            border: 1px solid gold;
            color: gold;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: gold;
            color: #111;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/smoothness/jquery-ui.css">
</head>
<body>
    <h1>Registrar Nueva Membresía</h1>
    <form action="guardar_membresia.php" method="POST">
        <label for="buscar_cliente">Buscar cliente:</label>
        <input type="text" id="buscar_cliente" name="buscar_cliente" placeholder="Escriba DNI, nombre o apellido">

        <label for="cliente_id">Seleccionar cliente:</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">Seleccione un cliente</option>
        </select>

        <!-- Aquí irían los demás campos del formulario -->

        <button type="submit">Registrar Membresía</button>
    </form>

    <script>
    $(function () {
        $("#buscar_cliente").autocomplete({
            source: "buscar_cliente_ajax.php",
            minLength: 2,
            select: function (event, ui) {
                $("#cliente_id").html('<option value="' + ui.item.id + '">' + ui.item.label + '</option>');
            }
        });
    });
    </script>
</body>
</html>
