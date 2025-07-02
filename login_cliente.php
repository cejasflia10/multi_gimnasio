<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $gimnasio_id = intval($_POST['gimnasio_id']);

    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado && $resultado->num_rows === 1) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id'];
        header("Location: panel_cliente.php");
        exit;
    } else {
        echo "<script>alert('DNI no encontrado en el gimnasio seleccionado'); window.location='login_cliente.php';</script>";
        exit;
    }
}

$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ingreso Cliente</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <style>
        body { background: #000; color: gold; font-family: Arial; text-align: center; padding-top: 80px; }
        input, select, button { padding: 10px; font-size: 16px; margin-top: 10px; width: 80%%; max-width: 300px; }
        form { display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Acceso Cliente</h2>
    <form method="POST">
        <select name="gimnasio_id" required>
            <option value="">Seleccioná tu gimnasio</option>
            <?php while($g = $gimnasios->fetch_assoc()): ?>
                <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
            <?php endwhile; ?>
        </select><br>
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
