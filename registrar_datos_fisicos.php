<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;

if ($cliente_id == 0) {
    echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado.</div>";
    exit;
}

// ✅ Verifico si ya cargó datos
$stmtCheck = $conexion->prepare("SELECT COUNT(*) AS total FROM datos_fisicos WHERE cliente_id=?");
$stmtCheck->bind_param("i", $cliente_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result()->fetch_assoc();

if ($resCheck['total'] > 0) {
    header("Location: panel_cliente.php");
    exit;
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso = $_POST['peso'] ?? '';
    $altura = $_POST['altura'] ?? '';
    $remera = $_POST['talle_remera'] ?? '';
    $pantalon = $_POST['talle_pantalon'] ?? '';
    $calzado = $_POST['talle_calzado'] ?? '';
    $patologias = isset($_POST['patologias']) ? implode(", ", $_POST['patologias']) : '';
    $tipo_diabetes = $_POST['tipo_diabetes'] ?? '';
    $medicaciones = $_POST['medicaciones'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    $fecha = date("Y-m-d");

    $stmt = $conexion->prepare("
        INSERT INTO datos_fisicos 
        (cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, patologias, tipo_diabetes, medicaciones, observaciones) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssssssss",
        $cliente_id,
        $fecha,
        $peso,
        $altura,
        $remera,
        $pantalon,
        $calzado,
        $patologias,
        $tipo_diabetes,
        $medicaciones,
        $observaciones
    );

    if ($stmt->execute()) {
        $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id=$cliente_id");
        header("Location: panel_cliente.php");
        exit;
    } else {
        $mensaje = "❌ Error al guardar los datos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Datos Físicos</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <div class="formulario">
        <h2>📋 Completar Datos Físicos</h2>
        <?php if ($mensaje): ?>
            <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Peso (kg):</label>
            <input type="text" name="peso" required>

            <label>Altura (cm):</label>
            <input type="text" name="altura" required>

            <label>Talle Remera:</label>
            <input type="text" name="talle_remera">

            <label>Talle Pantalón:</label>
            <input type="text" name="talle_pantalon">

            <label>Talle Calzado:</label>
            <input type="text" name="talle_calzado">

            <label>Patologías:</label>
            <label><input type="checkbox" name="patologias[]" value="Diabetes" onchange="toggleDiabetes(this)"> Diabetes</label>
            <label><input type="checkbox" name="patologias[]" value="Hipertensión"> Hipertensión</label>
            <label><input type="checkbox" name="patologias[]" value="Asma"> Asma</label>
            <label><input type="checkbox" name="patologias[]" value="Otra"> Otra</label>

            <div id="tipo_diabetes" style="display:none;">
                <label>Tipo de Diabetes:</label>
                <select name="tipo_diabetes">
                    <option value="">-- Seleccionar --</option>
                    <option value="Tipo 1">Tipo 1</option>
                    <option value="Tipo 2">Tipo 2</option>
                    <option value="Gestacional">Gestacional</option>
                </select>
            </div>

            <label>Medicaciones:</label>
            <textarea name="medicaciones"></textarea>

            <label>Observaciones:</label>
            <textarea name="observaciones"></textarea>

            <button type="submit" class="btn-guardar">Guardar Datos</button>
        </form>
    </div>
</div>
<script>
function toggleDiabetes(chk) {
    document.getElementById("tipo_diabetes").style.display = chk.checked ? "block" : "none";
}
</script>
</body>
</html>
