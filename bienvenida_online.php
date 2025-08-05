<?php
// bienvenida_online.php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = intval($_GET['cliente_id'] ?? 0);
if ($cliente_id <= 0) die("Cliente inválido.");

// Obtener datos del cliente y gimnasio_id
$stmt = $conexion->prepare("SELECT c.apellido, c.nombre, c.gimnasio_id, g.nombre AS gimnasio_nombre, g.logo FROM clientes c LEFT JOIN gimnasios g ON g.id = c.gimnasio_id WHERE c.id = ? LIMIT 1");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$cliente = $res->fetch_assoc()) {
    die("Cliente no encontrado.");
}
$stmt->close();

$gimnasio_id = intval($cliente['gimnasio_id']);

// Obtener enlace de links_gimnasio (tabla que creamos)
$enlace = '';
$rs = $conexion->query("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id LIMIT 1");
if ($rs && $row = $rs->fetch_assoc()) {
    $enlace = $row['enlace_whatsapp'] ?? '';
}

// Opcional: marcar "nuevo_online" en 0 si preferís que al ver la bienvenida ya no aparezca en el index
// $conexion->query("UPDATE clientes SET nuevo_online = 0 WHERE id = $cliente_id");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bienvenido <?= htmlspecialchars($cliente['nombre']) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background:#000; color:gold; font-family:Arial, sans-serif; padding:20px; }
        .contenedor { max-width:600px; margin:auto; text-align:center; }
        .btn-wpp { display:inline-block; background:#25D366; color:#fff; padding:12px 18px; border-radius:8px; text-decoration:none; font-weight:bold; margin-top:18px; }
    </style>
</head>
<body>
    <div class="contenedor">
        <?php if (!empty($cliente['logo'])): ?>
            <img src="<?= htmlspecialchars($cliente['logo']) ?>" alt="Logo" style="max-width:140px; display:block; margin:0 auto 10px;">
        <?php endif; ?>

        <h1>¡Bienvenido/a, <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?>!</h1>
        <p>Gracias por registrarte en <strong><?= htmlspecialchars($cliente['gimnasio_nombre'] ?? 'nuestro gimnasio') ?></strong>.</p>

        <?php if ($enlace): ?>
            <p>Únete a nuestro grupo de WhatsApp para recibir noticias y promociones:</p>
            <a class="btn-wpp" href="<?= htmlspecialchars($enlace) ?>" target="_blank" rel="noopener">📲 Unirme al grupo de WhatsApp</a>
        <?php else: ?>
            <p style="color:lightgray;">El gimnasio aún no configuró el enlace de WhatsApp. Pronto te lo enviaremos.</p>
        <?php endif; ?>

        <p style="margin-top:18px;"><a href="index.php" style="color:gold;">Volver al inicio</a></p>
    </div>
</body>
</html>
