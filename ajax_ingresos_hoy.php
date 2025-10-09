<?php
// ajax_alumnos_hoy.php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once 'conexion.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { echo ''; exit; }

$hoy = date('Y-m-d');

/* === Consulta === */
$sql = "
  SELECT c.apellido, c.nombre, a.hora 
  FROM asistencias a
  JOIN clientes c ON a.cliente_id = c.id
  WHERE a.fecha = ? AND c.gimnasio_id = ?
  ORDER BY a.hora DESC
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param('si', $hoy, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

/* === Presentación === */
?>
<div id="alumnos-hoy"
     style="display:flex;flex-direction:column;gap:12px;
            writing-mode:horizontal-tb;text-orientation:mixed;transform:none;
            white-space:normal;word-break:normal;overflow-wrap:break-word;
            max-width:100%;">

  <?php if ($res && $res->num_rows > 0): ?>
    <?php while($r = $res->fetch_assoc()): 
      $nom = htmlspecialchars(trim(($r['apellido'] ?? '') . ', ' . ($r['nombre'] ?? '')));
      $hora = htmlspecialchars($r['hora'] ?? '');
    ?>
      <div class="alumno-item"
           style="display:flex;align-items:center;justify-content:space-between;
                  padding:12px 16px;border:1px solid rgba(15,23,42,.08);
                  border-radius:14px;background:#fff;
                  box-shadow:0 8px 22px rgba(2,6,23,.08);
                  writing-mode:horizontal-tb;transform:none;max-width:100%;">
        <div style="display:flex;align-items:center;gap:10px;min-width:0;">
          <span aria-hidden="true" style="font-size:20px;line-height:1;">✅</span>
          <div style="font-size:16px;font-weight:600;color:#0f172a;white-space:normal;">
            <?= $nom ?>
          </div>
        </div>
        <div style="font-size:15px;color:#b45309;font-weight:700;white-space:nowrap;">
          ⏰ <?= $hora ?>
        </div>
      </div>
    <?php endwhile; ?>

  <?php else: ?>
    <div class="alumno-empty"
         style="display:flex;align-items:center;justify-content:center;
                padding:20px;border:1px dashed rgba(15,23,42,.15);
                border-radius:14px;background:#fff;min-height:72px;
                color:#64748b;font-weight:600;
                writing-mode:horizontal-tb;transform:none;">
      No se registraron ingresos de alumnos hoy.
    </div>
  <?php endif; ?>
</div>
