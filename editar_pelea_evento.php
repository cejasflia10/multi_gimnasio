<?php
/* ============================================
   editar_pelea_evento.php
   • Edita modalidad (id o texto), rondas y observaciones
   • Permite cambiar competidor rojo/azul (selects)
   • Tolerante a esquema (descubre columnas)
   • Muestra info básica de la pelea/competidores
   ============================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ---------- helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($col){ return '`'.str_replace('`','``', $col).'`'; }

/* ---------- inputs ---------- */
$evento_id = (int)($_GET['evento_id'] ?? $_POST['evento_id'] ?? 0);
$pelea_id  = (int)($_GET['pelea_id']  ?? $_POST['pelea_id']  ?? 0);

if ($evento_id<=0 || $pelea_id<=0){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Faltan parámetros. Volvé desde <a href="ver_peleas_evento.php">ver_peleas_evento</a>.</div>';
  exit;
}

/* ---------- descubrir columnas de peleas_evento ---------- */
$colsP = [];
$rsp = $conexion->query("SHOW COLUMNS FROM peleas_evento");
if(!$rsp){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>';
  exit;
}
while($r=$rsp->fetch_assoc()){ $colsP[strtolower($r['Field'])]=$r['Field']; }
$pickP = function(array $cands) use ($colsP){
  foreach($cands as $c){ $lc=strtolower($c); if(isset($colsP[$lc])) return $colsP[$lc]; }
  return null;
};

$C_ID        = $pickP(['id','pelea_id','id_pelea']);
$C_EVENTO    = $pickP(['evento_id','id_evento','evento']);
$C_ROJO      = $pickP(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL      = $pickP(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS    = $pickP(['rondas','rounds']);
$C_OBS       = $pickP(['observaciones','obs','comentarios','comentario','nota']);
$C_ORDEN     = $pickP(['orden','orden_manual','nro','nro_orden','posicion','position','sequence','rank','numero','nro_pelea','sort']);

/* Modalidad en PELEA (opcional, texto e id) */
$C_MODAL_P_ID  = $pickP(['modalidad_id','id_modalidad','modalidad_evento_id']);
$C_MODAL_P_TXT = $pickP(['modalidad','modo','reglamento','disciplina','tipo','tipo_pelea','titulo_modalidad']);

/* ---------- columnas de competidores_evento ---------- */
$colsC=[];
if($rc=$conexion->query("SHOW COLUMNS FROM competidores_evento")){
  while($r=$rc->fetch_assoc()){ $colsC[strtolower($r['Field'])]=$r['Field']; }
}
$pickC=function(array $cands) use ($colsC){
  foreach($cands as $c){ $lc=strtolower($c); if(isset($colsC[$lc])) return $colsC[$lc]; }
  return null;
};

$CE_ID   = $pickC(['id','competidor_id']);
$CE_APE  = $pickC(['apellido','apellidos','last_name']);
$CE_NOM  = $pickC(['nombre','nombres','first_name']);
$CE_ESC  = $pickC(['escuela_nombre','escuela','gimnasio','gym']);
$CE_FOTO = $pickC(['foto_competidor','foto','imagen','avatar','foto_url','image_url']);
$CE_EVT  = $pickC(['evento_id','id_evento','evento']); // para filtrar los competidores del evento

/* ---------- leer pelea + competidores actuales ---------- */
$select = [
  'p.'.bt($C_ID?:'id').' AS pelea_id',
  $C_RONDAS ? 'p.'.bt($C_RONDAS).' AS rondas' : 'NULL AS rondas',
  $C_OBS    ? 'p.'.bt($C_OBS).' AS observaciones' : 'NULL AS observaciones',
  $C_ROJO ? 'p.'.bt($C_ROJO).' AS rojo_id' : 'NULL AS rojo_id',
  $C_AZUL ? 'p.'.bt($C_AZUL).' AS azul_id' : 'NULL AS azul_id',
];

/* modalidades */
$tablaModal = (($t=$conexion->query("SHOW TABLES LIKE 'modalidades_evento'")) && $t->num_rows>0) ? 'modalidades_evento' : null;
$MOD_LABEL_COL='nombre';
if ($tablaModal){
  $mc=[];
  if($rc=$conexion->query("SHOW COLUMNS FROM $tablaModal")){
    while($r=$rc->fetch_assoc()){ $mc[strtolower($r['Field'])]=$r['Field']; }
  }
  $MOD_LABEL_COL=$mc['nombre']??($mc['modalidad']??($mc['descripcion']??($mc['name']??'nombre')));
}

if ($C_MODAL_P_ID && $tablaModal){
  $select[] = 'p.'.bt($C_MODAL_P_ID).' AS modalidad_id';
  $select[] = 'm.'.bt($MOD_LABEL_COL).' AS modalidad_lbl';
} else {
  $select[]='NULL AS modalidad_id';
  $select[]='NULL AS modalidad_lbl';
}

if ($C_MODAL_P_TXT){
  $select[] = 'p.'.bt($C_MODAL_P_TXT).' AS modalidad_txt';
} else {
  $select[]='NULL AS modalidad_txt';
}

/* competidores (datos para header) */
$select[] = 'cr.'.bt($CE_APE?:'apellido').' AS r_apellido';
$select[] = 'cr.'.bt($CE_NOM?:'nombre').' AS r_nombre';
$select[] = $CE_ESC ? 'cr.'.bt($CE_ESC).' AS r_escuela' : "NULL AS r_escuela";
$select[] = $CE_FOTO? 'cr.'.bt($CE_FOTO).' AS r_foto'    : "NULL AS r_foto";

$select[] = 'ca.'.bt($CE_APE?:'apellido').' AS a_apellido';
$select[] = 'ca.'.bt($CE_NOM?:'nombre').' AS a_nombre';
$select[] = $CE_ESC ? 'ca.'.bt($CE_ESC).' AS a_escuela' : "NULL AS a_escuela";
$select[] = $CE_FOTO? 'ca.'.bt($CE_FOTO).' AS a_foto'    : "NULL AS a_foto";

$joins=[];
if ($tablaModal && $C_MODAL_P_ID){
  $joins[]="LEFT JOIN $tablaModal m ON m.id = p.".bt($C_MODAL_P_ID);
}

$sql = "SELECT ".implode(",\n  ",$select)."
FROM peleas_evento p
JOIN competidores_evento cr ON p.".bt($C_ROJO)." = cr.".bt($CE_ID?:'id')."
LEFT JOIN competidores_evento ca ON p.".bt($C_AZUL)." = ca.".bt($CE_ID?:'id')."
".implode("\n",$joins)."
WHERE p.".bt($C_EVENTO)."=? AND p.".bt($C_ID?:'id')."=? LIMIT 1";

$st=$conexion->prepare($sql);
if(!$st){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">Error preparando detalle: '.h($conexion->error).'</div>';
  exit;
}
$st->bind_param('ii',$evento_id,$pelea_id);
$st->execute();
$pelea = $st->get_result()->fetch_assoc();
$st->close();
if(!$pelea){
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;border-radius:8px;">No se encontró la pelea.</div>';
  exit;
}

/* ---------- catálogo de modalidades (opcional) ---------- */
$modalidades = [];
if ($tablaModal){
  $q = "SELECT id, ".bt($MOD_LABEL_COL)." AS nombre FROM $tablaModal ORDER BY ".bt($MOD_LABEL_COL);
  if($rs=$conexion->query($q)){
    $modalidades = $rs->fetch_all(MYSQLI_ASSOC);
  }
  // Quitar duplicados tipo Low Kick / Lowkick / K1 / K-1, etc.
  if ($modalidades){
    $seen = [];
    $filtered = [];
    foreach ($modalidades as $m){
      $lbl = trim((string)$m['nombre']);
      if ($lbl === '') continue;
      $key = mb_strtolower($lbl,'UTF-8');
      $key = preg_replace('/[\s\-]+/u','', $key);
      if (isset($seen[$key])) continue;
      $seen[$key] = true;
      $m['nombre'] = $lbl;
      $filtered[] = $m;
    }
    $modalidades = $filtered;
  }
}

/* ---------- plantillas de título para modalidad_txt ---------- */
$plantillas_titulo = [
  'Boxeo título amateur',
  'Lowkick título amateurs',
  'Lowkick título proam',
  'Lowkick título profesional',
  'K1 título amateur',
  'K1 título proam',
  'K1 título profesional',
  'Muay Thai título amateur',
  'Muay Thai título proam',
];

/* ---------- catálogo de competidores del evento (para selects) ---------- */
$competidores = [];
if ($CE_ID && $CE_EVT){
  $q = "SELECT "
      . bt($CE_ID) ." AS id, "
      . bt($CE_APE?:'apellido') ." AS ape, "
      . bt($CE_NOM?:'nombre')  ." AS nom, "
      . ($CE_ESC ? bt($CE_ESC)." AS esc" : "'-' AS esc") . "
    FROM competidores_evento
    WHERE ".bt($CE_EVT)." = ?
    ORDER BY ape, nom";
  if ($st=$conexion->prepare($q)){
    $st->bind_param('i',$evento_id);
    $st->execute();
    $competidores = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
  }
}

/* ---------- POST: guardar ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion']) && $_POST['accion']==='guardar'){
  $rondas = isset($_POST['rondas']) && $_POST['rondas']!=='' ? (int)$_POST['rondas'] : null;
  $obs    = trim((string)($_POST['observaciones'] ?? ''));
  $mod_id = isset($_POST['modalidad_id']) && $_POST['modalidad_id']!=='' ? (int)$_POST['modalidad_id'] : null;
  $mod_tx = trim((string)($_POST['modalidad_txt'] ?? ''));

  $nuevo_rojo = isset($_POST['rojo_id']) && $_POST['rojo_id']!=='' ? (int)$_POST['rojo_id'] : null;
  $nuevo_azul = isset($_POST['azul_id']) && $_POST['azul_id']!=='' ? (int)$_POST['azul_id'] : null;

  if ($nuevo_rojo !== null && $nuevo_azul !== null && $nuevo_rojo === $nuevo_azul){
    $_SESSION['flash_error'] = 'Las dos esquinas no pueden tener el mismo competidor.';
    header('Location: editar_pelea_evento.php?evento_id='.$evento_id.'&pelea_id='.$pelea_id);
    exit;
  }

  $set = []; $types=''; $vals=[];

  if ($C_RONDAS !== null && $C_RONDAS !== ''){
    $set[] = bt($C_RONDAS).'=?'; $types.='i'; $vals[] = $rondas;
  }
  if ($C_OBS !== null && $C_OBS !== ''){
    $set[] = bt($C_OBS).'=?'; $types.='s'; $vals[] = ($obs!=='' ? $obs : null);
  }
  if ($C_MODAL_P_ID){
    $set[] = bt($C_MODAL_P_ID).'=?'; $types.='i'; $vals[] = $mod_id;
  }
  if ($C_MODAL_P_TXT){
    $set[] = bt($C_MODAL_P_TXT).'=?'; $types.='s'; $vals[] = ($mod_tx!=='' ? $mod_tx : null);
  }
  if ($C_ROJO){
    $set[] = bt($C_ROJO).'=?'; $types.='i'; $vals[] = $nuevo_rojo;
  }
  if ($C_AZUL){
    $set[] = bt($C_AZUL).'=?'; $types.='i'; $vals[] = $nuevo_azul;
  }

  if (!$set){
    $_SESSION['flash_error'] = 'No hay columnas para actualizar (revisá el esquema de <b>peleas_evento</b>).';
    header('Location: ver_peleas_evento.php?evento_id='.$evento_id);
    exit;
  }

  $sqlUp = "UPDATE peleas_evento SET ".implode(',', $set)." WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID?:'id')."=? LIMIT 1";
  $types .= 'ii'; $vals[]=$evento_id; $vals[]=$pelea_id;

  $st=$conexion->prepare($sqlUp);
  if(!$st){
    $_SESSION['flash_error'] = 'Prep update: '.$conexion->error;
    header('Location: editar_pelea_evento.php?evento_id='.$evento_id.'&pelea_id='.$pelea_id);
    exit;
  }

  // Si algún entero va como NULL, cambiamos tipo a 's' para que pase NULL real
  for ($i=0;$i<strlen($types);$i++){
    if ($types[$i]==='i' && $vals[$i]===null) { $types[$i]='s'; }
  }

  $st->bind_param($types, ...$vals);
  $st->execute();
  $ok = $st->affected_rows>=0;
  $st->close();

  if($ok) $_SESSION['flash_ok']='✅ Cambios guardados.';
  else    $_SESSION['flash_warn']='ℹ️ No hubo cambios para guardar.';
  header('Location: ver_peleas_evento.php?evento_id='.$evento_id);
  exit;
}

/* ---------- UI helpers ---------- */
function initials($ap,$no){
  $a = mb_substr(trim((string)$ap),0,1,'UTF-8');
  $n = mb_substr(trim((string)$no),0,1,'UTF-8');
  $t = trim($a.$n);
  return $t!=='' ? mb_strtoupper($t,'UTF-8') : '—';
}
$rIni = initials($pelea['r_apellido']??'',$pelea['r_nombre']??'');
$aIni = initials($pelea['a_apellido']??'',$pelea['a_nombre']??'');

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar pelea #<?= (int)$pelea_id ?> · Evento #<?= (int)$evento_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --bg:#ffffff; --card:#ffffff; --text:#0b0f19; --muted:#374151; --line:#cbd5e1;
      --btn:#1e88e5; --btn-sec-bg:#e5e7eb; --btn-dg:#d32f2f; --ok:#2e7d32;
    }
    *,*::before,*::after{box-sizing:border-box}
    html,body{background:var(--bg);color:var(--text);line-height:1.45}
    a{color:inherit}
    .wrap{max-width:900px;margin:0 auto;padding:16px}
    .card{border:1px solid var(--line);border-radius:12px;background:var(--card);padding:14px;margin-bottom:14px}
    .title{margin:0 0 8px 0;font-weight:900}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .field{display:flex;flex-direction:column;gap:6px}
    label{font-weight:800}
    input[type="text"],input[type="number"],select,textarea{padding:9px 10px;border:1px solid var(--line);border-radius:10px}
    textarea{min-height:92px}
    .btn{display:inline-block;padding:9px 12px;border-radius:10px;border:0;cursor:pointer;font-weight:800}
    .btn-primary{background:var(--btn);color:#fff}
    .btn-secondary{background:var(--btn-sec-bg)}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .competidor{display:flex;align-items:center;gap:10px}
    .avatar{width:52px;height:52px;border-radius:10px;object-fit:cover;border:1px solid var(--line);background:#f1f5f9}
    .ph{width:52px;height:52px;border-radius:10px;border:1px solid var(--line);display:inline-flex;align-items:center;justify-content:center;background:#eef2f7;font-weight:900}
    .muted{color:var(--muted);}
    .divider{height:1px;background:#e5e7eb;margin:10px 0}
    @media(max-width:720px){ .row,.grid2{grid-template-columns:1fr} }
  </style>
</head>
<body>
<div class="wrap">
  <?php if (!empty($_SESSION['flash_ok'])) { ?><div class="flash ok" style="border:1px solid #c8e6c9;background:#e8f5e9;color:#1b5e20;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700"><?= h($_SESSION['flash_ok']); ?></div><?php unset($_SESSION['flash_ok']); } ?>
  <?php if (!empty($_SESSION['flash_warn'])) { ?><div class="flash warn" style="border:1px solid #ffeeba;background:#fff3cd;color:#856404;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700"><?= $_SESSION['flash_warn']; ?></div><?php unset($_SESSION['flash_warn']); } ?>
  <?php if (!empty($_SESSION['flash_error'])) { ?><div class="flash err" style="border:1px solid #ffcdd2;background:#ffebee;color:#b71c1c;padding:8px 10px;border-radius:8px;margin:8px 0;font-weight:700"><?= h($_SESSION['flash_error']); ?></div><?php unset($_SESSION['flash_error']); } ?>

  <div class="card">
    <h2 class="title">Editar pelea #<?= (int)$pelea_id ?> — Evento #<?= (int)$evento_id ?></h2>
    <div class="grid2">
      <div class="competidor">
        <?php if (!empty($pelea['r_foto'])) { ?>
          <img src="<?= h($pelea['r_foto']) ?>" class="avatar" alt="Rojo" onerror="this.onerror=null;this.replaceWith(ph('<?= h($rIni) ?>'))">
        <?php } else { ?>
          <div class="ph"><?= h($rIni) ?></div>
        <?php } ?>
        <div>
          <div><strong><?= h(trim(($pelea['r_apellido']??'').' '.($pelea['r_nombre']??''))) ?></strong></div>
          <div class="muted"><?= h($pelea['r_escuela'] ?? '—') ?></div>
        </div>
      </div>
      <div class="competidor">
        <?php if (!empty($pelea['a_foto'])) { ?>
          <img src="<?= h($pelea['a_foto']) ?>" class="avatar" alt="Azul" onerror="this.onerror=null;this.replaceWith(ph('<?= h($aIni) ?>'))">
        <?php } else { ?>
          <div class="ph"><?= h($aIni) ?></div>
        <?php } ?>
        <div>
          <div><strong><?= h(trim(($pelea['a_apellido']??'').' '.($pelea['a_nombre']??''))) ?></strong></div>
          <div class="muted"><?= h($pelea['a_escuela'] ?? '—') ?></div>
        </div>
      </div>
    </div>
  </div>

  <form method="POST" class="card">
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <input type="hidden" name="pelea_id" value="<?= (int)$pelea_id ?>">

    <!-- Cambiar competidores -->
    <div class="row">
      <div class="field">
        <label>Esquina Roja — competidor</label>
        <?php if ($C_ROJO && $CE_ID && $CE_EVT) { ?>
          <select name="rojo_id" id="select_rojo">
            <option value="">— Seleccionar —</option>
            <?php
              $selR = isset($pelea['rojo_id']) ? (int)$pelea['rojo_id'] : null;
              foreach($competidores as $c){
                $id=(int)$c['id'];
                $nom=trim(($c['ape']??'').' '.($c['nom']??''));
                $esc=(string)($c['esc']??'-');
                $sel = ($selR!==null && $selR===$id) ? 'selected' : '';
                echo '<option value="'.$id.'" '.$sel.'>'.h($nom.' — '.$esc).'</option>';
              }
            ?>
          </select>
          <small class="muted">Cambia el competidor de la esquina roja.</small>
        <?php } else { ?>
          <div class="muted">⚠️ No se pudo habilitar el cambio: faltan columnas en <b>peleas_evento</b> o <b>competidores_evento</b>.</div>
        <?php } ?>
      </div>

      <div class="field">
        <label>Esquina Azul — competidor</label>
        <?php if ($C_AZUL && $CE_ID && $CE_EVT) { ?>
          <select name="azul_id" id="select_azul">
            <option value="">— (vacante) —</option>
            <?php
              $selA = isset($pelea['azul_id']) ? (int)$pelea['azul_id'] : null;
              foreach($competidores as $c){
                $id=(int)$c['id'];
                $nom=trim(($c['ape']??'').' '.($c['nom']??''));
                $esc=(string)($c['esc']??'-');
                $sel = ($selA!==null && $selA===$id) ? 'selected' : '';
                echo '<option value="'.$id.'" '.$sel.'>'.h($nom.' — '.$esc).'</option>';
              }
            ?>
          </select>
          <small class="muted">Dejar vacío para reservar la esquina.</small>
        <?php } else { ?>
          <div class="muted">⚠️ No se pudo habilitar el cambio: faltan columnas en <b>peleas_evento</b> o <b>competidores_evento</b>.</div>
        <?php } ?>
      </div>
    </div>

    <div style="margin:6px 0 12px">
      <button class="btn btn-secondary" type="button" id="btnSwap">↔️ Intercambiar esquinas</button>
    </div>

    <div class="divider"></div>

    <div class="row">
      <div class="field">
        <label>Modalidad (reglamento)</label>
        <?php if ($tablaModal && $C_MODAL_P_ID) { ?>
          <select name="modalidad_id" id="modalidad_id">
            <option value="">— (sin asignar)</option>
            <?php
              $selId = isset($pelea['modalidad_id']) ? (int)$pelea['modalidad_id'] : null;
              foreach ($modalidades as $m){
                $id=(int)$m['id'];
                $lbl=(string)$m['nombre'];
                $sel = ($selId!==null && $selId===$id) ? 'selected' : '';
                echo '<option value="'.$id.'" '.$sel.'>'.h($lbl).'</option>';
              }
              echo '<optgroup label="Títulos">';
              foreach ($plantillas_titulo as $tpl){
                echo '<option value="" data-titulo="'.h($tpl).'">★ '.h($tpl).'</option>';
              }
              echo '</optgroup>';
            ?>
          </select>
          <small class="muted">Elegí el reglamento general o un título específico.</small>
        <?php } else { ?>
          <div class="muted">No hay tabla <b>modalidades_evento</b>. Podés escribir manualmente abajo.</div>
        <?php } ?>

        <!-- SIEMPRE mostramos un campo de texto para el título / detalle -->
        <small class="muted">Texto / título mostrado en listados y PDF.</small>
        <input type="text"
               name="modalidad_txt"
               id="modalidad_txt"
               value="<?= h($pelea['modalidad_txt'] ?? '') ?>"
               placeholder="Ej: Lowkick título amateur, K1 título profesional" />
      </div>

      <div class="field">
        <label>Rondas</label>
        <input type="number" name="rondas" min="1" step="1"
               value="<?= h(isset($pelea['rondas']) && is_numeric($pelea['rondas']) ? (int)$pelea['rondas'] : 2) ?>">
        <small class="muted">Si tu tabla no tiene columna de rondas, este campo se ignorará.</small>
      </div>
    </div>

    <div class="field" style="margin-top:10px">
      <label>Observaciones</label>
      <textarea name="observaciones" placeholder="Ej: Exhibición, casco obligatorio, 2x1:30, etc."><?= h($pelea['observaciones'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
      <a class="btn btn-secondary" href="ver_peleas_evento.php?evento_id=<?= (int)$evento_id ?>">⟵ Volver</a>
      <button class="btn btn-primary" type="submit">💾 Guardar cambios</button>
    </div>
  </form>
</div>

<script>
  function ph(txt){
    const d=document.createElement('div');
    d.className='ph';
    d.textContent=txt||'—';
    return d;
  }
  // Intercambiar selects rojo/azul
  const sr = document.getElementById('select_rojo');
  const sa = document.getElementById('select_azul');
  const btnSwap = document.getElementById('btnSwap');
  if (btnSwap && sr && sa){
    btnSwap.addEventListener('click', ()=>{
      const vr = sr.value, va = sa.value;
      sr.value = va; sa.value = vr;
    });
  }

  // Cuando elijas un "★ título" en el combo, se copia al campo de texto
  const selModal = document.getElementById('modalidad_id');
  const modTxt   = document.getElementById('modalidad_txt');
  if (selModal && modTxt){
    selModal.addEventListener('change', ()=>{
      const opt = selModal.options[selModal.selectedIndex];
      if (opt && opt.dataset && opt.dataset.titulo){
        modTxt.value = opt.dataset.titulo;
        selModal.value = ''; // queda sin ID, solo texto
      }
    });
  }
</script>
</body>
</html>
