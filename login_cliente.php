<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_POST['dni'];

    // Buscar cliente con DNI
    $sql = "SELECT * FROM clientes WHERE dni = '$dni'";
    $resultado = $conexion->query($sql);

    if ($resultado && $resultado->num_rows == 1) {
        $cliente = $resultado->fetch_assoc();
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['gimnasio_id'] = $cliente['gimnasio_id']; // importante para filtrar después
        header("Location: panel_cliente.php");
        exit;
    } else {
        echo "<script>alert('DNI no encontrado');window.location='login_cliente.php';</script>";
    }
}
?>
<form method="post" style="text-align:center; margin-top: 100px;">
    <h2 style="color:gold">Acceso Cliente</h2>
    <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br><br>
    <button type="submit">Ingresar</button>
</form>
