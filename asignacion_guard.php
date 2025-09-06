<?php
// asignacion_guard.php — NO bloquea en modo juez; sólo avisa.
// Uso: if (!asignacion_permitida($conexion, $juez_id, $pelea_id, $msg)) { echo $msg; /* seguir igual */ }

if (!function_exists('asignacion_permitida')) {
  function asignacion_permitida(mysqli $db, int $juez_id, int $pelea_id, ?string &$msg = null): bool {
    // Bypass global si estamos en modo juez o pidieron bypass
    $modoJuez = !empty($_SESSION['__JUEZ_MODE__']) || (isset($_GET['modo']) && $_GET['modo'] === 'juez') || (!empty($_GET['bypass']));
    if ($modoJuez) { $msg = '⚠️ Nota: esta pelea no figura asignada, pero podés puntuar igualmente.'; return true; }

    // Detectar tabla de asignaciones, si existe
    $tbl = null;
    if ($r=$db->query("SHOW TABLES LIKE 'pelea_jueces'")) { if ($r->num_rows) $tbl = 'pelea_jueces'; $r->close(); }
    if (!$tbl && ($r=$db->query("SHOW TABLES LIKE 'peleas_jueces'"))) { if ($r->num_rows) $tbl = 'peleas_jueces'; $r->close(); }

    // Si no hay tabla de asignaciones, dejar pasar
    if (!$tbl) return true;

    // Consultar asignación
    $ok = true;
    if ($st = $db->prepare("SELECT 1 FROM `$tbl` WHERE pelea_id=? AND juez_id=? LIMIT 1")) {
      $st->bind_param('ii', $pelea_id, $juez_id);
      $st->execute();
      $ok = $st->get_result()->num_rows > 0;
      $st->close();
    }
    if (!$ok) { $msg = '⚠️ Nota: esta pelea no está asignada a tu usuario, pero se permite puntuar.'; }
    return true; // <<< nunca bloquea
  }
}
