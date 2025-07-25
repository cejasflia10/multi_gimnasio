<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
   
</head>
<script src="fullscreen.js"></script>

<body>
<div class="contenedor">
    <h1>Registrar Nueva Membresía</h1>
    <form method="POST" action="guardar_membresia.php">
        <label>Cliente:</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar cliente</option>
            <?php while ($c = $clientes->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] ?> (<?= $c['dni'] ?>)</option>
            <?php endwhile; ?>
        </select>

        <label>Plan:</label>
        <select name="plan_id" id="plan" required onchange="cargarDatosPlan()">
            <option value="">Seleccionar plan</option>
            <?php while ($p = $planes->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"
                        data-precio="<?= $p['precio'] ?>"
                        data-clases="<?= $p['clases_disponibles'] ?>"
                        data-duracion="<?= $p['duracion_meses'] ?>">
                    <?= $p['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Precio del Plan:</label>
        <input type="text" name="precio" id="precio" readonly>

        <label>Clases Disponibles:</label>
        <input type="number" name="clases_disponibles" id="clases_disponibles" readonly>

        <label>Fecha de Inicio:</label>
        <input type="date" name="fecha_inicio" id="fecha_inicio" required onchange="calcularVencimiento()">

        <label>Fecha de Vencimiento:</label>
        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" readonly>

        <label>Planes Adicionales:</label>
        <?php while ($a = $adicionales->fetch_assoc()): ?>
            <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>"> <?= $a['nombre'] ?><br>
        <?php endwhile; ?>

        <button type="submit">Guardar Membresía</button>
    </form>
</div>

<script>
    function cargarDatosPlan() {
        let plan = document.getElementById('plan');
        let selected = plan.options[plan.selectedIndex];
        let precio = selected.getAttribute('data-precio');
        let clases = selected.getAttribute('data-clases');
        let duracion = selected.getAttribute('data-duracion');

        document.getElementById('precio').value = precio;
        document.getElementById('clases_disponibles').value = clases;
        calcularVencimiento(); // Recalcular si ya hay fecha
    }

    function calcularVencimiento() {
        let plan = document.getElementById('plan');
        let duracion = plan.options[plan.selectedIndex]?.getAttribute('data-duracion');
        let fechaInicio = document.getElementById('fecha_inicio').value;
        if (!duracion || !fechaInicio) return;

        let fecha = new Date(fechaInicio);
        fecha.setMonth(fecha.getMonth() + parseInt(duracion));

        let mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
        let dia = fecha.getDate().toString().padStart(2, '0');
        let anio = fecha.getFullYear();

        document.getElementById('fecha_vencimiento').value = `${anio}-${mes}-${dia}`;
    }
</script>
</body>
</html>
