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

/* ===== Charset seguro ===== */
if (isset($conexion) && $conexion instanceof mysqli) {
    @$conexion->set_charset('utf8mb4');
}

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$' . number_format((float)$n, 2, ',', '.'); }

/** Devuelve true si la tabla tiene la columna */
function db_has_col(mysqli $db, string $table, string $col): bool {
    $table = $db->real_escape_string($table);
    $col   = $db->real_escape_string($col);
    $q = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return ($q && $q->num_rows > 0);
}

/* ===== Detectar columna de comprobante (si existe) ===== */
$comprobante_col = null;
foreach (['comprobante_url','comprobante','archivo_comprobante','comprobante_path'] as $cand) {
    if (db_has_col($conexion, 'cc_movimientos', $cand)) { $comprobante_col = $cand; break; }
}

/* ===== Detectar columna de fecha para ordenar comprobantes ===== */
$fecha_col = 'id'; // fallback por si no hay fecha
foreach (['fecha','created_at','fecha_mov','fechahora'] as $fc) {
    if (db_has_col($conexion, 'cc_movimientos', $fc)) { $fecha_col = $fc; break; }
}

/* ===== Ampliar group_concat para no truncar urls/rutas largas ===== */
if ($comprobante_col) {
    // Ignorar error si el hosting no permite SET SESSION
    @$conexion->query("SET SESSION group_concat_max_len = 1048576");
}

/* ===== Consulta base de saldos =====
   saldo = SUM(debe - haber). Mostramos solo saldos positivos (>0).
*/
$sql_saldos = "
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

/* ===== Si hay columna de comprobante, subconsulta con ÚLTIMO comprobante por cliente ===== */
if ($comprobante_col) {
    $col  = "`$comprobante_col`";
    $fcol = "`$fecha_col`";
    $sql_saldos = "
      SELECT
        s.cliente_id,
        s.nombre,
        s.apellido,
        s.saldo,
        COALESCE(comp.ultimo_comprobante, '') AS ultimo_comprobante
      FROM (
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
      ) AS s
      LEFT JOIN (
        SELECT
          cliente_id,
          /* Último según fecha detectada y luego id */
          SUBSTRING_INDEX(
            GROUP_CONCAT($col ORDER BY $fcol DESC, id DESC SEPARATOR '||'),
            '||', 1
          ) AS ultimo_comprobante
        FROM cc_movimientos
        WHERE gimnasio_id = ?
          AND $col IS NOT NULL AND $col <> ''
        GROUP BY cliente_id
      ) AS comp
        ON comp.cliente_id = s.cliente_id
      ORDER BY s.saldo DESC
    ";
}

/* ===== Ejecutar ===== */
$stmt = $conexion->prepare($sql_saldos);
if (!$stmt) {
    exit('Error preparando consulta: ' . $conexion->error);
}
if ($comprobante_col) {
    // 2 placeholders (gimnasio_id para saldos y para comp)
    $stmt->bind_param('ii', $gimnasio_id, $gimnasio_id);
} else {
    $stmt->bind_param('i', $gimnasio_id);
}
$stmt->execute();
$resultado = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuentas Corrientes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background-color:#000; color:gold; font-family:Arial, sans-serif; }
        .contenedor { max-width: 1000px; margin:auto; padding:20px; }
        table { width:100%; background:#111; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid gold; padding:10px; text-align:center; }
        th { background:#222; }
        .btn { padding:6px 12px; font-weight:700; border:none; border-radius:5px; cursor:pointer; text-decoration:none; margin:2px; display:inline-block; }
        .btn-pago { background:green; color:#fff; }
        .btn-eliminar { background:red; color:#fff; }
        .btn-ghost { background:#333; color:gold; }
        .muted { opacity:.7; }
        .num { text-align:right; white-space:nowrap; }
        .acciones { white-space:nowrap; }
        a.btn[href^="http"] { text-decoration:none; }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🧾 Clientes con Deuda (Cuenta Corriente)</h2>

    <table>
        <tr>
            <th>Cliente</th>
            <th class="num">Saldo</th>
            <?php if ($comprobante_col): ?>
                <th>Comprobante</th>
            <?php endif; ?>
            <th>Acción</th>
        </tr>
        <?php if (!$resultado || $resultado->num_rows === 0): ?>
            <tr><td colspan="<?= $comprobante_col ? 4 : 3 ?>" class="muted">Sin deudas para este gimnasio.</td></tr>
        <?php else: ?>
            <?php while($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= h(trim(($fila['apellido'] ?? '').' '.($fila['nombre'] ?? ''))) ?></td>
                <td class="num"><?= money($fila['saldo'] ?? 0) ?></td>

                <?php if ($comprobante_col): ?>
                  <td>
                    <?php
                      $url = trim((string)($fila['ultimo_comprobante'] ?? ''));
                      if ($url !== '') {
                          // Si es absoluta (http/https) la abrimos directo; si es relativa, también.
                          $is_abs = (bool)preg_match('~^https?://~i', $url);
                          $href = $is_abs ? $url : $url;
                          echo '<a class="btn btn-ghost" href="'.h($href).'" target="_blank" rel="noopener">Ver comprobante</a>';
                      } else {
                          echo '<span class="muted">—</span>';
                      }
                    ?>
                  </td>
                <?php endif; ?>

                <td class="acciones">
                    <a href="cc_detalle.php?cliente_id=<?= (int)$fila['cliente_id'] ?>#pago" class="btn btn-pago">Registrar Pago</a>
                    <a href="cc_detalle.php?cliente_id=<?= (int)$fila['cliente_id'] ?>" class="btn">Ver detalle</a>
                    <!-- Recibo/estado de cuenta en PDF (opcional) -->
                    <a href="cc_comprobante.php?cliente_id=<?= (int)$fila['cliente_id'] ?>" class="btn">PDF</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
