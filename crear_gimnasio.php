<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"]);
    $direccion = trim($_POST["direccion"]);
    $telefono = trim($_POST["telefono"]);
    $email = trim($_POST["email"]);
    $duracion = intval($_POST["duracion_plan"]);
    $limite = intval($_POST["limite_clientes"]);
    $panel = isset($_POST["acceso_panel"]) ? 1 : 0;
    $ventas = isset($_POST["acceso_ventas"]) ? 1 : 0;
    $asistencias = isset($_POST["acceso_asistencias"]) ? 1 : 0;

    // Verifica si ya existe un gimnasio con el mismo nombre
    $verificar = $conexion->prepare("SELECT id FROM gimnasios WHERE nombre = ?");
    $verificar->bind_param("s", $nombre);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        $mensaje = "Ya existe un gimnasio con ese nombre.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, direccion, telefono, email, duracion_plan, limite_clientes, acceso_panel, acceso_ventas, acceso_asistencias) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiIIII", $nombre, $direccion, $telefono, $email, $duracion, $limite, $panel, $ventas, $asistencias);
        if ($stmt->execute()) {
            $mensaje = "Gimnasio creado exitosamente.";
        } else {
            $mensaje = "Error al crear gimnasio.";
        }
        $stmt->close();
    }
    $verificar->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
      <link rel="stylesheet" href="estilo_unificado.css">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Gimnasio</title>
  
</head>
<body>
  <div class="contenedor">
  <h2>Agregar Nuevo Gimnasio</h2>
  <form method="POST" action="">
    <label>Nombre del Gimnasio:</label>
    <input type="text" name="nombre" required>

    <label>Dirección:</label>
    <input type="text" name="direccion" required>

    <label>Teléfono:</label>
    <input type="text" name="telefono">

    <label>Email:</label>
    <input type="email" name="email">

    <label>Duración del plan (días):</label>
    <input type="number" name="duracion_plan" required>

    <label>Límite de clientes:</label>
    <input type="number" name="limite_clientes" required>

    <label><input type="checkbox" name="acceso_panel"> Acceso a panel</label>
    <label><input type="checkbox" name="acceso_ventas"> Acceso a ventas</label>
    <label><input type="checkbox" name="acceso_asistencias"> Acceso a asistencias</label>

    <input type="submit" value="Registrar Gimnasio">
  </form>

  <?php if (!empty($mensaje)) echo "<p class='mensaje'>$mensaje</p>"; ?>
</div>
</body>

</html>
