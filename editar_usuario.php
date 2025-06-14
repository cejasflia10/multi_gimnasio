<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    echo "ID de usuario no especificado.";
    exit();
}

$id = $_GET['id'];
$consulta = "SELECT * FROM usuarios WHERE id = ?";
$stmt = $conexion->prepare($consulta);
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo "Usuario no encontrado.";
    exit();
}

$usuario = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            padding-top: 40px;
        }
        .formulario {
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 0 10px gold;
        }
        .formulario h2 {
            text-align: center;
            margin-bottom: 20px;
            color: gold;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }
        .botones {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .botones a, .botones button {
            padding: 10px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            color: #111;
            font-weight: bold;
        }
        .guardar { background-color: gold; }
        .eliminar { background-color: crimson; color: white; }
        .cancelar { background-color: #ccc; }
    </style>
</head>
<body>
    <div class="formulario">
        <h2>Editar Usuario</h2>
        <form action="actualizar_usuario.php" method="post">
            <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

            <label>Nombre:</label>
            <input type="text" name="nombre_usuario" value="<?= $usuario['nombre_usuario'] ?>" required>

            <label>Email:</label>
            <input type="email" name="email" value="<?= $usuario['email'] ?>" required>

            <label>Rol:</label>
            <select name="rol" required>
                <option value="Administrador" <?= $usuario['rol'] == 'Administrador' ? 'selected' : '' ?>>Administrador</option>
                <option value="Profesor" <?= $usuario['rol'] == 'Profesor' ? 'selected' : '' ?>>Profesor</option>
                <option value="Instructor" <?= $usuario['rol'] == 'Instructor' ? 'selected' : '' ?>>Instructor</option>
            </select>

<label>Gimnasio:</label>
<select name="id_gimnasio" required>
  <?php
    include("conexion.php");
    $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
    while ($g = $gimnasios->fetch_assoc()):
      $selected = ($g['id'] == $usuario['id_gimnasio']) ? 'selected' : '';
  ?>
    <option value="<?= $g['id'] ?>" <?= $selected ?>><?= $g['nombre'] ?></option>
  <?php endwhile; ?>
</select>

            <div class="botones">
                <button type="submit" class="guardar">Guardar</button>
                <a href="eliminar_usuario.php?id=<?= $usuario['id'] ?>" class="eliminar" onclick="return confirm('¿Seguro que deseas eliminar este usuario?')">Eliminar</a>
                <a href="usuarios.php" class="cancelar">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
