<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function toFloatOrNull($v){ if($v==='') return null; $v=str_replace(',','.',(string)$v); return is_numeric($v)?(float)$v:null; }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '{$t}'");
  $ok = ($q && $q->num_rows>0); if($q) $q->close(); return $ok;
}
function pick_peso_col(mysqli $db): ?string {
  foreach (['peso_kg','peso','kg','weight_kg'] as $c) if (has_col($db,'competidores_evento',$c)) return $c;
  return null;
}

/* ===== Verificaciones duplicados ===== */
function existe_dni_evento(mysqli $db, int $evento_id, string $dni): bool {
  $st = $db->prepare("SELECT 1 FROM competidores_evento WHERE evento_id=? AND dni=? LIMIT 1");
  if(!$st) return false;
  $st->bind_param('is',$evento_id,$dni);
  $st->execute(); $r=$st->get_result(); $ok=($r && $r->num_rows>0); $st->close(); return $ok;
}
function existe_nombre_apellido_evento(mysqli $db, int $evento_id, string $nombre, string $apellido): bool {
  $sql = "SELECT 1 FROM competidores_evento 
          WHERE evento_id=? 
            AND TRIM(LOWER(apellido))=TRIM(LOWER(?)) 
            AND TRIM(LOWER(nombre))=TRIM(LOWER(?))
          LIMIT 1";
  $st = $db->prepare($sql);
  if(!$st) return false;
  $st->bind_param('iss',$evento_id,$apellido,$nombre);
  $st->execute(); $r=$st->get_result(); $ok=($r && $r->num_rows>0); $st->close(); return $ok;
}

/* ===== Insert competidores_evento ===== */
function insert_min(mysqli $db, array $data): int {
  $cols=[]; $vals=[]; $types='';
  foreach($data as $c=>$v){
    if (has_col($db,'competidores_evento',$c)) {
      $cols[]="`$c`"; $vals[]=$v;
      $types.= is_int($v)?'i':(is_float($v)?'d':'s');
    }
  }
  if (!$cols) throw new RuntimeException('Sin columnas válidas.');
  $ph = implode(',', array_fill(0,count($cols),'?'));
  $sql = "INSERT INTO competidores_evento (".implode(',',$cols).") VALUES ($ph)";
  $st=$db->prepare($sql); if(!$st) throw new RuntimeException('Prep insert: '.$db->error);
  $refs=[]; $refs[]=&$types; foreach($vals as $i=>$_){ $refs[]=&$vals[$i]; }
  if(!call_user_func_array([$st,'bind_param'],$refs)) throw new RuntimeException($st->error);
  if(!$st->execute()) throw new RuntimeException($st->error);
  $id = (int)$db->insert_id; $st->close(); return $id;
}

/* ===== UPSERT ranking_competidores (eRanking) =====
   - Si existe por DNI, actualiza.
   - Si no, intenta por apellido+nombre (normalizado).
   - Si no existe, inserta.
   Campos mínimos: apellido, nombre, dni, edad, escuela_nombre.
   Si la tabla tiene updated_at / fecha_update, se actualiza el timestamp.
*/
function upsert_ranking_basico(mysqli $db, array $in): void {
  $tabla = 'ranking_competidores';
  if (!table_exists($db, $tabla)) return; // si no existe, salimos sin romper nada

  // columnas presentes
  $cols = [
    'apellido'=> has_col($db,$tabla,'apellido'),
    'nombre'=> has_col($db,$tabla,'nombre'),
    'dni'=> has_col($db,$tabla,'dni'),
    'edad'=> has_col($db,$tabla,'edad'),
    'escuela_nombre'=> has_col($db,$tabla,'escuela_nombre'),
    'updated_at'=> has_col($db,$tabla,'updated_at'),
    'fecha_update'=> has_col($db,$tabla,'fecha_update'),
    // por si existieran, no obligatorias:
    'wins'=> has_col($db,$tabla,'wins'),
    'losses'=> has_col($db,$tabla,'losses'),
    'draws'=> has_col($db,$tabla,'draws'),
    'no_contest'=> has_col($db,$tabla,'no_contest'),
  ];

  // Intentar localizar por DNI
  $id_row = null;
  if ($cols['dni'] && !empty($in['dni'])) {
    $st = $db->prepare("SELECT id FROM `$tabla` WHERE dni=? LIMIT 1");
    if ($st) {
      $st->bind_param('s', $in['dni']); $st->execute();
      $r = $st->get_result(); if ($r && $r->num_rows) { $id_row = (int)$r->fetch_assoc()['id']; }
      $st->close();
    }
  }
  // Si no apareció por DNI, intentar por apellido+nombre
  if ($id_row===null && $cols['apellido'] && $cols['nombre']) {
    $st = $db->prepare("SELECT id FROM `$tabla` WHERE TRIM(LOWER(apellido))=TRIM(LOWER(?)) AND TRIM(LOWER(nombre))=TRIM(LOWER(?)) LIMIT 1");
    if ($st) {
      $st->bind_param('ss', $in['apellido'], $in['nombre']); $st->execute();
      $r=$st->get_result(); if($r && $r->num_rows){ $id_row=(int)$r->fetch_assoc()['id']; }
      $st->close();
    }
  }

  // Armado de set
  $pairs=[]; $types=''; $vals=[];
  foreach (['apellido','nombre','dni','edad','escuela_nombre'] as $c){
    if ($cols[$c] && isset($in[$c])) { $pairs[]="`$c`=?"; $vals[]=$in[$c]; $types.= is_int($in[$c])?'i':'s'; }
  }
  // timestamps si corresponde
  if ($cols['updated_at']) { $pairs[]="`updated_at`=NOW()"; }
  if ($cols['fecha_update']) { $pairs[]="`fecha_update`=NOW()"; }

  if ($id_row!==null) {
    // UPDATE
    if (!$pairs) return;
    $sql = "UPDATE `$tabla` SET ".implode(',', $pairs)." WHERE id=?";
    $st = $db->prepare($sql); if(!$st) return;
    $types2 = $types.'i'; $vals2 = $vals; $vals2[] = $id_row;
    $refs=[]; $refs[]=&$types2; foreach($vals2 as $i=>$_){ $refs[]=&$vals2[$i]; }
    @call_user_func_array([$st,'bind_param'],$refs);
    @$st->execute(); $st->close();
  } else {
    // INSERT
    $icols=[]; $qms=[]; $typesI=''; $valsI=[];
    foreach (['apellido','nombre','dni','edad','escuela_nombre'] as $c) {
      if ($cols[$c] && isset($in[$c])) { $icols[]="`$c`"; $qms[]='?'; $valsI[]=$in[$c]; $typesI.= is_int($in[$c])?'i':'s'; }
    }
    if ($cols['updated_at']) { $icols[]='`updated_at`'; $qms[]='NOW()'; }
    if ($cols['fecha_update']) { $icols[]='`fecha_update`'; $qms[]='NOW()'; }

    if ($icols){
      $sql="INSERT INTO `$tabla` (".implode(',',$icols).") VALUES (".implode(',',$qms).")";
      $st=$db->prepare($sql); if($st){
        $refs=[]; if($typesI){ $refs[]=&$typesI; foreach($valsI as $i=>$_){ $refs[]=&$valsI[$i]; } @call_user_func_array([$st,'bind_param'],$refs); }
        @$st->execute(); $st->close();
      }
    }
  }
}

/* ===== evento_id y retorno ===== */
$evento_id = (int)($_POST['evento_id'] ?? $_GET['evento_id'] ?? $_SESSION['evento_id_actual'] ?? 0);
$_SESSION['evento_id_actual'] = $evento_id;

$return = $_GET['return'] ?? $_POST['return'] ?? 'ver_competidores_evento.php';
if (preg_match('~^(https?:)?//~i', $return)) $return = 'ver_competidores_evento.php';

/* ===================== POST: guardar ===================== */
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $apellido = post('apellido');
  $nombre   = post('nombre');
  $dni      = preg_replace('/\D+/','', post('dni'));
  $edad     = toIntOrNull(post('edad'));
  $escuela  = post('escuela_nombre');
  $peso     = toFloatOrNull(post('peso'));
  $modalidad_id  = toIntOrNull(post('modalidad_id'));
  $disciplina_id = toIntOrNull(post('disciplina_id'));
  $division_id   = toIntOrNull(post('division_id'));
  $categoria_tecnica_id = toIntOrNull(post('categoria_tecnica_id'));
  $peleas_previas = toIntOrNull(post('peleas_previas'));

  if ($apellido==='' || $nombre==='' || $dni==='' || $edad===null || $escuela==='' || 
      $peso===null || !$modalidad_id || !$disciplina_id || !$division_id || 
      !$categoria_tecnica_id || $peleas_previas===null){
    $_SESSION['flash_error']='Completá todos los campos obligatorios.';
    header('Location: agregar_competidor_min.php?evento_id='.$evento_id.'&return='.rawurlencode($return)); exit;
  }

  if (existe_dni_evento($conexion,$evento_id,$dni)){
    $_SESSION['flash_error']='El DNI ya está registrado en este evento.';
    header('Location: agregar_competidor_min.php?evento_id='.$evento_id.'&return='.rawurlencode($return)); exit;
  }

  if (existe_nombre_apellido_evento($conexion,$evento_id,$nombre,$apellido)){
    $_SESSION['flash_error']='Ya existe un competidor con ese nombre y apellido en este evento.';
    header('Location: agregar_competidor_min.php?evento_id='.$evento_id.'&return='.rawurlencode($return)); exit;
  }

  $pesoCol = pick_peso_col($conexion);
  $data = [
    'evento_id'=>$evento_id,
    'apellido'=>$apellido,
    'nombre'=>$nombre,
    'dni'=>$dni,
    'edad'=>$edad,
    'escuela_nombre'=>$escuela,
    'modalidad_id'=>$modalidad_id,
    'disciplina_id'=>$disciplina_id,
    'division_id'=>$division_id,
    'categoria_tecnica_id'=>$categoria_tecnica_id,
    'peleas_previas'=>$peleas_previas
  ];
  if ($pesoCol) $data[$pesoCol]=$peso;

  try{
    $id = insert_min($conexion,$data);

    // === También actualizamos el eRanking (si existe la tabla) ===
    upsert_ranking_basico($conexion, [
      'apellido'=>$apellido,
      'nombre'=>$nombre,
      'dni'=>$dni,
      'edad'=>$edad,
      'escuela_nombre'=>$escuela
      // Si más adelante querés mapear peleas_previas a wins/draws/losses, lo vemos y lo agregamos.
    ]);

    $_SESSION['flash_ok'] = '✅ Competidor #'.$id.' cargado y eRanking actualizado.';
  } catch(Throwable $e){
    $_SESSION['flash_error'] = 'Error guardando: '.$e->getMessage();
  }
  header('Location: '.$return.'?evento_id='.$evento_id); exit;
}

/* ===== Datos para autocompletar escuelas ===== */
$escuelas = [];
$q = $conexion->query("SELECT DISTINCT TRIM(escuela_nombre) AS nombre FROM competidores_evento WHERE escuela_nombre <> '' ORDER BY nombre ASC");
if ($q) while($r = $q->fetch_assoc()){ $escuelas[] = $r['nombre']; }
$categorias_tecnicas = $conexion->query("SELECT id, codigo, descripcion FROM categorias_tecnicas_evento ORDER BY id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Carga rápida de competidores</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{ --bg:#0b1115; --fg:#e6eef4; --card:#0f1720; --bd:#1f2a33; --accent:#0e7ad1; }
    body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,Arial,sans-serif}
    .wrap{max-width:820px;margin:0 auto;padding:14px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:12px;margin-top:10px}
    .grid{display:grid;gap:10px;grid-template-columns:1fr}
    @media(min-width:640px){ .grid-2{grid-template-columns:repeat(2,1fr)} .grid-3{grid-template-columns:repeat(3,1fr)} }
    label{font-size:12px;color:#cfe7ff}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #22313f;background:#0f1b25;color:var(--fg)}
    .btn{display:inline-block;padding:10px 12px;border-radius:8px;background:var(--accent);color:#fff;border:none;cursor:pointer}
    .alert{padding:10px;border-radius:8px;margin:10px 0}
    .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .bad{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4}

    /* Autocomplete */
    .ac-wrap{position:relative}
    .ac-list{position:absolute;z-index:40;left:0;right:0;top:100%;background:#0b1620;border:1px solid #213245;border-radius:10px;margin-top:4px;max-height:260px;overflow:auto;box-shadow:0 10px 25px rgba(0,0,0,.35);display:none}
    .ac-item{padding:10px 12px;cursor:pointer;display:flex;gap:8px;align-items:center}
    .ac-item:hover{background:#132235}
    .ac-name{font-weight:700}
    .ac-sub{font-size:12px;color:#9bbad8}
    .ac-empty{padding:10px 12px;color:#9bbad8}
    .mut{font-size:12px;color:#9bbad8}
  </style>
</head>
<body>
<div class="wrap">
  <h2>🏅 Carga rápida de competidores</h2>
  <?php if (!empty($_SESSION['flash_ok'])): ?><div class="alert ok"><?= h($_SESSION['flash_ok']); unset($_SESSION['flash_ok']); ?></div><?php endif; ?>
  <?php if (!empty($_SESSION['flash_error'])): ?><div class="alert bad"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

  <form action="" method="POST" autocomplete="off">
    <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
    <input type="hidden" name="return" value="<?= h($return) ?>">

    <div class="card">
      <div class="grid grid-2">
        <div class="ac-wrap">
          <label>Apellido* (buscar en eRanking)</label>
          <input name="apellido" id="apellido" required autocomplete="off" placeholder="Escribí el apellido">
          <div id="ac_list" class="ac-list"></div>
          <div class="mut" id="lock_msg" style="display:none;margin-top:6px">Se completó desde eRanking. Podés editar todo si querés.</div>
        </div>
        <div><label>Nombre*</label><input name="nombre" id="nombre" required></div>
      </div>

      <div class="grid grid-3">
        <div><label>DNI*</label><input name="dni" id="dni" required inputmode="numeric" pattern="\d+"></div>
        <div><label>Edad*</label><input type="number" min="0" name="edad" id="edad" required></div>
        <div><label>Peso (kg)*</label><input type="number" step="0.1" min="0" name="peso" required></div>
      </div>

      <div class="grid grid-2">
        <div>
          <label>Escuela / Gimnasio*</label>
          <input list="escuelas" name="escuela_nombre" id="escuela_nombre" required>
          <datalist id="escuelas">
            <?php foreach ($escuelas as $e): ?>
              <option value="<?= h($e) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div><label>Cant. de peleas*</label><input type="number" name="peleas_previas" id="peleas_previas" min="0" required></div>
      </div>

      <div class="grid grid-3">
        <div>
          <label>Modalidad*</label>
          <select name="modalidad_id" id="modalidad_id" required>
            <option value="">—</option>
            <option value="2">Boxeo</option>
            <option value="4">Low Kick</option>
            <option value="5">K1</option>
            <option value="6">MMA</option>
            <option value="7">Muay Thai</option>
            <option value="1">Exhibición</option>
          </select>
        </div>
        <div>
          <label>Disciplina*</label>
          <select name="disciplina_id" id="disciplina_id" required>
            <option value="">—</option>
            <option value="2">Amateurs</option>
            <option value="3">ProAm</option>
            <option value="4">Profesional</option>
            <option value="1">Exhibición</option>
          </select>
        </div>
        <div>
          <label>División*</label>
          <select name="division_id" id="division_id" required>
            <option value="">—</option>
            <option value="1">Infantil</option>
            <option value="2">Juvenil</option>
            <option value="3">Adultos</option>
            <option value="4">Masters</option>
            <option value="5">Veteranos</option>
          </select>
        </div>
      </div>

      <div class="grid grid-2">
        <div>
          <label>Categoría técnica*</label>
          <select name="categoria_tecnica_id" id="categoria_tecnica_id" required>
            <option value="">—</option>
            <?php while($ct = $categorias_tecnicas->fetch_assoc()): ?>
              <option value="<?= (int)$ct['id'] ?>"><?= h(($ct['codigo']??'').' - '.($ct['descripcion']??'')) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
      </div>
    </div>

    <div style="margin-top:12px">
      <button class="btn" type="submit" <?= ($evento_id<=0?'disabled':'') ?>>✅ Guardar</button>
    </div>
  </form>
</div>

<script>
  // ====== Helpers ======
  function esc(s){ return (s||'').toString().replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
  function sugerirDivisionPorEdad(e){
    const sel = document.getElementById('division_id');
    let d="3"; // Adultos default
    const edad = +e||0;
    if (edad<12) d="1";
    else if (edad<18) d="2";
    else if (edad<26) d="3";
    else if (edad<46) d="4";
    else d="5";
    sel.value = d;
  }
  document.getElementById('edad').addEventListener('input', e=> sugerirDivisionPorEdad(e.target.value));

  // Numerico DNI
  const dniEl = document.getElementById('dni');
  dniEl.addEventListener('input', e=> e.target.value=(e.target.value||'').replace(/\D+/g,''));

  // ====== Autocomplete por Apellido desde api_ranking_buscar.php ======
  const apeIn  = document.getElementById('apellido');
  const nomIn  = document.getElementById('nombre');
  const edadIn = document.getElementById('edad');
  const escIn  = document.getElementById('escuela_nombre');
  const peleasIn = document.getElementById('peleas_previas');
  const acList = document.getElementById('ac_list');
  const lockMsg = document.getElementById('lock_msg');

  let acTimer=null;
  apeIn.addEventListener('input', (e)=>{
    const q=(e.target.value||'').trim();
    lockMsg.style.display='none';
    if (acTimer) clearTimeout(acTimer);
    if (q.length<2){ acList.style.display='none'; return; }
    acTimer=setTimeout(()=> doLookup(q), 220);
  });
  document.addEventListener('click', (ev)=>{ if(!acList.contains(ev.target) && ev.target!==apeIn){ acList.style.display='none'; }});

  async function doLookup(q){
    try{
      // ⬇️ Cambiá esta URL si pusiste el endpoint en otro lado/nombre
      const url='api_ranking_buscar.php?q='+encodeURIComponent(q);
      const r = await fetch(url, {headers:{'Accept':'application/json'}});
      const data = r.ok ? await r.json() : [];
      renderAC(Array.isArray(data)?data:[]);
    }catch(e){ renderAC([]); }
  }
  function renderAC(items){
    if (!items.length){
      acList.innerHTML='<div class="ac-empty">Sin coincidencias…</div>';
      acList.style.display='block';
      return;
    }
    acList.innerHTML = items.slice(0,30).map((c,i)=>(
      `<div class="ac-item" data-i="${i}">
        <div class="ac-name">${esc(c.apellido)} ${esc(c.nombre)}</div>
        <div class="ac-sub">DNI: ${esc(c.dni||'—')} • Nac: ${esc(c.fecha_nacimiento||'—')} • ${esc(c.escuela_nombre||'')}</div>
      </div>`
    )).join('');
    acList.style.display='block';
    [...acList.querySelectorAll('.ac-item')].forEach((el,idx)=> el.addEventListener('click',()=> applyCompetidor(items[idx])));
  }
  function applyCompetidor(c){
    if (c.apellido) apeIn.value = c.apellido;
    if (c.nombre)   nomIn.value = c.nombre;
    if (c.dni)      dniEl.value = String(c.dni).replace(/\D+/g,'');
    if (c.escuela_nombre) escIn.value = c.escuela_nombre;
    // Edad: si viene fecha_nacimiento, la calculás del lado servidor en el form grande;
    // acá usamos la edad si el endpoint la trae; si no, el usuario la edita.
    if (c.edad!=null && c.edad!=='') { edadIn.value = +c.edad; sugerirDivisionPorEdad(+c.edad); }
    // Si el ranking trae conteos de peleas, los aproximamos a "peleas_previas"
    let tot = 0;
    if (c.wins!=null) tot += (+c.wins||0);
    if (c.losses!=null) tot += (+c.losses||0);
    if (c.draws!=null) tot += (+c.draws||0);
    if (c.no_contest!=null) tot += (+c.no_contest||0);
    if (tot>0) peleasIn.value = tot;

    lockMsg.style.display='block';
    acList.style.display='none';
  }
</script>
</body>
</html>
