<?php
include 'conexion.php';

$resultado = $conexion->query("SELECT nombre, apellido, dni FROM profesores ORDER BY apellido");

if ($resultado->num_rows === 0) {
    echo "<h2 style='color: white;'>No hay profesores cargados.</h2>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>QR Profesores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
      text-align: center;
    }
    .profesor {
      background-color: #222;
      border: 1px solid gold;
      border-radius: 10px;
      padding: 15px;
      margin: 15px auto;
      width: 280px;
    }
    img {
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <h1>🎓 Códigos QR de Profesores</h1>

  <?php while ($profesor = $resultado->fetch_assoc()): 
    $dni = trim($profesor['dni']);
    $qrCode = 'P' . $dni;
    $qrUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=' . urlencode($qrCode);
  ?>
    <div class="profesor">
      <strong><?php echo htmlspecialchars($profesor['apellido'] . ', ' . $profesor['nombre']); ?></strong><br>
      DNI: <?php echo $dni; ?><br>
      <img src="<?php echo $qrUrl; ?>" alt="QR"><br>
      <small><?php echo $qrCode; ?></small>
    </div>
  <?php endwhile; ?>
</body>
</html>
