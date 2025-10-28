<?php
/* ============================================================
   patch_fks_clientes_cascade.php — Parchea TODAS las FKs que
   referencian clientes(id) para poner ON DELETE CASCADE.
   • Requiere usuario con permisos ALTER.
   • Muestra lo que hace y falla de forma segura.
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

try {
  $dbNameRes = $conexion->query("SELECT DATABASE() AS db");
  $dbName = ($dbNameRes && ($r=$dbNameRes->fetch_assoc())) ? $r['db'] : null;
  if (!$dbName) throw new Exception("No se pudo obtener DATABASE().");

  // Detectar TODAS las FKs que referencian clientes(id)
  $sql = "
    SELECT
      rc.CONSTRAINT_NAME,
      rc.TABLE_NAME,
      kcu.COLUMN_NAME,
      rc.UPDATE_RULE,
      rc.DELETE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS rc
    JOIN information_schema.KEY_COLUMN_USAGE kcu
      ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
     AND rc.CONSTRAINT_NAME   = kcu.CONSTRAINT_NAME
     AND rc.TABLE_NAME        = kcu.TABLE_NAME
    WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
      AND rc.REFERENCED_TABLE_NAME = 'clientes'
      AND kcu.REFERENCED_COLUMN_NAME = 'id'
  ";
  $res = $conexion->query($sql);
  $rows = [];
  while($res && ($x=$res->fetch_assoc())) $rows[] = $x;

  if (!$rows) {
    echo "<pre>✔️ No se encontraron FKs hacia clientes(id). Nada que hacer.</pre>";
    exit;
  }

  echo "<pre>Encontradas ".count($rows)." FKs hacia clientes(id):\n";
  foreach ($rows as $r) {
    echo " - {$r['TABLE_NAME']}.{$r['COLUMN_NAME']}  FK={$r['CONSTRAINT_NAME']}  (DEL={$r['DELETE_RULE']}, UPD={$r['UPDATE_RULE']})\n";
  }
  echo "\nAplicando ON DELETE CASCADE...\n</pre>";

  $conexion->begin_transaction();
  foreach ($rows as $r) {
    $tbl  = $conexion->real_escape_string($r['TABLE_NAME']);
    $col  = $conexion->real_escape_string($r['COLUMN_NAME']);
    $fk   = $conexion->real_escape_string($r['CONSTRAINT_NAME']);

    // Dropear FK actual
    $conexion->query("ALTER TABLE `{$tbl}` DROP FOREIGN KEY `{$fk}`");

    // Volver a crear con CASCADE (manteniendo el mismo nombre)
    $sqlAdd = "ALTER TABLE `{$tbl}`
               ADD CONSTRAINT `{$fk}`
               FOREIGN KEY (`{$col}`) REFERENCES `clientes`(`id`)
               ON DELETE CASCADE ON UPDATE CASCADE";
    $conexion->query($sqlAdd);

    echo "<pre>✔️ {$tbl}.{$col} → clientes.id  [{$fk}] ahora con ON DELETE CASCADE</pre>";
  }
  $conexion->commit();

  echo "<pre>\n✅ Listo: todas las FKs hacia clientes(id) tienen ON DELETE CASCADE.</pre>";

} catch (Throwable $e) {
  if ($conexion->errno===0) { /* nada */ } else { @ $conexion->rollback(); }
  $msg = h($e->getMessage());
  echo "<pre>❌ Error aplicando parche: {$msg}</pre>";
}
