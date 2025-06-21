<?php
session_start();
include 'conexion.php';

// Verificamos si hay un gimnasio asociado al usuario
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Error: No se ha definido un gimnasio.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
            border: none;
            border-radius: 4px;
        }
        .boton {
            background-color: #ffd700;
            color: #111;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        .boton:hover {
            background-color: #e5c100;
        }
    </style>
</head>
<body>
    <h2>Agregar Cliente</h2>
    <form action="guardar_cliente.php" method="POST">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Fecha de Nacimiento:</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required onchange="calcularEdad()">

        <label>Edad:</label>
        <input type="text" name="edad" id="edad" readonly>

        <label>Domicilio:</label>
        <input type="text" name="domicilio">

        <label>Teléfono:</label>
        <input type="text" name="telefono">

        <label>Email:</label>
        <input type="email" name="email">

        <label>RFID (opcional):</label>
        <input type="text" name="rfid_uid">

        <label>Disciplina (opcional):</label>
        <input type="text" name="disciplina">

        <input type="hidden" name="gimnasio_id" value="<?php echo htmlspecialchars($gimnasio_id); ?>">

        <input type="submit" class="boton" value="Guardar Cliente">
    </form>

    <script>
        function calcularEdad() {
            const fechaNac = document.getElementById('fecha_nacimiento').value;
            if (!fechaNac) return;

            const hoy = new Date();
            const nacimiento = new Date(fechaNac);
            let edad = hoy.getFullYear() - nacimiento.getFullYear();
            const mes = hoy.getMonth() - nacimiento.getMonth();

            if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
                edad--;
            }

            document.getElementById('edad').value = edad;
        }
    </script>
</body>
</html>
