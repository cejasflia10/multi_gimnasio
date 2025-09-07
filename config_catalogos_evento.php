<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ========= Helpers ========= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0;
  if ($q) $q->close();
  return $ok;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql);
  $ok = $r && $r->num_rows>0;
  if($r) $r->close();
  return $ok;
}
function post($k,$def=null){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def; }
function toIntOrNull($v){ return ($v===''||$v===null||!is_numeric($v))?null:(int)$v; }

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf'];

/* ========= Catálogos soportados ========= */
$CATS = [
  'disciplinas_evento' => [
    'label'=>'Disciplinas',
    'known_cols'=>['nombre','orden','activo'],
    'ref_col'=>'disciplina_id'
  ],
  'modalidades_evento' => [
    'label'=>'Modalidades',
    'known_cols'=>['nombre','orden','activo'],
    'ref_col'=>'modalidad_id'
  ],
  'divisiones_evento' => [
    'label'=>'Divisiones',
    'known_cols'=>['nombre','min_edad','max_edad','orden','activo'],
    'ref_col'=>'division_id'
  ],
  'categorias_peso_evento' => [
    'label'=>'Categorías de Peso',
    'known_cols'=>['nombre','sexo','min_peso','max_peso','unidad','orden','activo'],
    'ref_col'=>'categoria_peso_id'
  ],
  'categorias_tecnicas_evento' => [
    'label'=>'Categorías Técnicas',
    'known_cols'=>['nombre','min_peleas','max_peleas','orden','activo'],
    'ref_col'=>'categoria_tecnica_id'
  ],
];
$tabla = $_GET['tabla'] ?? 'disciplinas_evento';
if (!isset($CATS[$tabla])) $tabla = 'disciplinas_evento';
$meta = $CATS[$tabla];
$label = $meta['label'];

/* ========= Cols existentes y orden de edición ========= */
$existing = [];
if (has_table($conexion,$tabla)) {
  $q=$conexion->query("SHOW COLUMNS FROM ".bt($tabla));
  while($r=$q->fetch_assoc()){ $existing[]=$r['Field']; }
  if($q) $q->close();
}
$edit_cols = array_values(array_filter($meta['known_cols'], fn($c)=> in_array($c,$existing,true)));
if (!in_array('nombre',$edit_cols,true) && in_array('nombre',$existing,true)) array_unshift($edit_cols,'nombre'); // asegurar nombre si existe

/* ========= Acciones (crear/editar/eliminar/activar) ========= */
$flash = ['ok'=>'','err'=>''];
function require_csrf(){ global $CSRF; if (!hash_equals($CSRF,(string)($_POST['csrf']??''))) { http_response_code(400); exit('CSRF inválido'); } }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
  require_csrf();
  $accion = $_POST['accion'] ?? '';
  if (!has_table($conexion,$tabla)) {
    $flash['err']="La tabla <b>".h($tabla)."</b> no existe. Creala en la BD. Abajo hay un SQL sugerido.";
  } else {
    if ($accion==='crear') {
      // Build INSERT dinámico
      $cols=[]; $types=''; $vals=[];
      foreach ($edit_cols as $c) {
        $v = $_POST[$c] ?? null;
        if ($v==='') $v=null;
        $cols[] = bt($c);
        if (in_array($c,['orden','min_edad','max_edad','min_peso','max_peso','min_peleas','max_peleas','activo'],true)) { $types.='i'; $vals[] = toIntOrNull($v) ?? 0; }
        else { $types.='s'; $vals[] = $v; }
      }
      if ($cols){
        $ph=rtrim(str_repeat('?,',count($cols)),',');
        $sql="INSERT INTO ".bt($tabla)." (".implode(',',$cols).") VALUES ($ph)";
        if($st=$conexion->prepare($sql)){
          $bind = [$st,$types]; foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
          call_user_func_array('mysqli_stmt_bind_param',$bind);
          $ok=$st->execute(); $st->close();
          $flash[$ok?'ok':'err']=$ok?'Elemento creado.':'No se pudo crear.';
        } else $flash['err']='Error preparando INSERT.';
      }
    }
    elseif ($accion==='actualizar') {
      $id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id>0){
        $sets=[]; $types=''; $vals=[];
        foreach ($edit_cols as $c) {
          $v = $_POST[$c] ?? null; if ($v==='') $v=null;
          $sets[] = bt($c)."=?";
          if (in_array($c,['orden','min_edad','max_edad','min_peso','max_peso','min_peleas','max_peleas','activo'],true)) { $types.='i'; $vals[] = toIntOrNull($v); }
          else { $types.='s'; $vals[] = $v; }
        }
        if ($sets){
          $sql="UPDATE ".bt($tabla)." SET ".implode(', ',$sets)." WHERE id=?";
          $types.='i'; $vals[]=$id;
          if($st=$conexion->prepare($sql)){
            $bind = [$st,$types]; foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
            call_user_func_array('mysqli_stmt_bind_param',$bind);
            $ok=$st->execute(); $st->close();
            $flash[$ok?'ok':'err']=$ok?'Guardado.':'No se pudo guardar.';
          } else $flash['err']='Error preparando UPDATE.';
        }
      }
    }
    elseif ($accion==='eliminar') {
      $id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id>0){
        $tComp = 'competidores_evento';
        $ref_col = $meta['ref_col'];
        $refs = 0;
        if (has_table($conexion,$tComp) && has_col($conexion,$tComp,$ref_col)) {
          if ($st=$conexion->prepare("SELECT COUNT(*) FROM ".bt($tComp)." WHERE ".bt($ref_col)."=?")){
            $st->bind_param('i',$id); $st->execute(); $st->bind_result($refs); $st->fetch(); $st->close();
          }
        }
        if (in_array('activo',$existing,true)) {
          if ($st=$conexion->prepare("UPDATE ".bt($tabla)." SET `activo`=0 WHERE id=?")){
            $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
            $flash[$ok?'ok':'err']=$ok?'Archivado.':'No se pudo archivar.';
          } else $flash['err']='Error preparando archivado.';
        } else {
          if ($refs>0){
            $flash['err']='No se puede eliminar: está referenciado por competidores.';
          } else {
            if ($st=$conexion->prepare("DELETE FROM ".bt($tabla)." WHERE id=? LIMIT 1")){
              $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
              $flash[$ok?'ok':'err']=$ok?'Eliminado.':'No se pudo eliminar.';
            } else $flash['err']='Error preparando DELETE.';
          }
        }
      }
    }
    elseif ($accion==='activar' && in_array('activo',$existing,true)) {
      $id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id>0 && $st=$conexion->prepare("UPDATE ".bt($tabla)." SET `activo`=1 WHERE id=?")){
        $st->bind_param('i',$id); $ok=$st->execute(); $st->close();
        $flash[$ok?'ok':'err']=$ok?'Activado.':'No se pudo activar.';
      }
    }
  }
}

/* ========= Traer datos ========= */
$rows=[]; $colsToShow=[];
if (has_table($conexion,$tabla)) {
  // Mostrar id, nombre y las columnas extras existentes de las conocidas
  $colsToShow = array_values(array_unique(array_merge(['id','nombre'],$edit_cols)));
  $select = implode(',', array_map(fn($c)=>bt($c), $colsToShow));
  if ($r=$conexion->query("SELECT $select FROM ".bt($tabla)." ORDER BY COALESCE(orden,9999), nombre, id")){
    while($row=$r->fetch_assoc()){ $rows[]=$row; }
    $r->close();
  }
}

/* ========= SQL sugerido si no existe la tabla ========= */
function ddl_hint($t){
  switch($t){
    case 'disciplinas_evento':
      return "CREATE TABLE disciplinas_evento (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, orden INT DEFAULT 0, activo TINYINT(1) DEFAULT 1);";
    case 'modalidades_evento':
      return "CREATE TABLE modalidades_evento (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, orden INT DEFAULT 0, activo TINYINT(1) DEFAULT 1);";
    case 'divisiones_evento':
      return "CREATE TABLE divisiones_evento (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, min_edad INT NULL, max_edad INT NULL, orden INT DEFAULT 0, activo TINYINT(1) DEFAULT 1);";
    case 'categorias_peso_evento':
      return "CREATE TABLE categorias_peso_evento (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, sexo ENUM('masculino','femenino') NULL, min_peso DECIMAL(6,2) NULL, max_peso DECIMAL(6,2) NULL, unidad VARCHAR(10) DEFAULT 'kg', orden INT DEFAULT 0, activo TINYINT(1) DEFAULT 1);";
    case 'categorias_tecnicas_evento':
      return "CREATE TABLE categorias_tecnicas_evento (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(50) NOT NULL, min_peleas INT NULL, max_peleas INT NULL, orden INT DEFAULT 0, activo TINYINT(1) DEFAULT 1);";
    default: return '';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>⚙️ Configurar catálogos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0b1115; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
    --card:#0f1720; --bd:#1f2a33; --line:#22313f; --accent:#0e7ad1;
    --ok:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
    --bad:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  a{color:var(--brand);text-decoration:none}
  .wrap{max-width:980px;margin:0 auto;padding:14px}
  .tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
  .tab{padding:8px 10px;border:1px solid var(--bd);border-radius:10px;background:#111a24;color:var(--fg)}
  .tab.active{background:var(--accent);border-color:#27455c;color:#fff}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:12px;margin-top:10px}
  .row{display:grid;grid-template-columns:1fr;gap:10px}
  @media (min-width:720px){ .row{grid-template-columns:1fr 1fr} }
  label{font-size:12px;color:#cfe7ff}
  input,select{width:100%;padding:10px;border-radius:10px;border:1px solid var(--line);background:#111a24;color:var(--fg)}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{padding:8px;border-bottom:1px solid #1c2a36;font-size:14px}
  th{color:#9ecbff;background:#0f1a26;position:sticky;top:0}
  .btn{padding:10px 12px;border-radius:10px;border:1px solid #27455c;background:var(--accent);color:#fff;cursor:pointer}
  .btn.ghost{background:#111a24;color:#e6eef4}
  .btn.warn{background:#5e2626;border-color:#5e2626}
  .note{font-size:12px;color:#9bb6d1}
  .alert{padding:10px;border-radius:10px;margin:10px 0}
  .ok{background:var(--ok);border:1px solid var(--okbd);color:var(--oktx)}
  .err{background:var(--bad);border:1px solid var(--badbd);color:var(--badt)}
  form.inline{display:inline}
</style>
</head>
<body>
<div class="wrap">
  <h2>⚙️ Configurar catálogos</h2>
  <div class="tabs">
    <?php foreach($CATS as $t=>$m): ?>
      <a class="tab <?= $t===$tabla?'active':''; ?>" href="?tabla=<?= h($t) ?>"><?= h($m['label']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($flash['ok']): ?><div class="alert ok"><?= $flash['ok'] ?></div><?php endif; ?>
  <?php if ($flash['err']): ?><div class="alert err"><?= $flash['err'] ?></div><?php endif; ?>

  <div class="card">
    <h3 style="margin:0 0 8px 0"><?= h($label) ?></h3>

    <?php if (!has_table($conexion,$tabla)): ?>
      <div class="alert err">La tabla <b><?= h($tabla) ?></b> no existe. Creala en tu base de datos con el siguiente SQL:</div>
      <pre class="note" style="white-space:pre-wrap"><?= h(ddl_hint($tabla)) ?></pre>
    <?php else: ?>

      <!-- Crear nuevo -->
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="accion" value="crear">
        <div class="row">
          <?php foreach($edit_cols as $c): ?>
            <div>
              <label><?= h(ucwords(str_replace('_',' ',$c))) ?></label>
              <?php if (in_array($c,['min_edad','max_edad','min_peso','max_peso','min_peleas','max_peleas','orden'],true)): ?>
                <input type="number" name="<?= h($c) ?>" inputmode="numeric" placeholder="<?= h($c) ?>">
              <?php elseif ($c==='sexo'): ?>
                <select name="sexo">
                  <option value="">(todos)</option>
                  <option value="masculino">Masculino</option>
                  <option value="femenino">Femenino</option>
                </select>
              <?php elseif ($c==='unidad'): ?>
                <select name="unidad">
                  <option value="kg">kg</option>
                  <option value="lb">lb</option>
                </select>
              <?php elseif ($c==='activo'): ?>
                <select name="activo">
                  <option value="1">Activo</option>
                  <option value="0">Inactivo</option>
                </select>
              <?php else: ?>
                <input type="text" name="<?= h($c) ?>" placeholder="<?= h($c) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:8px"><button class="btn">➕ Agregar</button></div>
      </form>

      <!-- Listado / edición -->
      <div class="table-wrap" style="overflow-x:auto;margin-top:12px">
        <table>
          <thead>
            <tr>
              <?php foreach($colsToShow as $c): ?><th><?= h(strtoupper($c)) ?></th><?php endforeach; ?>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="<?= count($colsToShow)+1 ?>" class="note">Sin registros.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <form method="post" class="inline" onsubmit="return confirm('Guardar cambios #<?= (int)$r['id'] ?>?');">
                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <?php foreach($colsToShow as $c): ?>
                  <td>
                    <?php if ($c==='id'): ?>
                      <b>#<?= (int)$r['id'] ?></b>
                    <?php elseif (in_array($c,['min_edad','max_edad','min_peso','max_peso','min_peleas','max_peleas','orden'],true)): ?>
                      <input type="number" name="<?= h($c) ?>" value="<?= h((string)$r[$c]) ?>" style="max-width:100px" inputmode="numeric">
                    <?php elseif ($c==='sexo'): ?>
                      <select name="sexo">
                        <option value="" <?= ($r[$c]===''||$r[$c]===null)?'selected':'' ?>>(todos)</option>
                        <option value="masculino" <?= ($r[$c]==='masculino')?'selected':'' ?>>Masculino</option>
                        <option value="femenino" <?= ($r[$c]==='femenino')?'selected':'' ?>>Femenino</option>
                      </select>
                    <?php elseif ($c==='unidad'): ?>
                      <select name="unidad">
                        <option value="kg" <?= ($r[$c]==='kg')?'selected':'' ?>>kg</option>
                        <option value="lb" <?= ($r[$c]==='lb')?'selected':'' ?>>lb</option>
                      </select>
                    <?php elseif ($c==='activo'): ?>
                      <select name="activo">
                        <option value="1" <?= ((string)$r[$c]==='1')?'selected':'' ?>>Activo</option>
                        <option value="0" <?= ((string)$r[$c]==='0')?'selected':'' ?>>Inactivo</option>
                      </select>
                    <?php else: ?>
                      <input type="text" name="<?= h($c) ?>" value="<?= h((string)$r[$c]) ?>">
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td>
                  <button class="btn">💾 Guardar</button>
                </td>
              </form>
              <td>
                <form method="post" class="inline" onsubmit="return confirm('¿Eliminar/archivar #<?= (int)$r['id'] ?>?');">
                  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <?php if (in_array('activo',$existing,true)): ?>
                    <input type="hidden" name="accion" value="<?= ((string)($r['activo']??'1'))==='1'?'eliminar':'activar' ?>">
                    <?php if (((string)($r['activo']??'1'))==='1'): ?>
                      <button class="btn warn">🗑 Archivar</button>
                    <?php else: ?>
                      <button class="btn ghost">♻️ Activar</button>
                    <?php endif; ?>
                  <?php else: ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <button class="btn warn">🗑 Eliminar</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="note" style="margin-top:8px">
        * Si existe <code>activo</code>, “Eliminar” archiva. Sin <code>activo</code>, si hay referencias en <code>competidores_evento</code>, se bloquea la eliminación.
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
