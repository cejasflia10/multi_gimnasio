<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identificador = $_POST['identificador']; // puede ser DNI, RFID, QR o huella (ID único)
    $plan_id = $_POST['plan_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_restantes = $_POST['clases_restantes'];
    $monto_pagado = $_POST['monto_pagado'];
    $metodo_pago = $_POST['metodo_pago'];

    // Buscar cliente por DNI, RFID o código QR (huella también si se implementa con ID único)
    $buscar = $conexion->prepare("SELECT id FROM clientes WHERE (dni = ? OR rfid = ? OR email = ?) AND id_gimnasio = ?");
    $buscar->bind_param("sssi", $identificador, $identificador, $identificador, $id_gimnasio);
    $buscar->execute();
    $res = $buscar->get_result();

    if ($res->num_rows == 1) {
        $cliente = $res->fetch_assoc();
        $cliente_id = $cliente['id'];

        $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_restantes, monto_pagado, metodo_pago, id_gimnasio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissidsi", $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_restantes, $monto_pagado, $metodo_pago, $id_gimnasio);

        if ($stmt->execute()) {
            echo "Membresía registrada correctamente.";
        } else {
            echo "Error al registrar membresía: " . $stmt->error;
        }
    } else {
        echo "Cliente no encontrado con el dato ingresado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía (Multi-ingreso)</title>
</head>
<body>
    <h2>Agregar Membresía</h2>
    <form method="post">
        DNI, RFID, correo o código QR: <input type="text" name="identificador" required><br>
        ID del plan: <input type="number" name="plan_id" required><br>
        Fecha inicio: <input type="date" name="fecha_inicio" required><br>
        Fecha vencimiento: <input type="date" name="fecha_vencimiento" required><br>
        Clases restantes: <input type="number" name="clases_restantes" required><br>
        Monto pagado: <input type="number" step="0.01" name="monto_pagado" required><br>
        Método de pago: 
        <select name="metodo_pago" required>
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Tarjeta Crédito">Tarjeta Crédito</option>
            <option value="Tarjeta Débito">Tarjeta Débito</option>
            <option value="Cuenta Corriente">Cuenta Corriente</option>
        </select><br>
        <input type="submit" value="Registrar Membresía">
    </form>
</body>
</html>
