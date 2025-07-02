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
    <meta charset="UTF-8">
    <title>Configurar Alias</title>
    <style>
        body { background-color: black; color: gold; font-family: Arial, sans-serif; padding: 30px; }
        .contenedor { max-width: 500px; margin: auto; background: #111; padding: 20px; border-radius: 10px; border: 1px solid gold; }
        input[type="text"] {
            width: 100%; padding: 10px; margin-top: 10px;
            border-radius: 5px; border: 1px solid #ccc;
        }
        button {
            background: gold; color: black; padding: 10px 20px;
            border: none; margin-top: 15px; font-weight: bold;
            border-radius: 5px;
        }
    </style>
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
