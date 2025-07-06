<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php'; // O usar 'menu_cliente.php' si corresponde

date_default_timezone_set('America/Argentina/Buenos_Aires');

$cliente_id = $_SESSION['cliente_id'] ?? null;
if (!$cliente_id) {
    header("Location: login_cliente.php");
    exit;
}

// Obtener reservas activas
$reservas_q = $conexion->query("
    SELECT r.id, r.fecha, t.dia, h.hora_inicio, h.hora_fin, p.apellido AS profesor
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN horarios h ON t.id_horario = h.id
    JOIN profesores p ON t.id_profesor = p.id
    WHERE r.cliente_id = $cliente_id
    ORDER BY r.fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Reservas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2 style="text-align: center;">🎟️ Mis Reservas</h2>

    <?php if ($reservas_q->num_rows === 0): ?>
        <p style="text-align: center; color: orange;">No tenés reservas activas.</p>
    <?php else: ?>
        <?php while ($r = $reservas_q->fetch_assoc()): ?>
            <div class="box" style="background:#111; padding:15px; border:1px solid gold; margin-bottom:10px; border-radius:8px;">
                <strong>📅 Fecha:</strong> <?= $r['fecha'] ?><br>
                <strong>📌 Día:</strong> <?= $r['dia'] ?><br>
                <strong>🕐 Horario:</strong> <?= $r['hora_inicio'] ?> - <?= $r['hora_fin'] ?><br>
                <strong>👨‍🏫 Profesor:</strong> <?= $r['profesor'] ?><br>
                <?php if ($r['fecha'] >= date('Y-m-d')): ?>
                    <a class="boton boton-rojo" href="cancelar_reserva.php?id=<?= $r['id'] ?>">Cancelar</a>
                <?php else: ?>
                    <span style="color: gray;">Reserva pasada</span>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <div style="text-align:center; margin-top: 20px;">
        <a href="panel_cliente.php" class="boton">← Volver al Panel</a>
    </div>
</div>
</body>
</html>
