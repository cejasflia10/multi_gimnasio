<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function err($s){ return "<div style='margin:10px 0;padding:10px;border-radius:8px;border:1px solid #7f1e1e;background:#2b0505;color:#ffb4b4'>❌ ".h($s)."</div>"; }
function ok($s){ return "<div style='margin:10px 0;padding:10px;border-radius:8px;border:1px solid #1e7f56;background:#052b18;color:#b7f7cf'>✅ ".h($s)."</div>"; }
function toFloat($s){ $s=str_replace(['.',','],['','.'],$s); return is_numeric($s)?(float)$s:null; }
function bmi($pesoKg, $alturaCm){ if ($pesoKg<=0 || $alturaCm<=0) return null; $m=$alturaCm/100.0; if($m<=0) return null; return round($pesoKg/($m*$m),2); }
/** kcal = MET * 3.5 * pesoKg * min / 200 (ACSM) */
function kcal_estimadas(?float $pesoKg, ?string $intensidad, ?int $min){
  if (!$pesoKg || !$min || $pesoKg<=0 || $min<=0) return null;
  $map = ['leve'=>3.0, 'moderado'=>5.5, 'intenso'=>8.0];
  $met = $map[strtolower((string)$intensidad)] ?? 5.5;
  return (int)round($met * 3.5 * $pesoKg * $min / 200.0);
}

/* ============ Resolver identidad desde la sesión (robusto) ============ */
function resolver_identidad(): array {
  $rol_raw = strtolower((string)(
      $_SESSION['rol'] ?? $_SESSION['perfil'] ?? $_SESSION['tipo'] ?? ''
  ));
  if (in_array($rol_raw, ['admin','administrator','superadmin'], true)) $rol = 'admin';
  elseif (in_array($rol_raw, ['profesor','profe','prof','teacher'], true) || !empty($_SESSION['profesor_id'])) $rol = 'profesor';
  else $rol = 'cliente';

  $cliente_id = (int)(
      $_SESSION['cliente_id'] ?? $_SESSION['id_cliente'] ?? $_SESSION['id'] ?? 0
  );
  $gym_id = (int)(
      $_SESSION['gimnasio_id'] ?? $_SESSION['gym_id'] ?? 0
  );
  return [$rol, $cliente_id, $gym_id];
}

/* ================= Debug opcional ================= */
if (!empty($_GET['debug_sesion'])) {
  header('Content-Type: text/plain; charset=utf-8');
  [$rol,$cliente_id,$gym_id] = resolver_identidad();
  echo "DEBUG SESION\nrol={$rol}\ncliente_id={$cliente_id}\ngimnasio_id={$gym_id}\n\n";
  print_r($_SESSION);
  exit;
}

/* ======== Asegurar tablas/columnas (compatibilidad con bases viejas) ======== */
$conexion->query("
CREATE TABLE IF NOT EXISTS datos_fisicos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  fecha DATE NOT NULL,
  peso DECIMAL(6,2) DEFAULT NULL,
  altura DECIMAL(6,2) DEFAULT NULL,
  talle_remera VARCHAR(20) DEFAULT NULL,
  talle_pantalon VARCHAR(20) DEFAULT NULL,
  talle_calzado VARCHAR(20) DEFAULT NULL,
  patologias TEXT,
  tipo_diabetes VARCHAR(30) DEFAULT NULL,
  medicaciones TEXT,
  observaciones TEXT,
  intensidad ENUM('leve','moderado','intenso') DEFAULT NULL,
  duracion_min INT DEFAULT NULL,
  gasto_calorico_kcal INT DEFAULT NULL,
  INDEX (cliente_id), INDEX (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Si la tabla ya existía y le faltan columnas nuevas, agrégalas */
function ensure_col(mysqli $db, string $table, string $col, string $def){
  $q = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$col}'");
  if ($q && $q->num_rows === 0) { $db->query("ALTER TABLE `{$table}` ADD `{$col}` {$def}"); }
  if ($q) $q->free();
}
ensure_col($conexion, 'datos_fisicos', 'intensidad', "ENUM('leve','moderado','intenso') NULL");
ensure_col($conexion, 'datos_fisicos', 'duracion_min', "INT NULL");
ensure_col($conexion, 'datos_fisicos', 'gasto_calorico_kcal', "INT NULL");

/* Recursos compartidos (dietas, entrenos en casa, recomendaciones) */
$conexion->query("
CREATE TABLE IF NOT EXISTS cliente_recursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  tipo ENUM('dieta','entrenamiento','recomendacion') NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  contenido TEXT NOT NULL,
  visible_cliente TINYINT(1) NOT NULL DEFAULT 1,
  creado_por ENUM('profesor','admin','sistema') DEFAULT 'profesor',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (cliente_id), INDEX (tipo), INDEX(visible_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ================= Variables base ================= */
[$rol, $cliente_id_sesion, $gym_id] = resolver_identidad();
$is_prof = in_array($rol, ['profesor','admin'], true);
$mensaje  = '';

/* ================= Resolver cliente objetivo ================= */
$target_cliente_id = 0;
if ($is_prof) {
  if (isset($_GET['cliente'])) {
    $target_cliente_id = max(0,(int)$_GET['cliente']);
  } elseif (isset($_POST['buscar_dni'])) {
    $dni = trim($_POST['buscar_dni']);
    if ($dni !== '') {
      if ($gym_id>0) { $st=$conexion->prepare("SELECT id FROM clientes WHERE dni=? AND gimnasio_id=? LIMIT 1"); $st->bind_param('si',$dni,$gym_id); }
      else           { $st=$conexion->prepare("SELECT id FROM clientes WHERE dni=? LIMIT 1"); $st->bind_param('s',$dni); }
      $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
      if ($row) { header("Location: ".$_SERVER['PHP_SELF']."?cliente=".$row['id']); exit; }
      else { $mensaje .= err("No se encontró cliente con DNI {$dni}".($gym_id?" en este gimnasio.":".")); }
    }
  }
} else {
  $target_cliente_id = (int)$cliente_id_sesion;
}

if ($target_cliente_id <= 0) {
  if ($is_prof) {
    ?>
    <!DOCTYPE html>
    <html lang="es"><head>
      <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Datos físicos - Buscar cliente</title>
      <style>
        body{background:#000;color:gold;font-family:Arial;margin:0;padding:24px}
        .card{max-width:640px;margin:24px auto;background:#111;padding:18px;border-radius:12px;border:1px solid #222}
        input,button{padding:10px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:gold}
        .row{display:grid;grid-template-columns:1fr auto;gap:8px}
        @media (max-width:700px){ .row{grid-template-columns:1fr} }
      </style>
    </head><body>
      <?php if (is_file(__DIR__.'/menu_profesor.php')) { include __DIR__.'/menu_profesor.php'; } ?>
      <div class="card">
        <h2>🔎 Buscar cliente por DNI</h2>
        <?= $mensaje ?>
        <form method="POST">
          <div class="row">
            <input type="text" name="buscar_dni" inputmode="numeric" placeholder="DNI del cliente..." autofocus>
            <button type="submit">Buscar</button>
          </div>
        </form>
      </div>
    </body></html><?php
    exit;
  }
  echo err('Acceso denegado. Falta identificar al cliente en la sesión.');
  exit;
}

/* ================= Cargar datos del cliente ================= */
$st = $conexion->prepare("SELECT id, apellido, nombre, dni FROM clientes WHERE id=? LIMIT 1");
$st->bind_param('i',$target_cliente_id);
$st->execute();
$cliente = $st->get_result()->fetch_assoc();
$st->close();
if (!$cliente) { echo err('Cliente no encontrado.'); exit; }

/* ================= Acciones: crear/editar evolución / recursos ================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  /* Crear / Actualizar evolución */
  if (!empty($_POST['act']) && in_array($_POST['act'],['create','update'],true)) {
    $peso        = toFloat($_POST['peso'] ?? '');
    $altura      = toFloat($_POST['altura'] ?? '');
    $remera      = trim($_POST['talle_remera'] ?? '');
    $pantalon    = trim($_POST['talle_pantalon'] ?? '');
    $calzado     = trim($_POST['talle_calzado'] ?? '');
    $patologias  = isset($_POST['patologias']) ? implode(", ", array_map('trim',(array)$_POST['patologias'])) : '';
    $tipo_diab   = trim($_POST['tipo_diabetes'] ?? '');
    $medic       = trim($_POST['medicaciones'] ?? '');
    $obs         = trim($_POST['observaciones'] ?? '');
    $intensidad  = in_array(strtolower($_POST['intensidad'] ?? ''), ['leve','moderado','intenso'], true) ? strtolower($_POST['intensidad']) : null;
    $duracion    = (int)($_POST['duracion_min'] ?? 0);
    if ($duracion<=0) $duracion = null;
    $fecha_in    = date('Y-m-d');

    $kcal = kcal_estimadas($peso, $intensidad, $duracion);

    if ($_POST['act']==='create') {
      $st = $conexion->prepare("
        INSERT INTO datos_fisicos
        (cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, patologias, tipo_diabetes, medicaciones, observaciones, intensidad, duracion_min, gasto_calorico_kcal)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");
      /* Bind como string para máxima compatibilidad */
      $st->bind_param(
        'ssssssssssssss',
        $cid, $fch, $ps, $alt, $rem, $pant, $calz, $pat, $td, $med, $ob, $int, $dur, $kc
      );
      $cid = (string)$target_cliente_id;
      $fch = (string)$fecha_in;
      $ps  = isset($peso)   ? (string)$peso   : null;
      $alt = isset($altura) ? (string)$altura : null;
      $rem = $remera; $pant=$pantalon; $calz=$calzado; $pat=$patologias; $td=$tipo_diab; $med=$medic; $ob=$obs; $int=$intensidad;
      $dur = isset($duracion) ? (string)$duracion : null;
      $kc  = isset($kcal) ? (string)$kcal : null;

      if ($st->execute()) {
        $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id=".$target_cliente_id);
        $mensaje .= ok('Registro guardado.');
      } else { $mensaje .= err('Error al guardar el registro.'); }
      $st->close();
    } else { // update
      if (!$is_prof) { $mensaje .= err('Solo el profesor puede editar.'); }
      else {
        $id_upd = (int)($_POST['id'] ?? 0);
        if ($id_upd>0) {
          $st=$conexion->prepare("SELECT 1 FROM datos_fisicos WHERE id=? AND cliente_id=?");
          $st->bind_param('ii',$id_upd,$target_cliente_id);
          $st->execute(); $okRow=$st->get_result()->fetch_row(); $st->close();
          if ($okRow) {
            $st=$conexion->prepare("
              UPDATE datos_fisicos
              SET peso=?, altura=?, talle_remera=?, talle_pantalon=?, talle_calzado=?, patologias=?, tipo_diabetes=?, medicaciones=?, observaciones=?, intensidad=?, duracion_min=?, gasto_calorico_kcal=?
              WHERE id=? AND cliente_id=?");
            $st->bind_param('ssssssssssssss',
              $ps, $alt, $rem, $pant, $calz, $pat, $td, $med, $ob, $int, $dur, $kc, $idu, $clid
            );
            $ps  = isset($peso)   ? (string)$peso   : null;
            $alt = isset($altura) ? (string)$altura : null;
            $rem = $remera; $pant=$pantalon; $calz=$calzado; $pat=$patologias; $td=$tipo_diab; $med=$medic; $ob=$obs; $int=$intensidad;
            $dur = isset($duracion) ? (string)$duracion : null;
            $kc  = isset($kcal) ? (string)$kcal : null;
            $idu = (string)$id_upd;
            $clid= (string)$target_cliente_id;

            if ($st->execute()) { $mensaje .= ok('Registro actualizado.'); }
            else { $mensaje .= err('Error al actualizar.'); }
            $st->close();
          } else { $mensaje .= err('Registro inválido.'); }
        } else { $mensaje .= err('ID inválido.'); }
      }
    }
  }

  /* Recursos: agregar / visibilidad / borrar (solo profe) */
  if ($is_prof && !empty($_POST['rec_act'])) {
    if ($_POST['rec_act']==='add') {
      $tipo   = in_array($_POST['tipo'] ?? '', ['dieta','entrenamiento','recomendacion'], true) ? $_POST['tipo'] : 'recomendacion';
      $titulo = trim($_POST['titulo'] ?? '');
      $cont   = trim($_POST['contenido'] ?? '');
      $vis    = isset($_POST['visible']) ? 1 : 0;
      if ($titulo!=='' && $cont!=='') {
        $st=$conexion->prepare("INSERT INTO cliente_recursos (cliente_id,tipo,titulo,contenido,visible_cliente,creado_por) VALUES (?,?,?,?,?,'profesor')");
        $st->bind_param('isssi',$target_cliente_id,$tipo,$titulo,$cont,$vis);
        if ($st->execute()) $mensaje .= ok('Recurso compartido.');
        else $mensaje .= err('No se pudo guardar el recurso.');
        $st->close();
      } else $mensaje .= err('Completá título y contenido.');
    }
    if ($_POST['rec_act']==='toggle') {
      $id=(int)($_POST['id']??0); $vis=(int)($_POST['vis']??0);
      if ($id>0) $conexion->query("UPDATE cliente_recursos SET visible_cliente={$vis} WHERE id={$id} AND cliente_id={$target_cliente_id}");
    }
    if ($_POST['rec_act']==='del') {
      $id=(int)($_POST['id']??0);
      if ($id>0) $conexion->query("DELETE FROM cliente_recursos WHERE id={$id} AND cliente_id={$target_cliente_id}");
    }
  }
}

/* ================= Filtros de evolución ================= */
$rango = $_GET['rango'] ?? 'mes'; // semana | mes | anio | custom
$fdesde = $_GET['fdesde'] ?? '';
$fhasta = $_GET['fhasta'] ?? '';
$hoy = new DateTime();
switch ($rango) {
  case 'semana': $desde = (clone $hoy)->modify('-6 days'); break;
  case 'anio':   $desde = (clone $hoy)->modify('-1 year'); break;
  case 'custom':
    $desde = $fdesde && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fdesde) ? new DateTime($fdesde) : (clone $hoy)->modify('-1 month');
    $hasta = $fhasta && preg_match('/^\d{4}-\d{2}-\d{2}$/',$fhasta) ? new DateTime($fhasta) : $hoy;
    break;
  case 'mes':
  default:       $desde = (clone $hoy)->modify('-1 month'); break;
}
if (!isset($hasta)) $hasta = $hoy;
$desde_sql = $desde->format('Y-m-d');
$hasta_sql = $hasta->format('Y-m-d');

/* ================= Cargar historial con filtro ================= */
$st = $conexion->prepare("SELECT * FROM datos_fisicos WHERE cliente_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha DESC, id DESC");
$st->bind_param('iss',$target_cliente_id,$desde_sql,$hasta_sql);
$st->execute(); $rs=$st->get_result();
$historial=[]; while($row=$rs->fetch_assoc()) $historial[]=$row;
$st->close();
$tiene_registros = count($historial)>0;
$ultimo = $tiene_registros ? $historial[0] : null;

/* ================= Recursos compartidos ================= */
$recursos = [];
$rq = $conexion->prepare("SELECT * FROM cliente_recursos WHERE cliente_id=? ORDER BY created_at DESC");
$rq->bind_param('i',$target_cliente_id);
$rq->execute(); $rr=$rq->get_result();
while($r=$rr->fetch_assoc()) $recursos[]=$r;
$rq->close();

/* =============== ¿Editar registro? =============== */
$edit_row = null;
if ($is_prof && isset($_GET['edit'])) {
  $eid=(int)$_GET['edit'];
  if ($eid>0){
    $st=$conexion->prepare("SELECT * FROM datos_fisicos WHERE id=? AND cliente_id=?");
    $st->bind_param('ii',$eid,$target_cliente_id);
    $st->execute(); $edit_row=$st->get_result()->fetch_assoc();
    $st->close();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Datos Físicos y Evolución</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{ --bg:#000; --fg:gold; --card:#101114; --line:#262a33; --muted:#a0a7b4; }
    body{background:var(--bg);color:var(--fg);font-family:Arial;margin:0}
    .wrap{max-width:1200px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    .row{display:grid;grid-template-columns:1fr;gap:8px}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0d0f14;color:var(--fg)}
    textarea{min-height:80px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid var(--line);padding:8px;text-align:left}
    th{background:#141824}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none;cursor:pointer}
    .btn:hover{background:#21293a}
    .muted{color:var(--muted);font-size:12px}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
    .copy{cursor:pointer;font-size:12px;border:1px dashed #444;padding:2px 6px;border-radius:6px}
    @media (max-width:900px){ .grid{grid-template-columns:1fr} .grid-3{grid-template-columns:1fr} }
  </style>
  <script>
    function toggleDiabetes(){
      const box = document.getElementById('chk-diabetes');
      const panel = document.getElementById('tipo-diabetes-panel');
      if (box && panel) panel.style.display = box.checked ? 'block' : 'none';
    }
    function copyText(txt){
      navigator.clipboard.writeText(txt).then(()=>alert('Copiado al portapapeles'));
    }
    function cambiarRango(sel){
      const v = sel.value;
      const block = document.getElementById('rango-custom');
      if (block) block.style.display = (v==='custom') ? 'grid' : 'none';
    }
  </script>
</head>
<body>

<?php
// === MENÚS RESTAURADOS ===
// Menú general si existe:
if (is_file(__DIR__.'/menu_horizontal.php')) { include __DIR__.'/menu_horizontal.php'; }
// Menú específico de profesores (solo si es profe/admin y el archivo existe):
if ($is_prof && is_file(__DIR__.'/menu_profesor.php')) { include __DIR__.'/menu_profesor.php'; }
?>

<div class="wrap">

  <div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h2 style="margin:0">👤 <?= h(($cliente['apellido']??'').' '.($cliente['nombre']??'')) ?></h2>
        <div class="muted">DNI: <?= h($cliente['dni']??'') ?> · ID: <?= (int)$cliente['id'] ?></div>
      </div>
      <?php if ($is_prof): ?>
        <form method="POST" style="display:flex;gap:8px;align-items:center">
          <input type="text" name="buscar_dni" placeholder="Buscar otro DNI..." inputmode="numeric">
          <button class="btn" type="submit">Buscar</button>
        </form>
      <?php endif; ?>
    </div>
    <?= $mensaje ?>
  </div>

  <!-- Filtros de evolución -->
  <div class="card">
    <h3 style="margin-top:0">📈 Evolución — Filtros</h3>
    <form method="GET" class="toolbar">
      <input type="hidden" name="cliente" value="<?= (int)$target_cliente_id ?>">
      <div>
        <label>Rango</label>
        <select name="rango" onchange="cambiarRango(this)">
          <option value="semana"  <?= $rango==='semana'?'selected':''; ?>>Semana</option>
          <option value="mes"     <?= $rango==='mes'?'selected':''; ?>>Mes</option>
          <option value="anio"    <?= $rango==='anio'?'selected':''; ?>>Año</option>
          <option value="custom"  <?= $rango==='custom'?'selected':''; ?>>Personalizado</option>
        </select>
      </div>
      <div id="rango-custom" style="display: <?= $rango==='custom'?'grid':'none' ?>; grid-template-columns:1fr 1fr; gap:8px">
        <div>
          <label>Desde</label>
          <input type="date" name="fdesde" value="<?= h($desde_sql) ?>">
        </div>
        <div>
          <label>Hasta</label>
          <input type="date" name="fhasta" value="<?= h($hasta_sql) ?>">
        </div>
      </div>
      <div>
        <button class="btn" type="submit">Aplicar</button>
      </div>
    </form>
  </div>

  <?php if (!empty($historial)): ?>
    <div class="card">
      <h3 style="margin-top:0">📌 Último registro</h3>
      <div class="grid">
        <div>
          <div>Peso: <b><?= h($ultimo['peso'] ?? '—') ?> kg</b></div>
          <div>Altura: <b><?= h($ultimo['altura'] ?? '—') ?> cm</b></div>
          <div>IMC: <b><?php $imc=bmi((float)($ultimo['peso'] ?? 0), (float)($ultimo['altura'] ?? 0)); echo $imc!==null? h($imc):'—'; ?></b></div>
        </div>
        <div>
          <div>Intensidad: <b><?= h($ultimo['intensidad'] ?? '—') ?></b></div>
          <div>Duración: <b><?= h($ultimo['duracion_min'] ?? '—') ?> min</b></div>
          <div>Gasto calórico: <b><?= h($ultimo['gasto_calorico_kcal'] ?? '—') ?> kcal</b></div>
        </div>
      </div>
      <div class="grid">
        <div>
          <div>Patologías: <b><?= h($ultimo['patologias'] ?? '—') ?></b></div>
          <div>Tipo diabetes: <b><?= h($ultimo['tipo_diabetes'] ?? '—') ?></b></div>
        </div>
        <div>
          <div>Medicaciones: <div class="muted"><?= nl2br(h($ultimo['medicaciones'] ?? '—')) ?></div></div>
          <div>Observaciones: <div class="muted"><?= nl2br(h($ultimo['observaciones'] ?? '—')) ?></div></div>
        </div>
      </div>
      <div class="muted" style="margin-top:6px">Fecha: <?= h($ultimo['fecha'] ?? '—') ?></div>
    </div>
  <?php endif; ?>

  <!-- Form CREAR / EDITAR -->
  <div class="card">
    <?php if ($is_prof && !empty($edit_row)): ?>
      <h3 style="margin-top:0">✏️ Editar registro (ID #<?= (int)$edit_row['id'] ?>)</h3>
      <form method="POST" class="row">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">

        <div class="grid-3">
          <div><label>Peso (kg)</label><input type="text" name="peso" value="<?= h($edit_row['peso'] ?? '') ?>" required></div>
          <div><label>Altura (cm)</label><input type="text" name="altura" value="<?= h($edit_row['altura'] ?? '') ?>" required></div>
          <div>
            <label>Intensidad</label>
            <select name="intensidad">
              <?php foreach (['leve','moderado','intenso'] as $op): ?>
                <option value="<?= $op ?>" <?= (($edit_row['intensidad'] ?? '')===$op)?'selected':''; ?>><?= ucfirst($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid-3">
          <div><label>Duración (min)</label><input type="number" name="duracion_min" min="0" value="<?= h($edit_row['duracion_min'] ?? '') ?>"></div>
          <div><label>Talle Remera</label><input type="text" name="talle_remera" value="<?= h($edit_row['talle_remera'] ?? '') ?>"></div>
          <div><label>Talle Pantalón</label><input type="text" name="talle_pantalon" value="<?= h($edit_row['talle_pantalon'] ?? '') ?>"></div>
        </div>

        <div class="grid-3">
          <div><label>Talle Calzado</label><input type="text" name="talle_calzado" value="<?= h($edit_row['talle_calzado'] ?? '') ?>"></div>
          <div><label class="muted">Patologías (marcá abajo)</label></div>
          <div></div>
        </div>

        <?php $tieneDiab = stripos((string)($edit_row['patologias'] ?? ''),'diabetes')!==false; ?>
        <div>
          <label>Patologías</label>
          <label><input id="chk-diabetes" type="checkbox" name="patologias[]" value="Diabetes" <?= $tieneDiab?'checked':''; ?> onclick="toggleDiabetes()"> Diabetes</label>
          <label><input type="checkbox" name="patologias[]" value="Hipertensión" <?= (stripos((string)($edit_row['patologias'] ?? ''),'hipertensión')!==false)?'checked':''; ?>> Hipertensión</label>
          <label><input type="checkbox" name="patologias[]" value="Asma" <?= (stripos((string)($edit_row['patologias'] ?? ''),'asma')!==false)?'checked':''; ?>> Asma</label>
          <label><input type="checkbox" name="patologias[]" value="Otra" <?= (stripos((string)($edit_row['patologias'] ?? ''),'otra')!==false)?'checked':''; ?>> Otra</label>

          <div id="tipo-diabetes-panel" style="margin-top:8px; display: <?= $tieneDiab ? 'block':'none' ?>;">
            <label>Tipo de Diabetes</label>
            <select name="tipo_diabetes">
              <option value="">-- Seleccionar --</option>
              <?php foreach(['Tipo 1','Tipo 2','Gestacional'] as $op): ?>
                <option <?= (($edit_row['tipo_diabetes'] ?? '')===$op)?'selected':''; ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid">
          <div><label>Medicaciones</label><textarea name="medicaciones"><?= h($edit_row['medicaciones'] ?? '') ?></textarea></div>
          <div><label>Observaciones</label><textarea name="observaciones"><?= h($edit_row['observaciones'] ?? '') ?></textarea></div>
        </div>

        <div>
          <button class="btn" type="submit">💾 Guardar cambios</button>
          <a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?cliente='.$target_cliente_id) ?>">Cancelar</a>
        </div>
      </form>
    <?php else: ?>
      <h3 style="margin-top:0">📝 Nuevo registro</h3>
      <form method="POST" class="row">
        <input type="hidden" name="act" value="create">
        <div class="grid-3">
          <div><label>Peso (kg)</label><input type="text" name="peso" required></div>
          <div><label>Altura (cm)</label><input type="text" name="altura" required></div>
          <div>
            <label>Intensidad</label>
            <select name="intensidad">
              <option value="leve">Leve</option>
              <option value="moderado" selected>Moderado</option>
              <option value="intenso">Intenso</option>
            </select>
          </div>
        </div>
        <div class="grid-3">
          <div><label>Duración (min)</label><input type="number" name="duracion_min" min="0" placeholder="Ej: 40"></div>
          <div><label>Talle Remera</label><input type="text" name="talle_remera"></div>
          <div><label>Talle Pantalón</label><input type="text" name="talle_pantalon"></div>
        </div>
        <div class="grid-3">
          <div><label>Talle Calzado</label><input type="text" name="talle_calzado"></div>
          <div><label class="muted">Patologías (marcá abajo)</label></div>
          <div></div>
        </div>
        <div>
          <label>Patologías</label>
          <label><input id="chk-diabetes" type="checkbox" name="patologias[]" value="Diabetes" onclick="toggleDiabetes()"> Diabetes</label>
          <label><input type="checkbox" name="patologias[]" value="Hipertensión"> Hipertensión</label>
          <label><input type="checkbox" name="patologias[]" value="Asma"> Asma</label>
          <label><input type="checkbox" name="patologias[]" value="Otra"> Otra</label>
          <div id="tipo-diabetes-panel" style="display:none; margin-top:8px">
            <label>Tipo de Diabetes</label>
            <select name="tipo_diabetes">
              <option value="">-- Seleccionar --</option>
              <option>Tipo 1</option><option>Tipo 2</option><option>Gestacional</option>
            </select>
          </div>
        </div>
        <div class="grid">
          <div><label>Medicaciones</label><textarea name="medicaciones"></textarea></div>
          <div><label>Observaciones</label><textarea name="observaciones"></textarea></div>
        </div>
        <button class="btn" type="submit">💾 Guardar</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Evolución / Historial -->
  <div class="card">
    <h3 style="margin-top:0">📊 Evolución</h3>
    <?php if (empty($historial)): ?>
      <div class="muted">Sin registros en el rango seleccionado.</div>
    <?php else: ?>
      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Fecha</th><th>Peso (kg)</th><th>Altura (cm)</th><th>IMC</th>
              <th>Intensidad</th><th>Duración (min)</th><th>Gasto (kcal)</th>
              <th>Patologías</th><th>Tipo Diabetes</th>
              <th>Medicaciones</th><th>Observaciones</th>
              <?php if ($is_prof): ?><th>Acciones</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $row): ?>
              <tr>
                <td><?= h($row['fecha'] ?? '—') ?></td>
                <td><?= h($row['peso'] ?? '—') ?></td>
                <td><?= h($row['altura'] ?? '—') ?></td>
                <td><?php $imc=bmi((float)($row['peso'] ?? 0), (float)($row['altura'] ?? 0)); echo $imc!==null? h($imc):'—'; ?></td>
                <td><?= h($row['intensidad'] ?? '—') ?></td>
                <td><?= h($row['duracion_min'] ?? '—') ?></td>
                <td><?= h($row['gasto_calorico_kcal'] ?? '—') ?></td>
                <td><?= h($row['patologias'] ?? '—') ?></td>
                <td><?= h($row['tipo_diabetes'] ?? '—') ?></td>
                <td><?= nl2br(h($row['medicaciones'] ?? '—')) ?></td>
                <td><?= nl2br(h($row['observaciones'] ?? '—')) ?></td>
                <?php if ($is_prof): ?>
                  <td><a class="btn" href="<?= h($_SERVER['PHP_SELF'].'?cliente='.$target_cliente_id.'&edit='.$row['id']) ?>">Editar</a></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recursos compartidos -->
  <div class="card">
    <h3 style="margin-top:0">📤 Recursos para el cliente</h3>
    <div class="muted">Compartí dietas, entrenamientos en casa, recomendaciones, etc. (el cliente los ve en su panel).</div>

    <?php if ($is_prof): ?>
      <form method="POST" class="grid">
        <input type="hidden" name="rec_act" value="add">
        <div>
          <label>Tipo</label>
          <select name="tipo">
            <option value="dieta">Dieta</option>
            <option value="entrenamiento">Entrenamiento</option>
            <option value="recomendacion" selected>Recomendación</option>
          </select>
        </div>
        <div>
          <label>Título</label>
          <input type="text" name="titulo" placeholder="Ej: Dieta hipocalórica semana 1">
        </div>
        <div style="grid-column:1 / -1">
          <label>Contenido (link o texto)</label>
          <textarea name="contenido" placeholder="Pegar link a Google Drive/YouTube o escribir indicaciones..."></textarea>
        </div>
        <div>
          <label><input type="checkbox" name="visible" checked> Visible para el cliente</label>
        </div>
        <div>
          <button class="btn" type="submit">➕ Compartir</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if (empty($recursos)): ?>
      <div class="muted" style="margin-top:8px">Aún no hay recursos compartidos.</div>
    <?php else: ?>
      <div style="overflow:auto;margin-top:10px">
        <table>
          <thead><tr><th>Tipo</th><th>Título</th><th>Contenido</th><th>Visible</th><th>Fecha</th><?php if($is_prof): ?><th>Acciones</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($recursos as $r): ?>
              <tr>
                <td><?= h(ucfirst($r['tipo'])) ?></td>
                <td><?= h($r['titulo']) ?></td>
                <td>
                  <div class="muted" style="max-width:420px;white-space:pre-wrap"><?= h($r['contenido']) ?></div>
                  <span class="copy" onclick="copyText('<?= h($r['contenido']) ?>')">Copiar</span>
                </td>
                <td><?= ($r['visible_cliente']? 'Sí':'No') ?></td>
                <td><?= h($r['created_at']) ?></td>
                <?php if ($is_prof): ?>
                  <td style="white-space:nowrap">
                    <form method="POST" style="display:inline">
                      <input type="hidden" name="rec_act" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="vis" value="<?= $r['visible_cliente']?0:1 ?>">
                      <button class="btn" type="submit"><?= $r['visible_cliente']?'Ocultar':'Mostrar' ?></button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar recurso?');">
                      <input type="hidden" name="rec_act" value="del">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn" type="submit">🗑️</button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
