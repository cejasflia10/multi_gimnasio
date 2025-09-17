<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_profesor.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($profesor_id <= 0 || $gimnasio_id <= 0) { http_response_code(403); exit('❌ Sesión inválida.'); }

$alumnos = $conexion->query("
  SELECT id, apellido, nombre
  FROM clientes
  WHERE gimnasio_id = {$gimnasio_id}
  ORDER BY apellido, nombre
");

$ok  = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$err = $_GET['err'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Subir Rutina</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:900px;margin:20px auto;padding:16px}
    .titulo-seccion{margin:0 0 12px}
    .alert{padding:12px;border-radius:8px;margin-bottom:12px}
    .ok{background:#e6ffed;border:1px solid #29a34a}
    .err{background:#ffecec;border:1px solid #d33}
    .formulario{display:grid;gap:12px}
    .grupo-formulario{display:grid;gap:6px}
    .btn-principal{padding:10px 14px;border:0;border-radius:8px;cursor:pointer}
  </style>
</head>
<body>
<div class="contenedor">
  <h2 class="titulo-seccion">📄 Subir Rutina / Archivo al Alumno</h2>

  <?php if ($ok === 1): ?>
    <div class="alert ok">✅ Rutina subida correctamente.</div>
  <?php endif; ?>

  <?php if (!empty($err)): ?>
    <div class="alert err">❌ <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form action="guardar_rutina.php" method="POST" enctype="multipart/form-data" class="formulario">
    <div class="grupo-formulario">
      <label for="cliente_id">Alumno:</label>
      <select name="cliente_id" id="cliente_id" required>
        <option value="">-- Elegir alumno --</option>
        <?php if ($alumnos): while ($c = $alumnos->fetch_assoc()): ?>
          <option value="<?= (int)$c['id'] ?>">
            <?= htmlspecialchars($c['apellido'].', '.$c['nombre'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endwhile; endif; ?>
      </select>
    </div>

    <div class="grupo-formulario">
      <label for="archivo">Archivo (PDF/JPG/PNG/DOC/DOCX, máx 20MB):</label>
      <input id="archivo" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
    </div>

    <div class="grupo-formulario">
      <button type="submit" class="btn-principal">Subir Rutina</button>
    </div>
  </form>
</div>
</body>
</html>
