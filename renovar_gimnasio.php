<?php
session_start();
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    exit("❌ Gimnasio no válido.");
}

$mensaje = '';

// Obtener gimnasio y planes disponibles
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $id")->fetch_assoc();
$planes = $conexion->query("SELECT * FROM planes_acceso");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $monto_plan = floatval($_POST["monto_plan"]);
    $forma_pago = $_POST["forma_pago"];
    $plan_id = intval($_POST["plan_id"]);

    $stmt = $conexion->prepare("UPDATE gimnasios SET fecha_vencimiento=?, monto_plan=?, forma_pago=?, plan_id=? WHERE id=?");
    $stmt->bind_param("sdsii", $fecha_vencimiento, $monto_plan, $forma_pago, $plan_id, $id);
    $stmt->execute();
    $stmt->close();

    $mensaje = "✅ Datos actualizados correctamente.";
    $gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $id")->fetch_assoc(); // refrescar datos
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Renovar Gimnasio</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 40px;
        }
        form {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 15px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            background: #333;
            border: 1px solid #555;
            border-radius: 6px;
            color: gold;
            margin-top: 5px;
        }
        .boton {
            margin-top: 20px;
            background: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .mensaje {
            color: lightgreen;
            font-weight: bold;
            text-align: center;
        }
    </style>
    <script>
        const planes = {};
        <?php
        $planes->data_seek(0);
        while($p = $planes->fetch_assoc()) {
            echo "planes[{$p['id']}] = " . json_encode($p) . ";\n";
        }
        ?>
        function actualizarPrecio() {
            const planId = document.querySelector('select[name="plan_id"]').value;
            const precio = planId && planes[planId] ? planes[planId].precio : '';
            document.querySelector('input[name="monto_plan"]').value = precio;
        }
    </script>
</head>
<body>

<h2 style="text-align:center;">♻️ Renovar Gimnasio</h2>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

<form method="POST">
    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required value="<?= $gimnasio['fecha_vencimiento'] ?? '' ?>">

    <label>Seleccionar Plan:</label>
    <select name="plan_id" onchange="actualizarPrecio()" required>
        <option value="">-- Seleccione un plan --</option>
        <?php
        $planes->data_seek(0);
        while($plan = $planes->fetch_assoc()):
        ?>
            <option value="<?= $plan['id'] ?>" <?= ($plan['id'] == ($gimnasio['plan_id'] ?? 0)) ? 'selected' : '' ?>>
                <?= htmlspecialchars($plan['nombre']) ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Monto del Plan ($):</label>
    <input type="number" name="monto_plan" step="0.01" required value="<?= $gimnasio['monto_plan'] ?? '' ?>">

    <label>Forma de Pago:</label>
    <select name="forma_pago" required>
        <option value="">Seleccione una opción</option>
        <option value="Efectivo" <?= ($gimnasio['forma_pago'] ?? '') == 'Efectivo' ? 'selected' : '' ?>>Efectivo</option>
        <option value="Transferencia" <?= ($gimnasio['forma_pago'] ?? '') == 'Transferencia' ? 'selected' : '' ?>>Transferencia</option>
        <option value="Débito" <?= ($gimnasio['forma_pago'] ?? '') == 'Débito' ? 'selected' : '' ?>>Débito</option>
        <option value="Crédito" <?= ($gimnasio['forma_pago'] ?? '') == 'Crédito' ? 'selected' : '' ?>>Crédito</option>
    </select>

    <button type="submit" class="boton">💾 Guardar Cambios</button>
    <a href="agregar_gimnasio.php" class="boton">⬅️ Volver</a>
</form>

<script>
    actualizarPrecio();
</script>

</body>
</html>
