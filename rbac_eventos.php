<?php
// rbac_eventos.php — RBAC para el panel (sin gimnasios)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function ev_has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* =========================== Migraciones =========================== */
$conexion->query("CREATE TABLE IF NOT EXISTS evento_roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS evento_permisos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(80) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS evento_roles_permisos (
  rol_id INT NOT NULL,
  permiso_id INT NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  FOREIGN KEY (rol_id) REFERENCES evento_roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permiso_id) REFERENCES evento_permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS evento_usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  rol_id INT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (rol_id) REFERENCES evento_roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Si existía la columna gimnasio_id, la quitamos (opcional) */
if (ev_has_col($conexion,'evento_usuarios','gimnasio_id')) {
  @ $conexion->query("ALTER TABLE evento_usuarios DROP COLUMN gimnasio_id");
}

/* Seed de roles */
$conexion->query("INSERT IGNORE INTO evento_roles (id,nombre,descripcion) VALUES
  (1,'admin','Acceso total'),
  (2,'organizador','Gestiona eventos y ventas'),
  (3,'taquilla','Venta/escanear, sin administración'),
  (4,'lectura','Solo lectura')");

/* Catálogo de permisos */
$perms = [
  'panel.ver'          => 'Ver panel principal',
  'eventos.ver'        => 'Ver eventos',
  'eventos.editar'     => 'Crear/editar eventos',
  'eventos.eliminar'   => 'Eliminar eventos',
  'usuarios.ver'       => 'Ver usuarios',
  'usuarios.crear'     => 'Crear usuarios',
  'usuarios.editar'    => 'Editar usuarios',
  'usuarios.eliminar'  => 'Eliminar usuarios',
  'ventas.ver'         => 'Ver ventas',
  'ventas.editar'      => 'Editar ventas',
  'tickets.escanear'   => 'Escanear/validar tickets',
  'tickets.ver'        => 'Ver tickets',
];
$st = $conexion->prepare("INSERT IGNORE INTO evento_permisos (clave,descripcion) VALUES (?,?)");
foreach($perms as $k=>$d){ $st->bind_param('ss',$k,$d); $st->execute(); }
$st->close();

/* Map rol→permisos */
function ev_perm_id(mysqli $db, string $clave): ?int {
  $st=$db->prepare("SELECT id FROM evento_permisos WHERE clave=? LIMIT 1");
  $st->bind_param('s',$clave); $st->execute(); $id=$st->get_result()->fetch_column(); $st->close();
  return $id ? (int)$id : null;
}
function ev_grant(mysqli $db, int $rol_id, array $claves){
  foreach($claves as $c){
    $pid = ev_perm_id($db,$c); if(!$pid) continue;
    $st=$db->prepare("INSERT IGNORE INTO evento_roles_permisos (rol_id,permiso_id) VALUES (?,?)");
    $st->bind_param('ii',$rol_id,$pid); $st->execute(); $st->close();
  }
}
ev_grant($conexion, 1, array_keys($perms)); // admin = todo
ev_grant($conexion, 2, ['panel.ver','eventos.ver','eventos.editar','ventas.ver','ventas.editar','tickets.escanear','tickets.ver','usuarios.ver','usuarios.editar']); // organizador
ev_grant($conexion, 3, ['panel.ver','ventas.ver','tickets.escanear','tickets.ver']); // taquilla
ev_grant($conexion, 4, ['panel.ver','eventos.ver','ventas.ver','tickets.ver']); // lectura

/* =================== Helpers de sesión/permisos =================== */
function ev_refresh_perms(mysqli $db){
  if (empty($_SESSION['evento_usuario_id'])) return;
  $uid = (int)$_SESSION['evento_usuario_id'];
  $sql="SELECT p.clave
        FROM evento_usuarios u
        JOIN evento_roles_permisos rp ON rp.rol_id=u.rol_id
        JOIN evento_permisos p ON p.id=rp.permiso_id
        WHERE u.id=? AND u.activo=1";
  $st=$db->prepare($sql); $st->bind_param('i',$uid); $st->execute();
  $res=$st->get_result(); $perms=[]; while($r=$res->fetch_assoc()){ $perms[$r['clave']]=true; }
  $st->close();
  $_SESSION['ev_perms']=$perms;
}
if (!isset($_SESSION['ev_perms']) && !empty($_SESSION['evento_usuario_id'])) {
  ev_refresh_perms($conexion);
}

function ev_can(string $perm): bool {
  if (!empty($_SESSION['ev_perms'][$perm])) return true;
  if (!empty($_SESSION['evento_rol_nombre']) && $_SESSION['evento_rol_nombre']==='admin') return true;
  return false;
}
function ev_require_perm(string $perm){
  if (!ev_can($perm)) { http_response_code(403); exit('⛔ No tenés permiso para esta acción.'); }
}
