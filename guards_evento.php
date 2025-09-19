<?php
if (session_status()===PHP_SESSION_NONE) session_start();

/**
 * ORG: acceso a todos los eventos
 * STAFF/JUEZ: solo si están en evento_asignaciones
 */
function can_access_event(mysqli $db, int $evento_id): bool {
  $role = strtoupper($_SESSION['rol'] ?? '');
  if ($role === 'ORGANIZADOR') return true;

  $uid = (int)($_SESSION['evento_usuario_id'] ?? 0);
  if ($uid <= 0) return false;

  $q = $db->prepare("SELECT 1 FROM evento_asignaciones WHERE evento_id=? AND user_id=? LIMIT 1");
  if (!$q) return false;
  $q->bind_param("ii", $evento_id, $uid);
  $q->execute();
  return (bool)$q->get_result()->fetch_row();
}

/** Úsalo al inicio de páginas que abren un evento */
function require_event_access(mysqli $db, int $evento_id): void {
  if ($evento_id<=0) { http_response_code(400); exit('⚠️ Falta ID de evento.'); }
  if (!can_access_event($db, $evento_id)) {
    http_response_code(403); exit('🚫 No podés acceder a este evento.');
  }
}
