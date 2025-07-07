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
    <title>Escáner QR - Panel Profesor</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background: black; color: gold; font-family: Arial; text-align: center; }
        #reader { width: 90%; margin: auto; }
        .info { margin-top: 20px; }
    </style>
</head>
<body>
    <h2>📷 Escáner QR - Panel Profesor</h2>
    <div id="reader"></div>
    <div id="result" class="info"></div>

    <script>
        function startScanner() {
            const scanner = new Html5Qrcode("reader");
            scanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                (dni) => {
                    scanner.stop();
                    fetch("scanner_qr_profesor.php?dni=" + dni)
                        .then(r => r.text())
                        .then(data => {
                            document.getElementById("result").innerHTML = data;
                            setTimeout(() => {
                                document.getElementById("result").innerHTML = '';
                                startScanner();
                            }, 3000);
                        });
                },
                (err) => {}
            );
        }

        startScanner();
    </script>

<?php
if (isset($_GET['dni'])) {
    $dni = $_GET['dni'];

    $stmt = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();

    if ($cliente) {
        $cliente_id = $cliente['id'];

        $stmt = $conexion->prepare("SELECT id, clases_disponibles, fecha_vencimiento 
                                    FROM membresias 
                                    WHERE cliente_id = ? AND gimnasio_id = ? 
                                    AND fecha_vencimiento >= CURDATE() 
                                    AND clases_disponibles > 0 
                                    ORDER BY fecha_vencimiento LIMIT 1");
        $stmt->bind_param("ii", $cliente_id, $gimnasio_id);
        $stmt->execute();
        $membresia = $stmt->get_result()->fetch_assoc();

        if ($membresia) {
            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = {$membresia['id']}");
            $conexion->query("INSERT INTO asistencias_clientes (cliente_id, gimnasio_id, fecha, hora) VALUES ($cliente_id, $gimnasio_id, CURDATE(), CURTIME())");
            $conexion->query("INSERT INTO alumnos_profesor (cliente_id, profesor_id, gimnasio_id, fecha_hora) VALUES ($cliente_id, $profesor_id, $gimnasio_id, NOW())");

            echo "<div class='info'>
                    ✅ {$cliente['apellido']}, {$cliente['nombre']}<br>
                    🎟️ Clases restantes: " . ($membresia['clases_disponibles'] - 1) . "<br>
                    📅 Vence: " . date('d/m/Y', strtotime($membresia['fecha_vencimiento'])) . "
                  </div>";
        } else {
            echo "<div class='info'>❌ Sin membresía activa o sin clases.</div>";
        }
    } else {
        echo "<div class='info'>❌ Cliente no encontrado.</div>";
    }
}
?>
</body>
</html>
