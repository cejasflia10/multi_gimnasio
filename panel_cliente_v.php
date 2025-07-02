<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$cliente_id = $_SESSION['cliente_id'] ?? ($_GET['cliente_id'] ?? 0);
$gimnasio_id = $_SESSION['gimnasio_id'] ?? ($_GET['gimnasio_id'] ?? 0);

if (!$cliente_id || !$gimnasio_id) {
    echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
    exit;
}

// Si vino por GET y no hay sesión, la iniciamos
if (!isset($_SESSION['cliente_id']) && isset($_GET['cliente_id'])) {
    $_SESSION['cliente_id'] = $cliente_id;
    $_SESSION['gimnasio_id'] = $gimnasio_id;
}


include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'];
$cliente_nombre = $_SESSION['cliente_nombre'] ?? 'Cliente';

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-top: 30px;
        }
        .datos {
            background: #111;
            padding: 20px;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
            border: 1px solid gold;
        }
        pre {
            background-color: #111;
            color: lime;
            padding: 10px;
            margin: 20px auto;
            max-width: 600px;
            overflow: auto;
        }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>

<div class="datos">
    <p><strong>DNI:</strong> <?= $cliente['dni'] ?></p>
    <p><strong>Email:</strong> <?= $cliente['email'] ?></p>
    <p><strong>Teléfono:</strong> <?= $cliente['telefono'] ?></p>
    <p><strong>Disciplina:</strong> <?= $cliente['disciplina'] ?></p>
</div>

<pre>🧪 SESIÓN ACTUAL:
<?php print_r($_SESSION); ?>
</pre>

</body>
</html>
