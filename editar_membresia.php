<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID de membresía no especificado.");
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "
SELECT * FROM membresias 
WHERE id = $id AND gimnasio_id = $gimnasio_id
";
$resultado = $conexion->query($query);
if ($resultado->num_rows === 0) {
    die("Membresía no encontrada.");
}
$m = $resultado->fetch_assoc();

// Obtener planes y adicionales
$planes = $conexion->query("SELECT id, nombre FROM planes WHERE gimnasio_id = $gimnasio_id");
$adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Membresía</title>
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
            margin-bottom: 20px;
        }
        label {
            margin-top: 10px;
            display: block;
        }
        input, select, button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            font-size: 16px;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<h1>Editar Membresía</h1>

<form method="POST" action="guardar_edicion_membresia.php">
    <input type="hidden" name="id" value="<?= $m['id'] ?>">

    <label>Seleccionar Plan:</label>
    <select name="plan_id" required>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" <?= $m['plan_id'] == $p['id'] ? 'selected' : '' ?>>
                <?= $p['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Plan Adicional (opcional):</label>
    <select name="adicional_id">
        <option value="">-- Ninguno --</option>
        <?php while ($a = $adicionales->fetch_assoc()): ?>
            <option value="<?= $a['id'] ?>" <?= $m['adicional_id'] == $a['id'] ? 'selected' : '' ?>>
                <?= $a['nombre'] ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" value="<?= $m['fecha_inicio'] ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= $m['fecha_vencimiento'] ?>" required>

    <label>Clases Restantes:</label>
    <input type="number" name="clases_restantes" value="<?= $m['clases_restantes'] ?>" required>

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" value="<?= $m['otros_pagos'] ?>" step="0.01">

    <label>Forma de Pago:</label>
    <select name="metodo_pago" required>
        <?php
        $metodos = ['efectivo', 'transferencia', 'debito', 'credito', 'cuenta_corriente'];
        foreach ($metodos as $metodo):
        ?>
            <option value="<?= $metodo ?>" <?= $m['metodo_pago'] == $metodo ? 'selected' : '' ?>>
                <?= ucfirst($metodo) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Total:</label>
    <input type="number" name="total" value="<?= $m['total'] ?>" step="0.01" required>

    <button type="submit">Guardar Cambios</button>
    <a href="ver_membresias.php"><button type="button">Volver</button></a>
</form>

</body>
</html>
