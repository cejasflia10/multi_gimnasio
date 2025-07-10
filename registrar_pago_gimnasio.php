<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gimnasio_id = intval($_POST['gimnasio_id']);
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $monto = floatval($_POST['monto']);
    $metodo = $_POST['metodo'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');

    $stmt = $conexion->prepare("INSERT INTO pagos_gimnasios (gimnasio_id, fecha, monto, metodo_pago, observaciones) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", $gimnasio_id, $fecha, $monto, $metodo, $observaciones);
    $stmt->execute();

    $mensaje = "<p style='color:lime;'>✅ Pago registrado correctamente.</p>";
}

// Obtener lista de gimnasios
$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Pagos de Gimnasios</title>
    <style>
        body { background: #111; color: gold; font-family: Arial; padding: 20px; }
        .formulario { max-width: 600px; margin: auto; background: #222; padding: 20px; border-radius: 10px; }
        label { display: block; margin-top: 10px; }
        input, select, textarea {
            width: 100%; padding: 8px; background: #000; color: white; border: 1px solid gold;
        }
        button {
            background: gold; color: black; padding: 10px 20px;
            margin-top: 20px; border: none; cursor: pointer; font-weight: bold;
        }
    </style>
</head>
<body>

<div class="formulario">
    <h2>💳 Registrar Pago de Gimnasio</h2>
    <?= $mensaje ?>
    <form method="post">
        <label>Gimnasio</label>
        <select name="gimnasio_id" required>
            <option value="">Seleccionar...</option>
            <?php while ($g = $gimnasios->fetch_assoc()): ?>
                <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Fecha del pago</label>
        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>

        <label>Monto</label>
        <input type="number" step="0.01" name="monto" required>

        <label>Método de pago</label>
        <select name="metodo">
            <option value="Transferencia">Transferencia</option>
            <option value="Efectivo">Efectivo</option>
            <option value="Débito">Débito</option>
            <option value="Crédito">Crédito</option>
            <option value="Otro">Otro</option>
        </select>

        <label>Observaciones</label>
        <textarea name="observaciones" rows="3"></textarea>

        <button type="submit">💾 Registrar Pago</button>
    </form>
</div>

</body>
</html>
