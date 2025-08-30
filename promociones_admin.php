<?php
/* promociones_admin.php */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$gym_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gym_id <= 0) { http_response_code(403); exit('Gimnasio no identificado.'); }

/* ====== Asegurar tabla promociones (si no existe) ====== */
$conexion->query("
  CREATE TABLE IF NOT EXISTS promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen_url VARCHAR(255) DEFAULT NULL,     -- puede ser URL o ruta local (uploads/promociones/...)
    link_url VARCHAR(255) DEFAULT NULL,
    color_fondo VARCHAR(20) DEFAULT '#111111',
    color_texto VARCHAR(20) DEFAULT '#FFD700',
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    prioridad INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (gimnasio_id),
    INDEX (activo),
    INDEX (fecha_fin)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ====== Variables ====== */
$msg = '';

/* ====== Helper de guardado de archivo ====== */
function guardar_imagen_promocion(?array $file): ?string {
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) return null;

  // Validaciones básicas
  $permitidos = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
  $mime = mime_content_type($file['tmp_name']);
  if (!isset($permitidos[$mime])) return null;

  // Límite (10MB)
  if (($file['size'] ?? 0) > 10*1024*1024) return null;

  // Carpeta
  $dir = __DIR__ . '/uploads/promociones';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);

  // Nombre seguro
  $ext = $permitidos[$mime];
  $name = 'promo_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $dest = $dir.'/'.$name;

  if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

  // Ruta pública relativa
  return 'uploads/promociones/'.$name;
}

/* ====== Crear / actualizar / activar / eliminar ====== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['act'] ?? '';

  if ($act === 'save') {
    $id  = (int)($_POST['id'] ?? 0);
    $tit = trim($_POST['titulo'] ?? '');
    $desc= trim($_POST['descripcion'] ?? '');
    $img_url_form = trim($_POST['imagen_url'] ?? ''); // si eligen URL manual
    $lnk = trim($_POST['link_url'] ?? '');
    $bg  = trim($_POST['color_fondo'] ?? '#111111');
    $fg  = trim($_POST['color_texto'] ?? '#FFD700');

    $fi  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_inicio'] ?? '') ? $_POST['fecha_inicio'] : null;
    $ff  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_fin'] ?? '') ? $_POST['fecha_fin'] : null;

    $pri = (int)($_POST['prioridad'] ?? 0);
    $actv= isset($_POST['activo']) ? 1 : 0;

    if ($tit==='') { $msg = '❌ Título requerido.'; }
    else {
      // Si suben archivo, guardamos y priorizamos ese; si no, usamos URL de texto
      $img_subida = guardar_imagen_promocion($_FILES['imagen_file'] ?? null);
      $img_final = $img_subida ?: ($img_url_form ?: null);

      if ($id>0) {
        $sql = "UPDATE promociones 
                SET titulo=?, descripcion=?, imagen_url=?, link_url=?, color_fondo=?, color_texto=?, 
                    fecha_inicio=?, fecha_fin=?, prioridad=?, activo=?
                WHERE id=? AND gimnasio_id=?";
        $st = $conexion->prepare($sql);
        $st->bind_param(
          'ssssssssiiii',
          $tit,$desc,$img_final,$lnk,$bg,$fg,$fi,$ff,$pri,$actv,$id,$gym_id
        );
      } else {
        $sql = "INSERT INTO promociones 
                (gimnasio_id,titulo,descripcion,imagen_url,link_url,color_fondo,color_texto,fecha_inicio,fecha_fin,prioridad,activo)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $st = $conexion->prepare($sql);
        $st->bind_param(
          'issssssssii',
          $gym_id,$tit,$desc,$img_final,$lnk,$bg,$fg,$fi,$ff,$pri,$actv
        );
      }

      if ($st && $st->execute()) { $msg = '✅ Promoción guardada.'; }
      else { $msg = '❌ Error al guardar.'; }
      if ($st) $st->close();
    }
  }

  if ($act === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $v  = (int)($_POST['v'] ?? 0);
    $conexion->query("UPDATE promociones SET activo={$v} WHERE id={$id} AND gimnasio_id={$gym_id}");
  }

  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // opcional: borrar archivo físico si la imagen_url apunta a uploads/promociones
    $q = $conexion->query("SELECT imagen_url FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
    if ($q && $row=$q->fetch_assoc()) {
      $url = (string)$row['imagen_url'];
      if ($url && str_starts_with($url, 'uploads/promociones/')) {
        $abs = __DIR__ . '/' . $url;
        if (is_file($abs)) @unlink($abs);
      }
    }
    $conexion->query("DELETE FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
  }
}

/* ====== Cargar para edición ====== */
$edit = null;
if (!empty($_GET['edit'])) {
  $idEd = (int)$_GET['edit'];
  $q = $conexion->query("SELECT * FROM promociones WHERE id={$idEd} AND gimnasio_id={$gym_id}");
  $edit = $q? $q->fetch_assoc(): null;
}

/* ====== Listado ====== */
$rs = $conexion->query("
  SELECT * 
  FROM promociones 
  WHERE gimnasio_id={$gym_id} 
  ORDER BY activo DESC, prioridad DESC, fecha_fin DESC, id DESC
");
$items = [];
if ($rs) { while($r=$rs->fetch_assoc()) $items[]=$r; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>📣 Promociones</title>
<style>
  :root{--bg:#000;--fg:gold;--card:#101114;--line:#262a33;--muted:#a0a7b4;}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}
  .wrap{max-width:1100px;margin:0 auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
  input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0d0f14;color:var(--fg)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none;cursor:pointer}
  .btn:hover{background:#21293a}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--line);padding:8px;text-align:left}
  th{background:#141824}
  .muted{color:var(--muted);font-size:12px}
  .thumb{width:70px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #333;background:#000}
  .swatch{display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap}
  .dot{width:18px;height:18px;border-radius:50%;border:1px solid #333;cursor:pointer}
  @media (max-width:900px){ .grid,.grid-3{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="wrap">
  <h1>📣 Promociones</h1>

  <?php if (!empty($msg)): ?>
    <div class="card"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0"><?= $edit ? 'Editar promoción' : 'Nueva promoción' ?></h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

      <div class="grid">
        <div>
          <label>Título</label>
          <input name="titulo" required value="<?= h($edit['titulo'] ?? '') ?>">
        </div>
        <div>
          <label>Prioridad (número)</label>
          <input name="prioridad" type="number" value="<?= h((string)($edit['prioridad'] ?? '0')) ?>">
        </div>
      </div>

      <div>
        <label>Descripción</label>
        <textarea name="descripcion"><?= h($edit['descripcion'] ?? '') ?></textarea>
      </div>

      <div class="grid">
        <div>
          <label>Imagen (archivo)</label>
          <input type="file" name="imagen_file" accept="image/*">
          <?php if (!empty($edit['imagen_url'])): ?>
            <div class="muted" style="margin-top:6px">Actual: <?= h($edit['imagen_url']) ?></div>
          <?php endif; ?>
        </div>
        <div>
          <label>Imagen (URL opcional)</label>
          <input name="imagen_url" value="<?= h($edit['imagen_url'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>

      <div>
        <label>Link (opcional)</label>
        <input name="link_url" value="<?= h($edit['link_url'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="grid-3">
        <div>
          <label>Color fondo</label>
          <input name="color_fondo" type="color" value="<?= h($edit['color_fondo'] ?? '#111111') ?>">
        </div>
        <div>
          <label>Color texto</label>
          <input name="color_texto" type="color" value="<?= h($edit['color_texto'] ?? '#FFD700') ?>">
        </div>
        <div>
          <label style="display:block">Paletas rápidas</label>
          <div class="swatch">
            <!-- Paletas: clic para aplicar -->
            <span class="dot" style="background:#111" data-bg="#111111" data-fg="#FFD700" title="Oscuro/Dorado"></span>
            <span class="dot" style="background:#001f3f" data-bg="#001f3f" data-fg="#66b2ff" title="Azul/Claro"></span>
            <span class="dot" style="background:#660000" data-bg="#660000" data-fg="#ffcccc" title="Rojo"></span>
            <span class="dot" style="background:#004d26" data-bg="#004d26" data-fg="#d9f2e6" title="Verde"></span>
            <span class="dot" style="background:#1a1d23" data-bg="#1a1d23" data-fg="#f1f5f9" title="Gris/Blanco"></span>
          </div>
        </div>
      </div>

      <div class="grid">
        <div>
          <label>Desde</label>
          <input name="fecha_inicio" type="date" value="<?= h($edit['fecha_inicio'] ?? date('Y-m-d')) ?>">
        </div>
        <div>
          <label>Hasta</label>
          <input name="fecha_fin" type="date" value="<?= h($edit['fecha_fin'] ?? date('Y-m-d')) ?>">
        </div>
      </div>

      <label style="display:inline-block;margin-top:8px">
        <input type="checkbox" name="activo" <?= ((int)($edit['activo'] ?? 1)===1)?'checked':''; ?>> Activa
      </label>

      <div style="margin-top:10px">
        <button class="btn" type="submit">💾 Guardar</button>
        <?php if ($edit): ?>
          <a class="btn" href="promociones_admin.php">Nueva</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Listado</h3>
    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Img</th><th>Título</th><th>Vigencia</th><th>Prioridad</th>
            <th>Colores</th><th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($items as $it): ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td>
                <?php if (!empty($it['imagen_url'])): ?>
                  <img class="thumb" src="<?= h($it['imagen_url']) ?>" alt="thumb">
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:bold"><?= h($it['titulo']) ?></div>
                <div class="muted" style="max-width:360px"><?= nl2br(h($it['descripcion'] ?? '')) ?></div>
                <?php if (!empty($it['link_url'])): ?>
                  <div class="muted">🔗 <a href="<?= h($it['link_url']) ?>" target="_blank" style="color:#66b2ff"><?= h($it['link_url']) ?></a></div>
                <?php endif; ?>
              </td>
              <td><?= h($it['fecha_inicio'] ?: '—') ?> → <?= h($it['fecha_fin'] ?: '—') ?></td>
              <td><?= (int)$it['prioridad'] ?></td>
              <td>
                <div class="swatch">
                  <span class="dot" style="background:<?= h($it['color_fondo'] ?: '#111') ?>;"></span>
                  <span class="dot" style="background:<?= h($it['color_texto'] ?: '#FFD700') ?>;"></span>
                </div>
                <div class="muted"><?= h($it['color_fondo'] ?: '#111') ?> / <?= h($it['color_texto'] ?: '#FFD700') ?></div>
              </td>
              <td><?= ((int)$it['activo']===1)?'✅ Activa':'⛔ Inactiva' ?></td>
              <td style="white-space:nowrap">
                <a class="btn" href="?edit=<?= (int)$it['id'] ?>">✏️ Editar</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Cambiar estado?');">
                  <input type="hidden" name="act" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="v" value="<?= ((int)$it['activo']===1)?0:1 ?>">
                  <button class="btn" type="submit"><?= ((int)$it['activo']===1)?'Desactivar':'Activar' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar promoción?');">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn" type="submit">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; if (empty($items)): ?>
            <tr><td colspan="8" class="muted">Sin promociones cargadas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
  // Paletas rápidas: setean color_fondo y color_texto al hacer clic
  document.addEventListener('DOMContentLoaded', () => {
    const dots = document.querySelectorAll('.dot[data-bg]');
    const bgInp = document.querySelector('input[name="color_fondo"]');
    const fgInp = document.querySelector('input[name="color_texto"]');
    dots.forEach(d => {
      d.addEventListener('click', () => {
        if (!bgInp || !fgInp) return;
        bgInp.value = d.getAttribute('data-bg') || '#111111';
        fgInp.value = d.getAttribute('data-fg') || '#FFD700';
      });
    });
  });
</script>
</body>
</html>
