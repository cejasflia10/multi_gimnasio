<?php
// editar_competidor_evento.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* (Opcional) Restringir a admin: descomentá si querés forzar solo admin
if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] !== 'admin') {
  http_response_code(403); exit('Acceso restringido');
}
*/

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0; if($q) $q->close();
  return $ok;
}
function cols(mysqli $db, string $t): array {
  $out=[]; if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){
    while($x=$r->fetch_assoc()){ $out[strtolower($x['Field'])]=$x['Field']; }
    $r->close();
  }
  return $out;
}
function pick(array $cands, array $pool){
  foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; }
  return null;
}

/* ===== Descubrir columnas de competidores_evento ===== */
if (!has_table($conexion,'competidores_evento')) exit('❌ Falta tabla competidores_evento');

$cols = cols($conexion,'competidores_evento');

$C_ID        = pick(['id','competidor_id'], $cols);
$C_NOMBRE    = pick(['nombre'], $cols);
$C_APELLIDO  = pick(['apellido'], $cols);
$C_ESC_NOM   = pick(['escuela_nombre','academia','gimnasio','equipo'], $cols);
$C_ESC_LOGO  = pick(['escuela_logo','logo_escuela','logo_academia'], $cols);
$C_FOTO      = pick(['foto_competidor','foto','avatar'], $cols);
$C_EDAD      = pick(['edad'], $cols);
$C_MODAL_ID  = pick(['modalidad_id'], $cols);
$C_PESO_ID   = pick(['categoria_peso_id','peso_id'], $cols);

if (!$C_ID) exit('❌ No se detectó columna ID en competidores_evento');

/* ===== Listas auxiliares (si existen) ===== */
$hasModTbl  = has_table($conexion,'modalidades_evento');
$hasPesoTbl = has_table($conexion,'categorias_peso_evento');

$mods = [];
if ($hasModTbl) {
  if ($r=$conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY nombre")){
    while($x=$r->fetch_assoc()){ $mods[] = ['id'=>(int)$x['id'], 'nombre'=>$x['nombre']]; }
    $r->close();
  }
}
$pesos = [];
if ($hasPesoTbl) {
  if ($r=$conexion->query("SELECT id, nombre FROM categorias_peso_evento ORDER BY nombre")){
    while($x=$r->fetch_assoc()){ $pesos[] = ['id'=>(int)$x['id'], 'nombre'=>$x['nombre']]; }
    $r->close();
  }
}

/* ===== ID por GET ===== */
$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0){ exit('❌ Falta parámetro id'); }

/* ===== Guardar (POST) ===== */
$msg=''; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  // Recolectar datos del form, sólo si la columna existe
  $sets=[]; $vals=[]; $types='';

  if ($C_APELLIDO && array_key_exists('apellido', $_POST)) {
    $v = trim((string)$_POST['apellido']);
    $sets[] = bt($C_APELLIDO) . "=?"; $vals[] = $v; $types .= 's';
  }
  if ($C_NOMBRE && array_key_exists('nombre', $_POST)) {
    $v = trim((string)$_POST['nombre']);
    $sets[] = bt($C_NOMBRE) . "=?"; $vals[] = $v; $types .= 's';
  }
  if ($C_ESC_NOM && array_key_exists('escuela', $_POST)) {
    $v = trim((string)$_POST['escuela']);
    $sets[] = bt($C_ESC_NOM) . "=?"; $vals[] = $v; $types .= 's';
  }
  if ($C_ESC_LOGO && array_key_exists('escuela_logo', $_POST)) {
    $v = trim((string)$_POST['escuela_logo']);
    $sets[] = bt($C_ESC_LOGO) . "=?"; $vals[] = $v; $types .= 's';
  }
  if ($C_FOTO && array_key_exists('foto', $_POST)) {
    $v = trim((string)$_POST['foto']);
    $sets[] = bt($C_FOTO) . "=?"; $vals[] = $v; $types .= 's';
  }
  if ($C_EDAD && array_key_exists('edad', $_POST)) {
    $v = (string)$_POST['edad']; $v = ctype_digit($v) ? (int)$v : null;
    $sets[] = bt($C_EDAD) . "=?"; $vals[] = $v; $types .= 'i';
  }
  if ($C_MODAL_ID && array_key_exists('modalidad_id', $_POST)) {
    $v = (string)$_POST['modalidad_id']; $v = ctype_digit($v) ? (int)$v : null;
    $sets[] = bt($C_MODAL_ID) . "=?"; $vals[] = $v; $types .= 'i';
  }
  if ($C_PESO_ID && array_key_exists('peso_id', $_POST)) {
    $v = (string)$_POST['peso_id']; $v = ctype_digit($v) ? (int)$v : null;
    $sets[] = bt($C_PESO_ID) . "=?"; $vals[] = $v; $types .= 'i';
  }

  if ($sets) {
    $sql = "UPDATE `competidores_evento` SET ".implode(', ',$sets)." WHERE ".bt($C_ID)."=?";
    $types .= 'i'; $vals[] = $id;

    if ($st=$conexion->prepare($sql)) {
      $st->bind_param($types, ...$vals);
      if ($st->execute()) { $msg='✅ Cambios guardados.'; }
      else { $err='No se pudo guardar.'; }
      $st->close();
    } else {
      $err='Error preparando UPDATE.';
    }
  } else {
    $err='No hay cambios para guardar.';
  }
}

/* ===== Leer competidor (SELECT con prepare) ===== */
$sel = "SELECT ".
       bt($C_ID)." AS id".
       ($C_APELLIDO ? ", ".bt($C_APELLIDO)." AS apellido" : ", NULL AS apellido").
       ($C_NOMBRE   ? ", ".bt($C_NOMBRE)  ." AS nombre"   : ", NULL AS nombre").
       ($C_ESC_NOM  ? ", ".bt($C_ESC_NOM) ." AS escuela"  : ", NULL AS escuela").
       ($C_ESC_LOGO ? ", ".bt($C_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo").
       ($C_FOTO     ? ", ".bt($C_FOTO)    ." AS foto"     : ", NULL AS foto").
       ($C_EDAD     ? ", ".bt($C_EDAD)    ." AS edad"     : ", NULL AS edad").
       ($C_MODAL_ID ? ", ".bt($C_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id").
       ($C_PESO_ID  ? ", ".bt($C_PESO_ID) ." AS peso_id"       : ", NULL AS peso_id").
       " FROM `competidores_evento` WHERE ".bt($C_ID)."=? LIMIT 1";

$st = $conexion->prepare($sel);
$st->bind_param('i', $id);
$st->execute();
$comp = $st->get_result()->fetch_assoc();
$st->close();

if (!$comp){ exit('❌ Competidor no encontrado'); }

/* ===== Render ===== */
$phUser='assets/placeholder-user.png';
$phGym ='assets/placeholder-gym.png';
$nombre = trim(($comp['apellido']??'').' '.($comp['nombre']??'')) ?: ('#'.$id);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>✏️ Editar competidor #<?= (int)$id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .wrap{max-width:900px;margin:24px auto;padding:12px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:14px}
  label{display:block;margin:8px 0 4px;color:#bcd8ff}
  input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
  .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
  .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
  img.pfp{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid #2b3c4f}
  .muted{color:#9ecbff}
</style>
</head>
<body>
<?php if (empty($_SESSION['__JUEZ_MODE__'])) { @include __DIR__.'/menu_eventos.php'; } ?>

<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">✏️ Editar competidor — <?= h($nombre) ?></h2>
    <?php if($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

    <form method="post" novalidate>
      <div class="row" style="align-items:center;margin-bottom:8px">
        <img class="pfp" src="<?= h($comp['foto'] ?: $phUser) ?>" alt="foto">
        <img class="pfp" src="<?= h($comp['escuela_logo'] ?: $phGym) ?>" alt="logo">
        <div class="muted">ID: <?= (int)$comp['id'] ?></div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Apellido</label>
          <input name="apellido" value="<?= h($comp['apellido']??'') ?>">
        </div>
        <div style="flex:1">
          <label>Nombre</label>
          <input name="nombre" value="<?= h($comp['nombre']??'') ?>">
        </div>
      </div>

      <div class="row">
        <div style="flex:2">
          <label>Academia</label>
          <input name="escuela" value="<?= h($comp['escuela']??'') ?>">
        </div>
        <div style="flex:1">
          <label>Edad</label>
          <input name="edad" type="number" min="0" step="1" value="<?= h((string)($comp['edad']??'')) ?>">
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>URL Foto competidor</label>
          <input name="foto" value="<?= h($comp['foto'] ?? '') ?>">
        </div>
        <div style="flex:1">
          <label>URL Logo academia</label>
          <input name="escuela_logo" value="<?= h($comp['escuela_logo'] ?? '') ?>">
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Modalidad</label>
          <?php if ($C_MODAL_ID && $mods): ?>
            <select name="modalidad_id">
              <option value="0">—</option>
              <?php foreach($mods as $m): ?>
                <option value="<?= (int)$m['id'] ?>" <?= (int)($comp['modalidad_id']??0)===(int)$m['id']?'selected':''; ?>>
                  <?= h($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($C_MODAL_ID): ?>
            <input value="(sin tabla modalidades_evento)" disabled>
          <?php else: ?>
            <input value="(columna modalidad_id no existe)" disabled>
          <?php endif; ?>
        </div>

        <div style="flex:1">
          <label>Categoría de peso</label>
          <?php if ($C_PESO_ID && $pesos): ?>
            <select name="peso_id">
              <option value="0">—</option>
              <?php foreach($pesos as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)($comp['peso_id']??0)===(int)$p['id']?'selected':''; ?>>
                  <?= h($p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($C_PESO_ID): ?>
            <input value="(sin tabla categorias_peso_evento)" disabled>
          <?php else: ?>
            <input value="(columna categoria_peso_id/peso_id no existe)" disabled>
          <?php endif; ?>
        </div>
      </div>

      <div class="row" style="margin-top:12px">
        <button class="btn" type="submit">💾 Guardar cambios</button>
        <a class="btn" href="ranking_competidores.php" style="background:#1b2836;border-color:#2b3c4f">⬅ Volver</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
