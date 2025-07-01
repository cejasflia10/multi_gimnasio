<?php
// Reforzar sesión para Render / producción
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // ⚠️ cambiar a 1 si usás HTTPS
    session_start();
}

include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $consulta = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $consulta->fetch_assoc();

    if (!$cliente) {
        $mensaje = "DNI no encontrado.";
    } else {
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];
        $membresia_q = $conexion->query("SELECT * FROM membresias 
            WHERE cliente_id = $cliente_id 
            AND fecha_vencimiento >= CURDATE() 
            AND clases_disponibles > 0
            ORDER BY id DESC LIMIT 1");

        if ($membresia_q->num_rows === 0) {
            $mensaje = "No tenés una membresía activa o sin clases disponibles.";
        } else {
            $_SESSION['cliente_id'] = $cliente_id;
            $_SESSION['cliente_nombre'] = $cliente['nombre'];
            $_SESSION['cliente_apellido'] = $cliente['apellido'];
            $_SESSION['gimnasio_id'] = $gimnasio_id;
            $_SESSION['rol'] = 'cliente';

            header("Location: panel_cliente.php");
            exit;
        }
    }
}
