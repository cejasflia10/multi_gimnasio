<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background-color: #111; color: gold; font-family: Arial, sans-serif; }
    h2 { text-align: center; }
  </style>
</head>
<body>
  <div style="padding: 20px; text-align: center;">
    <h2>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h2>
    <p>Panel de control de <strong>Fight Academy</strong></p>
  </div>
  <!-- Incluir asistencias de profesores y clientes -->
  <?php include 'asistencias_index.php'; ?>
</body>
</html>
