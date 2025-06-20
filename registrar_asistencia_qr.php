<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);

    if (empty($dni)) {
        echo "<script>alert('No se recibió DNI.'); window.location.href='escaneo_qr.php';</script>";
        exit;
    }

    $sql = "SELECT * FROM clientes WHERE dni = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        echo "<script>alert('Cliente no encontrado.'); window.location.href='escaneo_qr.php';</script>";
        exit;
    }

    $cliente = $resultado->fetch_assoc();
    $cliente_id = $cliente["id"];
    $nombre = $cliente["nombre"];
    $apellido = $cliente["apellido"];
    $gimnasio_id = $cliente["gimnasio_id"];

    $hoy = date("Y-m-d");
    $sqlM = "SELECT * FROM membresias WHERE cliente_id = ? AND vencimiento >= ? AND gimnasio_id = ? ORDER BY id DESC LIMIT 1";
    $stmtM = $conexion->prepare($sqlM);
    $stmtM->bind_param("isi", $cliente_id, $hoy, $gimnasio_id);
    $stmtM->execute();
    $resM = $stmtM->get_result();

    if ($resM->num_rows === 0) {
        echo "<script>alert('No tiene membresía vigente.'); window.location.href='escaneo_qr.php';</script>";
        exit;
    }

    $membresia = $resM->fetch_assoc();
    $clases_disponibles = $membresia["clases_disponibles"];
    $membresia_id = $membresia["id"];
    $vencimiento = $membresia["vencimiento"];

    if ($clases_disponibles < 1) {
        echo "<script>alert('No tiene clases disponibles.'); window.location.href='escaneo_qr.php';</script>";
        exit;
    }

    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $stmt = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $cliente_id, $fecha, $hora, $gimnasio_id);
    $stmt->execute();

    $nuevas_clases = $clases_disponibles - 1;
    $stmt = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE id = ?");
    $stmt->bind_param("ii", $nuevas_clases, $membresia_id);
    $stmt->execute();

    echo "<script>
        alert('Ingreso registrado: $apellido, $nombre. Clases restantes: $nuevas_clases. Vencimiento: $vencimiento');
        window.location.href='escaneo_qr.php';
    </script>";
    exit;
}
?>
