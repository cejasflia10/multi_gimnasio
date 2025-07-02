<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $gimnasio_id = intval($_POST['gimnasio_id']);

    echo "<p>DNI recibido: <strong>$dni</strong></p>";
    echo "<p>Gimnasio ID seleccionado: <strong>$gimnasio_id</strong></p>";

    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado && $resultado->num_rows === 1) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id'];
        echo "<p style='color:lightgreen;'>✔ Cliente encontrado. Redirigiendo al panel...</p>";
        echo "<script>setTimeout(function() { window.location = 'panel_cliente.php'; }, 2000);</script>";
    } else {
        echo "<p style='color:red;'>❌ No se encontró el cliente con ese DNI en ese gimnasio.</p>";
    }

    exit;
}

$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Ingreso Cliente (debug)</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <style>
        body { background: #111; color: gold; font-family: Arial; text-align: center; padding-top: 80px; }
        input, select, button { padding: 10px; font-size: 16px; margin-top: 10px; width: 80%%; max-width: 300px; }
        form { display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Acceso Cliente (Prueba)</h2>
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
