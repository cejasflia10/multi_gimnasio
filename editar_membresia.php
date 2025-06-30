
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_GET['id'])) {
    die("ID de membresía no especificado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$membresia = $conexion->query("SELECT * FROM membresias WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
$clientes = $conexion->query("SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        h1 { text-align: center; }
        form { max-width: 600px; margin: auto; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        .botones {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .botones button {
            flex: 1;
        }
    </style>
</head>
<body>
<h1>Editar Membresía</h1>
<form action="guardar_edicion_membresia.php" method="POST">
    <input type="hidden" name="id" value="<?= $membresia['id'] ?>">

    <label>Cliente:</label>
    <select name="cliente_id" required>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $membresia['cliente_id'] ? 'selected' : '' ?>>
                <?= $c['apellido'] . ', ' . $c['nombre'] . ' (' . $c['dni'] . ')' ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Plan:</label>
    <select name="plan_id" id="plan_id" required>
        <?php
        $planes2 = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
        while ($p = $planes2->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $p['id'] == $membresia['plan_id'] ? 'selected' : '' ?>><?= $p['nombre'] ?></option>
        <?php endwhile; ?>
    </select>

    <label>Precio:</label>
    <input type="number" step="0.01" name="precio" id="precio" value="<?= $membresia['precio'] ?>" required>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" id="clases_disponibles" value="<?= $membresia['clases_disponibles'] ?>" required>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= $membresia['fecha_inicio'] ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" value="<?= $membresia['fecha_vencimiento'] ?>" required>

    <label>Otros Pagos:</label>
    <input type="number" step="0.01" name="otros_pagos" value="<?= $membresia['otros_pagos'] ?>">

    <label>Forma de Pago:</label>
    <select name="forma_pago" required>
        <option value="efectivo" <?= $membresia['forma_pago'] == 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
        <option value="transferencia" <?= $membresia['forma_pago'] == 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
        <option value="debito" <?= $membresia['forma_pago'] == 'debito' ? 'selected' : '' ?>>Débito</option>
        <option value="credito" <?= $membresia['forma_pago'] == 'credito' ? 'selected' : '' ?>>Crédito</option>
        <option value="cuenta_corriente" <?= $membresia['forma_pago'] == 'cuenta_corriente' ? 'selected' : '' ?>>Cuenta Corriente</option>
    </select>

    <label>Total:</label>
    <input type="number" step="0.01" name="total" value="<?= $membresia['total'] ?>" required>

    <div class="botones">
        <button type="submit">Guardar Cambios</button>
        <button type="button" onclick="window.location.href='ver_membresias.php'">Volver</button>
    </div>
</form>

<script>
document.getElementById("plan_id").addEventListener("change", function() {
    const planId = this.value;
    const inicio = document.getElementById("fecha_inicio").value;

    fetch("obtener_datos_plan.php?plan_id=" + planId)
        .then(res => res.json())
        .then(data => {
            if (!data.error) {
                document.getElementById("precio").value = data.precio;
                document.getElementById("clases_disponibles").value = data.clases;

                if (inicio) {
                    let fechaInicio = new Date(inicio);
                    fechaInicio.setMonth(fechaInicio.getMonth() + parseInt(data.duracion));
                    let venc = fechaInicio.toISOString().split("T")[0];
                    document.getElementById("fecha_vencimiento").value = venc;
                }
            } else {
                alert("Error: " + data.error);
            }
        })
        .catch(err => alert("Error al obtener datos del plan: " + err));
});
</script>

</body>
</html>
