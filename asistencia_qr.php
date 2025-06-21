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
    <meta charset="UTF-8">
    <title>Asistencia por QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        h2 {
            margin-bottom: 30px;
        }
        input[type="text"] {
            width: 90%;
            padding: 15px;
            font-size: 20px;
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
            background-color: #222;
            color: #ffd700;
        }
        .info {
            margin-top: 20px;
            font-size: 18px;
            color: #fff;
        }
    </style>
</head>
<body>

<h2>Registro de Asistencia por QR</h2>

<form action="registrar_asistencia_qr.php" method="POST">
    <input type="hidden" name="gimnasio_id" value="<?php echo htmlspecialchars($gimnasio_id); ?>">
    <input type="text" name="dni_o_rfid" placeholder="Escaneá el QR o escribí el DNI" autofocus required>
</form>

<div class="info">
    Esperando escaneo...
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
