
<?php
include 'conexion.php';
include 'menu.php';

if (isset($_GET['id'])) {
  $id = $_GET['id'];
  $consulta = $conexion->query("SELECT * FROM clientes WHERE id=$id");
  $cliente = $consulta->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $id = $_POST['id'];
  $apellido = $_POST['apellido'];
  $nombre = $_POST['nombre'];
  $fecha_nacimiento = $_POST['fecha_nacimiento'];
  $edad = $_POST['edad'];
  $domicilio = $_POST['domicilio'];
  $telefono = $_POST['telefono'];
  $email = $_POST['email'];
  $rfid = $_POST['rfid'];
  $gimnasio = $_POST['gimnasio'];

  $sql = "UPDATE clientes SET apellido='$apellido', nombre='$nombre',
          fecha_nacimiento='$fecha_nacimiento', edad='$edad', domicilio='$domicilio',
          telefono='$telefono', email='$email', rfid='$rfid', gimnasio='$gimnasio'
          WHERE id=$id";

  if ($conexion->query($sql) === TRUE) {
    echo "<script>alert('Datos actualizados correctamente'); window.location.href='ver_clientes.php';</script>";
  } else {
    echo "Error al actualizar: " . $conexion->error;
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Cliente</title>
  <style>
    body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
    label { display: block; margin-top: 10px; }
    input, select { width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: none; }
    .btn { margin-top: 20px; background-color: gold; color: #000; font-weight: bold; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Editar Cliente</h2>
  <form method="POST">
    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">

    <label>Apellido:</label>
    <input type="text" name="apellido" value="<?php echo $cliente['apellido']; ?>" required>

    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?php echo $cliente['nombre']; ?>" required>

    <label>DNI (no editable):</label>
    <input type="text" value="<?php echo $cliente['dni']; ?>" disabled>

    <label>Fecha de Nacimiento:</label>
    <input type="date" name="fecha_nacimiento" value="<?php echo $cliente['fecha_nacimiento']; ?>" onchange="calcularEdad()" required>

    <label>Edad:</label>
    <input type="number" name="edad" id="edad" value="<?php echo $cliente['edad']; ?>" readonly required>

    <label>Domicilio:</label>
    <input type="text" name="domicilio" value="<?php echo $cliente['domicilio']; ?>" required>

    <label>Teléfono:</label>
    <input type="text" name="telefono" value="<?php echo $cliente['telefono']; ?>" required>

    <label>Email:</label>
    <input type="email" name="email" value="<?php echo $cliente['email']; ?>" required>

    <label>RFID:</label>
    <input type="text" name="rfid" value="<?php echo $cliente['rfid']; ?>">

    <label>Gimnasio:</label>
    <input type="text" name="gimnasio" value="<?php echo $cliente['gimnasio']; ?>" required>

    <button type="submit" class="btn">Guardar Cambios</button>
  </form>

  <script>
    function calcularEdad() {
      const fecha = document.querySelector('input[name="fecha_nacimiento"]').value;
      if (fecha) {
        const hoy = new Date();
        const nacimiento = new Date(fecha);
        let edad = hoy.getFullYear() - nacimiento.getFullYear();
        const m = hoy.getMonth() - nacimiento.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
        document.getElementById("edad").value = edad;
      }
    }
  </script>
</body>
</html>
