<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

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
<html>
<head>
    <meta charset="UTF-8">
    <title>Mis Reservas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        .reserva { background: #111; border: 1px solid gold; border-radius: 6px; padding: 10px; margin: 10px 0; }
        a.cancelar { background: red; color: #fff; padding: 5px 10px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 5px; }
    </style>
</head>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>

<h2 style="text-align: center;">🎟️ Mis Reservas</h2>

<?php if ($reservas_q->num_rows === 0): ?>
    <p style="text-align: center; color: orange;">No tenés reservas activas.</p>
<?php else: ?>
    <?php while ($r = $reservas_q->fetch_assoc()): ?>
        <div class="reserva">
            <strong>Fecha:</strong> <?= $r['fecha'] ?><br>
            <strong>Día:</strong> <?= $r['dia'] ?><br>
            <strong>Horario:</strong> <?= $r['hora_inicio'] ?> - <?= $r['hora_fin'] ?><br>
            <strong>Profesor:</strong> <?= $r['profesor'] ?><br>
            <?php if ($r['fecha'] >= date('Y-m-d')): ?>
                <a class="cancelar" href="cancelar_reserva.php?id=<?= $r['id'] ?>">Cancelar</a>
            <?php else: ?>
                <span style="color: gray;">Reserva pasada</span>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php endif; ?>

<a href="panel_cliente.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: gold; color: black; text-decoration: none; border-radius: 5px;">Volver al Panel</a>

</body>
</html>
