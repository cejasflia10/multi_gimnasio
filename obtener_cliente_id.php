<?php
include 'conexion.php';

$busqueda = $_GET['busqueda'] ?? '';

// Extrae el DNI del texto ingresado
if (preg_match('/DNI:\s*(\d+)/', $busqueda, $coincidencias)) {
    $dni = $coincidencias[1];

    $stmt = $conexion->prepare("SELECT id, apellido, nombre, dni, rfid_uid FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $res = $stmt->get_result();
    $cliente = $res->fetch_assoc();

    if ($cliente) {
        echo json_encode([
            "id" => $cliente["id"],
            "info" => "{$cliente['apellido']} {$cliente['nombre']} - DNI: {$cliente['dni']} - RFID: {$cliente['rfid_uid']}"
        ]);
        exit;
    }
}

// Si no se encontró, devolver null
echo json_encode(["id" => null]);
?>
