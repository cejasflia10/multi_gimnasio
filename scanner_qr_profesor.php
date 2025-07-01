<?php
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 1 si usás HTTPS
session_start();
echo "<pre>SESION ACTIVA:\n";
print_r($_SESSION);
echo "</pre>";

if (!isset($_SESSION['profesor_id'])) {
    echo "Acceso denegado.";
    exit;
}

$profesor_id = $_SESSION['profesor_id'];

// Obtener asistencias del profesor de hoy
$fecha_hoy = date('Y-m-d');
$asistencias_hoy = $conexion->query("
    SELECT c.nombre, c.apellido, ac.hora
    FROM asistencias_clientes ac
    JOIN clientes c ON ac.cliente_id = c.id
    WHERE ac.fecha = '$fecha_hoy' AND ac.profesor_id = $profesor_id
    ORDER BY ac.hora ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Escaneo QR para Ingreso</title>
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <style>
    body {
      background-color: black;
      color: gold;
      font-family: Arial, sans-serif;
      text-align: center;
      padding-top: 20px;
    }
    #reader {
      width: 300px;
      margin: auto;
      border: 2px solid gold;
    }
    #resultado {
      margin-top: 20px;
      font-size: 18px;
    }
    table {
      margin: auto;
      background: #111;
      color: gold;
      border: 1px solid gold;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      padding: 5px;
      border: 1px solid gold;
    }
  </style>
</head>
<body>

  <h2>📷 Escaneo QR para Ingreso</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

  <h3 style="margin-top: 30px;">✅ Alumnos Ingresados Hoy</h3>

  <?php if ($asistencias_hoy->num_rows > 0): ?>
      <table>
          <thead>
              <tr>
                  <th>Alumno</th>
                  <th>Hora</th>
              </tr>
          </thead>
          <tbody>
              <?php while ($fila = $asistencias_hoy->fetch_assoc()): ?>
                  <tr>
                      <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                      <td><?= $fila['hora'] ?></td>
                  </tr>
              <?php endwhile; ?>
          </tbody>
      </table>
  <?php else: ?>
      <p style="color:gray;">Aún no se registraron ingresos.</p>
  <?php endif; ?>

  <script>
    const scanner = new Html5Qrcode("reader");

    function iniciarEscaneo() {
      scanner.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 250, height: 250 }
        },
        (decodedText, decodedResult) => {
          scanner.stop().then(() => {
            // Enviar DNI al backend
            fetch("registrar_asistencia_qr.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded"
              },
              body: "dni=" + encodeURIComponent(decodedText)
            })
            .then(response => response.text())
            .then(data => {
              document.getElementById("resultado").innerHTML = data;

              // Recargar para actualizar listado
              setTimeout(() => {
                window.location.reload();
              }, 4000);
            })
            .catch(error => {
              document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al registrar asistencia.</span>";
              setTimeout(() => {
                window.location.reload();
              }, 4000);
            });
          });
        },
        errorMessage => {
          // Errores de lectura ignorados
        }
      ).catch(err => {
        document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al acceder a la cámara</span>";
      });
    }

    window.onload = iniciarEscaneo;
  </script>

</body>
</html>
