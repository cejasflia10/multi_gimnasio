<?php
if (session_status()===PHP_SESSION_NONE) session_start();

const ROL_ORGANIZADOR = 'ORGANIZADOR';
const ROL_STAFF       = 'STAFF';
const ROL_JUEZ        = 'JUEZ';

function user_id(): int { return (int)($_SESSION['evento_usuario_id'] ?? 0); }
function user_role(): string { return strtoupper((string)($_SESSION['rol'] ?? '')); }

function require_login(): void {
  if (user_id() <= 0) {
    $return_to = $_SERVER['REQUEST_URI'] ?? 'panel_eventos.php';
    header('Location: login_evento.php?return_to='.urlencode($return_to)); exit;
  }
}

/** Bloqueo simple por rol(es) para páginas completas */
function require_role(array $allow_roles): void {
  require_login();
  $role = user_role();
  $allow_upper = array_map('strtoupper', $allow_roles);
  if (!in_array($role, $allow_upper, true)) {
    http_response_code(403);
    exit('🚫 Acceso denegado para tu rol.');
  }
}
