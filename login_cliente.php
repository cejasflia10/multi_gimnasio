<?php
include 'conexion.php';
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $consulta->fetch_assoc();

    if (!$cliente) {
        $mensaje = "❌ DNI no encontrado.";
    } else {
        $cliente_id = $cliente['id'];
        header("Location: panel_cliente.php?cliente_id=$cliente_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="background:black; color:gold; font-family:Arial; text-align:center; padding-top:100px;">
    <h2>Acceso Cliente</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese DNI" required><br><br>
        <input type="submit" value="Entrar">
    </form>
    <?php if (!empty($mensaje)): ?>
        <p style="color:red;"><?= $mensaje ?></p>
    <?php endif; ?>
</body>
</html>
