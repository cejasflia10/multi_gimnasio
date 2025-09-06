<?php
/* =========================================================================
   ver_tipos_entrada.php — Configurar Tipos de Entradas del Evento (CRUD)
   - Arregla FK erróneo: tickets_tipos(evento_id) -> eventos_publicos(id)
     => lo elimina si no apunta a eventos_deportivos y deja INDEX
   - Evita duplicados en índice único (por nombre o por nombre_slug)
   ========================================================================= */
if (session_status() === PHP_SESSION_NONE) session_start();

/* (Opcional) Guardia de sesión */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'ver_tipos_entrada.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

/* -------- Conexión -------- */
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* -------- Helpers -------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function post($k,$def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def; }
function to_int($v,$min=0,$max=null){ if(!is_numeric($v)) return $min; $n=(int)$v; if($n<$min)$n=$min; if($max!==null && $n>$max)$n=$max; return $n; }
function to_money($v){ $v=str_replace(',','.',$v); return is_numeric($v)? number_format((float)$v,2,'.','') : '0.00'; }
function stmt_or_error($stmt, $sql, &$flash_err){
  global $conexion;
  if ($stmt === false) {
    $flash_err = "SQL prepare error: ".h($conexion->error)." <br><small>SQL: ".h($sql)."</small>";
    return false;
  }
  return $stmt;
}

/* ---- Unicidad por índice (nombre vs slug) ---- */
function unique_index_cols(mysqli $db, string $table, string $index): array {
  $t=$db->real_escape_string($table); $i=$db->real_escape_string($index);
  $sql="SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND INDEX_NAME='{$i}'
        ORDER BY SEQ_IN_INDEX ASC";
  $cols=[]; if($r=$db->query($sql)){ while($row=$r->fetch_assoc()){ $cols[]=$row['COLUMN_NAME']; } $r->close(); }
  return $cols;
}
function slugify($str): string {
  $s=(string)$str;
  if (function_exists('iconv')) {
    $tmp = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    if ($tmp!==false) $s=$tmp;
  }
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/','-',$s);
  $s = trim($s,'-');
  return $s ?: 'tipo';
}
function ensure_unique_nombre_y_slug(mysqli $db, int $evento_id, string $nombre): array {
  $hasSlug = has_col($db,'tickets_tipos','nombre_slug');
  $idxCols = unique_index_cols($db,'tickets_tipos','uq_evento_nombre'); // puede no existir
  $uniqueOnSlug = $hasSlug && in_array('nombre_slug', $idxCols, true);

  $baseNombre = trim($nombre);
  $baseSlug   = slugify($baseNombre);
  $tryNombre  = $baseNombre;
  $trySlug    = $baseSlug;
  $suf = 1;

  while (true) {
    if ($uniqueOnSlug) {
      $sql="SELECT 1 FROM tickets_tipos WHERE evento_id=? AND nombre_slug=? LIMIT 1";
      $st=$db->prepare($sql); $st->bind_param('is',$evento_id,$trySlug); $st->execute();
      $exists = ($st->get_result()->num_rows>0); $st->close();
      if (!$exists) break;
      $suf++; $trySlug = $baseSlug.'-'.$suf; // mantenemos nombre visible
    } else {
      $sql="SELECT 1 FROM tickets_tipos WHERE evento_id=? AND nombre=? LIMIT 1";
      $st=$db->prepare($sql); $st->bind_param('is',$evento_id,$tryNombre); $st->execute();
      $exists = ($st->get_result()->num_rows>0); $st->close();
      if (!$exists) break;
      $suf++; $tryNombre = $baseNombre.' ('.$suf.')';
      $trySlug = slugify($tryNombre);
    }
  }
  return [$tryNombre, $trySlug, $uniqueOnSlug, $hasSlug];
}

/* -------- Evento -------- */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_POST['evento_id'] ?? 0);
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }

/* Título del evento */
$flash_err = ''; $flash_ok = '';
$sqlEv = "SELECT `id`, `titulo`, `fecha` FROM `eventos_deportivos` WHERE `id`=? LIMIT 1";
$st = $conexion->prepare($sqlEv);
if (!$st) { $flash_err = "No pude leer el evento. Error: ".h($conexion->error)." <br><small>SQL: ".h($sqlEv)."</small>"; $evento=false; }
else { $st->bind_param('i',$evento_id); $st->execute(); $evento=$st->get_result()->fetch_assoc(); $st->close(); }
if (!$evento){ http_response_code(404); echo "<div style='padding:16px;color:#f99;background:#300;border:1px solid #600;border-radius:8px'>Evento no encontrado.</div>"; exit; }

/* -------- FIX FK erróneo en tickets_tipos.evento_id -------- */
function drop_wrong_fk_if_needed(mysqli $db, &$flash_err){
  $sql = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
          FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME='tickets_tipos'
            AND COLUMN_NAME='evento_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
          LIMIT 1";
  if (!($r = $db->query($sql))) return;
  if (!($fk = $r->fetch_assoc())) { $r->close(); return; }
  $r->close();

  $ref = (string)$fk['REFERENCED_TABLE_NAME'];
  $cst = (string)$fk['CONSTRAINT_NAME'];
  if ($ref !== 'eventos_deportivos') {
    $q1 = "ALTER TABLE `tickets_tipos` DROP FOREIGN KEY `{$cst}`";
    if (!$db->query($q1)) {
      $flash_err .= ($flash_err?'<br>':'')
        ."No pude eliminar el FOREIGN KEY `{$cst}` que apunta a `{$ref}`. "
        ."Ejecutá manualmente: <code>".h($q1)."</code> — Error: ".h($db->error);
      return;
    }
    @ $db->query("ALTER TABLE `tickets_tipos` ADD INDEX (`evento_id`)");
  }
}
drop_wrong_fk_if_needed($conexion, $flash_err);

/* -------- Tabla y migraciones (sin FK) -------- */
$create = "CREATE TABLE IF NOT EXISTS `tickets_tipos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evento_id` INT NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `nombre_slug` VARCHAR(120) NULL,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_disponible` INT NOT NULL DEFAULT 0,
  `stock_total` INT NOT NULL DEFAULT 0,
  `max_por_compra` INT NOT NULL DEFAULT 10,
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `canal` ENUM('online','taquilla','fisica','todos') NOT NULL DEFAULT 'todos',
  `etapa` ENUM('preventa','anticipada','general','puerta') NOT NULL DEFAULT 'general',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!$conexion->query($create)) {
  $flash_err .= ($flash_err?'<br>':'')."No pude asegurar la tabla `tickets_tipos`: ".h($conexion->error);
}

/* Migraciones suaves */
$migs = [
  ['nombre_slug',      "ALTER TABLE `tickets_tipos` ADD COLUMN `nombre_slug` VARCHAR(120) NULL AFTER `nombre`"],
  ['precio',           "ALTER TABLE `tickets_tipos` ADD COLUMN `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `nombre_slug`"],
  ['stock_disponible', "ALTER TABLE `tickets_tipos` ADD COLUMN `stock_disponible` INT NOT NULL DEFAULT 0 AFTER `precio`"],
  ['stock_total',      "ALTER TABLE `tickets_tipos` ADD COLUMN `stock_total` INT NOT NULL DEFAULT 0 AFTER `stock_disponible`"],
  ['max_por_compra',   "ALTER TABLE `tickets_tipos` ADD COLUMN `max_por_compra` INT NOT NULL DEFAULT 10 AFTER `stock_total`"],
  ['visible',          "ALTER TABLE `tickets_tipos` ADD COLUMN `visible` TINYINT(1) NOT NULL DEFAULT 1 AFTER `max_por_compra`"],
  ['canal',            "ALTER TABLE `tickets_tipos` ADD COLUMN `canal` ENUM('online','taquilla','fisica','todos') NOT NULL DEFAULT 'todos' AFTER `visible`"],
  ['etapa',            "ALTER TABLE `tickets_tipos` ADD COLUMN `etapa` ENUM('preventa','anticipada','general','puerta') NOT NULL DEFAULT 'general' AFTER `canal`"],
];
foreach($migs as [$col,$sql]){
  if (!has_col($conexion,'tickets_tipos',$col)) { @$conexion->query($sql); }
}
/* updated_at compatible */
if (!has_col($conexion,'tickets_tipos','updated_at')) {
  $sqlUpd = "ALTER TABLE `tickets_tipos`
             ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`";
  if (!$conexion->query($sqlUpd)) {
    @ $conexion->query("ALTER TABLE `tickets_tipos`
                        ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`");
  }
}
/* Asegurar DEFAULT y NOT NULL de stock_total */
@ $conexion->query("ALTER TABLE `tickets_tipos` MODIFY `stock_total` INT NOT NULL DEFAULT 0");

/* -------- Acciones (POST) -------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = post('accion');
  try {
    if ($accion === 'crear') {
      $nombre = post('nombre');
      $precio = to_money(post('precio','0'));
      $stock  = to_int(post('stock','0'),0,1000000);
      $stock_total = to_int(post('stock_total',$stock),0,1000000);
      $maxxc  = to_int(post('max_por_compra','10'),0,10000);
      $visible= isset($_POST['visible']) ? 1 : 0;
      $canal  = post('canal','todos');
      $etapa  = post('etapa','general');

      $canales = ['online','taquilla','fisica','todos'];
      $etapas  = ['preventa','anticipada','general','puerta'];
      if(!in_array($canal,$canales,true)) $canal='todos';
      if(!in_array($etapa,$etapas,true))  $etapa='general';
      if($nombre==='') throw new Exception('El nombre es obligatorio.');

      // Unicidad (nombre vs slug) según índice
      list($nombreOK, $slugOK, $uniqueOnSlug, $hasSlug) = ensure_unique_nombre_y_slug($conexion, $evento_id, $nombre);

      // Armado dinámico de columnas y placeholders (OJO: 7 campos al final)
      $cols  = "`evento_id`,`nombre`";
      $ph    = "?,?";
      $types = "is";
      $args  = [$evento_id, $nombreOK];

      if ($hasSlug) { // si existe la columna, guardamos el slug
        $cols  .= ",`nombre_slug`";
        $ph    .= ",?";
        $types .= "s";
        $args[] = $slugOK;
      }

      // Estos son SIEMPRE 7 columnas extra
      $cols  .= ",`precio`,`stock_disponible`,`stock_total`,`max_por_compra`,`visible`,`canal`,`etapa`";
      $ph    .= ",?,?,?,?,?,?,?";             // ← 7 placeholders (fix del bug)
      $types .= "siiiiss";                     // precio(s), stock(i), total(i), max(i), visible(i), canal(s), etapa(s)
      $args[] = $precio;
      $args[] = $stock;
      $args[] = $stock_total;
      $args[] = $maxxc;
      $args[] = $visible;
      $args[] = $canal;
      $args[] = $etapa;

      $sql="INSERT INTO `tickets_tipos` ($cols) VALUES ($ph)";
      $st = stmt_or_error($conexion->prepare($sql), $sql, $flash_err);
      if (!$st) throw new Exception('No se pudo preparar INSERT.');

      $bind = [$types];
      foreach($args as $k=>$_){ $bind[]=&$args[$k]; }
      call_user_func_array([$st,'bind_param'],$bind);

      if(!$st->execute()){
        throw new Exception('Exec INSERT: '.$conexion->error);
      }
      $st->close();
      $flash_ok='Tipo de entrada creado'.($nombreOK!==$nombre?' como “'.h($nombreOK).'”':'').'.';
    }

    if ($accion === 'actualizar') {
      $id = (int)post('id','0');
      $nombre = post('nombre');
      $precio = to_money(post('precio','0'));
      $stock  = to_int(post('stock','0'),0,1000000);
      $stock_total = to_int(post('stock_total',$stock),0,1000000);
      $maxxc  = to_int(post('max_por_compra','10'),0,10000);
      $visible= isset($_POST['visible']) ? 1 : 0;
      $canal  = post('canal','todos');
      $etapa  = post('etapa','general');

      $canales = ['online','taquilla','fisica','todos'];
      $etapas  = ['preventa','anticipada','general','puerta'];
      if(!in_array($canal,$canales,true)) $canal='todos';
      if(!in_array($etapa,$etapas,true))  $etapa='general';
      if($id<=0) throw new Exception('ID inválido.');
      if($nombre==='') throw new Exception('El nombre es obligatorio.');

      // Unicidad (ajustar nombre/slug si choca)
      list($nombreOK, $slugOK, $uniqueOnSlug, $hasSlug) = ensure_unique_nombre_y_slug($conexion, $evento_id, $nombre);

      if ($hasSlug) {
        $sql="UPDATE `tickets_tipos`
              SET `nombre`=?, `nombre_slug`=?, `precio`=?, `stock_disponible`=?, `stock_total`=?, `max_por_compra`=?, `visible`=?, `canal`=?, `etapa`=?
              WHERE `id`=? AND `evento_id`=?";
        $st = stmt_or_error($conexion->prepare($sql), $sql, $flash_err);
        if (!$st) throw new Exception('No se pudo preparar UPDATE.');
        // tipos: s s s i i i i s s i i
        $st->bind_param('sssiiiissii', $nombreOK, $slugOK, $precio, $stock, $stock_total, $maxxc, $visible, $canal, $etapa, $id, $evento_id);
      } else {
        $sql="UPDATE `tickets_tipos`
              SET `nombre`=?, `precio`=?, `stock_disponible`=?, `stock_total`=?, `max_por_compra`=?, `visible`=?, `canal`=?, `etapa`=?
              WHERE `id`=? AND `evento_id`=?";
        $st = stmt_or_error($conexion->prepare($sql), $sql, $flash_err);
        if (!$st) throw new Exception('No se pudo preparar UPDATE.');
        // tipos: s s i i i i s s i i
        $st->bind_param('ssiiiissii', $nombreOK, $precio, $stock, $stock_total, $maxxc, $visible, $canal, $etapa, $id, $evento_id);
      }

      if(!$st->execute()){ throw new Exception('Exec UPDATE: '.$conexion->error); }
      $st->close();
      $flash_ok='Tipo de entrada actualizado'.($nombreOK!==$nombre?' como “'.h($nombreOK).'”':'').'.';
    }

    if ($accion === 'eliminar') {
      $id = (int)post('id','0');
      if ($id<=0) throw new Exception('ID inválido.');

      // Bloqueo si hay tickets emitidos
      $sqlC="SELECT COUNT(*) c FROM `tickets` WHERE `tipo_id`=?";
      $stc = stmt_or_error($conexion->prepare($sqlC), $sqlC, $flash_err);
      if ($stc){ $stc->bind_param('i',$id); $stc->execute(); $rc=$stc->get_result()->fetch_assoc(); $stc->close();
        if ((int)($rc['c'] ?? 0) > 0) throw new Exception('No se puede eliminar: existen tickets emitidos de este tipo.');
      }

      $sql="DELETE FROM `tickets_tipos` WHERE `id`=? AND `evento_id`=?";
      $std = stmt_or_error($conexion->prepare($sql), $sql, $flash_err);
      if (!$std) throw new Exception('No se pudo preparar DELETE.');
      $std->bind_param('ii',$id,$evento_id);
      if(!$std->execute()){ throw new Exception('Exec DELETE: '.$conexion->error); }
      $std->close();
      $flash_ok='Tipo de entrada eliminado.';
    }
  } catch(Exception $e) {
    $flash_err = ($flash_err? $flash_err.'<br>':'') . h($e->getMessage());
  }
}

/* -------- Cargar tipos -------- */
$hasUpdated    = has_col($conexion,'tickets_tipos','updated_at');
$selUpdated    = $hasUpdated ? "`updated_at`" : "NULL AS `updated_at`";

$sqlList = "SELECT `id`,`nombre`,`precio`,`stock_disponible`,`stock_total`,`max_por_compra`,`visible`,`canal`,`etapa`, $selUpdated
            FROM `tickets_tipos` WHERE `evento_id`=? ORDER BY `precio` ASC, `id` ASC";
$st = $conexion->prepare($sqlList);
if (!$st) {
  $flash_err = ($flash_err?'<br>':'')."SQL prepare error: ".h($conexion->error)." <br><small>SQL: ".h($sqlList)."</small>";
  $tipos = [];
} else {
  $st->bind_param('i',$evento_id); $st->execute(); $tipos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Configurar entradas — <?= h($evento['titulo']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0b1115; --card:#0f1720; --bd:#1f2a33; --tx:#e6eef4; --mut:#9ecbff; --btn:#0e7ad1;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1; --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    body{margin:0;background:var(--bg);color:var(--tx);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:18px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:980px){.grid{grid-template-columns:1fr}}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:var(--btn);color:#fff;text-decoration:none;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f}
    input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:var(--tx)}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #1c2a36;padding:8px;text-align:left}
    th{color:var(--mut)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #3b4b5a;font-size:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <?php @include __DIR__.'/menu_eventos.php'; ?>

    <div style="margin-bottom:10px">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
    </div>

    <h2 style="margin:0 0 6px">🎟️ Configurar tipos de entradas — <?= h($evento['titulo']) ?></h2>
    <div class="pill">Evento #<?= (int)$evento_id ?></div>
    <?php if($flash_ok): ?><div class="ok"><?= $flash_ok ?></div><?php endif; ?>
    <?php if($flash_err): ?><div class="bad"><?= $flash_err ?></div><?php endif; ?>

    <!-- Crear nuevo tipo -->
    <div class="card" style="margin-top:10px">
      <h3 style="margin:0 0 8px">➕ Nuevo tipo</h3>
      <form method="post" action="">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
        <div class="grid">
          <div>
            <label>Nombre</label>
            <input type="text" name="nombre" placeholder="Ej.: General, VIP, Ringside, etc." required>
          </div>
          <div>
            <label>Precio</label>
            <input type="text" name="precio" placeholder="0.00" inputmode="decimal">
          </div>
          <div>
            <label>Stock disponible</label>
            <input type="number" name="stock" min="0" step="1" value="0">
          </div>
          <div>
            <label>Stock total (capacidad)</label>
            <input type="number" name="stock_total" min="0" step="1" placeholder="Si lo dejás vacío usa el stock disponible">
          </div>
          <div>
            <label>Máx. por compra</label>
            <input type="number" name="max_por_compra" min="0" step="1" value="10">
          </div>
          <div>
            <label>Canal</label>
            <select name="canal">
              <option value="todos">Todos</option>
              <option value="online">Online</option>
              <option value="taquilla">Taquilla</option>
              <option value="fisica">Física (preventa)</option>
            </select>
          </div>
          <div>
            <label>Etapa</label>
            <select name="etapa">
              <option value="general">General</option>
              <option value="preventa">Preventa</option>
              <option value="anticipada">Anticipada</option>
              <option value="puerta">Puerta</option>
            </select>
          </div>
          <div style="display:flex;align-items:flex-end;gap:8px">
            <label style="display:flex;align-items:center;gap:6px">
              <input type="checkbox" name="visible" checked> Visible
            </label>
          </div>
        </div>
        <div style="margin-top:10px">
          <button class="btn" type="submit">Crear tipo</button>
        </div>
      </form>
    </div>

    <!-- Listado y edición inline -->
    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Tipos existentes</h3>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Nombre</th>
              <th>Precio</th>
              <th>Stock disp.</th>
              <th>Stock total</th>
              <th>Máx/compra</th>
              <th>Canal</th>
              <th>Etapa</th>
              <th>Visible</th>
              <th>Actualizado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $tipos = $tipos ?? [];
          if(!$tipos): ?>
            <tr><td colspan="11" style="color:#9ecbff">No hay tipos cargados.</td></tr>
          <?php else: foreach($tipos as $t): ?>
            <tr>
              <form method="post" action="">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <td><?= (int)$t['id'] ?></td>
                <td><input name="nombre" value="<?= h($t['nombre']) ?>"></td>
                <td><input name="precio" value="<?= h(number_format((float)$t['precio'],2,'.','')) ?>" inputmode="decimal"></td>
                <td><input type="number" name="stock" min="0" step="1" value="<?= (int)$t['stock_disponible'] ?>"></td>
                <td><input type="number" name="stock_total" min="0" step="1" value="<?= (int)$t['stock_total'] ?>"></td>
                <td><input type="number" name="max_por_compra" min="0" step="1" value="<?= (int)$t['max_por_compra'] ?>"></td>
                <td>
                  <select name="canal">
                    <?php $cvals=['todos'=>'Todos','online'=>'Online','taquilla'=>'Taquilla','fisica'=>'Física (preventa)'];
                    foreach($cvals as $cv=>$lbl): ?>
                      <option value="<?= $cv ?>" <?= $t['canal']===$cv?'selected':''; ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <select name="etapa">
                    <?php $evals=['general'=>'General','preventa'=>'Preventa','anticipada'=>'Anticipada','puerta'=>'Puerta'];
                    foreach($evals as $ev=>$lbl): ?>
                      <option value="<?= $ev ?>" <?= $t['etapa']===$ev?'selected':''; ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </td>
                <td style="text-align:center">
                  <input type="checkbox" name="visible" <?= ((int)$t['visible']===1?'checked':'') ?>>
                </td>
                <td><small class="pill"><?= !empty($t['updated_at']) ? h((string)$t['updated_at']) : '-' ?></small></td>
                <td style="white-space:nowrap">
                  <button class="btn" type="submit">💾 Guardar</button>
              </form>
                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Eliminar este tipo?');">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button class="btn gray" type="submit">🗑️ Eliminar</button>
                  </form>
                </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:12px">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <a class="btn" href="ver_ventas_evento.php?evento_id=<?= (int)$evento_id ?>">📊 Ventas</a>
      <a class="btn" href="evento.php?id=<?= (int)$evento_id ?>" target="_blank">👀 Vista pública</a>
    </div>
  </div>
</body>
</html>
