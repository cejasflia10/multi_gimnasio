<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$gym_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gym_id <= 0) { http_response_code(403); exit('Gimnasio no identificado.'); }

$msg = '';

/* Crear / actualizar */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['act'] ?? '';
  if ($act === 'save') {
    $id  = (int)($_POST['id'] ?? 0);
    $tit = trim($_POST['titulo'] ?? '');
    $desc= trim($_POST['descripcion'] ?? '');
    $img = trim($_POST['imagen_url'] ?? '');
    $lnk = trim($_POST['link_url'] ?? '');
    $bg  = trim($_POST['color_fondo'] ?? '#111111');
    $fg  = trim($_POST['color_texto'] ?? '#FFD700');
    $fi  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_inicio'] ?? '') ? $_POST['fecha_inicio'] : date('Y-m-d');
    $ff  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_fin'] ?? '') ? $_POST['fecha_fin'] : date('Y-m-d');
    $pri = (int)($_POST['prioridad'] ?? 0);
    $actv= isset($_POST['activo']) ? 1 : 0;

    if ($tit==='') { $msg = 'Título requerido.'; }
    else {
      if ($id>0) {
        $st = $conexion->prepare("UPDATE promociones SET titulo=?, descripcion=?, imagen_url=?, link_url=?, color_fondo=?, color_texto=?, fecha_inicio=?, fecha_fin=?, prioridad=?, activo=? WHERE id=? AND gimnasio_id=?");
        $st->bind_param('ssssssssiiii',$tit,$desc,$img,$lnk,$bg,$fg,$fi,$ff,$pri,$actv,$id,$gym_id);
      } else {
        $st = $conexion->prepare("INSERT INTO promociones (gimnasio_id,titulo,descripcion,imagen_url,link_url,color_fondo,color_texto,fecha_inicio,fecha_fin,prioridad,activo) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('issssssssii',$gym_id,$tit,$desc,$img,$lnk,$bg,$fg,$fi,$ff,$pri,$actv);
      }
      if ($st && $st->execute()) { $msg = '✅ Promoción guardada.'; } else { $msg = '❌ Error al guardar.'; }
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
    $conexion->query("DELETE FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
  }
}

/* Cargar listado */
$rs = $conexion->query("SELECT * FROM promociones WHERE gimnasio_id={$gym_id} ORDER BY activo DESC, prioridad DESC, fecha_fin DESC, id DESC");
$items = [];
if ($rs) { while($r=$rs->fetch_assoc()) $items[]=$r; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Promociones</title>
<style>
  :root{--bg:#000;--fg:gold;--card:#101114;--line:#262a33;}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}
  .wrap{max-width:1100px;margin:0 auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
  input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0d0f14;color:var(--fg)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none;cursor:pointer}
  .btn:hover{background:#21293a}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--line);padding:8px;text-align:left}
  th{background:#141824}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="wrap">
  <h1>📣 Promociones</h1>

  <?php if ($msg): ?>
    <div class="card"><?= h($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0">Nueva/Editar promoción</h3>
    <form method="POST">
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="id" value="<?= (int)($_GET['edit'] ?? 0) ?>">
      <?php
        $edit = null;
        if (!empty($_GET['edit'])) {
          $idEd = (int)$_GET['edit'];
          $q = $conexion->query("SELECT * FROM promociones WHERE id={$idEd} AND gimnasio_id={$gym_id}");
          $edit = $q? $q->fetch_assoc(): null;
        }
        function v($a,$k,$d=''){ return h($a[$k] ?? $d); }
      ?>
      <div class="grid">
        <div><label>Título</label><input name="titulo" required value="<?= $edit? v($edit,'titulo'):'' ?>"></div>
        <div><label>Prioridad (número)</label><input name="prioridad" type="number" value="<?= $edit? v($edit,'prioridad','0'):'0' ?>"></div>
      </div>
      <div><label>Descripción</label><textarea name="descripcion"><?= $edit? v($edit,'descripcion'):'' ?></textarea></div>
      <div class="grid">
        <div><label>Imagen (URL opcional)</label><input name="imagen_url" value="<?= $edit? v($edit,'imagen_url'):'' ?>" placeholder="https://..."></div>
        <div><label>Link (opcional)</label><input name="link_url" value="<?= $edit? v($edit,'link_url'):'' ?>" placeholder="https://..."></div>
      </div>
      <div class="grid">
        <div><label>Color fondo</label><input name="color_fondo" value="<?= $edit? v($edit,'color_fondo','#111111'):'#111111' ?>"></div>
        <div><label>Color texto</label><input name="color_texto" value="<?= $edit? v($edit,'color_texto','#FFD700'):'#FFD700' ?>"></div>
      </div>
      <div class="grid">
        <div><label>Desde</label><input name="fecha_inicio" type="date" value="<?= $edit? v($edit,'fecha_inicio',date('Y-m-d')):date('Y-m-d') ?>"></div>
        <div><label>Hasta</label><input name="fecha_fin" type="date" value="<?= $edit? v($edit,'fecha_fin',date('Y-m-d')):date('Y-m-d') ?>"></div>
      </div>
      <label><input type="checkbox" name="activo" <?= ($edit? ((int)$edit['activo']===1):true)?'checked':''; ?>> Activa</label>
      <div style="margin-top:10px"><button class="btn" type="submit">💾 Guardar</button></div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Listado</h3>
    <div style="overflow:auto">
      <table>
        <thead><tr>
          <th>#</th><th>Título</th><th>Vigencia</th><th>Prioridad</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
          <?php foreach($items as $it): ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td><?= h($it['titulo']) ?></td>
              <td><?= h($it['fecha_inicio']) ?> → <?= h($it['fecha_fin']) ?></td>
              <td><?= (int)$it['prioridad'] ?></td>
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
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
