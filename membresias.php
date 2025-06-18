<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'];

// Obtener planes
$planes = [];
$res_planes = $conexion->query("SELECT id, nombre, clases, precio FROM planes WHERE gimnasio_id = $gimnasio_id");
while ($row = $res_planes->fetch_assoc()) {
    $planes[] = $row;
}

// Obtener planes adicionales
$adicionales = [];
$res_adic = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
while ($row = $res_adic->fetch_assoc()) {
    $adicionales[] = $row;
}

// Obtener clientes
$clientes = [];
$res_cli = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
while ($row = $res_cli->fetch_assoc()) {
    $clientes[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 1rem;
        }
        h2 {
            color: gold;
            text-align: center;
        }
        form, table {
            width: 100%;
            margin: auto;
            max-width: 600px;
        }
        label {
            display: block;
            margin: 0.5rem 0 0.2rem;
        }
        input, select {
            width: 100%;
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .adicionales label {
            display: block;
        }
        .btn {
            background: gold;
            color: #111;
            padding: 0.5rem;
            width: 100%;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .tabla {
            margin-top: 2rem;
            width: 100%;
            border-collapse: collapse;
        }
        .tabla th, .tabla td {
            border: 1px solid gold;
            padding: 0.5rem;
            text-align: center;
        }
        .tabla th {
            background: #222;
        }
    </style>
    <script>
        function actualizarTotal() {
            const plan = JSON.parse(document.getElementById('plan').selectedOptions[0].dataset.info);
            const adicionales = document.querySelectorAll('input[name="adicionales[]"]:checked');
            let total = parseFloat(plan.precio);
            adicionales.forEach(a => {
                total += parseFloat(a.dataset.precio);
            });
            document.getElementById('total').value = total.toFixed(2);
            document.getElementById('clases').value = plan.clases;
        }
    </script>
</head>
<body>
    <h2>Registrar Nueva Membresía</h2>
    <form method="POST" action="guardar_membresia.php">
        <label>Cliente</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar cliente</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] ?> - DNI: <?= $c['dni'] ?></option>
            <?php endforeach; ?>
        </select>

        <label>Plan</label>
        <select name="plan_id" id="plan" onchange="actualizarTotal()" required>
            <option value="">Seleccionar plan</option>
            <?php foreach ($planes as $p): ?>
                <option value="<?= $p['id'] ?>" data-info='<?= json_encode($p) ?>'>
                    <?= $p['nombre'] ?> - $<?= $p['precio'] ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Fecha de inicio</label>
        <input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>

        <label>Clases disponibles</label>
        <input type="number" id="clases" name="clases_restantes" readonly>

        <label>Planes adicionales</label>
        <div class="adicionales">
            <?php foreach ($adicionales as $a): ?>
                <label>
                    <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
                    <?= $a['nombre'] ?> ($<?= $a['precio'] ?>)
                </label>
            <?php endforeach; ?>
        </div>

        <label>Método de pago</label>
        <select name="metodo_pago" required>
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Tarjeta">Tarjeta</option>
            <option value="Cuenta Corriente">Cuenta Corriente</option>
        </select>

        <label>Total a pagar</label>
        <input type="text" id="total" name="total" readonly>

        <button class="btn" type="submit">Registrar Membresía</button>
    </form>
</body>
</html>
