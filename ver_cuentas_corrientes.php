<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) {
    http_response_code(403);
    exit('Acceso denegado');
}

// Consulta: saldo = SUM(debe - haber). Mostramos solo saldos positivos (>0).
$sql = "
  SELECT 
    m.cliente_id,
    COALESCE(c.nombre, '')   AS nombre,
    COALESCE(c.apellido, '') AS apellido,
    SUM(m.debe - m.haber)    AS saldo
  FROM cc_movimientos m
  LEFT JOIN clientes c ON c.id = m.cliente_id
  WHERE m.gimnasio_id = ?
  GROUP BY m.gimnasio_id, m.cliente_id
  HAVING saldo > 0.009
  ORDER BY saldo DESC
";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    exit('Error preparando consulta: ' . $conexion->error);
}
$stmt->bind_param('i', $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$' . number_format((float)$n, 2, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuentas Corrientes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background-color:#000; color:gold; font-family:Arial, sans-serif; }
        .contenedor { max-width: 900px; margin:auto; padding:20px; }
        table { width:100%; background:#111; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid gold; padding:10px; text-align:center; }
        th { background:#222; }
        .btn { padding:6px 12px; font-weight:700; border:none; border-radius:5px; cursor:pointer; text-decoration:none; margin:2px; display:inline-block; }
        .btn-pago { background:green; color:#fff; }
        .btn-eliminar { background:red; color:#fff; }
        .muted { opacity:.7; }
        .num { text-align:right; white-space:nowrap; }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🧾 Clientes con Deuda (Cuenta Corriente)</h2>

    <table>
        <tr>
            <th>Cliente</th>
            <th class="num">Saldo</th>
            <th>Acción</th>
        </tr>
        <?php if (!$resultado || $resultado->num_rows === 0): ?>
            <tr><td colspan="3" class="muted">Sin deudas para este gimnasio.</td></tr>
        <?php else: ?>
            <?php while($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= h(trim($fila['apellido'].' '.$fila['nombre'])) ?></td>
                <td class="num"><?= money($fila['saldo']) ?></td>
                <td>
                    <a href="cc_detalle.php?cliente_id=<?= (int)$fila['cliente_id'] ?>#pago" class="btn btn-pago">Registrar Pago</a>
                    <a href="cc_detalle.php?cliente_id=<?= (int)$fila['cliente_id'] ?>" class="btn">Ver detalle</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
