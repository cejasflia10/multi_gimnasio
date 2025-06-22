<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);

    $stmt = $conexion->prepare("SELECT c.id, c.nombre, c.apellido, m.clases_disponibles, m.fecha_vencimiento 
        FROM clientes c 
        INNER JOIN membresias m ON c.id = m.cliente_id 
        WHERE c.dni = ? 
        ORDER BY m.fecha_inicio DESC 
        LIMIT 1");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $hoy = date("Y-m-d");
        $vencimiento = $cliente['fecha_vencimiento'];
        $clases = (int)$cliente['clases_disponibles'];

        if ($vencimiento >= $hoy && $clases > 0) {
            $nuevas_clases = $clases - 1;
            $stmt2 = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE cliente_id = ?");
            $stmt2->bind_param("ii", $nuevas_clases, $cliente['id']);
            $stmt2->execute();

            $stmt3 = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, CURDATE(), CURTIME())");
            $stmt3->bind_param("i", $cliente['id']);
            $stmt3->execute();

            echo "<h3>Ingreso registrado correctamente</h3>";
            echo "<p>Cliente: <strong>{$cliente['apellido']} {$cliente['nombre']}</strong></p>";
            echo "<p>Clases restantes: <strong>$nuevas_clases</strong></p>";
            echo "<p>Vencimiento: <strong>$vencimiento</strong></p>";
        } else {
            echo "<h3>Plan vencido o sin clases disponibles</h3>";
        }
    } else {
        echo "<h3>Cliente no encontrado o sin membresía activa</h3>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro por QR</title>
  <style>
    body { background-color: black; color: gold; font-family: Arial, sans-serif; text-align: center; }
    input { font-size: 24px; padding: 10px; width: 80%; margin: 20px auto; background: #111; color: white; border: 2px solid gold; }
    button { padding: 10px 20px; font-size: 20px; background: gold; border: none; color: black; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Escaneo QR - Registro de Asistencia</h2>
  <form method="POST">
    <input type="text" name="dni" placeholder="Escanee QR o escriba DNI" autofocus required>
    <br>
    <button type="submit">Registrar</button>
  </form>
</body>
</html>
