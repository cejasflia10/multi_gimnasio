<?php
/* ==========================================================
   transmision_en_vivo.php — Vista pública de transmisión
   - URL: transmision_en_vivo.php?evento_id=#
   - YouTube: lee evento_transmision (youtube_url, pelea_inicio_id)
   - Pelea: usa peleas_evento (orden, competidor_rojo_id, competidor_azul_id, modalidad_id)
   - Nombres/Escuelas/Pesos: competidores_evento (por id y evento_id)
   - Modalidad: modalidades_evento | modalidades | fallback a competidores_evento.modalidad
   - Modo TV limpio: ?share=1
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getI($k){ return isset($_GET[$k]) ? (int)$_GET[$k] : 0; }
function getS($k){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : ''; }
function pick(array $row, array $cands, $def=''){
  foreach($cands as $c){ if(array_key_exists($c,$row) && $row[$c]!=='' && $row[$c]!==null) return $row[$c]; }
  return $def;
}
function table_exists(mysqli $cx, string $name): bool {
  $name = $cx->real_escape_string($name);
  $res = $cx->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$name}' LIMIT 1");
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}
function col_exists(mysqli $cx, string $t, string $c): bool {
  $t = $cx->real_escape_string($t); $c = $cx->real_escape_string($c);
  $res = $cx->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1");
  $ok  = $res && (bool)$res->fetch_row();
  if ($res instanceof mysqli_result) $res->free();
  return $ok;
}
function yt_id_from_url($url){
  $url = trim((string)$url);
  if ($url==='') return null;
  if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
  if (preg_match('~v=([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[1];
  if (preg_match('~/(live|embed)/([A-Za-z0-9_-]{6,})~', $url, $m)) return $m[2];
  return null;
}

/* ===== Params ===== */
$evento_id = getI('evento_id');
if ($evento_id<=0){ http_response_code(400); exit('Falta ?evento_id'); }
$pelea_id_req = getI('pelea_id');
$share = (getS('share')==='1');

/* ===== Evento ===== */
$ev = null;
$r = $conexion->query("SELECT id, titulo, fecha, lugar FROM eventos_deportivos WHERE id={$evento_id} LIMIT 1");
if ($r && $r->num_rows) { $ev=$r->fetch_assoc(); } if ($r instanceof mysqli_result) $r->free();
if (!$ev){ http_response_code(404); exit('Evento no encontrado'); }
$ev_titulo = trim($ev['titulo'] ?? '') ?: ('Evento #'.$ev['id']);
$ev_fecha  = $ev['fecha'] ?? '';
$ev_lugar  = $ev['lugar'] ?? '';

/* ===== Transmisión del evento ===== */
$tx = null;
$r = $conexion->query("SELECT youtube_url, pelea_inicio_id, activo FROM evento_transmision WHERE evento_id={$evento_id} LIMIT 1");
if ($r && $r->num_rows) { $tx=$r->fetch_assoc(); } if ($r instanceof mysqli_result) $r->free();
$youtube_url = $tx['youtube_url'] ?? '';
$youtube_id  = yt_id_from_url($youtube_url);

/* ===== Peleas del evento ===== */
$peleas = [];
$r = $conexion->query("SELECT id, orden, competidor_rojo_id, competidor_azul_id, modalidad_id
                       FROM peleas_evento
                       WHERE evento_id={$evento_id}
                       ORDER BY COALESCE(orden,9999), id ASC");
while($r && $row=$r->fetch_assoc()) $peleas[] = $row;
if ($r instanceof mysqli_result) $r->free();

/* ===== Colectar IDs de competidores y modalidades ===== */
$idsR=[]; $idsA=[]; $idsMod=[];
foreach($peleas as $p){
  if (!empty($p['competidor_rojo_id'])) $idsR[] = (int)$p['competidor_rojo_id'];
  if (!empty($p['competidor_azul_id'])) $idsA[] = (int)$p['competidor_azul_id'];
  if (!empty($p['modalidad_id']))       $idsMod[] = (int)$p['modalidad_id'];
}
$idsR = array_values(array_unique($idsR));
$idsA = array_values(array_unique($idsA));
$idsMod = array_values(array_unique($idsMod));

/* ===== Mapa competidores desde competidores_evento (por evento_id) ===== */
$mapComp = []; // id => ['nom','esc','peso','mod_text']
if ($idsR || $idsA){
  $todos = implode(',', array_map('intval', array_values(array_unique(array_merge($idsR,$idsA)))));
  // Traemos SOLO los de ESTE evento para evitar cruces
  $sqlCE = "
    SELECT id, nombre, apellido, escuela, escuela_nombre, escuela_logo, peso_kg,
           modalidad
    FROM competidores_evento
    WHERE evento_id={$evento_id} AND id IN ({$todos})
  ";
  $r = $conexion->query($sqlCE);
  while($r && $row=$r->fetch_assoc()){
    $id = (int)$row['id'];
    $nom = trim(($row['nombre']??'').' '.($row['apellido']??''));
    if ($nom==='') $nom = $row['nombre'] ?? ("Competidor #{$id}");
    $esc = trim(($row['escuela']??'') ?: ($row['escuela_nombre']??''));
    $peso = $row['peso_kg']; $peso = ($peso!==null && $peso!=='') ? (0+$peso) : '';
    $modtxt = trim($row['modalidad'] ?? '');
    $mapComp[$id] = ['nom'=>$nom, 'esc'=>$esc, 'peso'=>$peso, 'modtxt'=>$modtxt];
  }
  if ($r instanceof mysqli_result) $r->free();
}

/* ===== Mapa de modalidades por id (opcional) ===== */
$mapMod = []; // id => nombre
if ($idsMod){
  $nameFields = ['nombre','titulo','descripcion','detalle','modalidad'];
  $cands = [
    ['t'=>'modalidades_evento','id'=>'id'],
    ['t'=>'modalidades','id'=>'id'],
  ];
  foreach($cands as $c){
    if (!table_exists($conexion,$c['t']) || !col_exists($conexion,$c['t'],$c['id'])) continue;
    $ids_sql = implode(',', array_map('intval',$idsMod));
    $r = $conexion->query("SELECT * FROM {$c['t']} WHERE {$c['id']} IN ({$ids_sql})");
    while($r && $row=$r->fetch_assoc()){
      $id = (int)$row[$c['id']];
      if (!isset($mapMod[$id])){
        $nm = pick($row,$nameFields,"Modalidad #{$id}");
        $mapMod[$id] = $nm;
      }
    }
    if ($r instanceof mysqli_result) $r->free();
    if (count($mapMod)===count($idsMod)) break;
  }
}

/* ===== Seleccionar pelea actual ===== */
$pelea_sel = null;
if ($pelea_id_req>0){
  foreach($peleas as $p){ if ((int)$p['id']===$pelea_id_req){ $pelea_sel=$p; break; } }
}
if (!$pelea_sel){
  $ini = isset($tx['pelea_inicio_id']) ? (int)$tx['pelea_inicio_id'] : 0;
  if ($ini>0){ foreach($peleas as $p){ if ((int)$p['id']===$ini){ $pelea_sel=$p; break; } } }
}
if (!$pelea_sel && $peleas){ $pelea_sel = $peleas[0]; }

/* ===== Etiquetas (sin duplicar nombres) ===== */
$orden_txt = $pelea_sel ? ($pelea_sel['orden'] ?? '') : '';
$rojo_nom=''; $rojo_esc=''; $rojo_peso='';
$azul_nom=''; $azul_esc=''; $azul_peso='';
$mod_txt=''; $pills_peso_txt='';

if ($pelea_sel){
  $rid = (int)$pelea_sel['competidor_rojo_id'];
  $aid = (int)$pelea_sel['competidor_azul_id'];

  $rojo_nom  = $mapComp[$rid]['nom']  ?? "Competidor #{$rid}";
  $rojo_esc  = $mapComp[$rid]['esc']  ?? '';
  $rojo_peso = $mapComp[$rid]['peso'] ?? '';
  $azul_nom  = $mapComp[$aid]['nom']  ?? "Competidor #{$aid}";
  $azul_esc  = $mapComp[$aid]['esc']  ?? '';
  $azul_peso = $mapComp[$aid]['peso'] ?? '';

  // Modalidad: por id; si no hay, fallback al texto de competidores_evento
  $mid = (int)($pelea_sel['modalidad_id'] ?? 0);
  if ($mid>0 && isset($mapMod[$mid])) {
    $mod_txt = $mapMod[$mid];
  } else {
    // Fallback: si alguno de los dos tiene modalidad textual, úsala
    $mod_txt = trim(($mapComp[$rid]['modtxt'] ?? '').' '.($mapComp[$aid]['modtxt'] ?? ''));
    $mod_txt = trim($mod_txt);
  }

  // Píldora de pesos: X kg vs Y kg (si alguno existe)
  $pL = is_numeric($rojo_peso) ? (0+$rojo_peso).' kg' : '';
  $pR = is_numeric($azul_peso) ? (0+$azul_peso).' kg' : '';
  if ($pL && $pR)      $pills_peso_txt = "{$pL} vs {$pR}";
  elseif ($pL || $pR)  $pills_peso_txt = $pL ?: $pR;
}

/* ===== HTML ===== */
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?=h($ev_titulo)?> — Transmisión en vivo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --bg:#0b0b0b; --ink:#eee; --muted:#aaa; --brand:#ffd600; --card:#151515; --line:#262626; }
    html,body{background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0}
    header{<?= $share ? 'display:none;' : '' ?> position:sticky;top:0;background:#000a;padding:12px 16px;border-bottom:1px solid var(--line)}
    header h1{margin:0;font-size:18px}
    .wrap{max-width:1200px;margin:0 auto;padding:16px}
    .grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:14px}
    .video{position:relative;padding-top:56.25%;border-radius:12px;overflow:hidden}
    .video iframe{position:absolute;left:0;top:0;width:100%;height:100%;border:0}
    .meta{font-size:13px;color:var(--muted)}
    .title{font-size:18px;font-weight:800;margin:6px 0}
    .pills{margin:6px 0 0}
    .pill{display:inline-block;background:#222;border:1px solid var(--line);border-radius:999px;padding:4px 10px;font-size:12px;margin:4px 6px 0 0}
    .list{max-height:60vh;overflow:auto}
    .fight{border:1px solid var(--line);border-radius:10px;padding:10px;margin-bottom:8px}
    .fight a{color:var(--brand);text-decoration:none}
    .fight small{color:var(--muted)}
    .vs{display:flex;gap:10px;align-items:flex-start}
    .corner{flex:1}
    .corner h4{margin:0 0 4px;font-size:16px}
    .corner .sub{font-size:12px;color:var(--muted)}
    .divider{height:1px;background:var(--line);margin:10px 0}
    @media (max-width:920px){ .grid{grid-template-columns:1fr} }
    <?php if($share): ?>
    .wrap{padding:0}
    .card{border:none;border-radius:0}
    <?php endif; ?>
  </style>
</head>
<body>
<header>
  <h1>🎥 <?=h($ev_titulo)?></h1>
  <div class="wrap meta">
    <?= $ev_fecha ? '📅 '.h($ev_fecha).' · ' : '' ?>
    <?= $ev_lugar ? '📍 '.h($ev_lugar) : '' ?>
  </div>
</header>

<div class="wrap">
  <div class="<?= $share ? '' : 'grid' ?>">
    <div class="card">
      <?php if (!$youtube_id): ?>
        <div class="meta" style="padding:8px 0">⚠️ Este evento no tiene un enlace de YouTube configurado. Configuralo en <code>youtube_live_set.php</code>.</div>
      <?php else: ?>
        <div class="video">
          <iframe src="https://www.youtube.com/embed/<?=h($youtube_id)?>?rel=0&modestbranding=1"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                  allowfullscreen></iframe>
        </div>
      <?php endif; ?>

      <?php if ($pelea_sel): ?>
        <!-- Título: solo #orden (no repetimos nombres aquí) -->
        <div class="title" style="margin-top:10px">
          <?= ($orden_txt!=='' ? h("#".$orden_txt) : "Pelea") ?>
        </div>

        <?php if($mod_txt || $pills_peso_txt): ?>
          <div class="pills">
            <?php if($mod_txt):        ?><span class="pill">🏷️ <?=h($mod_txt)?></span><?php endif; ?>
            <?php if($pills_peso_txt): ?><span class="pill">⚖️ <?=h($pills_peso_txt)?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="vs" style="margin-top:8px">
          <div class="corner">
            <h4>🟥 <?=h($rojo_nom)?></h4>
            <?php if($rojo_esc!=='' || is_numeric($rojo_peso)): ?>
              <div class="sub">
                <?= $rojo_esc!=='' ? h($rojo_esc) : '' ?>
                <?= (is_numeric($rojo_peso) ? ($rojo_esc!=='' ? ' · ' : '').h((0+$rojo_peso).' kg') : '') ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="corner">
            <h4>🟦 <?=h($azul_nom)?></h4>
            <?php if($azul_esc!=='' || is_numeric($azul_peso)): ?>
              <div class="sub">
                <?= $azul_esc!=='' ? h($azul_esc) : '' ?>
                <?= (is_numeric($azul_peso) ? ($azul_esc!=='' ? ' · ' : '').h((0+$azul_peso).' kg') : '') ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="meta" style="padding-top:10px">No hay peleas cargadas aún para este evento.</div>
      <?php endif; ?>
    </div>

    <?php if(!$share): ?>
    <div class="card">
      <div class="title" style="margin:0 0 8px">Peleas del evento</div>
      <div class="list">
        <?php if(!$peleas): ?>
          <div class="meta">No hay peleas para listar.</div>
        <?php else: foreach($peleas as $p):
          $rid=(int)$p['competidor_rojo_id']; $aid=(int)$p['competidor_azul_id'];
          $rn = $mapComp[$rid]['nom'] ?? "Competidor #{$rid}";
          $re = $mapComp[$rid]['esc'] ?? '';
          $rp = $mapComp[$rid]['peso'] ?? '';
          $an = $mapComp[$aid]['nom'] ?? "Competidor #{$aid}";
          $ae = $mapComp[$aid]['esc'] ?? '';
          $ap = $mapComp[$aid]['peso'] ?? '';
          $num = $p['orden'] ?? '';
          $lab = ($num? "#$num · ":'')."$rn vs $an";
          $subs = [];
          if ($re) $subs[] = "🟥 $re";
          if ($ae) $subs[] = "🟦 $ae";
          if (is_numeric($rp) || is_numeric($ap)) {
            $spL = is_numeric($rp) ? (0+$rp).' kg' : '';
            $spR = is_numeric($ap) ? (0+$ap).' kg' : '';
            $subs[] = '⚖️ '.trim($spL.($spL&&$spR?' vs ':'').$spR);
          }
          $url = 'transmision_en_vivo.php?evento_id='.$evento_id.'&pelea_id='.(int)$p['id'];
        ?>
          <div class="fight">
            <div><a href="<?=h($url)?>"><?=h($lab)?></a></div>
            <?php if($subs): ?><small><?=h(implode(' · ', $subs))?></small><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="divider"></div>
      <div class="meta">
        Vista limpia para TV: <a href="transmision_en_vivo.php?evento_id=<?=$evento_id?>&pelea_id=<?= $pelea_sel?(int)$pelea_sel['id']:0 ?>&share=1">abrir en modo pantalla</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
