<?php
// helpers.php — utilidades comunes. Guardar en la raíz del proyecto.

// Escapar HTML seguro
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Backtick para nombres de columnas/tablas
if (!function_exists('bt')) {
  function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
}

// Consultas rápidas
if (!function_exists('fetchOne')) {
  function fetchOne(mysqli $cx, string $sql){
    $r = $cx->query($sql);
    return ($r && $r->num_rows) ? $r->fetch_assoc() : null;
  }
}
if (!function_exists('fetchAll')) {
  function fetchAll(mysqli $cx, string $sql): array{
    $out=[]; $r=$cx->query($sql);
    if ($r) while($f=$r->fetch_assoc()) $out[]=$f;
    return $out;
  }
}

// Metadata de la BD
if (!function_exists('tableExists')) {
  function tableExists(mysqli $cx, string $t): bool{
    $q = "SHOW TABLES LIKE '".$cx->real_escape_string($t)."'";
    $r = $cx->query($q);
    return ($r && $r->num_rows>0);
  }
}
if (!function_exists('colExists')) {
  function colExists(mysqli $cx, string $table, string $col): bool{
    $q = "SHOW COLUMNS FROM ".bt($table)." LIKE '".$cx->real_escape_string($col)."'";
    $r = $cx->query($q);
    return ($r && $r->num_rows>0);
  }
}
