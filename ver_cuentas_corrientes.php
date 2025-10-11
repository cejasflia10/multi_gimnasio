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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado igual al index -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ===== Maqueta alineada al index (claro, cards blancas) ===== */
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
    .page-card{
      background:var(--card); border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
    }
    .page-title{
      margin:0 0 12px 0; text-align:center; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* ===== Tabla “tipo index” ===== */
    .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ width:100%; min-width:720px; border-collapse:collapse; background:#fff; }
    .tabla thead th{
      position:sticky; top:0; z-index:1; background:#f7fafc; color:#0f172a;
      border-bottom:1px solid var(--stroke); text-align:center;
    }
    .tabla th, .tabla td{
      padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center; white-space:nowrap;
    }
    .tabla tbody tr:hover{ background:#f9fafb; }
    .num{ text-align:right; }

    /* Botones coherentes */
    .acciones{ white-space:nowrap; }
    .btn{
      background:linear-gradient(180deg,#fff,#f7fafc);
      border:1px solid var(--stroke); border-radius:10px;
      color:var(--ink); padding:8px 12px; font-weight:700; cursor:pointer;
      text-decoration:none; display:inline-block; margin:0 2px;
    }
    .btn:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }
    .btn-pago{ border-color:rgba(22,163,74,.35); }
    .btn-ghost{ border-color:rgba(180,83,9,.25); }
    .muted{ color:#64748b; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h2 class="page-title">🧾 Clientes con Deuda (Cuenta Corriente)</h2>

      <div class="table-wrap">
        <table class="tabla" aria-label="Cuentas corrientes con saldo">
          <thead>
            <tr>
              <th>Cliente</th>
              <th class="num">Saldo</th>
              <?php if ($comprobante_col): ?>
                <th>Comprobante</th>
              <?php endif; ?>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$resultado || $resultado->num_rows === 0): ?>
              <tr>
                <td colspan="<?= $comprobante_col ? 4 : 3 ?>" class="muted">Sin deudas para este gimnasio.</td>
              </tr>
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
                    <a href="cc_comprobante.php?cliente_id=<?= (int)$fila['cliente_id'] ?>" class="btn">PDF</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
