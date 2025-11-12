<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
$conexion->set_charset('utf8mb4');

/* ================= Utilidades ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``',$col).'`'; }
function flash_err($m){ $_SESSION['flash_error']=$m; }
function flash_ok($m){ $_SESSION['flash_ok']=$m; }
function sex_short($s){
  $s = strtolower(trim((string)$s));
  if ($s==='masculino' || $s==='m' || $s==='male') return 'M';
  if ($s==='femenino'  || $s==='f' || $s==='female') return 'F';
  return '-';
}

/* ================= CSRF ================= */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$CSRF = $_SESSION['csrf_token'];
function csrf_ok($t){ return !empty($_SESSION['csrf_token']) && !empty($t) && hash_equals($_SESSION['csrf_token'],$t); }

/* ================= evento_id ================= */
$evento_id = (int)($_GET['evento_id'] ?? $_POST['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
if ($evento_id<=0){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>evento_id</b>. Abrí esta pantalla desde el evento.</div>';
  exit;
}
$_SESSION['evento_id_actual'] = $evento_id;

/* ================= Detección columnas peleas_evento ================= */
function detect_cols_peleas(mysqli $db){
  $rs = $db->query("SHOW COLUMNS FROM peleas_evento");
  if(!$rs) return ['error'=>'No se pudo leer columnas de peleas_evento: '.$db->error];
  $have=[]; while($r=$rs->fetch_assoc()) $have[strtolower($r['Field'])]=$r['Field'];
  $pick=function($arr)use($have){ foreach($arr as $c){ $lc=strtolower($c); if(isset($have[$lc])) return $have[$lc]; } return null; };
  return [
    'id'     => $pick(['id','pelea_id','id_pelea']),
    'evento' => $pick(['evento_id','id_evento','evento']),
    'rojo'   => $pick(['rojo_id','competidor_rojo_id','id_rojo','id_competidor_rojo','rojo']),
    'azul'   => $pick(['azul_id','competidor_azul_id','id_azul','id_competidor_azul','azul']),
    'rondas' => $pick(['rondas','rounds']),
    'obs'    => $pick(['observaciones','obs','comentarios','nota']),
  ];
}
$pe_cols = detect_cols_peleas($conexion);
if (isset($pe_cols['error'])) { http_response_code(500); exit(h($pe_cols['error'])); }
if (!$pe_cols['evento'] || !$pe_cols['rojo'] || !$pe_cols['azul']) {
  http_response_code(500); exit('Faltan columnas obligatorias en <b>peleas_evento</b> (evento, rojo, azul).');
}

/* ================= Acciones POST (crear pelea) ================= */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion'] ?? '')==='crear_pelea'){
  if (!csrf_ok($_POST['csrf'] ?? '')) { flash_err('CSRF inválido.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }

  $formato = $_POST['formato'] ?? 'simple';
  $rondas  = (isset($_POST['rondas']) && is_numeric($_POST['rondas'])) ? max(1, min(12, (int)$_POST['rondas'])) : 3;
  $obsBase = trim((string)($_POST['observaciones'] ?? ''));

  $pairs = [];
  if ($formato==='simple'){
    $r=(int)($_POST['rojo_id'] ?? 0); $a=(int)($_POST['azul_id'] ?? 0);
    if ($r<=0 || $a<=0){ flash_err('Seleccioná ambas esquinas.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if ($r===$a){ flash_err('Rojo y Azul deben ser distintos.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $pairs[] = [$r,$a,''];
  } elseif ($formato==='triangular'){
    $r=(int)($_POST['tri_rojo_id'] ?? 0); $a=(int)($_POST['tri_azul_id'] ?? 0); $l=(int)($_POST['tri_libre_id'] ?? 0);
    if (min($r,$a,$l)<=0){ flash_err('Completá los 3 slots del Triangular.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$r,$a,$l]))!==3){ flash_err('Los 3 competidores deben ser distintos.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $pairs[] = [$r,$a,' (Triangular - Semifinal)'];
  } else { // super4
    $r1=(int)($_POST['sf1_rojo_id'] ?? 0); $a1=(int)($_POST['sf1_azul_id'] ?? 0);
    $r2=(int)($_POST['sf2_rojo_id'] ?? 0); $a2=(int)($_POST['sf2_azul_id'] ?? 0);
    if (min($r1,$a1,$r2,$a2)<=0){ flash_err('Completá SF1 y SF2.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$r1,$a1,$r2,$a2]))!==4){ flash_err('Los 4 competidores deben ser distintos.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $pairs[] = [$r1,$a1,' (Super 4 - SF1)'];
    $pairs[] = [$r2,$a2,' (Super 4 - SF2)'];
  }

  $conexion->begin_transaction();
  try{
    foreach($pairs as [$r,$a,$suf]){
      $cols = [$pe_cols['evento'],$pe_cols['rojo'],$pe_cols['azul']];
      $vals = [$evento_id,$r,$a];
      $types='iii';
      if($pe_cols['rondas']){ $cols[]=$pe_cols['rondas']; $vals[]=$rondas; $types.='i'; }
      if($pe_cols['obs'])   { $cols[]=$pe_cols['obs'];    $vals[]=trim($obsBase.$suf); $types.='s'; }
      $cols_bt=array_map('bt',$cols);
      $ph=implode(',',array_fill(0,count($cols_bt),'?'));
      $sql="INSERT INTO peleas_evento (".implode(',',$cols_bt).") VALUES ($ph)";
      $st=$conexion->prepare($sql); if(!$st) throw new Exception('Insert: '.$conexion->error);
      $b=[$types]; foreach($vals as &$v){ $b[]=&$v; } call_user_func_array([$st,'bind_param'],$b);
      if(!$st->execute()) throw new Exception('No se pudo guardar: '.$st->error);
      $st->close();
    }
    $conexion->commit();
  }catch(Throwable $e){
    $conexion->rollback(); flash_err($e->getMessage());
    header('Location: organizar_pelea.php?evento_id='.$evento_id); exit;
  }

  $creadas = count($pairs);
  $txtFmt = ($formato==='simple' ? '1 vs 1' : ($formato==='triangular' ? 'Triangular' : 'Super 4'));
  flash_ok("Se crearon $creadas pelea(s) — formato $txtFmt.");
  header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id); exit;
}

/* ===== Detectar posibles columnas de peso declarado ===== */
function detect_weight_cols(mysqli $db): array {
  $rs = $db->query("SHOW COLUMNS FROM competidores_evento");
  if(!$rs) return [];
  $have=[]; while($r=$rs->fetch_assoc()) $have[strtolower($r['Field'])]=$r['Field'];
  $cands = ['peso_kg','peso','peso_decl','peso_declarado','peso_oficial','peso_competidor'];
  $out=[]; foreach($cands as $c){ $lc=strtolower($c); if(isset($have[$lc])) $out[]=$have[$lc]; }
  return $out;
}
$WEIGHT_COLS = detect_weight_cols($conexion);

/* ===== SELECT con JOIN a categorias_evento (rango) y categorias_tecnicas_evento (A/B/C/N) ===== */
$weightSelect = '';
foreach($WEIGHT_COLS as $wc){ $weightSelect .= ', ce.'.bt($wc).' AS '.bt($wc); }

$sql = "
SELECT
  ce.id, ce.apellido, ce.nombre, ce.dni, ce.edad,
  ce.foto_competidor, ce.escuela_logo, ce.escuela_nombre,
  ce.sexo,
  ce.disciplina_id, d.nombre  AS disciplina,
  ce.modalidad_id,  m.nombre  AS modalidad,
  ce.division_id,   dv.nombre AS division,
  ce.categoria_tecnica_id,
  ce.categoria_peso_id,

  ct.codigo       AS cat_tec_codigo,
  ct.descripcion  AS cat_tec_descripcion,

  cat.nombre      AS cat_nombre,
  cat.peso_min    AS cat_peso_min,
  cat.peso_max    AS cat_peso_max,
  cat.genero      AS cat_genero
  {$weightSelect}
FROM competidores_evento ce
LEFT JOIN disciplinas_evento d          ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento m          ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento dv          ON dv.id = ce.division_id
LEFT JOIN categorias_evento cat         ON cat.id = ce.categoria_peso_id
LEFT JOIN categorias_tecnicas_evento ct ON ct.id  = ce.categoria_tecnica_id
WHERE ce.evento_id = ?
ORDER BY ce.apellido, ce.nombre
";
$st=$conexion->prepare($sql);
$st->bind_param('i',$evento_id);
$st->execute();
$res=$st->get_result();
$competidores_raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* ===== Normalizar valores de peso ===== */
function normalize_weight(?string $v): ?float {
  if ($v===null) return null;
  $s = trim((string)$v);
  if ($s==='') return null;
  $s = str_ireplace('kg','',$s);
  $s = str_replace([' ', "\xc2\xa0"], '', $s);
  $s = str_replace(',', '.', $s);
  if (!is_numeric($s)) return null;
  $f = (float)$s;
  if ($f <= 0) return null;
  return round($f, 2);
}
function normalize_range($min, $max): array {
  $mi = normalize_weight((string)$min);
  $ma = normalize_weight((string)$max);
  if ($mi!==null && $ma!==null && $ma>=$mi) return [round($mi,2), round($ma,2)];
  return [null,null];
}

/* ===== Construir array final de competidores ===== */
$competidores = [];
foreach($competidores_raw as $row){
  // Peso declarado (si existe alguna columna compatible)
  $decl = null;
  foreach($WEIGHT_COLS as $wc){
    if (array_key_exists($wc, $row)){
      $decl = normalize_weight($row[$wc]);
      if ($decl !== null) break;
    }
  }
  // Rango por categoría
  [$rmin,$rmax] = normalize_range($row['cat_peso_min'] ?? null, $row['cat_peso_max'] ?? null);
  // Texto para mostrar
  if ($decl !== null){
    $peso_txt = rtrim(rtrim(number_format($decl,2,'.',''), '0'), '.').' kg';
  } elseif ($rmin!==null && $rmax!==null) {
    $peso_txt = rtrim(rtrim(number_format($rmin,2,'.',''), '0'), '.').'–'.rtrim(rtrim(number_format($rmax,2,'.',''), '0'), '.').' kg (cat.)';
  } else {
    $peso_txt = '—';
  }
  // Peso efectivo para scoring (si no hay declarado, usar punto medio del rango)
  $peso_eff = $decl ?? (($rmin!==null && $rmax!==null) ? round(($rmin+$rmax)/2, 2) : null);

  $row['peso_decl_kg'] = $decl;        // número real si lo hay
  $row['peso_min_kg']  = $rmin;        // rango cat
  $row['peso_max_kg']  = $rmax;
  $row['peso_eff_kg']  = $peso_eff;    // para scoring
  $row['peso_txt']     = $peso_txt;    // para UI
  $row['sexo_short']   = sex_short($row['sexo'] ?? '');
  $row['cat_tec_codigo'] = trim((string)($row['cat_tec_codigo'] ?? ''));
  $competidores[] = $row;
}

/* Placeholders */
$placeholderFoto = 'assets/placeholder-user.png';
$placeholderLogo = 'assets/placeholder-logo.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Organizar Peleas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:1200px;margin:0 auto;padding:16px}
    .alert{padding:10px 12px;border-radius:8px;margin-bottom:12px}
    .alert.error{background:#fdecea;color:#b71c1c;border:1px solid #f5c6cb}
    .alert.ok{background:#e6f4ea;color:#0f5132;border:1px solid #badbcc}
    label{font-weight:600;font-size:14px}
    select,button,input[type=number],input[type=text]{width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px}
    .table-wrap{width:100%;overflow-x:auto}
    table{width:100%;border-collapse:collapse;min-width:1100px}
    th,td{border:1px solid #e7e7e7;padding:8px 10px;vertical-align:middle}
    th{background:#f6f7f9;text-align:left}
    .avatar{width:50px;height:50px;object-fit:cover;border-radius:8px}
    .logo{width:50px;height:50px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:8px}
    .cols{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    @media (max-width:880px){.cols{grid-template-columns:1fr}}
    .btn-primary{background:#1e88e5;color:#fff;border:0;padding:10px 14px;border-radius:10px;cursor:pointer}
    .btn-secondary{background:#e9ecef;color:#0f172a;border:0;padding:10px 14px;border-radius:10px;cursor:pointer;text-decoration:none;display:inline-block}
    .muted{color:#475569;font-size:13px}
    .slot-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}
    .legend{display:flex;gap:10px;align-items:center;font-size:13px;color:#334155;margin:8px 0 14px}
    .chip{padding:2px 8px;border-radius:999px;border:1px solid #cbd5e1;background:#f1f5f9}
    .layout{display:grid;grid-template-columns:1.6fr .9fr; gap:14px; align-items:start}
    @media (max-width:1000px){.layout{grid-template-columns:1fr}}
    .card{border:1px solid #e5e7eb;border-radius:12px;background:#fff}
    .card h3{margin:0;padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:16px}
    .card .body{padding:10px 12px}
    .sugg{display:flex;flex-direction:column;gap:8px}
    .sugg-item{border:1px dashed #e5e7eb;border-radius:10px;padding:8px}
    .tier-ex{color:#065f46;font-weight:700}
    .tier-mk{color:#047857;font-weight:700}
    .tier-kg{color:#92400e;font-weight:700}
    .tier-pc{color:#334155;font-weight:700}
    .compare{display:flex;gap:10px;flex-wrap:wrap;font-size:13px}
    .compare .pill{padding:4px 8px;border-radius:999px;border:1px solid #cbd5e1;background:#f8fafc}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🥊 Organización de Peleas — Evento #<?= (int)$evento_id ?></h2>

  <?php if (!empty($_SESSION['flash_error'])){ ?><div class="alert error"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php } ?>
  <?php if (!empty($_SESSION['flash_ok']))   { ?><div class="alert ok"><?= $_SESSION['flash_ok']; unset($_SESSION['flash_ok']); ?></div><?php } ?>

  <div class="legend">
    <span class="chip">Peso ideal:</span>
    <span class="tier-ex">Exacto (=0.00 kg)</span>
    <span class="tier-mk">±0.5 kg</span>
    <span class="tier-kg">±1.0 kg</span>
    <span class="tier-pc">Pactada (>1.0 kg)</span>
  </div>

  <form method="POST" action="" id="form-bout">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
    <input type="hidden" name="accion" value="crear_pelea">

    <div class="cols" style="margin:12px 0;">
      <div>
        <label>Formato</label>
        <select name="formato" id="formato">
          <option value="simple">1 vs 1</option>
          <option value="triangular">Triangular (3 competidores)</option>
          <option value="super4">Super 4 (cuadrangular)</option>
        </select>
      </div>
      <div>
        <label>Rondas</label>
        <input type="number" name="rondas" id="rondas_input" min="1" max="12" value="3">
      </div>
      <div>
        <label>Observaciones</label>
        <input type="text" name="observaciones" placeholder="(opcional)">
      </div>
    </div>

    <div class="layout">
      <div>
        <div id="slots-container" class="slot-grid" style="margin-bottom:10px;"></div>

        <!-- Comparación -->
        <div class="card" id="card-compare" style="display:none;margin-top:8px;">
          <h3>Comparación de pesos</h3>
          <div class="body">
            <div class="compare" id="compare-content"></div>
          </div>
        </div>

        <div class="cols" style="grid-template-columns:1fr auto;align-items:center;margin-top:10px;">
          <div class="muted">
            <b>Triangular:</b> SF (Rojo vs Azul) + <u>Libre</u> (espera al ganador).<br>
            <b>Super 4:</b> SF1 y SF2. La final se arma luego con ganadores.
          </div>
          <div>
            <button type="submit" class="btn-primary" id="btn-guardar">✅ Confirmar y Agregar pelea(s)</button>
            <a class="btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">📋 Ver/Editar/Eliminar peleas</a>
          </div>
        </div>
      </div>

      <!-- Sugerencias -->
      <div class="card">
        <h3>🔎 Sugerencias para Rojo</h3>
        <div class="body">
          <div class="muted">Ordena por puntaje total, luego por Δ (Exacto → ±0.5 → ±1.0 → Pactada).</div>
          <div class="sugg" id="sugg-list" style="margin-top:8px;"></div>
        </div>
      </div>
    </div>
  </form>

  <!-- Listado -->
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>Foto</th>
          <th>Apellido y Nombre</th>
          <th>DNI</th>
          <th>Edad</th>
          <th>Sexo</th>
          <th>Cat. Técnica</th>
          <th>Disciplina</th>
          <th>Modalidad</th>
          <th>División</th>
          <th>Peso</th>
          <th>Escuela</th>
          <th>Logo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$competidores){ ?>
        <tr><td colspan="13">No hay competidores cargados en este evento.</td></tr>
      <?php } else { foreach($competidores as $c){
        $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
        $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;
      ?>
        <tr>
          <td><img src="<?= h($srcFoto) ?>" class="avatar" alt="Foto"></td>
          <td><?= h($c['apellido'].' '.$c['nombre']) ?></td>
          <td><?= h($c['dni']) ?></td>
          <td><?= h($c['edad']) ?></td>
          <td><?= h($c['sexo_short']) ?></td>
          <td><?= h($c['cat_tec_codigo'] ?: '-') ?></td>
          <td><span class="muted"><?= h($c['disciplina'] ?? '-') ?></span></td>
          <td><?= h($c['modalidad'] ?? '-') ?></td>
          <td><?= h($c['division'] ?? '-') ?></td>
          <td><?= h($c['peso_txt']) ?></td>
          <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
          <td><img src="<?= h($srcLogo) ?>" class="logo" alt="Logo"></td>
          <td><a class="btn-secondary" href="editar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>&id=<?= (int)$c['id'] ?>">✏️ Editar</a></td>
        </tr>
      <?php } } ?>
      </tbody>
    </table>
  </div>

  <!-- Opciones para selects -->
  <?php
    ob_start();
    echo '<option value=\"\">Seleccioná competidor…</option>';
    foreach($competidores as $c){
      $esc   = trim((string)($c['escuela_nombre'] ?? '-'));
      $sx    = $c['sexo_short'];
      $tec   = $c['cat_tec_codigo'] ?: '-';
      $label = trim(($c['apellido'].' '.$c['nombre']).' — '.($c['division'] ?? '-').' / '.($c['modalidad'] ?? '-').' — '.$c['peso_txt'].' — ['.$sx.' · Tec: '.$tec.'] — '.$esc);
      echo '<option value="'.(int)$c['id'].'">'.h($label).'</option>';
    }
    $OPTIONS_HTML = ob_get_clean();

    // JSON para scoring
    $map = [];
    foreach($competidores as $c){
      $map[(int)$c['id']] = [
        'id' => (int)$c['id'],
        'nombre' => trim(($c['apellido'] ?? '').' '.($c['nombre'] ?? '')),
        'sexo' => (string)($c['sexo'] ?? ''),
        'sexo_short' => (string)$c['sexo_short'],
        'disciplina_id' => (int)($c['disciplina_id'] ?? 0),
        'modalidad_id'  => (int)($c['modalidad_id'] ?? 0),
        'division_id'   => (int)($c['division_id'] ?? 0),
        'division_txt'  => (string)($c['division'] ?? ''),
        'modalidad_txt' => (string)($c['modalidad'] ?? ''),
        'categoria_tecnica_id' => (int)($c['categoria_tecnica_id'] ?? 0),
        'cat_tec_codigo' => (string)($c['cat_tec_codigo'] ?? ''),
        'categoria_peso_id'    => (int)($c['categoria_peso_id'] ?? 0),
        'peso_decl_kg' => ($c['peso_decl_kg']===null ? null : (float)$c['peso_decl_kg']),
        'peso_min_kg'  => ($c['peso_min_kg']===null ? null : (float)$c['peso_min_kg']),
        'peso_max_kg'  => ($c['peso_max_kg']===null ? null : (float)$c['peso_max_kg']),
        'peso_eff_kg'  => ($c['peso_eff_kg']===null ? null : (float)$c['peso_eff_kg']),
        'peso_txt'     => (string)$c['peso_txt'],
        'escuela_nombre' => (string)($c['escuela_nombre'] ?? ''),
      ];
    }
    $COMP_JSON = json_encode($map, JSON_UNESCAPED_UNICODE);
  ?>
</div>

<script>
  const formatoSel = document.getElementById('formato');
  const slots = document.getElementById('slots-container');
  const btn = document.getElementById('btn-guardar');
  const optionsHTML = <?php echo json_encode($OPTIONS_HTML, JSON_UNESCAPED_UNICODE); ?>;
  const COMP = <?php echo $COMP_JSON; ?>;
  const suggList = document.getElementById('sugg-list');
  const cardCompare = document.getElementById('card-compare');
  const compareContent = document.getElementById('compare-content');

  const TIER = { EXACT: 'Exacto', HALF: '±0.5', ONE: '±1.0', PACT: 'Pactada' };

  function kg(n){ return (Math.round(n*100)/100).toFixed(2).replace(/\.00$/,''); }
  function kgText(x){ if (x==null || isNaN(x)) return '—'; return kg(parseFloat(x))+' kg'; }

  // distancia entre rangos (0 si solapan)
  function rangeDistance(aMin,aMax,bMin,bMax){
    if(aMin==null||aMax==null||bMin==null||bMax==null) return null;
    if (aMax < bMin) return bMin - aMax;
    if (bMax < aMin) return aMin - bMax;
    return 0;
  }

  // delta preferido
  function delta(a,b){
    if (a.peso_eff_kg!=null && b.peso_eff_kg!=null){
      const d = Math.abs(parseFloat(a.peso_eff_kg) - parseFloat(b.peso_eff_kg));
      return Number.isNaN(d) ? null : d;
    }
    return rangeDistance(a.peso_min_kg,a.peso_max_kg,b.peso_min_kg,b.peso_max_kg);
  }

  // tier por delta
  function tierOfDelta(d){
    if (d==null) return TIER.PACT;
    if (d === 0) return TIER.EXACT;
    if (d <= 0.5) return TIER.HALF;
    if (d <= 1.0) return TIER.ONE;
    return TIER.PACT;
  }

  // Puntaje (incluye sexo y cat técnica + tiers de peso)
  function scoreMatch(a, b){
    if(!a || !b) return 0;
    let s = 0;
    if (a.sexo && b.sexo && a.sexo.toLowerCase() === b.sexo.toLowerCase()) s += 1;     // SEXO
    if (a.categoria_tecnica_id && a.categoria_tecnica_id === b.categoria_tecnica_id) s += 1; // TECNICA A/B/C/N
    if (a.modalidad_id && a.modalidad_id === b.modalidad_id) s += 1;
    if (a.disciplina_id && a.disciplina_id === b.disciplina_id) s += 1;
    if (a.division_id && a.division_id === b.division_id) s += 1;
    if (a.categoria_peso_id && a.categoria_peso_id === b.categoria_peso_id) s += 2;

    const d = delta(a,b);
    const tr = tierOfDelta(d);
    if (tr === TIER.EXACT) s += 3;
    else if (tr === TIER.HALF) s += 2;
    else if (tr === TIER.ONE) s += 1;
    // Pactada = 0

    if ((a.escuela_nombre||'').trim() && (b.escuela_nombre||'').trim()){
      if (a.escuela_nombre.trim().toLowerCase() !== b.escuela_nombre.trim().toLowerCase()){
        s += 1;
      }
    }
    return s;
  }

  function badgeForTier(tr){
    if (tr===TIER.EXACT) return '🟩 Exacto';
    if (tr===TIER.HALF)  return '🟩 ±0.5';
    if (tr===TIER.ONE)   return '🟨 ±1.0';
    return '⚪ Pactada';
  }

  // colorear opciones Azul
  function colorOpponents(azulSelect, rojoId){
    if (!azulSelect) return;
    Array.from(azulSelect.options).forEach(opt=>{ if (opt.dataset && opt.dataset.label){ opt.textContent = opt.dataset.label; } });
    const a = COMP[rojoId]; if (!a) return;
    Array.from(azulSelect.options).forEach(opt=>{
      if (!opt.value) return;
      const id = parseInt(opt.value,10);
      if (!id || id===rojoId) return;
      const b = COMP[id]; if(!b) return;
      const d  = delta(a,b);
      const tr = tierOfDelta(d);
      const badge = badgeForTier(tr);
      if (!opt.dataset.label) opt.dataset.label = opt.textContent;
      opt.textContent = badge ? (badge + ' · ' + opt.dataset.label) : opt.dataset.label;
    });
  }

  // sugerencias
  function renderSuggestions(rojoId){
    if (!suggList) return;
    suggList.innerHTML = '';
    const a = COMP[rojoId];
    if (!a){ suggList.innerHTML = '<div class="muted">Seleccioná el Rincón Rojo para ver sugerencias.</div>'; return; }

    const items = Object.values(COMP)
      .filter(b => b.id !== a.id)
      .map(b => {
        const s  = scoreMatch(a,b);
        const d  = delta(a,b);
        const tr = tierOfDelta(d);
        return { b, s, d, tr };
      })
      .sort((u,v)=>{
        if (v.s !== u.s) return v.s - u.s;     // mayor puntaje
        const du = (u.d==null)? 999 : u.d;
        const dv = (v.d==null)? 999 : v.d;
        return du - dv;                         // menor Δ
      })
      .slice(0, 14);

    if (!items.length){ suggList.innerHTML = '<div class="muted">No hay rivales para sugerir.</div>'; return; }

    items.forEach(({b,s,d,tr})=>{
      const cls = (tr===TIER.EXACT)?'tier-ex':(tr===TIER.HALF)?'tier-mk':(tr===TIER.ONE)?'tier-kg':'tier-pc';
      const node = document.createElement('div');
      node.className = 'sugg-item';
      node.innerHTML = `
        <div><span class="${cls}">${badgeForTier(tr)}</span>
          <b>${b.nombre || ('ID '+b.id)}</b>
          <span class="muted">— ${b.modalidad_txt||'-'} / ${b.division_txt||'-'} · [${b.sexo_short||'-'} · Tec: ${b.cat_tec_codigo||'-'}]</span>
        </div>
        <div class="muted">Rojo: <b>${COMP[rojoId].peso_txt}</b> · Rival: <b>${b.peso_txt}</b> · Δ: <b>${d==null?'—':kg(d)+' kg'}</b></div>
      `;
      suggList.appendChild(node);
    });
  }

  // comparación
  function renderCompare(rojoId, azulId){
    if (!cardCompare || !compareContent) return;
    const a = COMP[rojoId], b = COMP[azulId];
    if (!a || !b){ cardCompare.style.display='none'; compareContent.innerHTML=''; return; }
    const d  = delta(a,b);
    const tr = tierOfDelta(d);
    compareContent.innerHTML = `
      <span class="pill"><b>Rojo:</b> ${a.nombre || ('ID '+a.id)}</span>
      <span class="pill">Sexo/Tec: <b>${a.sexo_short||'-'} · ${a.cat_tec_codigo||'-'}</b></span>
      <span class="pill">Peso: <b>${a.peso_txt}</b></span>
      <span class="pill"><b>Azul:</b> ${b.nombre || ('ID '+b.id)}</span>
      <span class="pill">Sexo/Tec: <b>${b.sexo_short||'-'} · ${b.cat_tec_codigo||'-'}</b></span>
      <span class="pill">Peso: <b>${b.peso_txt}</b></span>
      <span class="pill"><b>${badgeForTier(tr)}</b>${d==null?'':' — Δ '+kg(d)+' kg'}</span>
    `;
    cardCompare.style.display = 'block';
  }

  // slots
  function selectTpl(name, label){
    return `
      <div>
        <label>${label}</label>
        <select name="${name}" class="slot-select">
          ${optionsHTML}
        </select>
      </div>
    `;
  }
  function renderSlots(){
    const fmt = formatoSel.value;
    let html = '';
    if (fmt === 'simple'){
      html = `${selectTpl('rojo_id','Rincón Rojo')}${selectTpl('azul_id','Rincón Azul')}`;
    } else if (fmt === 'triangular'){
      html = `
        ${selectTpl('tri_rojo_id','Triangular — SF (Rojo)')}
        ${selectTpl('tri_azul_id','Triangular — SF (Azul)')}
        <div class="full" style="height:0;"></div>
        ${selectTpl('tri_libre_id','Triangular — Libre (espera la final)')}
      `;
    } else {
      html = `
        <div class="full" style="font-weight:600;">Semifinal 1</div>
        ${selectTpl('sf1_rojo_id','SF1 — Rojo')}
        ${selectTpl('sf1_azul_id','SF1 — Azul')}
        <div class="full" style="font-weight:600;margin-top:6px;">Semifinal 2</div>
        ${selectTpl('sf2_rojo_id','SF2 — Rojo')}
        ${selectTpl('sf2_azul_id','SF2 — Azul')}
        <div class="full muted" style="margin-top:6px;">Final libre: se arma luego con ganadores</div>
      `;
    }
    slots.innerHTML = html;
    attachLogic();
    validar();
    renderSuggestions(null);
    renderCompare(null, null);
  }

  function attachLogic(){
    const selects = Array.from(document.querySelectorAll('.slot-select'));
    function refreshDisables(){
      const used = new Set(selects.map(s => s.value).filter(v => v));
      selects.forEach(sel => {
        const cur = sel.value;
        Array.from(sel.options).forEach(opt => {
          if (!opt.value) return;
          opt.disabled = (opt.value !== cur) && used.has(opt.value);
        });
      });
    }
    const fmt = formatoSel.value;
    function pair(rojoName, azulName){
      const rojo = document.querySelector(`[name="${rojoName}"]`);
      const azul = document.querySelector(`[name="${azulName}"]`);
      if (!rojo || !azul) return;

      if (rojo.value) {
        const rId = parseInt(rojo.value,10);
        colorOpponents(azul, rId);
        renderSuggestions(rId);
      }
      if (rojo.value && azul.value){
        renderCompare(parseInt(rojo.value,10), parseInt(azul.value,10));
      }

      rojo.addEventListener('change', ()=>{
        refreshDisables();
        const rId = parseInt(rojo.value||'0',10);
        colorOpponents(azul, rId);
        renderSuggestions(rId || null);
        const aId = parseInt(azul.value||'0',10);
        if (rId && aId && rId!==aId) renderCompare(rId,aId); else renderCompare(null,null);
        validar();
      });
      azul.addEventListener('change', ()=>{
        refreshDisables();
        const rId = parseInt(rojo.value||'0',10);
        const aId = parseInt(azul.value||'0',10);
        if (rId && aId && rId!==aId) renderCompare(rId,aId); else renderCompare(null,null);
        validar();
      });
    }
    if (fmt === 'simple'){
      pair('rojo_id','azul_id');
    } else if (fmt === 'triangular'){
      pair('tri_rojo_id','tri_azul_id');
    } else {
      pair('sf1_rojo_id','sf1_azul_id');
      pair('sf2_rojo_id','sf2_azul_id');
    }

    selects.forEach(sel => sel.addEventListener('change', () => { refreshDisables(); validar(); }));
    refreshDisables();
  }

  function validar(){
    if (!btn) return;
    const fmt = formatoSel.value;
    function getVal(name){ const s=document.querySelector(`[name="${name}"]`); return s ? parseInt(s.value||'0') : 0; }
    if (fmt === 'simple'){
      const r=getVal('rojo_id'), a=getVal('azul_id');
      btn.disabled = !(r && a && r!==a);
      btn.title = btn.disabled ? "Elegí Rojo y Azul (distintos)." : "";
      return;
    }
    if (fmt === 'triangular'){
      const r=getVal('tri_rojo_id'), a=getVal('tri_azul_id'), l=getVal('tri_libre_id');
      const all=[r,a,l].filter(Boolean);
      btn.disabled = !(r && a && l && (new Set(all).size===3));
      btn.title = btn.disabled ? "Completá los 3 slots con competidores distintos." : "";
      return;
    }
    const r1=getVal('sf1_rojo_id'), a1=getVal('sf1_azul_id'), r2=getVal('sf2_rojo_id'), a2=getVal('sf2_azul_id');
    const vals=[r1,a1,r2,a2].filter(Boolean);
    btn.disabled = !(vals.length===4 && (new Set(vals).size===4));
    btn.title = btn.disabled ? "Completá SF1 y SF2 con 4 competidores distintos." : "";
  }

  document.getElementById('formato').addEventListener('change', renderSlots);
  renderSlots();
</script>
</body>
</html>
