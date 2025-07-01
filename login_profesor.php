<?php
session_start();
include 'conexion.php';

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_POST['dni'];

    $stmt = $conexion->prepare("SELECT id FROM profesores WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();
        $_SESSION['profesor_id'] = $fila['id'];
        header("Location: panel_profesor.php");
        exit;
    } else {
        $mensaje = "❌ DNI no encontrado. Verificá tus datos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <!-- App Instalable -->
<link rel="manifest" href="manifest_profesor.json">
<link rel="icon" href="icono_profesor.png">
<meta name="theme-color" content="#a00a00">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js');
  }
</script>

    <meta charset="UTF-8">
    <title>Acceso Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        h1 {
            margin-bottom: 20px;
        }
        form {
            background: #111;
            padding: 20px;
            border: 1px solid gold;
            border-radius: 10px;
            max-width: 400px;
            margin: auto;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        button {
            background-color: gold;
            color: black;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        .mensaje {
            margin-top: 20px;
            color: red;
        }
    </style>
</head>
<body>

<h1>🔐 Ingreso Profesor</h1>

<form method="POST">
    <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
    <button type="submit">Ingresar</button>
</form>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>

</body>
</html>
