<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
$mensaje = '';
$info = '';

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

        // Verificar si ya registró ingreso hoy
        $verificar = $conexion->prepare("SELECT id FROM asistencias WHERE cliente_id = ? AND fecha = CURDATE()");
        $verificar->bind_param("i", $cliente['id']);
        $verificar->execute();
        $verificado = $verificar->get_result();

        if ($verificado->num_rows > 0) {
            $mensaje = "⚠️ Ya se registró un ingreso hoy.";
        } elseif ($vencimiento >= $hoy && $clases > 0) {
            $nuevas_clases = $clases - 1;
            $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE cliente_id = ?")
                ->bind_param("ii", $nuevas_clases, $cliente['id'])->execute();

            $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, CURDATE(), CURTIME())")
                ->bind_param("i", $cliente['id'])->execute();

            $mensaje = "✅ Ingreso registrado correctamente";
            $info = "Cliente: <strong>{$cliente['apellido']} {$cliente['nombre']}</strong><br>
                     Clases restantes: <strong>$nuevas_clases</strong><br>
                     Vencimiento: <strong>$vencimiento</strong>";
        } else {
            $mensaje = "❌ Plan vencido o sin clases disponibles.";
        }
    } else {
        $mensaje = "❌ Cliente no encontrado o sin membresía activa.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro QR de Asistencia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

  <h2>📲 Escaneo QR - Registro de Asistencia</h2>
  <form method="POST">
    <input type="text" name="dni" placeholder="Escanee el QR o escriba DNI" autofocus required>
    <br>
    <button type="submit">Registrar</button>
  </form>

  <?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
    <div class="info"><?= $info ?></div>
  <?php endif; ?>

</body>
</html>
