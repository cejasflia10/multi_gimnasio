<?php
include 'conexion.php';
// Verificar sesión válida
if (!isset($_SESSION['profesor_id'])) {
    header("Location: login_profesor.php");
    exit;
}
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
  </style>
</head>
<body>

  <h2>📷 Escaneo QR para Ingreso</h2>
  <div id="reader"></div>
  <div id="resultado"></div>

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

              // Reiniciar escaneo después de 4 segundos
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
              }, 4000);
            })
            .catch(error => {
              document.getElementById("resultado").innerHTML = "<span style='color:red;'>❌ Error al registrar asistencia.</span>";
              setTimeout(() => {
                document.getElementById("resultado").innerHTML = "";
                iniciarEscaneo();
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

    // Iniciar al cargar
    window.onload = iniciarEscaneo;
  </script>
<?php
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

<h3 style="margin-top: 30px;">✅ Alumnos Ingresados Hoy</h3>

<?php if ($asistencias_hoy->num_rows > 0): ?>
    <table style="margin:auto; background:#111; color:gold; border:1px solid gold; border-collapse:collapse; margin-top:10px;">
        <thead>
            <tr>
                <th style="padding:5px; border:1px solid gold;">Alumno</th>
                <th style="padding:5px; border:1px solid gold;">Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $asistencias_hoy->fetch_assoc()): ?>
                <tr>
                    <td style="padding:5px; border:1px solid gold;">
                        <?= $fila['apellido'] . ', ' . $fila['nombre'] ?>
                    </td>
                    <td style="padding:5px; border:1px solid gold;">
                        <?= $fila['hora'] ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p style="color:gray;">Aún no se registraron ingresos.</p>
<?php endif; ?>

</body>
</html>
