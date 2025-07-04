<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Bloqueo total del acceso
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Gimnasio</title>
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .bloqueado {
            background-color: #220000;
            border: 2px solid red;
            padding: 30px;
            border-radius: 10px;
            color: #ff5555;
            max-width: 600px;
            margin: auto;
        }
        a {
            margin-top: 20px;
            display: inline-block;
            color: gold;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="bloqueado">
        <h2>⛔ Acceso Restringido</h2>
        <p>No está autorizado para eliminar gimnasios.</p>
        <p>Por favor, comuníquese con el administrador para realizar esta acción.</p>
    </div>
    <a href="index.php">← Volver al panel</a>
</body>
</html>
