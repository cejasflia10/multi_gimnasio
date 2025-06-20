<?php
include 'conexion.php';
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR - Registrar Asistencia</title>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 20px;
    }
    #reader {
      width: 300px;
      margin: auto;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
    }
  </style>
</head>
<body>
  <h2>Escaneo QR para Ingreso</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

  <script>
    function onScanSuccess(decodedText) {
      fetch('registrar_asistencia_qr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'dni_qr=' + encodeURIComponent(decodedText)
      })
      .then(response => response.text())
      .then(data => {
        document.getElementById("resultado").innerHTML = data;
      })
      .catch(error => {
        document.getElementById("resultado").innerText = "Error al enviar: " + error;
      });
    }

    function onScanFailure(error) {
      console.warn("Error escaneando: ", error);
    }

    const html5QrCode = new Html5Qrcode("reader");
    Html5Qrcode.getCameras().then(devices => {
      if (devices.length) {
        html5QrCode.start(
          { facingMode: "environment" },
          { fps: 10, qrbox: 250 },
          onScanSuccess,
          onScanFailure
        );
      } else {
        document.getElementById("resultado").innerText = "No se detectaron cámaras.";
      }
    });
  </script>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni_qr'])) {
    include 'conexion.php';
    $dni = trim($_POST['dni_qr']);
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

    $stmt = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $cliente_id = $cliente['id'];

        $membresia = $conexion->query("SELECT id, clases_restantes, fecha_vencimiento FROM membresias WHERE cliente_id = $cliente_id ORDER BY fecha_vencimiento DESC LIMIT 1")->fetch_assoc();

        if ($membresia) {
            $fecha_actual = date("Y-m-d");
            $vencimiento = $membresia['fecha_vencimiento'];
            $clases = $membresia['clases_restantes'];

            if ($vencimiento >= $fecha_actual && $clases > 0) {
                $nuevas_clases = $clases - 1;
                $conexion->query("UPDATE membresias SET clases_restantes = $nuevas_clases WHERE id = {$membresia['id']}");

                echo "<div>✅ <strong>{$cliente['nombre']} {$cliente['apellido']}</strong><br>
                      DNI: $dni<br>
                      Clases restantes: $nuevas_clases<br>
                      Válido hasta: $vencimiento</div>";
            } else {
                echo "<div style='color:red'>❌ Membresía vencida o sin clases.<br>Fecha de vencimiento: $vencimiento<br>Clases: $clases</div>";
            }
        } else {
            echo "<div style='color:red'>❌ No se encontró membresía activa.</div>";
        }
    } else {
        echo "<div style='color:red'>❌ Cliente no registrado.</div>";
    }
}
?>
