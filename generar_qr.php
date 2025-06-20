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
    <title>Generar QR - Clientes</title>
    <style>
        body { background-color: #111; color: #FFD700; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #FFD700; padding: 8px; text-align: center; }
        th { background-color: #000; }
        .btn { padding: 6px 12px; background-color: #FFD700; color: #000; font-weight: bold; text-decoration: none; border-radius: 4px; }
        img { width: 60px; height: 60px; }
    </style>
</head>
<body>
    <h2>Generar QR para Clientes</h2>
    <table>
        <tr>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>QR</th>
            <th>Acción</th>
        </tr>
        <?php
        $sql = "SELECT id, apellido, nombre, dni FROM clientes WHERE gimnasio_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $gimnasio_id);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $dni = $row['dni'];
            $qr_file = "temp_qr/" . $dni . ".png";
            if (!file_exists($qr_file)) {
                QRcode::png($dni, $qr_file, QR_ECLEVEL_L, 4);
            }
            echo "<tr>";
            echo "<td>{$row['apellido']}, {$row['nombre']}</td>";
            echo "<td>{$row['dni']}</td>";
            echo "<td><img src='$qr_file'></td>";
            echo "<td><a class='btn' href='generar_qr.php?regenerar={$row['dni']}'>Regenerar</a></td>";
            echo "</tr>";
        }

        // Forzar regeneración de QR si se solicita por GET
        if (isset($_GET['regenerar'])) {
            $dni = $_GET['regenerar'];
            $qr_file = "temp_qr/" . $dni . ".png";
            QRcode::png($dni, $qr_file, QR_ECLEVEL_L, 4);
            echo "<script>alert('QR regenerado correctamente'); window.location.href='generar_qr.php';</script>";
        }
        ?>
    </table>
</body>
</html>
