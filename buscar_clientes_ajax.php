<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis');

ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$q = trim((string)($_GET['q'] ?? ''));
$debug = !empty($_GET['debug']);
if ($q==='') out([]);

/* Toma datos de sesión si existen (pero NO son obligatorios) */
$gym_id   = (int)($_SESSION['gimnasio_id'] ?? $_SESSION['gym_id'] ?? 0);

/* ====== utilidades para acentos ====== */
if (!function_exists('mb_strtolower')) {
  function mb_strtolower($s,$enc=null){ return strtolower($s); }
}
function accentless_expr($col){
  $x = "LOWER($col)";
  $rep = [
    ["'á'","'a'"],["'é'","'e'"],["'í'","'i'"],["'ó'","'o'"],["'ú'","'u'"],
    ["'Á'","'a'"],["'É'","'e'"],["'Í'","'i'"],["'Ó'","'o'"],["'Ú'","'u'"],
    ["'ä'","'a'"],["'ë'","'e'"],["'ï'","'i'"],["'ö'","'o'"],["'ü'","'u'"],
    ["'Ä'","'a'"],["'Ë'","'e'"],["'Ï'","'i'"],["'Ö'","'o'"],["'Ü'","'u'"],
    ["'ñ'","'n'"],["'Ñ'","'n'"]
  ];
  foreach ($rep as [$a,$b]) { $x = "REPLACE($x,$a,$b)"; }
  return $x;
}
function accentless_token($t){
  $map = [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
    'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
    'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
    'Ä'=>'a','Ë'=>'e','Ï'=>'i','Ö'=>'o','Ü'=>'u',
    'ñ'=>'n','Ñ'=>'n'
  ];
  return strtr($t,$map);
}

/* Normaliza y tokeniza */
$raw_tokens = preg_split('/\s+/', $q);
$tokens = array_values(array_filter(array_map(function($t){
  $t = trim($t);
  if ($t==='') return '';
  return mb_strtolower($t, 'UTF-8');
}, $raw_tokens)));
$tokens_acl = array_map('accentless_token', $tokens);

/* Construye WHERE dinámico */
function build_where_and_params(array $tokens_acl, bool $filter_gym, int $gym_id){
  $ap   = accentless_expr('apellido');
  $no   = accentless_expr('nombre');
  $apno = "CONCAT($ap,' ',$no)";
  $noap = "CONCAT($no,' ',$ap)";

  $whereBlocks = [];
  $params = [];
  $types  = '';

  foreach ($tokens_acl as $tk){
    $like = '%'.$tk.'%';
    $block = [];
    $block[] = "$ap LIKE ?";   $params[]=$like; $types.='s';
    $block[] = "$no LIKE ?";   $params[]=$like; $types.='s';
    $block[] = "$apno LIKE ?"; $params[]=$like; $types.='s';
    $block[] = "$noap LIKE ?"; $params[]=$like; $types.='s';
    if (preg_match('/\d/', $tk)) { // si tiene números, también DNI
      $block[] = "dni LIKE ?"; $params[]=$like; $types.='s';
    }
    $whereBlocks[] = '('.implode(' OR ',$block).')';
  }

  $sql = "SELECT id, apellido, nombre, dni FROM clientes WHERE ";
  $prefix = [];
  if ($filter_gym && $gym_id>0){
    $prefix[] = "gimnasio_id = ?";
    $types = 'i'.$types;
    array_unshift($params, $gym_id);
  }
  $prefix[] = implode(' AND ', $whereBlocks);
  $sql .= implode(' AND ', $prefix);
  $sql .= " ORDER BY apellido, nombre LIMIT ?";

  $params[] = 50; $types.='i';

  return [$sql,$types,$params];
}

function run_query(mysqli $db, $sql, $types, $params){
  $st = $db->prepare($sql);
  if (!$st) {
    return ['ok'=>false,'err'=>$db->error,'rows'=>[]];
  }
  // bind flexible
  $bind = [];
  $bind[] = &$types;
  foreach ($params as $k => $v) $bind[] = &$params[$k];
  call_user_func_array([$st,'bind_param'],$bind);

  $st->execute();
  $rs = $st->get_result();
  $rows = [];
  if ($rs){
    while($r=$rs->fetch_assoc()){
      $rows[] = [
        'id'       => (int)$r['id'],
        'apellido' => (string)($r['apellido'] ?? ''),
        'nombre'   => (string)($r['nombre'] ?? ''),
        'dni'      => (string)($r['dni'] ?? ''),
      ];
    }
  }
  $st->close();
  return ['ok'=>true,'rows'=>$rows];
}

/* Si tenemos gym_id en sesión, filtramos por él; si no, buscamos global */
list($sql,$types,$params) = build_where_and_params($tokens_acl, $gym_id>0, $gym_id);
$res = run_query($conexion,$sql,$types,$params);

if ($debug){
  out([
    'q'=>$q,
    'tokens'=>$tokens,
    'tokens_ai'=>$tokens_acl,
    'gym_id'=>$gym_id,
    'sql_ok'=>$res['ok'],
    'err'=>$res['ok']?null:$res['err'],
    'count'=>count($res['rows']),
    'sample'=>array_slice($res['rows'],0,5),
  ]);
}

out($res['rows']);
