<?php
/* ============================================================
   categorias_evento_admin.php
   CRUD simple para la tabla categorias_evento
   Campos: id, nombre, peso_min, peso_max, genero, edad_min, edad_max
   Depende de: conexion.php (mysqli $conexion)
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD (revisá conexion.php).');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '{$t}'");
  $ok = ($q && $q->num_rows>0); if($q) $q->close(); return $ok;
}
function dparse($v): ?float {
  $s = trim((string)$v);
  if ($s==='') return null;
  if (strpos($s, ',') !== false && strpos($s, '.') === false) $s = str_replace(',', '.', $s); // admite "70,50"
  if (!is_numeric($s)) return null;
  return (float)$s;
}
function ok_range(?float $a, ?float $b): bool { return $a!==null && $b!==null && $a <= $b; }
function is_intlike($v): ?int {
  $s = trim((string)$v);
  if ($s==='') return null;
  if (!preg_match('/^-?\d+$/', $s)) return null;
  return (int)$s;
}
function flash($k,$v){ $_SESSION[$k]=$v; }
function get_flash($k){ $v=$_SESSION[$k]??''; unset($_SESSION[$k]); return $v; }

$flash_ok    = get_flash('flash_ok');
$flash_error = get_flash('flash_error');

/* ===== Guardas (POST) ===== */
$GENS = ['masculino','femenino','mixto'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action   = $_POST['action'] ?? '';
  try {
    if ($action==='create' || $action==='update') {
      $id       = is_intlike($_POST['id'] ?? '');
      $nombre   = trim((string)($_POST['nombre']??''));
      $peso_min = dparse($_POST['peso_min']??'');
      $peso_max = dparse($_POST['peso_max']??'');
      $genero   = strtolower(trim((string)($_POST['genero']??'mixto')));
      $edad_min = is_intlike($_POST['edad_min']??'0');
      $edad_max = is_intlike($_POST['edad_max']??'99');

      if ($nombre==='')                          throw new RuntimeException('El nombre es obligatorio.');
      if ($peso_min===null || $peso_max===null)  throw new RuntimeException('Cargá peso mínimo y máximo.');
      if (!ok_range($peso_min,$peso_max))        throw new RuntimeException('peso_min no puede ser mayor que peso_max.');
      if (!in_array($genero,$GENS,true))         $genero = 'mixto';
      if ($edad_min===null || $edad_max===null)  throw new RuntimeException('Cargá edad mínima y máxima (enteros).');
      if ($edad_min < 0 || $edad_max < 0)        throw new RuntimeException('Las edades no pueden ser negativas.');
      if ($edad_min > $edad_max)                 throw new RuntimeException('edad_min no puede ser mayor que edad_max.');

      if ($action==='create') {
        $sql = "INSERT INTO categorias_evento (nombre, peso_min, peso_max, genero, edad_min, edad_max)
                VALUES (?,?,?,?,?,?)";
        $st = $conexion->prepare($sql);
        if (!$st) throw new RuntimeException('Error INSERT: '.$conexion->error);
        $st->bind_param('sddsii', $nombre, $peso_min, $peso_max, $genero, $edad_min, $edad_max);
        if (!$st->execute()) throw new RuntimeException('No se pudo insertar: '.$st->error);
        $st->close();
        flash('flash_ok','✅ Categoría creada (#'.$conexion->insert_id.').');

      } else { // update
        if (!$id || $id<=0) throw new RuntimeException('ID inválido para editar.');
        $sql="UPDATE categorias_evento
                 SET nombre=?, peso_min=?, peso_max=?, genero=?, edad_min=?, edad_max=?
               WHERE id=?";
        $st=$conexion->prepare($sql);
        if(!$st) throw new RuntimeException('Error UPDATE: '.$conexion->error);
        $st->bind_param('sddsiii', $nombre, $peso_min, $peso_max, $genero, $edad_min, $edad_max, $id);
        if(!$st->execute()) throw new RuntimeException('No se pudo actualizar: '.$st->error);
        $st->close();
        flash('flash_ok','✅ Categoría editada (#'.$id.').');
      }
    }

    if ($action==='delete') {
      $id = is_intlike($_POST['id'] ?? '');
      if (!$id || $id<=0) throw new RuntimeException('ID inválido para eliminar.');
      $st=$conexion->prepare("DELETE FROM categorias_evento WHERE id=?");
      if(!$st) throw new RuntimeException('Error DELETE: '.$conexion->error);
      $st->bind_param('i',$id);
      if(!$st->execute()) throw new RuntimeException('No se pudo eliminar: '.$st->error);
      $st->close();
      flash('flash_ok','🗑️ Categoría eliminada (#'.$id.').');
    }

  } catch(Throwable $e){
    flash('flash_error','❌ '.$e->getMessage());
  }
  header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit;
}

/* ===== Chequeo tabla / Listado ===== */
if (!has_table($conexion,'categorias_evento')) { http_response_code(500); exit('❌ Falta la tabla: categorias_evento'); }

$rows=[];
$sql="SELECT id, nombre, peso_min, peso_max, genero, edad_min, edad_max
      FROM categorias_evento
      ORDER BY genero, peso_min, nombre, id";
if ($r=$conexion->query($sql)) { while($row=$r->fetch_assoc()) $rows[]=$row; $r->close(); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>⚖️ Categorías de peso — Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0b1115; --fg:#e6eef4; --muted:#96a7b8; --border:#1f2a33;
    --card:#0f1720; --okbg:#0f251b; --okbd:#164b31; --okfg:#b6f3d1;
    --badbg:#2a1414; --badbd:#5e2626; --badfg:#ffb4b4; --btn:#0e7ad1;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  .wrap{max-width:1100px;margin:0 auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:12px;margin:12px 0}
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
  .btn{background:var(--btn);color:#fff;border:0;border-radius:10px;padding:10px 14px;cursor:pointer}
  .btn2{border:1px solid var(--border);background:transparent;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer}
  label{font-size:12px;color:#cfe7ff;display:block;margin-bottom:4px}
  input,select{border:1px solid var(--border);border-radius:10px;padding:8px 10px;background:#0f1b25;color:var(--fg)}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--border);padding:8px 10px;text-align:left}
  th{background:#0b1520}
  .ok{background:var(--okbg);border:1px solid var(--okbd);color:var(--okfg);padding:10px;border-radius:10px}
  .bad{background:var(--badbg);border:1px solid var(--badbd);color:var(--badfg);padding:10px;border-radius:10px}
  .muted{color:var(--muted)}
  .num{width:120px}
  .txt{min-width:220px}
  .gen{min-width:160px}
  .act{white-space:nowrap}
  form.inline{display:inline}
</style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>
<div class="wrap">

  <h2 style="margin:6px 0 12px">⚖️ Categorías de peso — Administración</h2>

  <?php if ($flash_ok): ?><div class="ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if ($flash_error): ?><div class="bad"><?= h($flash_error) ?></div><?php endif; ?>

  <!-- Alta rápida (AHORA CON EDAD MIN/MAX VISIBLES) -->
  <div class="card">
    <h3 style="margin:0 0 8px">➕ Agregar categoría</h3>
    <form method="post" action="">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div>
          <label>Nombre*</label>
          <input class="txt" type="text" name="nombre" placeholder="Ej.: Ligero" required>
        </div>
        <div>
          <label>Peso mínimo (kg)*</label>
          <input class="num" type="text" name="peso_min" placeholder="Ej.: 60,00" inputmode="decimal" required>
        </div>
        <div>
          <label>Peso máximo (kg)*</label>
          <input class="num" type="text" name="peso_max" placeholder="Ej.: 63,50" inputmode="decimal" required>
        </div>
        <div>
          <label>Género*</label>
          <select class="gen" name="genero">
            <option value="mixto">Mixto</option>
            <option value="masculino">Masculino</option>
            <option value="femenino">Femenino</option>
          </select>
        </div>
        <div>
          <label>Edad mínima*</label>
          <input class="num" type="number" name="edad_min" value="0" min="0" required>
        </div>
        <div>
          <label>Edad máxima*</label>
          <input class="num" type="number" name="edad_max" value="99" min="0" required>
        </div>
        <div>
          <label>&nbsp;</label>
          <button class="btn" type="submit">Guardar</button>
        </div>
      </div>
    </form>
    <div class="muted" style="margin-top:6px">
      • Los pesos aceptan coma o punto (ej.: 70,50 ó 70.50).<br>
      • Validamos que <b>peso_min ≤ peso_max</b> y <b>edad_min ≤ edad_max</b>.
    </div>
  </div>

  <!-- Listado + edición inline -->
  <div class="card">
    <h3 style="margin:0 0 8px">📋 Listado</h3>
    <table>
      <thead>
        <tr>
          <th style="width:60px">ID</th>
          <th>Nombre</th>
          <th>Peso min</th>
          <th>Peso max</th>
          <th>Género</th>
          <th>Edad min</th>
          <th>Edad max</th>
          <th class="act">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="muted" style="text-align:center">No hay categorías cargadas.</td></tr>
      <?php else: foreach ($rows as $c): ?>
        <tr>
          <td>#<?= (int)$c['id'] ?></td>
          <td>
            <form method="post" class="inline" action="">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input class="txt" type="text" name="nombre" value="<?= h($c['nombre']) ?>" required>
          </td>
          <td><input class="num" type="text" name="peso_min" value="<?= h(number_format((float)$c['peso_min'],2,',','')) ?>" inputmode="decimal" required></td>
          <td><input class="num" type="text" name="peso_max" value="<?= h(number_format((float)$c['peso_max'],2,',','')) ?>" inputmode="decimal" required></td>
          <td>
            <select class="gen" name="genero">
              <option value="mixto"     <?= ($c['genero']==='mixto')?'selected':''; ?>>Mixto</option>
              <option value="masculino" <?= ($c['genero']==='masculino')?'selected':''; ?>>Masculino</option>
              <option value="femenino"  <?= ($c['genero']==='femenino')?'selected':''; ?>>Femenino</option>
            </select>
          </td>
          <td><input class="num" type="number" name="edad_min" value="<?= (int)$c['edad_min'] ?>" min="0" required></td>
          <td><input class="num" type="number" name="edad_max" value="<?= (int)$c['edad_max'] ?>" min="0" required></td>
          <td class="act">
              <button class="btn" type="submit">💾 Guardar</button>
            </form>
            <form method="post" class="inline" action="" onsubmit="return confirm('¿Eliminar categoría #<?= (int)$c['id'] ?>?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn2" type="submit">🗑️ Eliminar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
