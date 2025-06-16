<?php
include 'conexion.php';
include 'menu.php';

$id = $_GET['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $actualizar = "UPDATE disciplinas SET nombre='$nombre', descripcion='$descripcion' WHERE id=$id";
    mysqli_query($conexion, $actualizar);
    header("Location: disciplinas.php");
}

$consulta = mysqli_query($conexion, "SELECT * FROM disciplinas WHERE id = $id");
$disciplina = mysqli_fetch_assoc($consulta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Disciplina</title>
    <style>
        body { background-color: #111; color: #f1c40f; font-family: Arial, sans-serif; margin: 0; }
        .formulario { margin-left: 240px; padding: 20px; max-width: 600px; }
        input, textarea { width: 100%; padding: 10px; margin-top: 10px; background: #222; color: white; border: 1px solid #f1c40f; border-radius: 4px; }
        label { margin-top: 15px; display: block; font-weight: bold; }
        button { margin-top: 15px; background: #f1c40f; color: #111; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
        button:hover { background-color: #d4ac0d; }
    </style>
</head>
<body>
<div class="formulario">
    <h2>Editar Disciplina</h2>
    <form method="POST">
        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo $disciplina['nombre']; ?>" required>
        <label>Descripción:</label>
        <textarea name="descripcion"><?php echo $disciplina['descripcion']; ?></textarea>
        <button type="submit">Actualizar</button>
    </form>
</div>
</body>
</html>
