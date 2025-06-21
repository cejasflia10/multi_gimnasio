<?php
session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "ID de membresía no especificado.";
    exit;
}

$id = intval($_GET['id']);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;

// Obtener datos de la membresía
$stmt = $conexion->prepare("SELECT * FROM membresias WHERE id = ? AND gimnasio_id = ?");
$stmt->bind_param("ii", $id, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows == 0) {
    echo "Membresía no encontrada.";
    exit;
}

$m = $resultado->fetch_assoc();
$stmt->close();

// Obtener planes
$planes = $conexion->query("SELECT id, nombre FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener adicionales
$adicionales = $conexion->query("SELECT id, nombre FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        form {
            max-width: 600px;
            margin: auto;
            background: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 4px;
            margin-bottom: 12px;
            border-radius: 5px;
            border: none;
            font-size: 16px;
        }
        .boton {
            background-color: #ffd700;
            color: #111;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            cursor: pointer;
            border-radius: 5px;
        }
        .boton:hover {
            background-color: #e5c100;
        }
        .volver {
            display: block;
            margin: 20px auto;
            text-align: center;
            background: #ffd700;
            color: #111;
            padding: 10px 20px;
            border-radius: 5px;
            width: fit-content;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>Editar Membresía</h2>

<form action="guardar_edicion_membresia.php" method="POST">
    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" value="<?php echo $m['fecha_inicio']; ?>" required>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?php echo $m['fecha_vencimiento']; ?>" required>

    <label>Clases Disponibles:</label>
    <input type="number" name="clases_disponibles" value="<?php echo $m['clases_disponibles']; ?>" required>

    <label>Plan:</label>
    <select name="plan_id" required>
        <?php while ($p = $planes->fetch_assoc()) { ?>
            <option value="<?php echo $p['id']; ?>" <?php if ($m['plan_id'] == $p['id']) echo 'selected'; ?>>
                <?php echo $p['nombre']; ?>
            </option>
        <?php } ?>
    </select>

    <label>Plan Adicional:</label>
    <select name="adicional_id">
        <option value="">Ninguno</option>
        <?php while ($a = $adicionales->fetch_assoc()) { ?>
            <option value="<?php echo $a['id']; ?>" <?php if ($m['adicional_id'] == $a['id']) echo 'selected'; ?>>
                <?php echo $a['nombre']; ?>
            </option>
        <?php } ?>
    </select>

    <label>Otros Pagos:</label>
    <input type="number" name="otros_pagos" value="<?php echo $m['otros_pagos']; ?>" step="0.01">

    <label>Método de Pago:</label>
    <select name="metodo_pago" required>
        <?php
        $metodos = ['Efectivo', 'Transferencia', 'Tarjeta Débito', 'Tarjeta Crédito', 'Cuenta Corriente'];
        foreach ($metodos as $metodo) {
            $sel = ($m['metodo_pago'] == $metodo) ? 'selected' : '';
            echo "<option value='$metodo' $sel>$metodo</option>";
        }
        ?>
    </select>

    <label>Total a Pagar:</label>
    <input type="number" name="total" value="<?php echo $m['total']; ?>" step="0.01" required>

    <input type="submit" class="boton" value="Guardar Cambios">
</form>

<a href="ver_membresias.php" class="volver">Volver a Membresías</a>

</body>
</html>
