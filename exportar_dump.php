<?php
/* exportar_dump.php
   Genera un dump SQL (estructura + datos) usando la conexión actual (Railway).
   Crea un archivo dump_YYYYmmdd_His.sql y permite descargarlo con ?download=1
*/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

@ini_set('memory_limit', '512M');
@set_time_limit(300);

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD (revisá conexion.php).');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

date_default_timezone_set('America/Argentina/San_Luis');

$resDb = $conexion->query('SELECT DATABASE()');
if (!$resDb) { exit('❌ No se pudo ejecutar SELECT DATABASE(): '.$conexion->error); }
list($DB_NAME) = $resDb->fetch_row();
if (!$DB_NAME) { exit('❌ No se pudo detectar DATABASE().'); }

$fname = __DIR__ . '/dump_' . date('Ymd_His') . '.sql';

function q($sql, mysqli $conn){
  $res = $conn->query($sql);
  if(!$res){ throw new Exception("SQL error: ".$conn->error."\nQuery: ".$sql); }
  return $res;
}

try {
  $f = fopen($fname, 'w');
  if (!$f) throw new Exception('No se pudo crear el archivo: '.$fname);

  fwrite($f, "-- Dump generado por exportar_dump.php\n");
  fwrite($f, "-- Base: `{$DB_NAME}`\n");
  fwrite($f, "-- Fecha: ".date('Y-m-d H:i:s')."\n\n");
  fwrite($f, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

  // Listar tablas
  $tables = [];
  $r = q('SHOW TABLES', $conexion);
  while($row = $r->fetch_array()){
    $tables[] = $row[0];
  }

  foreach ($tables as $t) {
    $t_esc = '`'.str_replace('`','``',$t).'`';

    // Estructura
    $createRow = q('SHOW CREATE TABLE '.$t_esc, $conexion)->fetch_array(MYSQLI_NUM);
    $create_sql = $createRow[1] ?? '';
    if (!$create_sql) throw new Exception('No se pudo obtener SHOW CREATE TABLE de '.$t);

    fwrite($f, "\n-- ----------------------------\n");
    fwrite($f, "-- Estructura de tabla {$t_esc}\n");
    fwrite($f, "-- ----------------------------\n");
    fwrite($f, "DROP TABLE IF EXISTS {$t_esc};\n");
    fwrite($f, $create_sql . ";\n\n");

    // Datos
    $rs = q('SELECT * FROM '.$t_esc, $conexion);
    $fields = $rs->fetch_fields();

    if ($rs->num_rows > 0) {
      $cols = array_map(function($fld){ return '`'.str_replace('`','``',$fld->name).'`'; }, $fields);
      $col_list = implode(',', $cols);

      $batch = [];
      $batchSize = 200; // ajustable
      while($row = $rs->fetch_assoc()){
        $vals = [];
        foreach ($fields as $fld) {
          $name = $fld->name;
          $val  = $row[$name];

          if (is_null($val)) { $vals[] = 'NULL'; continue; }

          $isNumeric = in_array($fld->type, [
            MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG,
            MYSQLI_TYPE_INT24, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL,
            MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE
          ], true);

          if ($isNumeric && $val !== '') $vals[] = (string)$val;
          else $vals[] = "'" . $conexion->real_escape_string($val) . "'";
        }
        $batch[] = '(' . implode(',', $vals) . ')';

        if (count($batch) >= $batchSize) {
          fwrite($f, "INSERT INTO {$t_esc} ({$col_list}) VALUES\n" . implode(",\n", $batch) . ";\n");
          $batch = [];
        }
      }
      if (count($batch) > 0) {
        fwrite($f, "INSERT INTO {$t_esc} ({$col_list}) VALUES\n" . implode(",\n", $batch) . ";\n");
      }
    }
  }

  fwrite($f, "\nSET FOREIGN_KEY_CHECKS=1;\n");
  fclose($f);

  if (isset($_GET['download']) && $_GET['download']=='1') {
    $base = basename($fname);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="'.$base.'"');
    header('Content-Length: '.filesize($fname));
    readfile($fname);
    exit;
  }

  echo "✅ Dump creado: <code>".htmlspecialchars(basename($fname), ENT_QUOTES, 'UTF-8')."</code><br>";
  echo "👉 Para descargar: agrega <code>?download=1</code> a esta URL.<br>";
  echo "⚠️ Cuando termines, BORRÁ el archivo del servidor por seguridad.";

} catch (Exception $e) {
  http_response_code(500);
  echo "❌ Error al exportar: ".htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
