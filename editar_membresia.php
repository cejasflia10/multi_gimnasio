<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$id = (int)($_GET['id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($id <= 0 || $gimnasio_id <= 0) { http_response_code(400); die('Datos inválidos'); }

$sql = "
  SELECT m.*, c.apellido, c.nombre, c.dni, p.nombre AS plan_nombre
  FROM membresias m
  JOIN clientes c ON c.id = m.cliente_id
  JOIN planes   p ON p.id = m.plan_id
  WHERE m.id = {$id} AND m.gimnasio_id = {$gimnasio_id}
  LIMIT 1
";
$membresia = $conexion->query($sql)->fetch_assoc();
if (!$membresia) { die('Membresía no encontrada'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Membresía</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#000;color:gold;font-family:system-ui,Arial,sans-serif}
    .contenedor{max-width:700px;margin:0 auto;padding:16px}
    .bloque{background:#111;border:1px solid #444;border-radius:10px;padding:14px;margin-bottom:14px}
    label{display:block;margin:10px 0 6px}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #555;background:#0c0c0c;color:#ddd}
    .solo-lectura{opacity:.7}
    .ayuda{font-size:12px;color:#9aa}
    .acciones{display:flex;gap:10px;margin-top:14px}
    .btn{padding:10px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer}
    .btn-guardar{background:#16a34a;color:#fff}
    .btn-volver{background:#334155;color:#fff;text-decoration:none;display:inline-block}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>✏️ Editar Membresía</h2>

  <form action="guardar_edicion_membresia.php" method="POST">
    <input type="hidden" name="id" value="<?= (int)$membresia['id'] ?>">
    <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">

    <div class="bloque">
      <label>Cliente</label>
      <input class="solo-lectura" type="text" value="<?= htmlspecialchars($membresia['apellido'].', '.$membresia['nombre'].' ('.$membresia['dni'].')') ?>" readonly>

      <label>Plan</label>
      <input class="solo-lectura" type="text" value="<?= htmlspecialchars($membresia['plan_nombre']) ?>" readonly>

      <label>Fecha de inicio</label>
      <input class="solo-lectura" type="date" value="<?= htmlspecialchars($membresia['fecha_inicio']) ?>" readonly>

      <label>Precio total</label>
      <input class="solo-lectura" type="text" value="$<?= number_format((float)$membresia['total'],2,',','.') ?>" readonly>

      <div class="ayuda">Estos campos son informativos y no pueden editarse aquí.</div>
    </div>

    <div class="bloque">
      <label>Clases disponibles (editable)</label>
      <input type="number" name="clases_disponibles" min="0"
             value="<?= (int)($membresia['clases_disponibles'] ?? $membresia['clases_restantes'] ?? 0) ?>" required>

      <label>Fecha de vencimiento (editable)</label>
      <input type="date" name="fecha_vencimiento" value="<?= htmlspecialchars($membresia['fecha_vencimiento']) ?>" required>
      <div class="ayuda">Solo podés modificar estos dos campos.</div>
    </div>

    <div class="acciones">
      <button type="submit" class="btn btn-guardar">💾 Guardar cambios</button>
      <a class="btn btn-volver" href="ver_membresias.php">⬅ Volver</a>
    </div>
  </form>
</div>
</body>
</html>
