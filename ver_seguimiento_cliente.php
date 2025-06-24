<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if (!$cliente_id) {
    die("Acceso denegado.");
}

$query = "SELECT * FROM fichas_seguimiento WHERE cliente_id = $cliente_id ORDER BY semana DESC";
$resultado = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Seguimiento</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        .ficha {
            background: #111;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .campo {
            margin-bottom: 10px;
        }
        .campo label {
            font-weight: bold;
        }
    </style>
</head>
<body>
<h2>Historial de Seguimiento Alimenticio</h2>
<?php while ($fila = $resultado->fetch_assoc()): ?>
    <div class="ficha">
        <div class="campo"><label>Semana:</label> <?php echo $fila['semana']; ?></div>
        <div class="campo"><label>Fecha de inicio:</label> <?php echo $fila['fecha_inicio']; ?></div>
        <div class="campo"><label>Peso inicio:</label> <?php echo $fila['peso_inicio']; ?> kg</div>
        <div class="campo"><label>Peso fin:</label> <?php echo $fila['peso_fin']; ?> kg</div>
        <div class="campo"><label>Satisfacción:</label> <?php echo $fila['satisfaccion']; ?></div>
        <div class="campo"><label>Adherencia:</label> <?php echo $fila['adherencia']; ?></div>
        <div class="campo"><label>Dificultades:</label> <?php echo nl2br($fila['dificultades']); ?></div>
        <div class="campo"><label>Comidas diarias:</label>
            D: <?php echo nl2br($fila['desayuno']); ?><br>
            A: <?php echo nl2br($fila['almuerzo']); ?><br>
            M: <?php echo nl2br($fila['merienda']); ?><br>
            C: <?php echo nl2br($fila['cena']); ?>
        </div>
        <div class="campo"><label>Plan semanal:</label>
            Lunes: <?php echo nl2br($fila['lunes']); ?><br>
            Martes: <?php echo nl2br($fila['martes']); ?><br>
            Miércoles: <?php echo nl2br($fila['miercoles']); ?><br>
            Jueves: <?php echo nl2br($fila['jueves']); ?><br>
            Viernes: <?php echo nl2br($fila['viernes']); ?><br>
            Sábado: <?php echo nl2br($fila['sabado']); ?><br>
            Domingo: <?php echo nl2br($fila['domingo']); ?>
        </div>
        <div class="campo"><label>Seguimiento diario:</label> <?php echo nl2br($fila['seguimiento']); ?></div>
        <div class="campo"><label>Registrado el:</label> <?php echo $fila['fecha_registro']; ?></div>
    </div>
<?php endwhile; ?>
</body>
</html>
