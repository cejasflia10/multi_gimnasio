<?php
session_start();
include 'conexion.php';

function calcularEdad($fecha_nacimiento) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $hoy->diff($nacimiento)->y;
    return $edad;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nac = $_POST['fecha_nacimiento'] ?? '';
    $disciplina = $_POST['disciplina'] ?? '';
    $modalidad = $_POST['modalidad'] ?? [];
    $categoria = $_POST['categoria'] ?? '';
    $peso = $_POST['peso'] ?? '';
    $division = $_POST['division'] ?? '';
    $domicilio = $_POST['domicilio'] ?? '';
    $localidad = $_POST['localidad'] ?? '';
    $escuela = $_POST['escuela_nombre'] ?? '';
    $pago = floatval($_POST['pago_inscripcion'] ?? 0);

    // Validación básica
    if (!$nombre || !$apellido || !$dni || !$fecha_nac || !$disciplina) {
        echo "❌ Datos incompletos.";
        exit;
    }

    $edad = calcularEdad($fecha_nac);
    $modalidad_json = json_encode(array_slice($modalidad, 0, 3));

    // Carga de imágenes
    $foto_competidor = '';
    $logo_escuela = '';

    if (isset($_FILES['foto_competidor']) && $_FILES['foto_competidor']['error'] == 0) {
        $ext = pathinfo($_FILES['foto_competidor']['name'], PATHINFO_EXTENSION);
        $foto_competidor = 'fotos/' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['foto_competidor']['tmp_name'], $foto_competidor);
    }

    if (isset($_FILES['escuela_logo']) && $_FILES['escuela_logo']['error'] == 0) {
        $ext = pathinfo($_FILES['escuela_logo']['name'], PATHINFO_EXTENSION);
        $logo_escuela = 'logos/' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['escuela_logo']['tmp_name'], $logo_escuela);
    }

    // Guardar en BD
    $stmt = $conexion->prepare("INSERT INTO competidores_evento 
        (nombre, apellido, dni, fecha_nacimiento, edad, disciplina, modalidad, categoria, peso, division,
         domicilio, localidad, escuela_nombre, escuela_logo, foto_competidor, pago_inscripcion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisssssssssds", 
        $nombre, $apellido, $dni, $fecha_nac, $edad, $disciplina, $modalidad_json, $categoria, $peso,
        $division, $domicilio, $localidad, $escuela, $logo_escuela, $foto_competidor, $pago
    );

    if ($stmt->execute()) {
        echo "<h3>✅ Competidor registrado correctamente</h3>";
        echo "<a href='registro_competidor.php'>Volver</a>";
    } else {
        echo "❌ Error al guardar.";
    }
}
?>
