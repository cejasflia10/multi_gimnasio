<?php
// ajax_reservas.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once 'conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { echo ''; exit; }

/* Fecha segura (YYYY-MM-DD). Si no matchea, uso hoy */
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) {
  $fecha_filtro = date('Y-m-d');
}

/* Traer reservas del día */
$sql = "
  SELECT rc.dia_semana, rc.hora_inicio, rc.fecha_reserva,
         c.nombre, c.apellido,
         CONCAT(p.apellido, ' ', p.nombre) AS profesor
  FROM reservas_clientes rc
  JOIN clientes c   ON rc.cliente_id  = c.id
  JOIN profesores p ON rc.profesor_id = p.id
  WHERE rc.fecha_reserva = ?
    AND rc.gimnasio_id   = ?
  ORDER BY rc.hora_inicio
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('si', $fecha_filtro, $gimnasio_id);
$stmt->execute();
$reservas = $stmt->get_result();

/* ====== UI “a prueba de verticalidad” (todo inline) ====== */
?>
<div id="reservas-list"
     style="display:flex;flex-direction:column;gap:12px;
            writing-mode:horizontal-tb;text-orientation:mixed;transform:none;
            white-space:normal;word-break:normal;overflow-wrap:break-word;
            max-width:100%;">

  <?php if ($reservas && $reservas->num_rows): ?>
    <?php while ($r = $reservas->fetch_assoc()): 
      $dia   = htmlspecialchars($r['dia_semana'] ?? '');
      $hora  = htmlspecialchars($r['hora_inicio'] ?? '');
      $cli   = htmlspecialchars(trim(($r['apellido'] ?? '') . ' ' . ($r['nombre'] ?? '')));
      $profe = htmlspecialchars($r['profesor'] ?? '');
    ?>
      <div class="res-item"
           style="display:flex;align-items:flex-start;gap:12px;
                  padding:12px 14px;border:1px solid rgba(15,23,42,.08);
                  border-radius:14px;background:#fff;
                  box-shadow:0 8px 22px rgba(2,6,23,.08);
                  writing-mode:horizontal-tb;transform:none;max-width:100%;">
        <div aria-hidden="true" style="font-size:22px;line-height:1.1;">📅</div>
        <div style="display:flex;flex-direction:column;gap:6px;min-width:0;">
          <div style="font-weight:800;color:#b45309;font-size:16px;">
            <?= $dia ?> · <span aria-hidden="true">🕒</span> <?= $hora ?>
          </div>
          <div style="font-size:16px;color:#0f172a;">
            <span aria-hidden="true">👤</span>
            <?= $cli ?>
          </div>
          <div style="font-size:15px;color:#334155;">
            <span aria-hidden="true">👨‍🏫</span>
            <?= $profe ?>
          </div>
        </div>
      </div>
    <?php endwhile; ?>

  <?php else: ?>
    <div class="res-empty"
         style="display:flex;align-items:center;justify-content:center;
                padding:20px;border:1px dashed rgba(15,23,42,.15);
                border-radius:14px;background:#fff;min-height:72px;
                color:#64748b;font-weight:600;
                writing-mode:horizontal-tb;transform:none;">
      No hay reservas registradas para este día.
    </div>
  <?php endif; ?>
</div>
