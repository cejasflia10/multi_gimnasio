<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_eventos.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Utilidades ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``',$col).'`'; }
function flash_err($m){ $_SESSION['flash_error']=$m; }
function flash_ok($m){ $_SESSION['flash_ok']=$m; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $rs = $db->query("SHOW TABLES LIKE '$name'");
  return $rs && $rs->num_rows>0;
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
    // NOTA: la final se arma luego; el "libre" espera.
  } else { // super4
    $r1=(int)($_POST['sf1_rojo_id'] ?? 0); $a1=(int)($_POST['sf1_azul_id'] ?? 0);
    $r2=(int)($_POST['sf2_rojo_id'] ?? 0); $a2=(int)($_POST['sf2_azul_id'] ?? 0);
    if (min($r1,$a1,$r2,$a2)<=0){ flash_err('Completá SF1 y SF2.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    if (count(array_unique([$r1,$a1,$r2,$a2]))!==4){ flash_err('Los 4 competidores deben ser distintos.'); header('Location: organizar_pelea.php?evento_id='.$evento_id); exit; }
    $pairs[] = [$r1,$a1,' (Super 4 - SF1)'];
    $pairs[] = [$r2,$a2,' (Super 4 - SF2)'];
  }

  // INSERT directo (sin validaciones extra)
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

/* ================= Catálogos básicos (solo para filtros/labels) ================= */
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento ORDER BY nombre");
$modalidades = $conexion->query("SELECT id, nombre FROM modalidades_evento ORDER BY nombre");
$divisiones  = $conexion->query("SELECT id, nombre FROM divisiones_evento  ORDER BY nombre");

/* ================= Listado de competidores (simple) ================= */
$sql = "
SELECT
  ce.id, ce.apellido, ce.nombre, ce.dni, ce.edad,
  ce.foto_competidor, ce.escuela_logo, ce.escuela_nombre,
  d.nombre  AS disciplina,
  m.nombre  AS modalidad,
  dv.nombre AS division,
  ce.peso_kg
FROM competidores_evento ce
LEFT JOIN disciplinas_evento d  ON d.id  = ce.disciplina_id
LEFT JOIN modalidades_evento m  ON m.id  = ce.modalidad_id
LEFT JOIN divisiones_evento dv ON dv.id = ce.division_id
WHERE ce.evento_id = ?
ORDER BY ce.apellido, ce.nombre
";
$st=$conexion->prepare($sql);
$st->bind_param('i',$evento_id);
$st->execute();
$res=$st->get_result();
$competidores = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

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
    .filters{display:grid;grid-template-columns:repeat(6,minmax(160px,1fr));gap:12px;align-items:end;margin-bottom:14px}
    @media (max-width:1000px){.filters{grid-template-columns:repeat(3,1fr)}}
    @media (max-width:640px){.filters{grid-template-columns:1fr}}
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
    .btn-danger{background:#dc2626;color:#fff;border:0;padding:8px 12px;border-radius:8px;cursor:pointer}
    .muted{color:#475569;font-size:13px}
    form.inline{display:inline}
    .slot-grid{display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:10px}
    .slot-grid .full{grid-column:1/-1}
    .small-note{font-size:12px;color:#6b7280;margin-top:6px}
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🥊 Organización de Peleas — Evento #<?= (int)$evento_id ?></h2>

  <?php if (!empty($_SESSION['flash_error'])){ ?><div class="alert error"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php } ?>
  <?php if (!empty($_SESSION['flash_ok']))   { ?><div class="alert ok"><?= $_SESSION['flash_ok']; unset($_SESSION['flash_ok']); ?></div><?php } ?>

  <!-- CREACIÓN DE PELEA(S) -->
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

    <div id="slots-container" class="slot-grid" style="margin-bottom:10px;"></div>

    <div class="cols" style="grid-template-columns:1fr auto;align-items:center;">
      <div class="muted">
        <b>Triangular:</b> SF (Rojo vs Azul) + <u>Libre</u> (espera al ganador).<br>
        <b>Super 4:</b> SF1 y SF2. La final se arma luego con ganadores.
      </div>
      <div>
        <button type="submit" class="btn-primary" id="btn-guardar">✅ Confirmar y Agregar pelea(s)</button>
        <a class="btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">📋 Ver/Editar/Eliminar peleas</a>
      </div>
    </div>
  </form>

  <!-- LISTADO DE COMPETIDORES -->
  <div class="table-wrap" style="margin-top:12px;">
    <table>
      <thead>
        <tr>
          <th>Foto</th>
          <th>Apellido y Nombre</th>
          <th>DNI</th>
          <th>Edad</th>
          <th>Disciplina</th>
          <th>Modalidad</th>
          <th>División</th>
          <th>Peso (decl.)</th>
          <th>Escuela</th>
          <th>Logo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$competidores){ ?>
        <tr><td colspan="11">No hay competidores cargados en este evento.</td></tr>
      <?php } else { foreach($competidores as $c){
        $srcFoto = !empty($c['foto_competidor']) ? $c['foto_competidor'] : $placeholderFoto;
        $srcLogo = !empty($c['escuela_logo'])    ? $c['escuela_logo']    : $placeholderLogo;
        $pesoDecl = (isset($c['peso_kg']) && $c['peso_kg']!=='') ? (rtrim(rtrim(number_format((float)$c['peso_kg'],2,'.',''), '0'), '.').' kg') : '—';
      ?>
        <tr>
          <td><img src="<?= h($srcFoto) ?>" class="avatar" alt="Foto"></td>
          <td><?= h($c['apellido'].' '.$c['nombre']) ?></td>
          <td><?= h($c['dni']) ?></td>
          <td><?= h($c['edad']) ?></td>
          <td><span class="muted"><?= h($c['disciplina'] ?? '-') ?></span></td>
          <td><?= h($c['modalidad'] ?? '-') ?></td>
          <td><?= h($c['division'] ?? '-') ?></td>
          <td><?= h($pesoDecl) ?></td>
          <td><?= h($c['escuela_nombre'] ?? '-') ?></td>
          <td><img src="<?= h($srcLogo) ?>" class="logo" alt="Logo"></td>
          <td>
            <a class="btn-secondary" href="editar_competidor_evento.php?evento_id=<?= (int)$evento_id ?>&id=<?= (int)$c['id'] ?>">✏️ Editar</a>
          </td>
        </tr>
      <?php } } ?>
      </tbody>
    </table>
  </div>

  <!-- Opciones para selects (incluye Escuela, Peso declar., División y Modalidad SOLO a modo informativo en la etiqueta) -->
  <?php
    ob_start();
    echo '<option value=\"\">Seleccioná competidor…</option>';
    foreach($competidores as $c){
      $pesoDecl = (isset($c['peso_kg']) && $c['peso_kg']!=='') ? (rtrim(rtrim(number_format((float)$c['peso_kg'],2,'.',''), '0'), '.').' kg') : '—';
      $esc     = trim((string)($c['escuela_nombre'] ?? '-'));
      $label = trim(($c['apellido'].' '.$c['nombre']).' — '.($c['division'] ?? '-').' / '.($c['modalidad'] ?? '-').' — '.$pesoDecl.' — '.$esc);
      echo '<option value="'.(int)$c['id'].'">'.h($label).'</option>';
    }
    $OPTIONS_HTML = ob_get_clean();
  ?>
</div>

<script>
  const formatoSel = document.getElementById('formato');
  const slots = document.getElementById('slots-container');
  const btn = document.getElementById('btn-guardar');
  const optionsHTML = <?php echo json_encode($OPTIONS_HTML, JSON_UNESCAPED_UNICODE); ?>;

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
    attachUniqueLogic();
    validar();
  }

  function attachUniqueLogic(){
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
    selects.forEach(sel => sel.addEventListener('change', () => { refreshDisables(); validar(); }));
    refreshDisables();
  }

  // Validación mínima: sólo que estén completos y sean distintos
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

  formatoSel.addEventListener('change', renderSlots);
  renderSlots();
</script>
</body>
</html>
