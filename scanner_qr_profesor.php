<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);

    if ($dni !== '') {
        $dni = $conexion->real_escape_string($dni);

        // Buscar cliente
        $cliente = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE dni = '$dni'")->fetch_assoc();

        if ($cliente) {
            $cliente_id = $cliente['id'];
            $nombre = $cliente['nombre'];
            $apellido = $cliente['apellido'];

            // Verificar membresía activa
            $membresia = $conexion->query("
                SELECT id, clases_disponibles, fecha_vencimiento 
                FROM membresias 
                WHERE cliente_id = $cliente_id 
                  AND fecha_vencimiento >= CURDATE() 
                  AND clases_disponibles > 0
                ORDER BY fecha_vencimiento DESC LIMIT 1
            ")->fetch_assoc();

            if ($membresia) {
                $membresia_id = $membresia['id'];
                $clases = $membresia['clases_disponibles'] - 1;

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_disponibles = $clases WHERE id = $membresia_id");

                // Registrar asistencia
                $fecha = date('Y-m-d');
                $hora = date('H:i:s');
                $conexion->query("
                    INSERT INTO asistencias_clientes (cliente_id, fecha, hora, gimnasio_id) 
                    VALUES ($cliente_id, '$fecha', '$hora', $gimnasio_id)
                ");

                // Registrar relación con profesor
                $conexion->query("
                    INSERT INTO alumnos_turno_profesor (profesor_id, cliente_id, fecha, hora, gimnasio_id) 
                    VALUES ($profesor_id, $cliente_id, '$fecha', '$hora', $gimnasio_id)
                ");

                $mensaje = "✅ $apellido $nombre<br>Clases disponibles: $clases";
            } else {
                $mensaje = "⚠️ $apellido $nombre<br>Sin clases disponibles o membresía vencida.";
            }
        } else {
            $mensaje = "❌ Cliente no encontrado.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escanear Ingreso Cliente</title>
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <style>
    body { background-color: #000; color: gold; text-align: center; padding: 20px; font-family: Arial; }
    #reader { width: 100%; max-width: 400px; margin: auto; display: <?= $mensaje ? 'none' : 'block' ?>; }
    .mensaje { font-size: 18px; margin-top: 20px; }
  </style>
</head>
<body>

<h2>📲 Escanear QR del Alumno</h2>

<div id="reader"></div>

<?php if ($mensaje): ?>
  <div class="mensaje"><?= $mensaje ?></div>
  <script>
    setTimeout(() => {
      window.location.href = 'scanner_qr_profesor.php';
    }, 4000);
  </script>
<?php endif; ?>

<form method="POST" id="formulario">
  <input type="hidden" name="dni" id="dni">
</form>

<script>
  function onScanSuccess(decodedText) {
    document.getElementById("dni").value = decodedText;
    document.getElementById("formulario").submit();
    html5QrcodeScanner.clear();
  }

  let html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
  html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>
