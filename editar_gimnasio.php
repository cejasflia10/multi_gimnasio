<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();
include 'menu_horizontal.php'; // Este es el menú correcto según lo que pediste

if (!isset($_GET['id'])) {
    die("ID de gimnasio no especificado.");
}
$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM gimnasios WHERE id = $id");
if ($resultado->num_rows === 0) {
    die("Gimnasio no encontrado.");
}
$gimnasio = $resultado->fetch_assoc();

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $plan = substr($_POST["plan"], 0, 100);
    $fecha_vencimiento = !empty($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : null;
    $duracion = $_POST["duracion_plan"];
    $limite = $_POST["limite_clientes"];
    $panel = isset($_POST["acceso_panel"]) ? 1 : 0;
    $ventas = isset($_POST["acceso_ventas"]) ? 1 : 0;
    $asistencias = isset($_POST["acceso_asistencias"]) ? 1 : 0;

    $stmt = $conexion->prepare("
        UPDATE gimnasios 
        SET nombre=?, direccion=?, telefono=?, email=?, plan=?, fecha_vencimiento=?, 
            duracion_plan=?, limite_clientes=?, acceso_panel=?, acceso_ventas=?, acceso_asistencias=? 
        WHERE id=?
    ");

    if (!$stmt) {
        die("Error en prepare(): " . $conexion->error);
    }

    $stmt->bind_param(
        "ssssssiiiiii",
        $nombre, $direccion, $telefono, $email, $plan, $fecha_vencimiento,
        $duracion, $limite, $panel, $ventas, $asistencias, $id
    );

    if ($stmt->execute()) {
        if (!empty($_POST["usuario"]) && !empty($_POST["clave"])) {
            $usuario = $_POST["usuario"];

            $verificar = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $verificar->bind_param("s", $usuario);
            $verificar->execute();
            $verificar->store_result();

            if ($verificar->num_rows === 0) {
                $clave = password_hash($_POST["clave"], PASSWORD_BCRYPT);
                $rol = "admin";
                $stmt_user = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, rol, id_gimnasio) VALUES (?, ?, ?, ?)");
                $stmt_user->bind_param("sssi", $usuario, $clave, $rol, $id);
                $stmt_user->execute();
            }
            $verificar->close();
        }

        header("Location: gimnasios.php");
        exit;
    } else {
        $error = "Error al guardar los cambios: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: gold;
        }
        form {
            max-width: 600px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            background-color: #222;
            border: 1px solid gold;
            color: gold;
        }
        .checkboxes {
            margin-top: 15px;
        }
        .checkboxes label {
            font-weight: normal;
            display: block;
        }
        button {
            margin-top: 20px;
            padding: 12px;
            width: 100%;
            background-color: gold;
            color: black;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }
        a {
            display: block;
            text-align: center;
            color: gold;
            margin-top: 20px;
            text-decoration: underline;
        }
        .error {
            text-align: center;
            color: red;
            margin-top: 10px;
        }
        .logo-preview {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo-preview img {
            max-height: 80px;
            border-radius: 10px;
            border: 2px solid gold;
        }
    </style>
</head>
<body>

<h2>Editar Gimnasio</h2>

<?php if (!empty($gimnasio['logo'])): ?>
<div class="logo-preview">
    <img src="logos/<?= htmlspecialchars($gimnasio['logo']) ?>" alt="Logo del gimnasio">
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php endif; ?>

<form method="POST">
    <label>Nombre:</label>
    <input type="text" name="nombre" value="<?= htmlspecialchars($gimnasio['nombre'] ?? '', ENT_QUOTES) ?>" required>

    <label>Dirección:</label>
    <input type="text" name="direccion" value="<?= htmlspecialchars($gimnasio['direccion'] ?? '', ENT_QUOTES) ?>">

    <label>Teléfono:</label>
    <input type="text" name="telefono" value="<?= htmlspecialchars($gimnasio['telefono'] ?? '', ENT_QUOTES) ?>">

    <label>Email:</label>
    <input type="email" name="email" value="<?= htmlspecialchars($gimnasio['email'] ?? '', ENT_QUOTES) ?>">

    <label>Plan:</label>
    <input type="text" name="plan" maxlength="100" value="<?= htmlspecialchars($gimnasio['plan'] ?? '', ENT_QUOTES) ?>">

    <label>Fecha de vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($gimnasio['fecha_vencimiento'] ?? '', ENT_QUOTES) ?>">

    <label>Duración del plan (en meses):</label>
    <input type="number" name="duracion_plan" value="<?= htmlspecialchars($gimnasio['duracion_plan'] ?? '', ENT_QUOTES) ?>" min="1">

    <label>Límite de clientes:</label>
    <input type="number" name="limite_clientes" value="<?= htmlspecialchars($gimnasio['limite_clientes'] ?? '', ENT_QUOTES) ?>" min="0">

    <div class="checkboxes">
        <label><input type="checkbox" name="acceso_panel" <?= !empty($gimnasio['acceso_panel']) ? 'checked' : '' ?>> Acceso al panel</label>
        <label><input type="checkbox" name="acceso_ventas" <?= !empty($gimnasio['acceso_ventas']) ? 'checked' : '' ?>> Acceso a ventas</label>
        <label><input type="checkbox" name="acceso_asistencias" <?= !empty($gimnasio['acceso_asistencias']) ? 'checked' : '' ?>> Acceso a asistencias</label>
    </div>

    <label>Crear nuevo usuario (opcional):</label>
    <input type="text" name="usuario" placeholder="Usuario">
    <input type="password" name="clave" placeholder="Clave (mínimo 6 caracteres)">

    <button type="submit">Guardar cambios</button>
    <a href="gimnasios.php">Volver</a>
</form>

</body>
</html>
