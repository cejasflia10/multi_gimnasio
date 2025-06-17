<?php
include 'conexion.php';
if (!isset($_GET['id'])) {
    die("ID de gimnasio no especificado.");
}
$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM gimnasios WHERE id = $id");
if ($resultado->num_rows === 0) {
    die("Gimnasio no encontrado.");
}
$gimnasio = $resultado->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $plan = $_POST["plan"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $duracion = $_POST["duracion_plan"];
    $limite = $_POST["limite_clientes"];
    $panel = isset($_POST["acceso_panel"]) ? 1 : 0;
    $ventas = isset($_POST["acceso_ventas"]) ? 1 : 0;
    $asistencias = isset($_POST["acceso_asistencias"]) ? 1 : 0;

    $stmt = $conexion->prepare("UPDATE gimnasios SET nombre=?, direccion=?, telefono=?, email=?, plan=?, fecha_vencimiento=?, duracion_plan=?, limite_clientes=?, acceso_panel=?, acceso_ventas=?, acceso_asistencias=? WHERE id=?");
    $stmt->bind_param("ssssssiiiiii", $nombre, $direccion, $telefono, $email, $plan, $fecha_vencimiento, $duracion, $limite, $panel, $ventas, $asistencias, $id);
    $stmt->execute();

    // Crear usuario si se cargó
    if (!empty($_POST["usuario"]) && !empty($_POST["clave"])) {
        $usuario = $_POST["usuario"];
        $clave = password_hash($_POST["clave"], PASSWORD_BCRYPT);
        $rol = "admin";
        $stmt_user = $conexion->prepare("INSERT INTO usuarios (usuario, clave, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
        $stmt_user->bind_param("sssi", $usuario, $clave, $rol, $id);
        $stmt_user->execute();
    }

    header("Location: gimnasios.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Gimnasio</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial; padding: 20px; }
    form { background-color: #222; padding: 20px; border-radius: 10px; max-width: 700px; }
    input, label, select { display: block; width: 100%; margin-top: 10px; }
    input[type=checkbox] { width: auto; }
    button { margin-top: 15px; padding: 10px; background: gold; color: black; border: none; border-radius: 5px; font-weight: bold; }
  </style>
</head>
<body>
  <h2>Editar Gimnasio: <?php echo htmlspecialchars($gimnasio["nombre"]); ?></h2>
  <form method="post">
    <label>Nombre: <input type="text" name="nombre" value="<?php echo $gimnasio["nombre"]; ?>" required></label>
    <label>Dirección: <input type="text" name="direccion" value="<?php echo $gimnasio["direccion"]; ?>" required></label>
    <label>Teléfono: <input type="text" name="telefono" value="<?php echo $gimnasio["telefono"]; ?>" required></label>
    <label>Email: <input type="email" name="email" value="<?php echo $gimnasio["email"]; ?>" required></label>
    <label>Plan:
      <select name="plan">
        <option value="Mensual" <?php if ($gimnasio["plan"]=="Mensual") echo "selected"; ?>>Mensual</option>
        <option value="Bimestral" <?php if ($gimnasio["plan"]=="Bimestral") echo "selected"; ?>>Bimestral</option>
        <option value="Trimestral" <?php if ($gimnasio["plan"]=="Trimestral") echo "selected"; ?>>Trimestral</option>
      </select>
    </label>
    <label>Fecha de Vencimiento: <input type="date" name="fecha_vencimiento" value="<?php echo $gimnasio["fecha_vencimiento"]; ?>"></label>
    <label>Duración del plan (días): <input type="number" name="duracion_plan" value="<?php echo $gimnasio["duracion_plan"]; ?>"></label>
    <label>Límite de clientes: <input type="number" name="limite_clientes" value="<?php echo $gimnasio["limite_clientes"]; ?>"></label>
    <label><input type="checkbox" name="acceso_panel" value="1" <?php if ($gimnasio["acceso_panel"]) echo "checked"; ?>> Acceso al panel</label>
    <label><input type="checkbox" name="acceso_ventas" value="1" <?php if ($gimnasio["acceso_ventas"]) echo "checked"; ?>> Acceso a ventas</label>
    <label><input type="checkbox" name="acceso_asistencias" value="1" <?php if ($gimnasio["acceso_asistencias"]) echo "checked"; ?>> Acceso a asistencias</label>

    <h3>Crear Usuario Administrador (opcional)</h3>
    <label>Usuario: <input type="text" name="usuario"></label>
    <label>Contraseña: <input type="password" name="clave"></label>

    <button type="submit">Guardar Cambios</button>
  </form>
</body>
</html>
