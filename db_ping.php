<?php
error_reporting(E_ALL); ini_set('display_errors',1);
$h='127.0.0.1'; $p=3306; $u='root'; $pw=''; $db='multi_gimnasio';

$cx = @mysqli_init();
if (defined('MYSQLI_OPT_PROTOCOL') && defined('MYSQLI_PROTOCOL_TCP')) {
  mysqli_options($cx, MYSQLI_OPT_PROTOCOL, MYSQLI_PROTOCOL_TCP);
}
mysqli_options($cx, MYSQLI_OPT_CONNECT_TIMEOUT, 3);

if (!@mysqli_real_connect($cx, $h, $u, $pw, $db, $p)) {
  die("❌ Falló conectar a {$h}:{$p} → ".mysqli_connect_error());
}
echo "✅ Conectó por TCP a {$h}:{$p}";
