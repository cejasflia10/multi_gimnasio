<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($profesor_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escáner Profesor</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background-color: black;
            color: gold;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        #reader {
            width: 90%;
            margin: auto;
        }
        .info {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h2>📷 Escáner QR - Panel Profesor</h2>
    <div id="reader"></div>
    <div id="result" class="info"></div>

    <script>
        function iniciarScanner() {
            const scanner = new Html5Qrcode("reader");
            scanner.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: 250
                },
                (dni) => {
                    scanner.stop();
                    fetch("scanner_qr_profesor.php?dni=" + dni)
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById("result").innerHTML = data;
                            setTimeout(() => {
                                document.getElementById("result").innerHTML = '';
                                iniciarScanner();
                            }, 3000);
                        });
                },
                (error) => {
                    // silencioso
                }
            );
        }

        iniciarScanner();
    </script>

<?php
if (isset($_GET['dni'])) {
    $dni = $_GET['dni'];

    // Buscar cliente
    $sql = "SELECT id, nombre, apellido FROM clientes WHERE dni = ? AND gimnasio_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($cliente = $res->fetch_assoc()) {
        $cliente_id = $cliente['id'];

        // Buscar membresía activa
        $sqlM = "SELECT id, clases_disponibles, fecha_vencimiento FROM membresias 
                 WHERE cliente_id = ? AND gimnasio_id = ? 
                 AND fecha_vencimiento >= CURDATE() 
                 AND clases_disponibles > 0 ORDER BY fecha_vencimiento LIMIT 1";
        $stmtM = $conexion->prepare($sqlM);
        $stmtM->bind_param("ii", $cliente_id, $gimnasio_id);
        $stmtM->execute();
        $membresia = $stmtM->get_result()->fetch_assoc();

        if ($membresia) {
            // Descontar clase
            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = {$membresia['id']}");

            // Registrar asistencia
            $conexion->query("INSERT INTO asistencias_clientes (cliente_id, gimnasio_id, fecha, hora) 
                              VALUES ($cliente_id, $gimnasio_id, CURDATE(), CURTIME())");

            // Registrar relación con profesor
            $conexion->query("INSERT INTO alumnos_profesor (cliente_id, profesor_id, gimnasio_id, fecha_hora) 
                              VALUES ($cliente_id, $profesor_id, $gimnasio_id, NOW())");

            echo "<div class='info'>
                    ✅ {$cliente['apellido']}, {$cliente['nombre']}<br>
                    📅 Vence: " . date('d/m/Y', strtotime($membresia['fecha_vencimiento'])) . "<br>
                    🎟️ Clases restantes: " . ($membresia['clases_disponibles'] - 1) . "
                  </div>";
        } else {
            echo "<div class='info'>❌ No tiene membresía activa o sin clases disponibles.</div>";
        }
    } else {
        echo "<div class='info'>❌ Cliente no encontrado.</div>";
    }
}
?>
</body>
</html>
