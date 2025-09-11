<?php
/* ========================================================================
   importar_competidores_evento.php — Importador para tu planilla
   ======================================================================== */

/* ----- Mostrar errores (evitar página en blanco) ----- */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* Muestra FATALES en caso de “pantalla en blanco” */
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "FATAL PHP:\n{$e['message']}\nFile: {$e['file']}\nLine: {$e['line']}\n";
  }
});

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ----- Autoload de Composer (para PhpSpreadsheet, si existe) ----- */
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

/* -------------------- Helpers -------------------- */
/* Fallback si no tenés mbstring: */
if (!function_exists('mb_strtoupper')) {
  function mb_strtoupper($s, $enc = 'UTF-8') { return strtoupper((string)$s); }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function norm_basic($s){
  $s = mb_strtoupper(trim((string)$s), 'UTF-8');
  $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
  $s = preg_replace('/\s+/u',' ',$s);
  return $s;
}
function to_float($s){
  $s = trim((string)$s);
  if ($s==='') return null;
  // “63,5” -> “63.5”; “1.234,5” -> “1234.5”
  $s = str_replace(['.',','], ['','.'], $s);
  return is_numeric($s) ? (float)$s : null;
}
function detect_delim(string $sample): string {
  $cands=[",",";","|","\t"]; $best=","; $max=0;
  foreach($cands as $d){ $n=substr_count($sample,$d); if($n>$max){$max=$n; $best=$d;} }
  return $best;
}
function read_csv_rows(string $path): array {
  $rows=[];
  $first = @file_get_contents($path, false, null, 0, 4096) ?: '';
  $delim = detect_delim($first);
  $f = fopen($path,'r'); if(!$f) return $rows;
  $firstLine = fgets($f); if($firstLine===false){ fclose($f); return $rows; }
  $firstLine = ltrim($firstLine, "\xEF\xBB\xBF"); // BOM
  $hdr = str_getcsv($firstLine,$delim);
  $rows[]=$hdr;
  while(($line=fgets($f))!==false){ $rows[] = str_getcsv($line,$delim); }
  fclose($f);
  return $rows;
}
function read_xlsx_rows(string $path): array {
  if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) return [];
  $spread = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
  $sheet  = $spread->getActiveSheet();
  $rows=[];
  foreach ($sheet->toArray(null, true, true, true) as $row) { $rows[] = array_values($row); }
  return $rows;
}

/* -------------------- Column mapping de tu planilla -------------------- */
function idx_of_header(array $hdr, array $aliases): ?int {
  $normHdr = array_map('norm_basic', $hdr);
  foreach ($aliases as $al) {
    $nal = norm_basic($al);
    foreach ($normHdr as $i=>$h) if ($h===$nal) return $i;
  }
  return null;
}

function split_apellido_nombre(string $full): array {
  $t = preg_replace('/\s+/u',' ',trim($full));
  $parts = $t==='' ? [] : explode(' ', $t);
  if (!$parts) return ['apellido'=>'', 'nombre'=>''];

  $particles = ['DE','DEL','DELA','DE LA','DI','DA','VON','VAN','MC','MAC','SAN','SANTA'];

  if (count($parts)===2) return ['apellido'=>$parts[0], 'nombre'=>$parts[1]];

  $first2 = mb_strtoupper($parts[0],'UTF-8').' '.mb_strtoupper($parts[1]??'','UTF-8');
  $first1 = mb_strtoupper($parts[0],'UTF-8');

  if (in_array($first2, $particles, true)) {
    $ap = $parts[0].' '.$parts[1];
    $no = trim(implode(' ', array_slice($parts,2)));
    return ['apellido'=>$ap, 'nombre'=>$no];
  }
  if (in_array($first1, $particles, true) && isset($parts[1])) {
    $ap = $parts[0].' '.$parts[1];
    $no = trim(implode(' ', array_slice($parts,2)));
    return ['apellido'=>$ap, 'nombre'=>$no];
  }

  $ap = array_shift($parts);
  return ['apellido'=>$ap, 'nombre'=>implode(' ',$parts)];
}

function detectar_modalidad_y_estado(array $row, array $map){
  // $map: LOW, K1, TAHI, BOX, INF, JUNIOR, NEO, A, B, C
  $mod = null;
  $pick = function($v){ $v = trim((string)$v); return ($v!=='' && strtoupper($v)!=='0' && strtoupper($v)!=='NO'); };

  if (isset($map['LOW'])  && $pick($row[$map['LOW']]  ?? '')) $mod = 'LOW';
  if (!$mod && isset($map['K1'])   && $pick($row[$map['K1']]   ?? '')) $mod = 'K1';
  if (!$mod && isset($map['TAHI']) && $pick($row[$map['TAHI']] ?? '')) $mod = 'THAI'; // TAHI -> THAI
  if (!$mod && isset($map['BOX'])  && preg_match('/BOX(EO)?/i', (string)($row[$map['BOX']] ?? ''))) $mod = 'BOXEO';

  $estado = null;
  foreach (['A','B','C'] as $k){
    if (isset($map[$k]) && $pick($row[$map[$k]] ?? '')) { $estado = $k; break; }
  }
  if (!$estado) {
    foreach (['INF','JUNIOR','NEO'] as $k){
      if (isset($map[$k]) && $pick($row[$map[$k]] ?? '')) { $estado = $k; break; }
    }
  }
  return [$mod,$estado];
}

function adivinar_academia(array $row){
  // Toma el último campo textual “fuerte”
  for ($i = count($row)-1; $i>=0; $i--) {
    $v = trim((string)$row[$i]); if ($v==='') continue;
    $V = norm_basic($v);
    if (preg_match('/KG$/', $V)) continue;
    if (in_array($V, ['VM','POR TITULO','POR TITULO ZONAL','TITULO REGIONAL','EXIBICION','EXHIBICION','LOW LIGHT'], true)) continue;
    if (preg_match('/[A-Z]/',$V)) return trim($v);
  }
  return '';
}

/* -------------------- DB helpers -------------------- */
function table_has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql); $ok=$r && $r->num_rows>0; if($r) $r->close(); return $ok;
}
function find_id_by_nombre(mysqli $db, string $table, string $nombre): ?int {
  $sql = "SELECT id FROM `$table` WHERE TRIM(LOWER(nombre)) = TRIM(LOWER(?)) LIMIT 1";
  if ($st=$db->prepare($sql)){
    $st->bind_param('s',$nombre); $st->execute();
    $res=$st->get_result(); $row=$res?$res->fetch_assoc():null; $st->close();
    if($row) return (int)$row['id'];
  }
  return null;
}
function resolver_modalidad_id(mysqli $db, ?string $mod): ?int {
  if (!$mod) return null;
  $mapSyn = [
    'LOW'=>['LOW','LOW KICK','LOWKICK'],
    'K1' =>['K1','K-1'],
    'THAI'=>['THAI','MUAY THAI','MUAYTHAI','TAHI'],
    'BOXEO'=>['BOXEO','BOX','BOX E','BOXEO E']
  ];
  $cands = $mapSyn[$mod] ?? [$mod];
  foreach ($cands as $name) {
    $id = find_id_by_nombre($db,'modalidades_evento',$name);
    if ($id) return $id;
  }
  // LIKE laxa
  $name = $cands[0];
  $q = $db->prepare("SELECT id FROM modalidades_evento WHERE LOWER(nombre) LIKE CONCAT('%',LOWER(?), '%') LIMIT 1");
  if ($q){ $q->bind_param('s',$name); $q->execute(); $res=$q->get_result(); $row=$res?$res->fetch_assoc():null; $q->close(); if($row) return (int)$row['id']; }
  return null;
}
function resolver_peso_id(mysqli $db, ?string $catNombre): ?int {
  if (!$catNombre) return null;
  $id = find_id_by_nombre($db,'categorias_peso_evento',$catNombre);
  if ($id) return $id;
  $q = $db->prepare("SELECT id FROM categorias_peso_evento WHERE LOWER(nombre) LIKE CONCAT('%',LOWER(?), '%') LIMIT 1");
  if ($q){ $q->bind_param('s',$catNombre); $q->execute(); $res=$q->get_result(); $row=$res?$res->fetch_assoc():null; $q->close(); if($row) return (int)$row['id']; }
  return null;
}

/* ¿Qué columna usar para academia/equipo en tu tabla? */
$academyCol = 'academia';
foreach (['academia','escuela_nombre','gimnasio','equipo'] as $cand) {
  if (table_has_col($conexion,'competidores_evento',$cand)) { $academyCol = $cand; break; }
}

/* Dedup sin DNI por (apellido, nombre, academia) */
function buscar_existente(mysqli $db, string $apellido, string $nombre, string $academia, string $academyCol): ?int {
  $sql = "SELECT id FROM `competidores_evento`
          WHERE TRIM(LOWER(`apellido`)) = TRIM(LOWER(?))
            AND TRIM(LOWER(`nombre`))   = TRIM(LOWER(?))
            AND TRIM(LOWER(`$academyCol`)) = TRIM(LOWER(?))
          LIMIT 1";
  if ($st=$db->prepare($sql)){
    $st->bind_param('sss',$apellido,$nombre,$academia);
    $st->execute();
    $res=$st->get_result(); $row=$res?$res->fetch_assoc():null;
    $st->close();
    if($row) return (int)$row['id'];
  }
  return null;
}

/* -------------------- UPSERT -------------------- */
function upsert_row(mysqli $db, array $data, string $academyCol): array {
  $apellido = trim((string)($data['apellido'] ?? ''));
  $nombre   = trim((string)($data['nombre'] ?? ''));
  $academia = trim((string)($data['academia'] ?? ''));
  if ($apellido==='' && $nombre==='') return ['action'=>'skip','why'=>'sin nombre'];

  $idExist = buscar_existente($db,$apellido,$nombre,$academia,$academyCol);

  if ($idExist) {
    $set=[]; $vals=[]; $types='';
    foreach (['modalidad_id','peso_id','estado','activo'] as $k){
      if (array_key_exists($k,$data) && $data[$k]!==null && $data[$k]!==''){
        $set[] = "$k = ?";
        $vals[] = $data[$k];
        $types.= is_int($data[$k]) ? 'i' : 's';
      }
    }
    if ($set){
      $sql = "UPDATE `competidores_evento` SET ".implode(', ',$set)." WHERE id = ?";
      $st=$db->prepare($sql);
      if(!$st) return ['action'=>'error','err'=>$db->error];
      $types.='i'; $vals[]=$idExist;
      $st->bind_param($types, ...$vals);
      $ok=$st->execute(); $st->close();
      return $ok ? ['action'=>'update','id'=>$idExist]
                 : ['action'=>'error','err'=>$db->error];
    }
    return ['action'=>'skip','id'=>$idExist];
  }

  $cols = ['apellido','nombre',$academyCol,'estado','activo','wins','losses','draws','nc','modalidad_id','peso_id'];
  $vals = [
    $apellido, $nombre, $academia,
    ($data['estado'] ?? null),
    (isset($data['activo']) ? (int)$data['activo'] : 1),
    0,0,0,0,
    ($data['modalidad_id'] ?? null),
    ($data['peso_id'] ?? null),
  ];
  $cols2=[]; $qms=[]; $bind=[]; $types='';
  foreach ($cols as $i=>$c){
    $v = $vals[$i];
    if ($v===null){ $cols2[]="`$c`"; $qms[]="NULL"; }
    else { $cols2[]="`$c`"; $qms[]="?"; $bind[]=$v; $types.= (is_int($v)?'i':'s'); }
  }
  $sql="INSERT INTO `competidores_evento` (".implode(',',$cols2).") VALUES (".implode(',',$qms).")";
  $st=$db->prepare($sql);
  if(!$st) return ['action'=>'error','err'=>$db->error];
  if ($bind){ $st->bind_param($types, ...$bind); }
  $ok=$st->execute(); $id=$ok? $st->insert_id:0; $st->close();
  return $ok ? ['action'=>'insert','id'=>$id]
             : ['action'=>'error','err'=>$db->error];
}

/* -------------------- Procesamiento del archivo -------------------- */
$report = null;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['archivo']) && is_uploaded_file($_FILES['archivo']['tmp_name'])) {
  $tmp  = $_FILES['archivo']['tmp_name'];
  $name = $_FILES['archivo']['name'];
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  if ($ext==='csv') {
    $rows = read_csv_rows($tmp);
  } elseif ($ext==='xlsx') {
    if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
      $rows = read_xlsx_rows($tmp);
    } else {
      $report = ['fatal' => "Para XLSX instalá PhpSpreadsheet: <code>composer require phpoffice/phpspreadsheet</code>. O convertí a CSV."];
      $rows = [];
    }
  } else {
    $report = ['fatal'=>"Formato no soportado: <b>".h($ext)."</b>. Usá CSV o XLSX."];
    $rows = [];
  }

  if (!$report && $rows && count($rows[0])>0) {
    $hdr = $rows[0];

    $iNyA     = idx_of_header($hdr, ['APELLIDOS Y NOMBRE','APELLIDO Y NOMBRE','APELLIDOS, NOMBRE','NOMBRE Y APELLIDO']);
    $iINF     = idx_of_header($hdr, ['INF.','INF','INFANTIL']);
    $iJUN     = idx_of_header($hdr, ['JUNIOR','JR']);
    $iNEO     = idx_of_header($hdr, ['NEO','NEWWAZA','NEOPHYTE']);
    $iA       = idx_of_header($hdr, ['A']);
    $iB       = idx_of_header($hdr, ['B']);
    $iC       = idx_of_header($hdr, ['C']);
    $iPESO    = idx_of_header($hdr, ['PESO']);
    $iLOW1    = idx_of_header($hdr, ['LOW']);
    $iK1_1    = idx_of_header($hdr, ['K1','K-1']);
    $iTHAI1   = idx_of_header($hdr, ['TAHI','THAI','MUAY THAI']);
    $iBOX     = idx_of_header($hdr, ['BOX','BOXEO']);
    $iCatNom  = idx_of_header($hdr, ['Nombre de la categoría de peso','CATEGORIA DE PESO','CATEGORÍA DE PESO','CATEGORIA']);
    $iAcademia= idx_of_header($hdr, ['ACADEMIA','ESCUELA','EQUIPO','TEAM','GYM','GIMNASIO']);

    $iLOW2 = $iK1_2 = $iTHAI2 = null;
    for ($j=0; $j<count($hdr); $j++){
      $H = norm_basic($hdr[$j]);
      if ($H==='LOW' && $iLOW1!==null && $j!==$iLOW1) $iLOW2=$j;
      if (($H==='K1'||$H==='K-1') && $iK1_1!==null && $j!==$iK1_1) $iK1_2=$j;
      if (in_array($H,['TAHI','THAI','MUAY THAI'],true) && $iTHAI1!==null && $j!==$iTHAI1) $iTHAI2=$j;
    }

    $ins=0; $upd=0; $skip=0; $err=0; $errs=[];

    for ($r=1; $r<count($rows); $r++){
      $row = $rows[$r];

      $nyatxt = $iNyA!==null ? trim((string)($row[$iNyA] ?? '')) : '';
      if ($nyatxt===''){ $skip++; continue; }
      $pn = split_apellido_nombre($nyatxt);
      $apellido = $pn['apellido'];
      $nombre   = $pn['nombre'];

      $academia = $iAcademia!==null ? trim((string)($row[$iAcademia] ?? '')) : '';
      if ($academia==='') $academia = adivinar_academia($row);

      $map1 = [
        'LOW'=>$iLOW1, 'K1'=>$iK1_1, 'TAHI'=>$iTHAI1,
        'BOX'=>$iBOX, 'A'=>$iA, 'B'=>$iB, 'C'=>$iC, 'INF'=>$iINF, 'JUNIOR'=>$iJUN, 'NEO'=>$iNEO
      ];
      [$mod,$estado] = detectar_modalidad_y_estado($row, $map1);

      if (!$mod){
        $map2 = [
          'LOW'=>$iLOW2, 'K1'=>$iK1_2, 'TAHI'=>$iTHAI2,
          'BOX'=>$iBOX, 'A'=>$iA, 'B'=>$iB, 'C'=>$iC, 'INF'=>$iINF, 'JUNIOR'=>$iJUN, 'NEO'=>$iNEO
        ];
        [$mod,$estado] = detectar_modalidad_y_estado($row, $map2);
      }

      $modalidad_id = resolver_modalidad_id($conexion, $mod);
      $pesoNum = $iPESO!==null ? to_float($row[$iPESO] ?? '') : null; // informativo
      $catNom  = $iCatNom!==null ? trim((string)($row[$iCatNom] ?? '')) : '';
      $peso_id = resolver_peso_id($conexion, $catNom);

      $data = [
        'apellido'=>$apellido,
        'nombre'=>$nombre,
        'academia'=>$academia,
        'modalidad_id'=>$modalidad_id,
        'peso_id'=>$peso_id,
        'estado'=>$estado ?: null,
        'activo'=>1
      ];

      $res = upsert_row($conexion,$data,$academyCol);
      if ($res['action']==='insert') $ins++;
      elseif ($res['action']==='update') $upd++;
      elseif ($res['action']==='skip') $skip++;
      else { $err++; $errs[] = $res['err'] ?? 'error desconocido'; }
    }

    $report = compact('ins','upd','skip','err','errs');
  } elseif(!$report) {
    $report = ['fatal'=>'No se encontraron filas en el archivo.'];
  }
}

/* -------------------- UI -------------------- */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Importar planilla de competidores</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,Segoe UI,Arial}
  .wrap{max-width:920px;margin:22px auto;padding:16px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:16px}
  input[type=file],button{padding:10px;border-radius:10px;border:1px solid #27455c;background:#111a24;color:#e6eef4}
  .ok{background:#0f2a18;border:1px solid #1f6f3a;padding:10px;border-radius:10px;margin-top:12px}
  .warn{background:#2a1414;border:1px solid #5e2626;padding:10px;border-radius:10px;margin-top:12px;color:#ffb4b4}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{padding:8px;border-bottom:1px solid #1c2a36}
  th{background:#0f1a26;color:#9ecbff;text-align:left}
  code{background:#0b131c;padding:2px 6px;border-radius:6px;border:1px solid #263341}
</style>
</head>
<body>
<?php /* Aislar problemas de includes: dejalo comentado y luego lo volvés a activar */
// @include __DIR__.'/menu_eventos.php';
?>
<div class="wrap">
  <div class="card">
    <h2 style="margin-top:0">📥 Importar competidores — Tu planilla</h2>
    <p>Subí el archivo (CSV/XLSX) con columnas como
      <b>APELLIDOS Y NOMBRE, INF/JUNIOR/NEO, A/B/C, PESO, LOW/K1/TAHI/BOXEO, “Nombre de la categoría de peso”</b> y el <b>equipo</b>.
    </p>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="file" name="archivo" accept=".csv,.xlsx" required>
      <button type="submit">Importar</button>
    </form>

    <?php if ($report): ?>
      <?php if (!empty($report['fatal'])): ?>
        <div class="warn"><?= $report['fatal'] ?></div>
      <?php else: ?>
        <div class="ok">
          <b>Resultado:</b>
          <table>
            <tr><th>Insertados</th><td><?= (int)$report['ins'] ?></td></tr>
            <tr><th>Actualizados</th><td><?= (int)$report['upd'] ?></td></tr>
            <tr><th>Saltados</th><td><?= (int)$report['skip'] ?></td></tr>
            <tr><th>Errores</th><td><?= (int)$report['err'] ?></td></tr>
          </table>
          <?php if (!empty($report['errs'])): ?>
            <div style="margin-top:6px">
              <b>Detalles:</b>
              <ul><?php foreach($report['errs'] as $e) echo '<li>'.h($e).'</li>'; ?></ul>
            </div>
          <?php endif; ?>
          <div style="margin-top:8px;font-size:13px;opacity:.9">
            • Modalidad: LOW/K1/THAI o texto BOX/BOXEO.<br>
            • Estado: A/B/C (prioridad) o INF/JUNIOR/NEO.<br>
            • Peso: intenta vincular “Nombre de la categoría de peso” con <code>categorias_peso_evento.nombre</code>.<br>
            • Sin DNI: dedup por (Apellido, Nombre, <?= h($academyCol) ?>).
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="warn" style="margin-top:12px">
      <b>Notas:</b><br>
      • Para XLSX: <code>composer require phpoffice/phpspreadsheet</code> (o convertí a CSV).<br>
      • Si modalidad/categoría no existe en tablas de referencia, no se crea automáticamente.
    </div>
  </div>
</div>
</body>
</html>
