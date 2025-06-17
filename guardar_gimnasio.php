<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $plan = $_POST['plan'] ?? '';
    $monto = $_POST['monto'] ?? 0;
    $vencimiento = $_POST['vencimiento'] ?? null;
    $logo_path = null;

    // Subida del logo
    if (!empty($_FILES['logo']['name'])) {
        $carpeta_destino = "logos/";
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0755, true);
        }

        $nombre_archivo = basename($_FILES['logo']['name']);
        $ruta_archivo = $carpeta_destino . uniqid() . "_" . $nombre_archivo;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta_archivo)) {
            $logo_path = $ruta_archivo;
        }
    }

    // Insertar en la base de datos
    $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, logo, direccion, telefono, email, plan, monto, vencimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssds", $nombre, $logo_path, $direccion, $telefono, $email, $plan, $monto, $vencimiento);

    if ($stmt->execute()) {
        echo "<script>alert('Gimnasio registrado correctamente');window.location.href='gimnasios.php';</script>";
    } else {
        echo "Error al guardar el gimnasio: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "Acceso no permitido.";
}
?>
