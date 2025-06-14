<?php
include 'conexion.php';

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $email = $_POST["email"];
    $disciplina_id = $_POST["disciplina_id"];

    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, email, disciplina_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $email, $disciplina_id);
    $stmt->execute();

    $mensaje = "Cliente registrado exitosamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Cliente</title>
    <style>
        body { background-color: #111; color: #fff; font-family: Arial; padding-left: 240px; }
        .container { padding: 30px; max-width: 600px; }
        h1 { color: #ffc107; }
        label, input, select { display: block; margin-top: 10px; padding: 8px; width: 100%; }
        .btn { margin-top: 15px; padding: 10px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        .mensaje { margin-top: 15px; color: #0f0; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Registrar Cliente</h1>

    <form method="POST">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>Email:</label>
        <input type="email" name="email">

        <label for="disciplina_id">Disciplina:</label>
        <select name="disciplina_id" required>
            <option value="">Seleccionar disciplina</option>
            <?php
            $disciplinas = $conexion->query("SELECT * FROM disciplinas ORDER BY nombre");
            while ($d = $disciplinas->fetch_assoc()) {
                echo "<option value='{$d['id']}'>{$d['nombre']}</option>";
            }
            ?>
        </select>

        <button type="submit" class="btn">Registrar</button>
    </form>

    <?php if ($mensaje): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</div>
</body>
</html>
