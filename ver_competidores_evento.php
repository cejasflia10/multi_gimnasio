<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ====== evento_id ====== */
$evento_id = (int)($_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) { http_response_code(400); exit('❌ Falta evento_id'); }

/* ====== Catálogos ====== */
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY id");
$modalidades = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY id");
$categorias_peso = $conexion->query("SELECT id, nombre FROM categorias_peso_evento ORDER BY id");
$divisiones = $conexion->query("SELECT id, nombre FROM divisiones_evento ORDER BY id");
$categorias_tecnicas = $conexion->query("SELECT id, codigo, descripcion FROM categorias_tecnicas_evento ORDER BY id");

/* ====== Filtros (por ID) ====== */
$f_disciplina_id        = (isset($_GET['disciplina_id'])        && is_numeric($_GET['disciplina_id']))        ? (int)$_GET['disciplina_id']        : null;
$f_modalidad_id         = (isset($_GET['modalidad_id'])         && is_numeric($_GET['modalidad_id']))         ? (int)$_GET['modalidad_id']         : null;
$f_categoria_peso_id    = (isset($_GET['categoria_peso_id'])    && is_numeric($_GET['categoria_peso_id']))    ? (int)$_GET['categoria_peso_id']    : null;
$f_division_id          = (isset($_GET['division_id'])          && is_numeric($_GET['division_id']))          ? (int)$_GET['division_id']          : null;
$f_categoria_tecnica_id = (isset($_GET['categoria_tecnica_id']) && is_numeric($_GET['categoria_tecnica_id'])) ? (int)$_GET['categoria_tecnica_id'] : null;

/* ====== SQL con JOINs + filtros dinámicos ====== */
$sql = "
SELECT
  ce.id,
  ce.apellido,
  ce.nombre,
  ce.dni,
  ce.edad,
  ce.fecha_nacimiento,
  ce.foto_competidor,
  ce.escuela_logo,
  ce.escuela_nombre,
  ce.pago_inscripcion,
  d.nombre  AS disciplina,
  m.nombre  AS modalidad,
  cp.nombre AS categoria_peso,
  dv.nombre AS division,
  ct.codigo AS categoria_tecnica_codigo,
  ct.descripcion AS categoria_tecnica_desc
FROM competidores_evento ce
LEFT JOIN disciplinas_evento         d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento         m  ON m.id  = ce.modalidad_id
LEFT JOIN categorias_peso_evento     cp ON cp.id = ce.categoria_peso_id
LEFT JOIN divisiones_evento          dv ON dv.id = ce.division_id
LEFT JOIN categorias_tecnicas_evento ct ON ct.id = ce.categoria_tecnica_id
WHERE ce.evento_id = ?
";

$types = 'i';
$params = [$evento_id];

if (!is_null($f_disciplina_id))        { $sql .= " AND ce.disciplina_id = ?";         $types.='i'; $params[]=$f_disciplina_id; }
if (!is_null($f_modalidad_id))         { $sql .= " AND ce.modalidad_id = ?";          $types.='i'; $params[]=$f_modalidad_id; }
if (!is_null($f_categoria_peso_id))    { $sql .= " AND ce.categoria_peso_id = ?";     $types.='i'; $params[]=$f_categoria_peso_id; }
if (!is_null($f_division_id))          { $sql .= " AND ce.division_id = ?";           $types.='i'; $params[]=$f_division_id; }
if (!is_null($f_categoria_tecnica_id)) { $sql .= " AND ce.categoria_tecnica_id = ?";  $types.='i'; $params[]=$f_categoria_tecnica_id; }

$sql .= " ORDER BY ce.apellido, ce.nombre";

$st = $conexion->prepare($sql);
if (!$st) { http_response_code(500); exit('❌ SQL prepare: '.$conexion->error); }

/* bind dinámico */
$refs = [];
foreach ($params as $k=>&$v) { $refs[$k] = &$v; }
array_unshift($refs, $types);
call_user_func_array([$st,'bind_param'], $refs);

$st->execute();
$res = $st->get_result();
$competidores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* Placeholders */
$placeholderFoto = 'assets/placeholder-user.png';
$placeholderLogo = 'assets/placeholder-logo.png';

/* Escapar */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Ver Competidores del Evento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor { max-width: 1200px; margin: 0 auto; padding: 16px; }
    /* Filtros en grid */
    form .filters {
      display: grid;
      grid-template-columns: repeat(5, minmax(160px, 1fr));
      gap: 12px;
      align-items: end;
      margin-bottom: 16px;
    }
    form label { font-weight: 600; font-size: 14px; }
    form select, form button {
      width: 100%; padding: 8px 10px; border: 1px solid #dcdcdc; border-radius: 8px;
    }
    form button { cursor: pointer; }
    @media (max-width: 900px) {
      form .filters { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 600px) {
      form .filters { grid-template-columns: 1fr; }
      form button { padding: 12px; }
    }

    /* Tabla desktop + wrapper para scroll horizontal si hace falta */
    .table-wrap { width: 100%; overflow-x: auto; }
    .tabla { width: 100%; border-collapse: collapse; min-width: 860px; }
    .tabla th, .tabla td { border: 1px solid #e7e7e7; padding: 8px 10px; vertical-align: middle; }
    .tabla th { background: #f6f7f9; text-align: left; }
    .avatar { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
    .logo   { width: 60px; height: 60px; object-fit: contain; }

    /* Modo tarjeta en móviles */
    @media (max-width: 680px) {
      .tabla, .tabla thead, .tabla tbody, .tabla th, .tabla td, .tabla tr { display: block; }
      .tabla thead { position: absolute; left: -9999px; top: -9999px; }
      .tabla tr { border: 1px solid #e7e7e7; border-radius: 10px; margin-bottom: 12px; background: #fff; }
      .tabla td {
        border: none; border-bottom: 1px solid #f0f0f0;
        display: flex; justify-content: space-between; gap: 12px;
        padding: 10px 12px;
      }
      .tabla td:last-child { border-bottom: none; }
      .tabla td::before {
        content: attr(data-label);
        font-weight: 600; min-width: 42%;
      }
      .avatar { width: 72px; height: 72px; }
      .logo   { width: 72px; height: 72px; }
    }

    .btn-principal { display: inline-block; padding: 10px 14px; border-radius: 10px; background: #1e88e5; color:#fff; text-decoration:none; }
    .btn-principal:hover { background:#166fbe; }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>👊 Competidores del Evento #<?= (int)$evento_id ?></h2>

  <form method="GET">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <div class="filters">
      <div>
        <label>Disciplina</label>
        <select name="disciplina_id">
          <option value="">Todas</option>
          <?php while($d = $disciplinas->fetch_assoc()): ?>
            <option value="<?= (int)$d['id'] ?>" <?= (!is_null($f_disciplina_id) && $f_disciplina_id==(int)$d['id'])?'selected':'' ?>>
              <?= h($d['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label>Modalidad</label>
        <select name="modalidad_id">
          <option value="">Todas</option>
          <?php while($m = $modalidades->fetch_assoc()): ?>
            <option value="<?= (int)$m['id'] ?>" <?= (!is_null($f_modalidad_id) && $f_modalidad_id==(int)$m['id'])?'selected':'' ?>>
              <?= h($m['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label>Categoría Técnica</label>
        <select name="categoria_tecnica_id">
          <option value="">Todas</option>
          <?php while($ct = $categorias_tecnicas->fetch_assoc()): ?>
            <option value="<?= (int)$ct['id'] ?>" <?= (!is_null($f_categoria_tecnica_id) && $f_categoria_tecnica_id==(int)$ct['id'])?'selected':'' ?>>
              <?= h(($ct['codigo'] ?? '') . (isset($ct['descripcion']) ? ' - '.$ct['descripcion'] : '')) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label>Categoría de Peso</label>
        <select name="categoria_peso_id">
          <option value="">Todas</option>
          <?php while($cp = $categorias_peso->fetch_assoc()): ?>
            <option value="<?= (int)$cp['id'] ?>" <?= (!is_null($f_categoria_peso_id) && $f_categoria_peso_id==(int)$cp['id'])?'selected':'' ?>>
              <?= h($cp['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <label>División</label>
        <select name="division_id">
          <option value="">Todas</option>
          <?php while($dv = $divisiones->fetch_assoc()): ?>
            <option value="<?= (int)$dv['id'] ?>" <?= (!is_null($f_division_id) && $f_division_id==(int)$dv['id'])?'selected':'' ?>>
              <?= h($dv['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div>
        <button type="submit">🔍 Filtrar</button>
      </div>
    </div>
  </form>

  <?php if (!$competidores): ?>
    <p>No hay competidores que coincidan con los filtros.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tabla">
        <thead>
          <tr>
            <th>Foto</th>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Disciplina</th>
            <th>Modalidad</th>
            <th>Cat. Técnica</th>
            <th>Categoría Peso</th>
            <th>División</th>
            <th>Escuela</th>
            <th>Logo</th>
            <th>Inscripción</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($competidores as $c):
            $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
            $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;
            $catTec  = trim(($c['categoria_tecnica_codigo'] ?? '').' - '.($c['categoria_tecnica_desc'] ?? ''));
          ?>
            <tr>
              <td data-label="Foto"><img class="avatar" src="<?= h($srcFoto) ?>" alt="Foto"></td>
              <td data-label="Apellido y Nombre"><?= h($c['apellido']) ?> <?= h($c['nombre']) ?></td>
              <td data-label="DNI"><?= h($c['dni']) ?></td>
              <td data-label="Edad"><?= h($c['edad']) ?></td>
              <td data-label="Disciplina"><?= h($c['disciplina'] ?? '-') ?></td>
              <td data-label="Modalidad"><?= h($c['modalidad'] ?? '-') ?></td>
              <td data-label="Cat. Técnica"><?= h($catTec !== ' - ' ? $catTec : '-') ?></td>
              <td data-label="Categoría Peso"><?= h($c['categoria_peso'] ?? '-') ?></td>
              <td data-label="División"><?= h($c['division'] ?? '-') ?></td>
              <td data-label="Escuela"><?= h($c['escuela_nombre'] ?? '-') ?></td>
              <td data-label="Logo"><img class="logo" src="<?= h($srcLogo) ?>" alt="Logo"></td>
              <td data-label="Inscripción">$ <?= h($c['pago_inscripcion'] ?? '0.00') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p style="margin-top:14px">
    <a class="btn-principal" href="agregar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>">➕ Agregar competidor</a>
  </p>
</div>
</body>
</html>
