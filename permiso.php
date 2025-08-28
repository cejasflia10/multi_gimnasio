<?php
// permiso.php — control de acceso por features según plan y overrides del gimnasio
// Requiere tablas: gimnasios(plan_id), plan_permisos(plan_id, feature, enabled),
//                  gimnasios_permisos(gimnasio_id, feature, enabled)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

// ==== Config rápida ====
const PERM_SESSION_KEY = 'perms_effective'; // cache de permisos efectivos en sesión

// ---- Utils de entorno ----
function gym_id_from_session(): int {
    return (int)($_SESSION['gimnasio_id'] ?? 0);
}

/**
 * Carga los permisos EFECTIVOS (override del gimnasio si existe; si no, valor del plan)
 * y los guarda en $_SESSION[PERM_SESSION_KEY] como ['feature' => 0|1, ...]
 */
function refresh_permissions(?int $gimnasio_id = null): void {
    global $conexion;

    $gimnasio_id = $gimnasio_id ?? gym_id_from_session();
    $_SESSION[PERM_SESSION_KEY] = []; // reset

    if (!$gimnasio_id || !($conexion instanceof mysqli)) return;

    // 1) Traer plan_id del gimnasio
    $plan_id = 0;
    if ($st = $conexion->prepare("SELECT plan_id FROM gimnasios WHERE id = ? LIMIT 1")) {
        $st->bind_param('i', $gimnasio_id);
        $st->execute();
        $st->bind_result($plan_id);
        $st->fetch();
        $st->close();
    }
    if ($plan_id <= 0) return;

    // 2) Construir conjunto de features (union de plan + overrides del gym) y calcular 'effective'
    $sql = "
        SELECT f.feature,
               COALESCE(gp.enabled, pp.enabled, 0) AS effective
        FROM (
          SELECT feature FROM plan_permisos WHERE plan_id = ?
          UNION
          SELECT feature FROM gimnasios_permisos WHERE gimnasio_id = ?
        ) AS f
        LEFT JOIN plan_permisos pp
               ON pp.plan_id = ? AND pp.feature = f.feature
        LEFT JOIN gimnasios_permisos gp
               ON gp.gimnasio_id = ? AND gp.feature = f.feature
        ORDER BY f.feature
    ";
    if ($st = $conexion->prepare($sql)) {
        $st->bind_param('iiii', $plan_id, $gimnasio_id, $plan_id, $gimnasio_id);
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $_SESSION[PERM_SESSION_KEY][$row['feature']] = (int)$row['effective'] === 1 ? 1 : 0;
        }
        $st->close();
    }
}

/**
 * Devuelve true si el gimnasio tiene habilitada la feature.
 * Usa caché en sesión; si no existe, la reconstruye automáticamente.
 */
function has_perm(string $feature): bool {
    // Normalizamos clave
    $feature = trim($feature);

    if (!isset($_SESSION[PERM_SESSION_KEY]) || !is_array($_SESSION[PERM_SESSION_KEY])) {
        refresh_permissions(); // primer acceso de la sesión o después de login/cambio de plan
    }

    // En caso de que el menú se cargue antes de setear gimnasio_id:
    if (!isset($_SESSION[PERM_SESSION_KEY]) || empty($_SESSION[PERM_SESSION_KEY])) {
        return false;
    }

    // Si no está definida la feature, por seguridad => false
    $val = $_SESSION[PERM_SESSION_KEY][$feature] ?? 0;
    return (int)$val === 1;
}

/**
 * Corta la ejecución si no hay permiso. Úsalo al inicio de las páginas protegidas.
 * Ejemplo: require_permiso('profesores');
 */
function require_permiso(string $feature): void {
    if (!has_perm($feature)) {
        http_response_code(402);
        exit('<div style="max-width:760px;margin:40px auto;padding:16px;border:1px solid #eab308;border-radius:8px;
              background:#1f1b00;color:#fde047;font-family:Arial,sans-serif;text-align:center">
              <div style="font-size:18px;font-weight:bold;margin-bottom:6px">Tu plan no habilita esta sección</div>
              <div style="opacity:.9">Para acceder a <b>'.htmlspecialchars($feature, ENT_QUOTES, 'UTF-8').'</b>, necesitás mejorar el plan.</div>
              <div style="margin-top:10px">
                <a href="planes.php" style="display:inline-block;padding:8px 12px;background:#eab308;color:#111;
                text-decoration:none;border-radius:6px;font-weight:bold">Ver planes</a>
              </div>
        </div>');
    }
}

/**
 * Si cambiás de plan, renovás o modificás overrides, llamá a esta función para
 * regenerar el cache. Por ejemplo, después de guardar en editar_gimnasio.php
 *   -> after DB update:
 *      refresh_permissions($gimnasio_id);
 */
