<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? $_GET['gimnasio_id'] ?? null;

if (!$gimnasio_id) {
    die("Gimnasio no especificado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Asistencia por QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body>
<div class="contenedor">

<h2>Registro de Asistencia por QR</h2>

<form action="registrar_asistencia_qr.php" method="POST">
    <input type="hidden" name="gimnasio_id" value="<?php echo htmlspecialchars($gimnasio_id); ?>">
    <input type="text" name="dni_o_rfid" placeholder="Escaneá el QR o escribí el DNI" autofocus required>
</form>

<div class="info">
    Esperando escaneo...
</div>
</div>

<script>
    // Autoenviar al ingresar
    const input = document.querySelector('input[name="dni_o_rfid"]');
    input.addEventListener("input", function () {
        if (this.value.length >= 6) {
            this.form.submit();
        }
    });
</script>

</body>
</html>
