<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['profesor_id']) || !isset($_SESSION['gimnasio_id'])) {
    echo "<div style='color: red; font-size: 20px;'>Acceso denegado.</div>";
    exit;
}

$profesor_id = $_SESSION['profesor_id'];
$gimnasio_id = $_SESSION['gimnasio_id'];
$mensaje = "";
$dni = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $dni = $conexion->real_escape_string($dni);

    $cliente = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id")->fetch_assoc();

    if ($cliente) {
        $cliente_id = $cliente['id'];

        // Registrar en alumnos_profesor con fecha_hora
        $conexion->query("INSERT INTO alumnos_profesor (cliente_id, profesor_id, gimnasio_id, fecha_hora)
                          VALUES ($cliente_id, $profesor_id, $gimnasio_id, NOW())");

        // Buscar membresía activa
        $membresia = $conexion->query("SELECT * FROM membresias 
                                       WHERE cliente_id = $cliente_id 
                                       AND fecha_vencimiento >= CURDATE() 
                                       AND clases_disponibles > 0 
                                       ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();

        if ($membresia) {
            // Descontar una clase
            $conexion->query("UPDATE membresias 
                              SET clases_disponibles = clases_disponibles - 1 
                              WHERE id = {$membresia['id']}");

            $mensaje = "✔️ {$cliente['apellido']}, {$cliente['nombre']}<br>
                        🧾 Clases restantes: " . ($membresia['clases_disponibles'] - 1) . "<br>
                        📅 Vencimiento: " . date('d/m/Y', strtotime($membresia['fecha_vencimiento'])) . "<br>
                        🧑‍🏫 Ingreso registrado correctamente.";
        } else {
            $mensaje = "❌ El cliente no tiene una membresía activa.";
        }
    } else {
        $mensaje = "❌ Cliente no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escáner QR - Panel Profesor</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background: #000; color: gold; text-align: center; font-family: Arial, sans-serif; padding: 20px; }
        .scanner { width: 100%; max-width: 400px; margin: auto; }
        .mensaje { margin-top: 20px; font-size: 18px; color: white; }
        button { margin-top: 15px; padding: 10px 20px; font-size: 16px; background: gold; border: none; cursor: pointer; }
    </style>
</head>
<body>

<h2>📷 Escáner QR - Panel Profesor</h2>

<div class="scanner">
    <div id="reader" style="width: 100%;"></div>
</div>

<div class="mensaje"><?= $mensaje ?></div>

<?php if ($mensaje): ?>
<script>
    setTimeout(() => { window.location.href = 'scanner_qr_profesor.php'; }, 4000);
</script>
<?php endif; ?>

<form method="POST" id="formulario" style="display:none;">
    <input type="hidden" name="dni" id="dni">
</form>

<script>
function onScanSuccess(decodedText) {
    document.getElementById("dni").value = decodedText;
    document.getElementById("formulario").submit();
}

const html5QrCode = new Html5Qrcode("reader");
Html5Qrcode.getCameras().then(devices => {
    if (devices && devices.length) {
        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            onScanSuccess
        );
    }
});
</script>

</body>
</html>
