<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

// Guardar alias si se envía el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alias'])) {
    $nuevo_alias = $conexion->real_escape_string(trim($_POST['alias']));
    $conexion->query("UPDATE gimnasios SET alias = '$nuevo_alias' WHERE id = $gimnasio_id");
    echo "<script>alert('Alias actualizado correctamente');</script>";
}

// Obtener alias actual
$alias_actual = '';
$res = $conexion->query("SELECT alias FROM gimnasios WHERE id = $gimnasio_id");
if ($res && $row = $res->fetch_assoc()) {
    $alias_actual = $row['alias'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Configurar Alias</title>
    
</head>
<body>
    <div class="contenedor">
        <h2>⚙️ Configurar alias para transferencias</h2>
        <form method="POST">
            <label for="alias">Alias actual:</label>
            <input type="text" name="alias" value="<?= htmlspecialchars($alias_actual) ?>" required>
            <button type="submit">Guardar Alias</button>
        </form>
    </div>
</body>
</html>
