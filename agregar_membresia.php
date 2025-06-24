<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener planes disponibles para el gimnasio logueado
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$planes = $conexion->query("SELECT id, nombre FROM planes WHERE gimnasio_id = $gimnasio_id");
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
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            margin-top: 5px;
            background-color: #333;
            color: white;
        }
        input[readonly] {
            background-color: #444;
        }
        button {
            margin-top: 20px;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #e6c200;
        }
        @media (max-width: 600px) {
            form {
                width: 100%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <h1>Agregar Membresía</h1>

    <form action="guardar_membresia.php" method="POST">

        <label for="cliente_dni">DNI del Cliente</label>
        <input type="text" name="cliente_dni" id="cliente_dni" required>

        <label for="plan">Seleccionar Plan</label>
        <select id="plan" name="plan_id" onchange="cargarDatosPlan(this.value)" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($p = $planes->fetch_assoc()) { ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
            <?php } ?>
        </select>

        <label for="precio">Precio del Plan</label>
        <input type="number" id="precio" name="precio" readonly>

        <label for="clases_disponibles">Clases Disponibles</label>
        <input type="number" id="clases_disponibles" name="clases_disponibles" readonly>

        <label for="fecha_inicio">Fecha de Inicio</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" required onchange="calcularVencimiento()">

        <label for="fecha_vencimiento">Fecha de Vencimiento</label>
        <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" readonly>

        <input type="hidden" id="duracion_meses_oculta" name="duracion_meses">

        <button type="submit">Registrar Membresía</button>
    </form>

    <script>
    function cargarDatosPlan(planId) {
        if (!planId) return;

        fetch('obtener_datos_plan.php?plan_id=' + planId)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }

                document.getElementById('precio').value = data.precio;
                document.getElementById('clases_disponibles').value = data.clases_disponibles;
                document.getElementById('duracion_meses_oculta').value = data.duracion_meses;

                calcularVencimiento();
            })
            .catch(err => {
                console.error('Error al cargar plan:', err);
            });
    }

    function calcularVencimiento() {
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const meses = parseInt(document.getElementById('duracion_meses_oculta').value || 0);

        if (fechaInicio && meses > 0) {
            let fecha = new Date(fechaInicio);
            fecha.setMonth(fecha.getMonth() + meses);
            let vencimiento = fecha.toISOString().split('T')[0];
            document.getElementById('fecha_vencimiento').value = vencimiento;
        }
    }
    </script>
</body>
</html>
