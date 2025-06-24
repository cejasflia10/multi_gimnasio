<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$dni = $_GET['dni'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$dni || !$gimnasio_id) {
    die("❌ Error: DNI o gimnasio no válidos.");
}

// Buscar cliente
$cliente = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id LIMIT 1")->fetch_assoc();

if (!$cliente) {
    echo "❌ Cliente no encontrado.";
    exit;
}

// Buscar membresía activa
$hoy = date('Y-m-d');
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE id_cliente = {$cliente['id']} 
    AND fecha_vencimiento >= '$hoy' 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1")->fetch_assoc();

if (!$membresia) {
    echo "<body style='background:#111;color:yellow;text-align:center;padding-top:40px;'>
    <h2>⚠️ Sin membresía activa o sin clases.</h2>
    <a href='scanner_qr.php' style='color:yellow;font-size:18px;'>⬅️ Escanear otro</a>
    </body>";
    exit;
}

// Descontar clase
$conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = {$membresia['id']}");

// Registrar asistencia
$conexion->query("INSERT INTO asistencias (id_cliente, fecha, hora, id_gimnasio) 
    VALUES ({$cliente['id']}, CURDATE(), CURTIME(), $gimnasio_id)");

// Mostrar datos
echo "<body style='background:#111;color:gold;font-family:Arial;text-align:center;padding:30px;'>
    <h2>✅ Asistencia registrada</h2>
    <p><strong>{$cliente['apellido']} {$cliente['nombre']}</strong></p>
    <p>DNI: {$cliente['dni']}</p>
    <p>Disciplina: {$cliente['disciplina']}</p>
    <p>Clases restantes: " . ($membresia['clases_disponibles'] - 1) . "</p>
    <p>Vencimiento: {$membresia['fecha_vencimiento']}</p>
    <br>
    <a href='scanner_qr.php' style='color:yellow;font-size:18px;'>⬅️ Escanear otro</a>
</body>";
?>
