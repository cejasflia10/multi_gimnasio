
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
require_once "phpqrcode/qrlib.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $_POST["dni"];
    $cliente_id = $_POST["cliente_id"];

    if (!$dni || !$cliente_id) {
        die("Datos incompletos.");
    }

    $contenido = "ID:$cliente_id | DNI:$dni";

    // Crear carpeta si no existe
    $directorio = "qrs/";
    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }

    $archivo = $directorio . "cliente_" . $cliente_id . ".png";
    QRcode::png($contenido, $archivo, QR_ECLEVEL_L, 10);

    echo "<body style='background-color:#111; color:#FFD700; font-family:Arial; text-align:center; padding:20px'>";
    echo "<h1>QR generado correctamente</h1>";
    echo "<p>DNI: $dni</p>";
    echo "<p>ID Cliente: $cliente_id</p>";
    echo "<img src='$archivo' alt='QR del Cliente'><br><br>";
    echo "<a href='generar_qr.php' style='color:#FFD700'>← Volver</a>";
    echo "</body>";
} else {
    echo "Acceso inválido.";
}
?>
