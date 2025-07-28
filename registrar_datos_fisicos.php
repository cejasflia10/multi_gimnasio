<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;

if ($cliente_id == 0) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// Verifico si ya cargó datos físicos para no mostrar el formulario más de una vez
$stmtCheck = $conexion->prepare("SELECT COUNT(*) AS total FROM datos_fisicos WHERE cliente_id = ?");
$stmtCheck->bind_param("i", $cliente_id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result()->fetch_assoc();

if ($resCheck['total'] > 0) {
    // Ya cargó datos, redirijo al panel cliente
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
    $fecha = date('Y-m-d');

    $stmt = $conexion->prepare("INSERT INTO datos_fisicos 
        (cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, patologias, tipo_diabetes, medicaciones, observaciones) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssssssss",
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
        // Actualizo flag en cliente para indicar que ya cargó datos
        $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id=$cliente_id");
        header("Location: panel_cliente.php");
        exit;
    } else {
        $mensaje = "❌ Error al guardar los datos. Intente nuevamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Registrar Datos Físicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="estilo_unificado.css" />
</head>
<body>

<div class="contenedor">
    <div class="formulario">
        <h2>📋 Completar Datos Físicos</h2>
        <?php if ($mensaje): ?>
            <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <label for="peso">Peso (kg):</label>
            <input type="text" id="peso" name="peso" required pattern="^\d+(\.\d{1,2})?$" title="Ingrese un número válido">

            <label for="altura">Altura (cm):</label>
            <input type="text" id="altura" name="altura" required pattern="^\d+(\.\d{1,2})?$" title="Ingrese un número válido">

            <label for="talle_remera">Talle Remera:</label>
            <input type="text" id="talle_remera" name="talle_remera">

            <label for="talle_pantalon">Talle Pantalón:</label>
            <input type="text" id="talle_pantalon" name="talle_pantalon">

            <label for="talle_calzado">Talle Calzado:</label>
            <input type="text" id="talle_calzado" name="talle_calzado">

            <label>Patologías (marcar las que tenga):</label>
            <div class="checkbox-group">
                <label><input type="checkbox" name="patologias[]" value="Diabetes" onchange="toggleDiabetes(this)"> Diabetes</label>
                <label><input type="checkbox" name="patologias[]" value="Hipertensión"> Hipertensión</label>
                <label><input type="checkbox" name="patologias[]" value="Asma"> Asma</label>
                <label><input type="checkbox" name="patologias[]" value="Otra"> Otra</label>
            </div>

            <div id="tipo_diabetes" style="display:none;">
                <label for="tipo_diabetes_select">Tipo de Diabetes:</label>
                <select id="tipo_diabetes_select" name="tipo_diabetes">
                    <option value="">-- Seleccionar --</option>
                    <option value="Tipo 1">Tipo 1</option>
                    <option value="Tipo 2">Tipo 2</option>
                    <option value="Gestacional">Gestacional</option>
                </select>
            </div>

            <label for="medicaciones">¿Toma medicaciones? ¿Cuáles?</label>
            <textarea id="medicaciones" name="medicaciones" placeholder="Describa las medicaciones que toma"></textarea>

            <label for="observaciones">Observaciones:</label>
            <textarea id="observaciones" name="observaciones" placeholder="Otros comentarios, alergias, etc."></textarea>

            <button type="submit" class="btn-guardar">Guardar Datos</button>
        </form>
    </div>
</div>

<script>
function toggleDiabetes(checkbox) {
    document.getElementById("tipo_diabetes").style.display = checkbox.checked ? "block" : "none";
}
</script>

</body>
</html>
