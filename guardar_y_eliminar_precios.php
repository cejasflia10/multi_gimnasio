<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    $ids = $_POST['id'] ?? [];
    $min = $_POST['rango_min'] ?? [];
    $max = $_POST['rango_max'] ?? [];
    $precio = $_POST['precio'] ?? [];

    foreach ($ids as $i => $id) {
        $id = intval($id);
        $minimo = intval($min[$i]);
        $maximo = intval($max[$i]);
        $monto = floatval($precio[$i]);

        $conexion->query("UPDATE precio_hora SET rango_min = $minimo, rango_max = $maximo, precio = $monto WHERE id = $id AND gimnasio_id = $gimnasio_id");
    }
}

if ($accion === 'eliminar') {
    $ids_eliminar = $_POST['eliminar_ids'] ?? [];
    foreach ($ids_eliminar as $id) {
        $id = intval($id);
        $conexion->query("DELETE FROM precio_hora WHERE id = $id AND gimnasio_id = $gimnasio_id");
    }
}

header("Location: configurar_precios_hora.php");
exit;
