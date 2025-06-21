<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
include "phpqrcode/qrlib.php";

if (isset($_POST['dni']) && !empty($_POST['dni'])) {
    $dni = $_POST['dni'];
    $filename = "qr_" . $dni . ".png";
    QRcode::png($dni, $filename);
    echo "<h1 style='color:gold;'>QR generado correctamente</h1>";
    echo "<img src='{$filename}' />";
} else {
    echo "No se recibió el DNI";
}
?>
