<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// ✅ Solo validamos que haya un gimnasio logueado
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    echo "❌ Acceso denegado. Gimnasio no identificado.";
    exit;
}

// DEPURACIÓN: mostramos lo que llega
if (empty($_FILES)) {
    echo "❌ No se recibió ningún archivo.";
    echo "<pre>"; print_r($_FILES); echo "</pre>";
    exit;
}

// ✅ Procesar archivo
$archivo = $_FILES['logo'];
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($ext, $permitidos)) {
    die("❌ Formato no permitido ($ext).");
}

if (!is_dir('logos')) {
    mkdir('logos', 0777, true);
}

$nombre_archivo = 'logos/logo_' . $gimnasio_id . '.' . $ext;

if (move_uploaded_file($archivo['tmp_name'], $nombre_archivo)) {
    $conexion->query("UPDATE gimnasios SET logo = '$nombre_archivo' WHERE id = $gimnasio_id");
    echo "<script>window.location.href = 'index.php?logo=ok';</script>";
    exit;
} else {
    echo "❌ Error al guardar el archivo.";
}
