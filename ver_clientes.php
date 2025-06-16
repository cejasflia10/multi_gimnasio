
<?php
include 'conexion.php';
include 'menu.php';

$consulta = "SELECT * FROM clientes";
$resultado = $conexion->query($consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Clientes Registrados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h1 {
      text-align: center;
      font-size: 24px;
      margin-bottom: 20px;
    }
    .tabla-container {
      overflow-x: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      color: #fff;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #444;
    }
    th {
      background-color: #333;
      color: gold;
    }
    .btn-agregar {
      background-color: gold;
      color: #000;
      padding: 10px 15px;
      text-decoration: none;
      font-weight: bold;
      display: inline-block;
      margin-bottom: 10px;
      border-radius: 5px;
    }
  </style>
</head>
<script>
document.getElementById('buscador').addEventListener('keyup', function () {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll('table tbody tr');

    filas.forEach(function (fila) {
        let texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(filtro) ? '' : 'none';
    });
});
</script>

<body>

<h1>Clientes registrados</h1>
<a class="btn-agregar" href="agregar_cliente.php">+ Agregar Cliente</a>

<div class="tabla-container">
<table>
  <tr>
    <th>Apellido</th>
    <th>Nombre</th>
    <th>DNI</th>
    <th>Fecha Nac.</th>
    <th>Edad</th>
    <th>Domicilio</th>
    <th>Teléfono</th>
    <th>Email</th>
    <th>RFID</th>
    <th>Gimnasio</th>
  </tr>
  <?php while ($fila = $resultado->fetch_assoc()) { ?>
    <tr><td><?= htmlspecialchars($cliente['apellido'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['nombre'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['dni'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['fecha_nacimiento'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['edad'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['domicilio'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['email'] ?? '') ?></td>
<td><?= htmlspecialchars($cliente['rfid'] ?? '') ?></td>

    </tr>
  <?php } ?>
</table>
</div>

</body>
</html>
