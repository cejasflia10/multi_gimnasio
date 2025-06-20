<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
include 'phpqrcode/qrlib.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR para Clientes</title>
    <style>
        body { background-color: #000; color: #FFD700; font-family: Arial, sans-serif; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #FFD700; padding: 10px; text-align: center; }
        th { background-color: #111; }
        .btn { padding: 5px 10px; background-color: #FFD700; color: #000; text-decoration: none; border-radius: 5px; }
        img { width: 60px; }
    </style>
</head>
<body>
    <h2>Generar QR para Clientes</h2>
    <table>
        <tr>
            <th>Nombre</th>
            <th>DNI</th>
            <th>QR</th>
            <th>Acción</th>
        </tr>
        <?php
        $sql = "SELECT id, nombre, apellido, dni FROM clientes WHERE gimnasio_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $gimnasio_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $qr_value = $row['dni'];
            $qr_file = "temp_qr/" . $qr_value . ".png";
            if (!file_exists($qr_file)) {
                QRcode::png($qr_value, $qr_file, QR_ECLEVEL_L, 4);
            }
            echo "<tr>";
            echo "<td>{$row['apellido']}, {$row['nombre']}</td>";
            echo "<td>{$row['dni']}</td>";
            echo "<td><img src='$qr_file'></td>";
            echo "<td><a class='btn' href='generar_qr.php?force={$row['dni']}'>Regenerar</a></td>";
            echo "</tr>";
        }

        // Forzar regeneración si se pasa ?force=dni
        if (isset($_GET['force'])) {
            $dni = $_GET['force'];
            $file = "temp_qr/" . $dni . ".png";
            QRcode::png($dni, $file, QR_ECLEVEL_L, 4);
            echo "<script>alert('QR regenerado para DNI $dni'); window.location.href='generar_qr.php';</script>";
        }
        ?>
    </table>
</body>
</html>
