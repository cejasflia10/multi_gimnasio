<?php
// gestionar_peleas.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

// (opcional) sólo admins
if (isset($_SESSION['user_rol']) && $_SESSION['user_rol']!=='admin') { http_response_code(403); exit('Acceso restringido'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $q=$db->query("SHOW TABLES LIKE '$t'"); $ok=$q&&$q->num_rows>0; if($q)$q->close(); return $ok; }
function cols(mysqli $db, string $t): array { $out=[]; if($r=$db->query("SHOW COLUMNS FROM ".bt($t))){ while($x=$r->fetch_assoc()){ $out[strtolower($x['Field'])]=$x['Field']; } $r->close(); } return $out; }
function pick(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }

if (!has_table($conexion,'peleas_evento') || !has_table($conexion,'competidores_evento')) exit('❌ Faltan tablas base');

$peCols = cols($conexion,'peleas_evento');
$ceCols = cols($conexion,'competidores_evento');

$C_ID_P    = pick(['id'], $peCols);
$C_EVENTO  = pick(['evento_id','id_evento','evento'], $peCols);
$C_AZUL    = pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'], $peCols);
$C_ROJO    = pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'], $peCols);
$C_RONDAS  = pick(['rondas','rounds'], $peCols);
$C_FECHA   = pick(['fecha','fecha_pelea','fpelea','created_at'], $peCols);
if (!$C_ID_P || !$C_AZUL || !$C_ROJO) exit('❌ No se detectaron columnas mínimas en peleas_evento');

$C_ID_CE   = pick(['id','competidor_id'], $ceCols);
$C_APE     = pick(['apellido'], $ceCols);
$C_NOM     = pick(['nombre'], $ceCols);
if (!$C_ID_CE) exit('❌ Falta ID en competidores_evento');

// Listas para selects
$compet = [];
if ($r=$conexion->query("SELECT ".bt($C_ID_CE)." AS id, TRIM(CONCAT(COALESCE(".bt($C_APE).",'') ,' ', COALESCE(".bt($C_NOM).",'') )) AS nombre FROM `competidores_evento` ORDER BY apellido, nombre")) {
  while($x=$r->fetch_assoc()){ $compet[]=['id'=>(int)$x['id'], 'nombre'=>$x['nombre'] ?: ('#'.$x['id'])]; }
  $r->close();
}
$eventos=[];
if ($C_EVENTO && has_table($conexion,'eventos')) {
  if ($r=$conexion->query("SELECT id, COALESCE(nombre, CONCAT('Evento #',id)) AS nombre FROM eventos ORDER BY id DESC")){
    while($x=$r->fetch_assoc()){ $eventos[]=['id'=>(int)$x['id'], 'nombre'=>$x['nombre']]; }
    $r->close();
  }
}

$msg=''; $err='';
$edit_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $pelea_id = (int)($_POST['pelea_id'] ?? 0); // 0=>alta
  $azul_id  = (int)($_POST['azul_id'] ?? 0);
  $rojo_id  = (int)($_POST['rojo_id'] ?? 0);
  $evento_id= (int)($_POST['evento_id'] ?? 0);
  $rondas   = (int)($_POST['rondas'] ?? 3);
  $fecha    = trim((string)($_POST['fecha'] ?? ''));

  if ($azul_id<=0 || $rojo_id<=0 || $azul_id===$rojo_id) $err='Elegí dos competidores válidos (distintos).';

  if (!$err) {
    $fields=[]; $types=''; $vals=[];

    // set obligatorios
    $fields[$C_AZUL] = $azul_id;  $types.='i'; $vals[]=$azul_id;
    $fields[$C_ROJO] = $rojo_id;  $types.='i'; $vals[]=$rojo_id;

    if ($C_EVENTO && $evento_id>0){ $fields[$C_EVENTO]=$evento_id; $types.='i'; $vals[]=$evento_id; }
    if ($C_RONDAS){ $fields[$C_RONDAS]=$rondas; $types.='i'; $vals[]=$rondas; }
    if ($C_FECHA && $fecha!==''){ $fields[$C_FECHA]=$fecha; $types.='s'; $vals[]=$fecha; }

    if ($pelea_id>0){
      // UPDATE
      $set = implode(', ', array_map(fn($c)=>bt($c).'=?', array_keys($fields)));
      $sql = "UPDATE `peleas_evento` SET $set WHERE ".bt($C_ID_P)."=?";
      $types.='i'; $vals[]=$pelea_id;
      if ($st=$conexion->prepare($sql)){
        $st->bind_param($types, ...$vals);
        if ($st->execute()) { $msg='✅ Pelea actualizada.'; $edit_id=0; } else { $err='No se pudo actualizar.'; }
        $st->close();
      } else { $err='Error preparando UPDATE'; }
    } else {
      // INSERT
      $colnames = implode(', ', array_map('bt', array_keys($fields)));
      $placeholders = implode(', ', array_fill(0, count($fields), '?'));
      $sql = "INSERT INTO `peleas_evento` ($colnames) VALUES ($placeholders)";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param($types, ...$vals);
        if ($st->execute()) { $msg='✅ Pelea creada (ID '.$st->insert_id.').'; } else { $err='No se pudo crear la pelea.'; }
        $st->close();
      } else { $err='Error preparando INSERT'; }
    }
  }
}

// Leer pelea en edición (si corresponde)
$edit=null;
if ($edit_id>0){
  $sql="SELECT ".bt($C_ID_P)." AS id, ".bt($C_AZUL)." AS azul_id, ".bt($C_ROJO)." AS rojo_id".
       ($C_EVENTO? ", ".bt($C_EVENTO)." AS evento_id": "").
       ($C_RONDAS? ", ".bt($C_RONDAS)." AS rondas": "").
       ($C_FECHA?  ", ".bt($C_FECHA) ." AS fecha": "").
       " FROM `peleas_evento` WHERE ".bt($C_ID_P)."=$edit_id LIMIT 1";
  $edit = $conexion->query($sql)?->fetch_assoc();
}

// Listado rápido de últimas 50 peleas
$list = [];
$sel = "SELECT p.".bt($C_ID_P)." AS id, p.".bt($C_AZUL)." AS a, p.".bt($C_ROJO)." AS r".
       ($C_EVENTO? ", p.".bt($C_EVENTO)." AS e":"").
       ($C_RONDAS? ", p.".bt($C_RONDAS)." AS n":"").
       ($C_FECHA?  ", p.".bt($C_FECHA) ." AS f":"").
       " FROM `peleas_evento` p ORDER BY p.".bt($C_ID_P)." DESC LIMIT 50";
if ($r=$conexion->query($sel)){
  while($x=$r->fetch_assoc()){ $list[]=$x; }
  $r->close();
}
// map id->nombre
$mapN=[]; foreach($compet as $c){ $mapN[$c['id']]=$c['nombre']; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>🗂️ Gestionar peleas</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .wrap{max-width:1100px;margin:20px auto;padding:12px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:14px}
  label{display:block;margin:8px 0 4px;color:#bcd8ff}
  input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer}
  .ok{margin:10px 0;padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
  .bad{margin:10px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{padding:8px;border-bottom:1px solid #1c2a36}
  th{color:#9ecbff;background:#0f1a26}
  a.btn-mini{padding:6px 10px;border-radius:8px;border:1px solid #2b3c4f;background:#1b2836;color:#e6eef4;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">🗂️ Gestionar peleas</h2>
    <?php if($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bad"><?= h($err) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="pelea_id" value="<?= (int)($edit['id']??0) ?>">
      <div class="row">
        <div style="flex:1">
          <label>Rincón AZUL</label>
          <select name="azul_id" required>
            <option value="">— elegir —</option>
            <?php
            $prefA = isset($_GET['azul_id'])?(int)$_GET['azul_id']:0;
            $selA = (int)($edit['azul_id'] ?? 0) ?: $prefA;
            foreach($compet as $c){
              $sel = $selA===$c['id'] ? 'selected' : '';
              echo '<option value="'.$c['id'].'" '.$sel.'>'.h($c['nombre']).' (#'.$c['id'].')</option>';
            }
            ?>
          </select>
        </div>
        <div style="flex:1">
          <label>Rincón ROJO</label>
          <select name="rojo_id" required>
            <option value="">— elegir —</option>
            <?php
            $prefR = isset($_GET['rojo_id'])?(int)$_GET['rojo_id']:0;
            $selR = (int)($edit['rojo_id'] ?? 0) ?: $prefR;
            foreach($compet as $c){
              $sel = $selR===$c['id'] ? 'selected' : '';
              echo '<option value="'.$c['id'].'" '.$sel.'>'.h($c['nombre']).' (#'.$c['id'].')</option>';
            }
            ?>
          </select>
        </div>
      </div>

      <div class="row">
        <div style="flex:1">
          <label>Evento</label>
          <?php if($C_EVENTO): ?>
            <select name="evento_id">
              <option value="0">—</option>
              <?php
                $selE = (int)($edit['evento_id'] ?? 0);
                foreach($eventos as $e){
                  $sel = $selE===$e['id'] ? 'selected' : '';
                  echo '<option value="'.$e['id'].'" '.$sel.'>'.h($e['nombre']).'</option>';
                }
              ?>
            </select>
          <?php else: ?>
            <input value="(La tabla peleas_evento no tiene evento_id)" disabled>
          <?php endif; ?>
        </div>
        <div style="flex:1">
          <label>Rondas</label>
          <select name="rondas">
            <?php $n=(int)($edit['rondas'] ?? 3); for($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= $i===$n?'selected':''; ?>><?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div style="flex:1">
          <label>Fecha (opcional)</label>
          <input name="fecha" type="datetime-local" value="<?= h($edit['fecha'] ?? '') ?>" <?= $C_FECHA?'':'disabled' ?>>
        </div>
      </div>

      <div style="margin-top:10px">
        <button class="btn"><?= $edit ? 'Guardar cambios' : 'Crear pelea' ?></button>
        <a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="ver_peleas_evento.php">Volver</a>
      </div>
    </form>
  </div>

  <div class="card" style="margin-top:14px">
    <h3 style="margin:0 0 8px 0">Últimas 50 peleas</h3>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>ID</th><th>Azul</th><th>Rojo</th><th>Evento</th><th>Rds</th><th>Fecha</th><th></th>
        </tr></thead>
        <tbody>
        <?php if(!$list): ?>
          <tr><td colspan="7">Sin datos</td></tr>
        <?php else: foreach($list as $p): ?>
          <tr>
            <td>#<?= (int)$p['id'] ?></td>
            <td><?= h($mapN[(int)$p['a']] ?? ('#'.(int)$p['a'])) ?></td>
            <td><?= h($mapN[(int)$p['r']] ?? ('#'.(int)$p['r'])) ?></td>
            <td><?= isset($p['e']) ? (int)$p['e'] : '-' ?></td>
            <td><?= isset($p['n']) ? (int)$p['n'] : '-' ?></td>
            <td><?= h($p['f'] ?? '-') ?></td>
            <td>
              <a class="btn-mini" href="?pelea_id=<?= (int)$p['id'] ?>">Editar</a>
              <a class="btn-mini" href="combate_en_vivo.php?pelea_id=<?= (int)$p['id'] ?>">Ver en vivo</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
