<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$precio_base = floatval($_POST['precio_base'] ?? 0);
$fecha_cierre = $_POST['fecha_cierre'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$imagen_url = '';

if (!empty($_FILES['imagen']['name'])) {
    $nombre = basename($_FILES['imagen']['name']);
    $ruta = 'imagenes_subastas/' . time() . '_' . $nombre;
    move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta);
    $imagen_url = $ruta;
}

if ($titulo && $descripcion && $precio_base > 0 && $fecha_cierre && $gimnasio_id) {
    $stmt = $conexion->prepare("INSERT INTO subastas (titulo, descripcion, precio_base, fecha_cierre, imagen, gimnasio_id) 
                                VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdssi", $titulo, $descripcion, $precio_base, $fecha_cierre, $imagen_url, $gimnasio_id);
    $stmt->execute();
}

header("Location: crear_subasta.php?ok=1");
exit;
