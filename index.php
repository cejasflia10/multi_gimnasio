<?php
session_start();
if (!isset($_SESSION["gimnasio_id"])) {
    header("Location: login.php");
    exit;
}
include 'menu.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <main class="panel">
        <h1>Bienvenido al Panel</h1>
        <div class="cards">
            <div class="card">Pagos del día: <strong>$0</strong></div>
            <div class="card">Pagos del mes: <strong>$0</strong></div>
            <div class="card">Ventas del día: <strong>$0</strong></div>
            <div class="card">Ventas del mes: <strong>$0</strong></div>
        </div>
    </main>
</body>
</html>
