<?php
// permiso.php — recarga SIEMPRE desde BD + bypass opcional por gimnasio
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* ====== GIMNASIOS QUE TIENEN TODO HABILITADO (bypass) ====== */
const GYM_ALLOW_ALL = [6]; // ← Gym 6 TODO habilitado

/* ===================== MAPA PÁGINA => FEATURE ===================== */
function _perm_map(): array {
  return [
    // Panel de gimnasios
    'panel_gimnasios.php'   => 'panel_gimnasio',
    'agregar_gimnasio.php'  => 'panel_gimnasio',
    'editar_gimnasio.php'   => 'panel_gimnasio',
    'renovar_gimnasio.php'  => 'panel_gimnasio',

    // Clientes
    'ver_clientes.php'      => 'clientes',
    'agregar_cliente.php'   => 'clientes',

    // Membresías
    'ver_membresias.php'    => 'membresias',
    'nueva_membresia.php'   => 'membresias',
    'disciplinas.php'       => 'membresias',
    'planes.php'            => 'membresias',
    'adicionales.php'       => 'membresias',

    // Pagos
    'ver_pagos_pendientes.php'   => 'pagos',
    'config_alias.php'           => 'pagos',
    'ver_pagos_mes.php'          => 'pagos',
    'ver_cuentas_corrientes.php' => 'pagos',
    'gastos.php'                 => 'pagos',

    // Asistencias
    'ver_asistencia.php'          => 'asistencias',
    'registrar_asistencia.php'    => 'asistencias',
    'scanner_qr.php'              => 'asistencias',
    'ver_asistencias_profesor.php'=> 'asistencias',

    // Ventas
    'agregar_producto.php'    => 'ventas',
    'ver_productos.php'       => 'ventas',
    'ver_facturas.php'        => 'ventas',
    'ventas_proteccion.php'   => 'ventas',
    'ventas_suplementos.php'  => 'ventas',
    'ventas_indumentaria.php' => 'ventas',

    // Profesores
    'agregar_profesor.php'       => 'profesores',
    'ver_profesores.php'         => 'profesores',
    'turnos_profesor.php'        => 'profesores',
    'editar_tarifa_profesor.php' => 'profesores',
    'reporte_horas_profesor.php' => 'profesores',
    'biometria/enrolar_profesores.php' => 'profesores',
    'login_profesor.php'         => 'profesores_panel',

    // Panel cliente
    'cliente_acceso.php'      => 'panel_cliente',
    'panel_configuracion.php' => 'panel_cliente',

    // Eventos
    'panel_eventos.php'   => 'eventos_panel',
    'login_evento.php'    => 'eventos_panel',
    'eventos_publicos.php'=> 'eventos',
  ];
}

/* ===================== LECTURA DE PERMISOS DESDE BD ===================== */
function _read_permissions_from_db(mysqli $db, int $gimnasio_id): array {
  // Bypass: si el gym está en la lista, todo habilitado.
  if (in_array($gimnasio_id, GYM_ALLOW_ALL, true)) {
    // Devolvemos un set "permitir todo" básico para que el menú se muestre
    return [
      'panel_gimnasio'=>1,'clientes'=>1,'membresias'=>1,'pagos'=>1,'asistencias'=>1,
      'ventas'=>1,'profesores'=>1,'profesores_panel'=>1,'panel_cliente'=>1,'eventos_panel'=>1,'eventos'=>1
    ];
  }

  // Plan del gimnasio
  $plan_id = 0;
  if ($st = $db->prepare("SELECT plan_id FROM gimnasios WHERE id = ? LIMIT 1")) {
    $st->bind_param('i', $gimnasio_id);
    $st->execute();
    $st->bind_result($plan_id);
    $st->fetch();
    $st->close();
  }

  $perms = [];
  $sql = "
    SELECT f.feature, COALESCE(gp.enabled, pp.enabled, 0) AS enabled
    FROM (
      SELECT feature FROM plan_permisos WHERE plan_id = ?
      UNION
      SELECT feature FROM gimnasios_permisos WHERE gimnasio_id = ?
    ) f
    LEFT JOIN plan_permisos pp
           ON pp.plan_id = ? AND pp.feature = f.feature
    LEFT JOIN gimnasios_permisos gp
           ON gp.gimnasio_id = ? AND gp.feature = f.feature
    ORDER BY f.feature
  ";
  if ($st = $db->prepare($sql)) {
    $st->bind_param('iiii', $plan_id, $gimnasio_id, $plan_id, $gimnasio_id);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) {
      $feat = trim((string)$r['feature']);
      $perms[$feat] = ((int)$r['enabled'] === 1) ? 1 : 0;
    }
    $st->close();
  }
  return $perms;
}

/* ===================== HELPERS ===================== */
function has_feature(string $feature): bool {
  if (!empty($_SESSION['rol']) && $_SESSION['rol'] === 'admin') return true; // bypass admin
  $perms = $_SESSION['permisos'] ?? [];
  return isset($perms[$feature]) ? ((int)$perms[$feature] === 1) : false;
}
function checkFeature(string $f): bool { return has_feature($f); }
function tiene_permiso(string $f): bool { return has_feature($f); }

/** Fuerza recarga de permisos */
function refresh_permissions(int $gimnasio_id): void {
  global $conexion;
  if (!($conexion instanceof mysqli)) return;
  $_SESSION['permisos'] = _read_permissions_from_db($conexion, $gimnasio_id);
  $_SESSION['permisos_gimnasio_id'] = $gimnasio_id;
}

/* ===================== GUARDIA DE ACCESO ===================== */
function guardia_permiso(?string $feature = null): void {
  global $conexion;

  if (session_status() === PHP_SESSION_NONE) session_start();
  if (!($conexion instanceof mysqli)) {
    http_response_code(500);
    exit('<div style="padding:12px;border:1px solid #c00">❌ Sin conexión a BD</div>');
  }

  // Bypass admin
  if (!empty($_SESSION['rol']) && $_SESSION['rol'] === 'admin') return;

  $gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
  if ($gimnasio_id <= 0) {
    http_response_code(403);
    exit("<div style='font:14px/1.4 Arial;background:#fff;color:#111;padding:16px;border:2px solid #c00;border-radius:8px'>
      <h3>Acceso denegado</h3><p>Falta <code>gimnasio_id</code> en sesión.</p>
    </div>");
  }

  // Bypass por gimnasio
  if (in_array($gimnasio_id, GYM_ALLOW_ALL, true)) return;

  $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
  $map = _perm_map();
  if ($feature === null) { $feature = $map[$script] ?? null; }
  if ($feature === null || $feature === '') return; // página no mapeada → no bloqueamos

  // Recarga SIEMPRE desde BD
  $_SESSION['permisos'] = _read_permissions_from_db($conexion, $gimnasio_id);
  $_SESSION['permisos_gimnasio_id'] = $gimnasio_id;

  // Debug: ?debug_perms=1
  if (!empty($_GET['debug_perms'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "GIM: {$gimnasio_id}\nSCRIPT: {$script}\nFEATURE: {$feature}\n\n";
    print_r($_SESSION['permisos']);
    exit;
  }

  if (!has_feature($feature)) {
    http_response_code(403);
    exit("<div style='font:14px/1.4 Arial;background:#fff;color:#111;padding:16px;border:2px solid #c00;border-radius:8px;max-width:800px;margin:24px auto'>
      <h3 style='margin:0 0 8px'>Acceso denegado</h3>
      <p>No tenés permiso para <b>{$feature}</b>. Gym ID <b>{$gimnasio_id}</b>.</p>
      <p><small>script: {$script}</small></p>
    </div>");
  }
}
