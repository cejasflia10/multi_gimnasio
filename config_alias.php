<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Gimnasio no especificado.");
}
// Guardar alias si se envió el formulario
$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alias = trim($_POST['alias']);
    $stmt = $conexion->prepare("REPLACE INTO configuraciones (clave, valor) VALUES ('alias_transferencia', ?)");
    $stmt->bind_param("s", $alias);
    $stmt->execute();
    $mensaje = "✅ Alias actualizado correctamente.";
}

// Obtener alias actual
$alias_actual = "";
$res = $conexion->query("SELECT valor FROM configuraciones WHERE clave = 'alias_transferencia'");
if ($fila = $res->fetch_assoc()) {
    $alias_actual = $fila['valor'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Alias de Transferencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        form {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border: 1px solid gold;
            border-radius: 10px;
        }
        label, input {
            display: block;
            width: 100%;
            margin-top: 10px;
        }
        input[type="text"] {
            padding: 10px;
            border-radius: 6px;
            border: none;
            background: #fff;
            color: #000;
        }
        button {
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
            text-align: center;
            margin-top: 20px;
            color: lightgreen;
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>

<h1>⚙️ Editar Alias de Transferencia</h1>

<form method="POST">
    <label>Alias actual:</label>
    <input type="text" name="alias" value="<?= htmlspecialchars($alias_actual) ?>" required>
    <button type="submit">Guardar Alias</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
