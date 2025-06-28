<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if (!isset($_SESSION['rol']) || !isset($_POST['id'])) {
    die("Acceso no autorizado.");
}

$rol = $_SESSION['rol'];
$id = intval($_POST['id']);

// Escapar y validar entradas
$apellido = $conexion->real_escape_string(trim($_POST['apellido']));
$nombre = $conexion->real_escape_string(trim($_POST['nombre']));
$dni = $conexion->real_escape_string(trim($_POST['dni']));
$fecha_nacimiento = $conexion->real_escape_string($_POST['fecha_nacimiento']);
$domicilio = $conexion->real_escape_string(trim($_POST['domicilio']));
$telefono = $conexion->real_escape_string(trim($_POST['telefono']));
$email = $conexion->real_escape_string(trim($_POST['email']));
$disciplina = $conexion->real_escape_string(trim($_POST['disciplina']));

// Solo admins pueden modificar gimnasio_id
if ($rol === 'admin' && isset($_POST['gimnasio_id']) && is_numeric($_POST['gimnasio_id'])) {
    $gimnasio_id = intval($_POST['gimnasio_id']);

    $sql = "UPDATE clientes SET 
                apellido = '$apellido',
                nombre = '$nombre',
                dni = '$dni',
                fecha_nacimiento = '$fecha_nacimiento',
                domicilio = '$domicilio',
                telefono = '$telefono',
                email = '$email',
                disciplina = '$disciplina',
                gimnasio_id = $gimnasio_id
            WHERE id = $id";
} else {
    // Sin modificar gimnasio_id
    $sql = "UPDATE clientes SET 
                apellido = '$apellido',
                nombre = '$nombre',
                dni = '$dni',
                fecha_nacimiento = '$fecha_nacimiento',
                domicilio = '$domicilio',
                telefono = '$telefono',
                email = '$email',
                disciplina = '$disciplina'
            WHERE id = $id";
}

if ($conexion->query($sql)) {
    header("Location: ver_clientes.php?ok=1");
    exit;
} else {
    echo "<p style='color:red;'>❌ Error al guardar cambios: " . $conexion->error . "</p>";
}
?>
