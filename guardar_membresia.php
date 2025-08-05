<?php
// guardar_membresia.php (corregido)
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($gimnasio_id == 0) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso no permitido.");
}

// --- 1) Recibir y sanitizar POST ---
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$plan_id = intval($_POST['plan_id'] ?? 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_vencimiento_post = $_POST['fecha_vencimiento'] ?? '';
$otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
$descuento_pct = floatval($_POST['descuento'] ?? 0); // porcentaje (ej. 10 = 10%)
$adicionales = $_POST['adicionales'] ?? []; // array ids

// pagos ingresados por el recepcionista (lo que efectivamente entra ahora)
$pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
$pago_debito = floatval($_POST['pago_debito'] ?? 0);
$pago_credito = floatval($_POST['pago_credito'] ?? 0);
// campo que indica que el recepcionista quiere anotar que parte quedará en cuenta corriente
$pago_cuenta_corriente_manual = floatval($_POST['pago_cuenta_corriente'] ?? 0);

// Validaciones mínimas
if ($cliente_id <= 0) die("Cliente inválido.");
if ($plan_id <= 0) die("Plan inválido.");

// --- 2) Recuperar datos del plan desde DB (precio, clases, duración) ---
$plan = $conexion->query("SELECT precio, clases_disponibles, duracion_meses FROM planes WHERE id = $plan_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$plan) die("Plan no encontrado.");

$precio_plan = floatval($plan['precio']);
$clases_plan = intval($plan['clases_disponibles']);
$duracion = intval($plan['duracion_meses']);

// Si no viene fecha_vencimiento desde el formulario, calcularla acá
if (empty($fecha_vencimiento_post)) {
    $fecha_vencimiento = date('Y-m-d', strtotime($fecha_inicio . " + $duracion months"));
} else {
    $fecha_vencimiento = $fecha_vencimiento_post;
}

// --- 3) Calcular precio de adicionales (desde DB, no confiar en valores del cliente) ---
$total_adicionales = 0.0;
$adicionales_ids = [];
if (!empty($adicionales) && is_array($adicionales)) {
    $adicionales_ids = array_map('intval', $adicionales);
    $ids_list = implode(',', $adicionales_ids);
    if (!empty($ids_list)) {
        $resAd = $conexion->query("SELECT id, precio FROM planes_adicionales WHERE id IN ($ids_list) AND gimnasio_id = $gimnasio_id");
        while ($r = $resAd->fetch_assoc()) {
            $total_adicionales += floatval($r['precio']);
        }
    }
}

// --- 4) Recalcular total en servidor ---
$total_bruto = $precio_plan + $total_adicionales + $otros_pagos;
$total_final = round($total_bruto - ($total_bruto * ($descuento_pct / 100)), 2);

// --- 5) Calcular total abonado AHORA (excluimos cuenta corriente manual para evitar doble contar) ---
$total_abonado = round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2);

// --- 6) Determinar deuda (lo que falta) ---
$deuda = round($total_final - $total_abonado, 2); // si >0 = falta plata (deuda), si 0 = ok, si <0 = sobrante

// Si el recepcionista indicó manualmente pago_cuenta_corriente (ej: quiere registrar que parte será a cuenta corriente),
// preferimos respetar la intención: restamos esa cifra del monto que falta y consideramos el resto como deuda.
// Pero **no** sumamos pago_cuenta_corriente_manual dentro de `total_abonado`.
if ($pago_cuenta_corriente_manual > 0) {
    // restamos el monto manualmente indicado (el staff quiere registrar que $X vaya a cuenta corriente)
    // interpretamos pago_cuenta_corriente_manual como "parte que se registra como deuda", así que reducimos la deuda en ese monto.
    $deuda_after_manual = round($deuda - $pago_cuenta_corriente_manual, 2);
    // Si queda deuda positiva -> se registra por la diferencia. Si queda negativa -> sobrante.
    $deuda = $deuda_after_manual;
}

// --- 7) Insertar membresía (guardamos total_pagado = lo efectivamente abonado ahora) ---
$metodos = [];
if ($pago_efectivo > 0) $metodos[] = "Efectivo:$pago_efectivo";
if ($pago_transferencia > 0) $metodos[] = "Transferencia:$pago_transferencia";
if ($pago_debito > 0) $metodos[] = "Debito:$pago_debito";
if ($pago_credito > 0) $metodos[] = "Credito:$pago_credito";
$metodo_pago = !empty($metodos) ? implode('|', $metodos) : 'Sin pagar ahora';

$total_pagado_guardar = $total_abonado; // lo que entró de caja ahora
$saldo_cc = 0.0; // monto que guardaremos en cuentas_corrientes (sigue convención: NEGATIVO = deuda)
$descripcion_cc = '';

if ($deuda > 0.009) {
    // queda deuda: guardamos monto negativo en cuentas_corrientes
    $saldo_cc = -1 * $deuda;
    $descripcion_cc = "Deuda por membresía (membresía generada)";
} elseif ($deuda < -0.009) {
    // sobrante: cliente pagó demás -> registramos saldo positivo si querés (a favor del cliente)
    $saldo_cc = abs($deuda); // monto positivo a favor
    $descripcion_cc = "Saldo a favor por pago en exceso (membresía)";
} else {
    $saldo_cc = 0.0; // todo ok
}

// Preparar insert en membresias
$stmt = $conexion->prepare("
    INSERT INTO membresias 
    (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, total, gimnasio_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    die("Error prepare: " . $conexion->error);
}

// Tipos: i i s s i d d d d s d d i  => "iissiddddsddi"
$types = "iissiddddsddi";
$stmt->bind_param($types,
    $cliente_id,
    $plan_id,
    $fecha_inicio,
    $fecha_vencimiento,
    $clases_plan,
    $precio_plan,
    $otros_pagos,
    $descuento_pct,
    $total_pagado_guardar,
    $metodo_pago,
    $saldo_cc,
    $total_final,
    $gimnasio_id
);

if (!$stmt->execute()) {
    die("Error al guardar membresía: " . $stmt->error);
}
$membresia_id = $stmt->insert_id;
$stmt->close();

// --- 8) Guardar adicionales asociados (si corresponde) ---
if (!empty($adicionales_ids)) {
    $insAd = $conexion->prepare("INSERT INTO membresia_adicionales (membresia_id, adicional_id) VALUES (?, ?)");
    foreach ($adicionales_ids as $aid) {
        $aid = intval($aid);
        $insAd->bind_param("ii", $membresia_id, $aid);
        $insAd->execute();
    }
    $insAd->close();
}

// --- 9) Guardar registro en cuentas_corrientes si corresponde (saldo negativo = deuda; saldo positivo = a favor) ---
if (abs($saldo_cc) > 0.009) {
    $fecha_actual = date('Y-m-d H:i:s');
    $descripcion = $conexion->real_escape_string($descripcion_cc . " - membresia_id:$membresia_id");

    $stmt_cc = $conexion->prepare("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt_cc) {
        // no fatal: mostrar error para debugging
        error_log("Error prepare cuentas_corrientes: " . $conexion->error);
    } else {
        $stmt_cc->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha_actual, $descripcion, $saldo_cc);
        $stmt_cc->execute();
        $stmt_cc->close();
    }
}

// --- 10) Redirigir con éxito ---
header("Location: ver_membresias.php?exito=1");
exit;
