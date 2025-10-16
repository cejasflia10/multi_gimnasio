<?php
/* api_ranking_buscar.php
   Devuelve JSON con coincidencias para autocompletar competidores.
   Busca por APELLIDO, NOMBRE o DNI (parcial), acento-insensible. */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['error'=>'sin_bd']); exit;
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  $r = $db->query($sql);
  $ok = ($r && $r->num_rows>0); if($r) $r->close(); return $ok;
}
function first_existing_col(mysqli $db, string $t, array $cands): ?string {
  foreach ($cands as $c) if (has_col($db,$t,$c)) return $c;
  return null;
}

/* Tabla candidata (con apellido, nombre, dni) */
$tablas_candidatas = ['ranking_competidores','competidores','competidores_base','competidores_evento','clientes'];
$tabla = null;
foreach ($tablas_candidatas as $t) {
  if ($conexion->query("SHOW TABLES LIKE '{$t}'")->num_rows
      && has_col($conexion,$t,'apellido') && has_col($conexion,$t,'nombre') && has_col($conexion,$t,'dni')) {
    $tabla = $t; break;
  }
}
if (!$tabla) { echo json_encode([]); exit; }

/* Parámetro */
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) { echo json_encode([]); exit; }

/* Columnas opcionales */
$opt_all = [
  'fecha_nacimiento','sexo','provincia','localidad','provincia_id','localidad_id',
  'escuela_nombre','wins','losses','draws','no_contest'
];
$cols_opt = [];
foreach ($opt_all as $c) if (has_col($conexion,$tabla,$c)) $cols_opt[] = "`$c`";

/* Intentamos detectar columnas de foto y logo (nombres posibles) */
$foto_col = first_existing_col($conexion,$tabla,['foto_competidor','foto_url','foto','imagen','avatar','fotoCloud','foto_cloud','foto_cdn']);
$logo_col = first_existing_col($conexion,$tabla,['escuela_logo','logo','logo_url','logo_cloud','logoCloud','logo_cdn','escuela_logo_url']);

$select_cols = "`apellido`,`nombre`,`dni`".($cols_opt?','.implode(',',$cols_opt):'');
if ($foto_col) $select_cols .= ",`$foto_col` AS __foto__";
if ($logo_col) $select_cols .= ",`$logo_col` AS __logo__";

/* Normalizador acentos en SQL */
$norm = function($field){
  $f = "`$field`";
  $rep = [
    "REPLACE(%s,'á','a')","REPLACE(%s,'é','e')","REPLACE(%s,'í','i')",
    "REPLACE(%s,'ó','o')","REPLACE(%s,'ú','u')","REPLACE(%s,'ä','a')",
    "REPLACE(%s,'ë','e')","REPLACE(%s,'ï','i')","REPLACE(%s,'ö','o')",
    "REPLACE(%s,'ü','u')","REPLACE(%s,'Á','A')","REPLACE(%s,'É','E')",
    "REPLACE(%s,'Í','I')","REPLACE(%s,'Ó','O')","REPLACE(%s,'Ú','U')",
    "REPLACE(%s,'Ñ','N')","REPLACE(%s,'ñ','n')"
  ];
  $expr = $f;
  foreach ($rep as $r) $expr = sprintf($r, $expr);
  return "LOWER($expr)";
};
$APE = $norm('apellido');
$NOM = $norm('nombre');
$DNI = "`dni`";

$param = mb_strtolower($q, 'UTF-8');
$param_norm = strtr($param, [
  'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
  'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u','ñ'=>'n'
]);
$like = "%".$conexion->real_escape_string($param_norm)."%";

/* Query */
$sql = "SELECT $select_cols FROM `$tabla`
        WHERE ($APE LIKE ? OR $NOM LIKE ? OR $DNI LIKE ?)
        ORDER BY apellido, nombre
        LIMIT 50";
$st = $conexion->prepare($sql);
$st->bind_param('sss', $like, $like, $like);
$st->execute();
$res = $st->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $dni = preg_replace('/\D+/','', (string)($row['dni'] ?? ''));
  $fn  = isset($row['fecha_nacimiento']) ? (string)$row['fecha_nacimiento'] : '';
  if ($fn && preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $fn, $m)) {
    $fn = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
  }
  $foto  = (string)($row['__foto__'] ?? '');
  $logo  = (string)($row['__logo__'] ?? '');

  $out[] = [
    'apellido' => (string)$row['apellido'],
    'nombre'   => (string)$row['nombre'],
    'dni'      => $dni,
    'fecha_nacimiento' => $fn,
    'sexo'     => $row['sexo'] ?? '',
    'provincia'=> $row['provincia'] ?? '',
    'localidad'=> $row['localidad'] ?? '',
    'provincia_id'=> $row['provincia_id'] ?? null,
    'localidad_id'=> $row['localidad_id'] ?? null,
    'escuela_nombre'=> $row['escuela_nombre'] ?? '',
    'wins'     => (int)($row['wins'] ?? 0),
    'losses'   => (int)($row['losses'] ?? 0),
    'draws'    => (int)($row['draws'] ?? 0),
    'no_contest'=> (int)($row['no_contest'] ?? 0),
    'foto_competidor_url' => $foto,
    'escuela_logo_url'    => $logo,
  ];
}
$st->close();

echo json_encode($out, JSON_UNESCAPED_UNICODE);
