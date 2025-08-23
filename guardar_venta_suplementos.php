<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* ====== ENTRADAS ====== */
$cliente_nombre   = trim($_POST['cliente_nombre'] ?? '');
$cliente_id       = (int)($_POST['cliente_id'] ?? 0);       // si existe, mejor para CC
$es_temporal      = (int)($_POST['cliente_temporal'] ?? 0);
$tipo_venta_raw   = strtolower(trim($_POST['tipo_venta'] ?? 'productos'));
$gimnasio_id      = (int)($_POST['gimnasio_id'] ?? 0);
$total            = (float)($_POST['total'] ?? 0);

// Métodos de pago (ingresados)
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_tarjeta       = (float)($_POST['pago_tarjeta'] ?? 0);
$pago_cc_post       = (float)($_POST['pago_cc'] ?? 0); // opcional del form

// Detalle de productos
$productos   = $_POST['producto_nombre'] ?? [];
$precios     = $_POST['precio'] ?? [];
$cantidades  = $_POST['cantidad'] ?? [];

/* ====== VALIDACIONES BÁSICAS ====== */
if ($gimnasio_id <= 0) { http_response_code(403); exit("❌ Gimnasio inválido."); }
if ($total <= 0) { exit("❌ Total inválido."); }
if (!is_array($productos) || count($productos) === 0) { exit("❌ No se seleccionaron productos."); }

/* Lista blanca de tablas por tipo de venta */
$tipo_venta = in_array($tipo_venta_raw, ['suplementos','productos'], true) ? $tipo_venta_raw : 'productos';
$tabla_ventas   = ($tipo_venta === 'suplementos') ? 'ventas_suplementos' : 'ventas_productos';
$tabla_detalle  = $tabla_ventas . '_detalle';
$tabla_stock    = ($tipo_venta === 'suplementos') ? 'suplementos' : 'productos';

/* ====== Cálculo seguro de Cuenta Corriente en servidor ====== */
$pagado_ahora = max(0, round($pago_efectivo + $pago_transferencia + $pago_tarjeta, 2));
$cc_calc      = max(0, round($total - $pagado_ahora, 2)); // lo que falta pagar
$pago_cc      = max(0, min(round($pago_cc_post, 2), round($total, 2))); // capado por si viene del form
if (abs($pago_cc - $cc_calc) > 0.01) { $pago_cc = $cc_calc; } // servidor manda

/* Aviso visual (no detiene) si falta pago */
if ($pagado_ahora + $pago_cc < $total - 0.01) {
    echo "<div style='color:#b91c1c; font-size:16px;'>⚠️ El total pagado es menor al total; se generará saldo en cuenta corriente.</div>";
}

/* ====== TRANSACCIÓN ====== */
$conexion->begin_transaction();

try {
    $fecha = date('Y-m-d H:i:s');

    /* 1) INSERT venta */
    $sqlVenta = "
        INSERT INTO {$tabla_ventas}
            (cliente_nombre, total, pago_efectivo, pago_transferencia, pago_tarjeta, pago_cc, gimnasio_id, fecha, cliente_id, cliente_temporal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmtVenta = $conexion->prepare($sqlVenta);
    if (!$stmtVenta) { throw new Exception("Prep venta: ".$conexion->error); }
    $stmtVenta->bind_param(
        "sdddddisii",
        $cliente_nombre, $total, $pago_efectivo, $pago_transferencia, $pago_tarjeta, $pago_cc, $gimnasio_id, $fecha, $cliente_id, $es_temporal
    );
    if (!$stmtVenta->execute()) { throw new Exception("Exec venta: ".$stmtVenta->error); }
    $venta_id = (int)$stmtVenta->insert_id;
    $stmtVenta->close();

    /* 2) INSERT detalle + actualizar stock */
    $sqlDet = "INSERT INTO {$tabla_detalle} (venta_id, producto, precio, cantidad, subtotal) VALUES (?, ?, ?, ?, ?)";
    $stmtDet = $conexion->prepare($sqlDet);
    if (!$stmtDet) { throw new Exception("Prep detalle: ".$conexion->error); }

    $sqlStock = "UPDATE {$tabla_stock} SET stock = stock - ? WHERE nombre = ? AND gimnasio_id = ?";
    $stmtStock = $conexion->prepare($sqlStock);
    if (!$stmtStock) { throw new Exception("Prep stock: ".$conexion->error); }

    for ($i = 0; $i < count($productos); $i++) {
        $nombre   = trim((string)$productos[$i]);
        $precio   = (float)($precios[$i] ?? 0);
        $cantidad = (int)($cantidades[$i] ?? 0);
        if ($nombre === '' || $precio <= 0 || $cantidad <= 0) { continue; }

        $subtotal = round($precio * $cantidad, 2);

        $stmtDet->bind_param("isdid", $venta_id, $nombre, $precio, $cantidad, $subtotal);
        if (!$stmtDet->execute()) { throw new Exception("Exec detalle: ".$stmtDet->error); }

        $stmtStock->bind_param("isi", $cantidad, $nombre, $gimnasio_id);
        if (!$stmtStock->execute()) {
            // No rompas la venta por un stock puntual, pero registrá advertencia:
            error_log("WARN stock no actualizado para '{$nombre}': ".$stmtStock->error);
        }
    }
    $stmtDet->close();
    $stmtStock->close();

    /* 3) Si hay deuda y tengo cliente_id válido → asiento DEBE en cc_movimientos */
    if ($pago_cc > 0.009 && $cliente_id > 0) {
        $concepto = "Deuda por venta #{$venta_id} ({$tipo_venta})";
        $stmtCC = $conexion->prepare("
            INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        if (!$stmtCC) { throw new Exception("Prep CC: ".$conexion->error); }
        $stmtCC->bind_param("iiissd", $gimnasio_id, $cliente_id, $venta_id, $fecha, $concepto, $pago_cc);
        if (!$stmtCC->execute()) { throw new Exception("Exec CC: ".$stmtCC->error); }
        $stmtCC->close();
    }

    $conexion->commit();

    echo "<div style='color:#16a34a; font-size:18px;'>✅ Venta registrada correctamente.</div>";
    echo "<br><a href='ventas_{$tipo_venta}.php' style='color:gold;'>← Volver</a>";

} catch (Throwable $e) {
    $conexion->rollback();
    http_response_code(500);
    echo "<div style='color:#b91c1c; font-size:16px;'>❌ Error al registrar la venta: ".htmlspecialchars($e->getMessage())."</div>";
    echo "<br><a href='ventas_{$tipo_venta}.php' style='color:gold;'>← Volver</a>";
    exit;
}
// === Datos del gimnasio ===
$gym = ['nombre'=>'','direccion'=>'','cuit'=>'','logo'=>''];
$res = $conexion->query("SELECT nombre, direccion, cuit, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
if ($res && $res->num_rows) { $gym = $res->fetch_assoc(); }

// === Cliente ===
$cli = [
  'nombre' => $cliente_nombre ?? 'Consumidor Final',
  'dni'    => $cliente_dni ?? null,
  'id'     => $cliente_id ?? null,
];

// === Venta ===
$venta = [
  'id'        => $venta_id ?? null,
  'fecha'     => date('Y-m-d'),
  'hora'      => date('H:i'),
  'descuento' => $descuento ?? 0,
  'total'     => $total_con_descuento ?? $total_final ?? $total,
  'cc'        => $pago_cuenta_corriente ?? $pago_cc ?? 0,
];

// === Items ===
$items = [];
for ($i=0;$i<count($productos);$i++){
  $items[] = [
    'nombre'   => $productos[$i],
    'cantidad' => $cantidades[$i],
    'unitario' => $precios[$i],
    'subtotal' => $precios[$i]*$cantidades[$i],
  ];
}
if (empty($items)) {
  $items[] = ['nombre'=>'Productos','cantidad'=>1,'unitario'=>$venta['total'],'subtotal'=>$venta['total']];
}

// === Pagos ===
$pagos = [
  'efectivo'      => $pago_efectivo ?? 0,
  'transferencia' => $pago_transferencia ?? 0,
  'debito'        => $pago_debito ?? 0,
  'credito'       => $pago_credito ?? 0,
  'cc'            => $pago_cuenta_corriente ?? $pago_cc ?? 0,
];

// === Generar Factura PDF ===
require_once __DIR__.'/factura_pdf.php';
$nombre_archivo = 'factura_venta_'.$venta['id'].'.pdf';
$ruta_guardar   = __DIR__.'/facturas/'.$nombre_archivo;

generar_y_entregar_factura_pdf($gym, $cli, $venta, $items, $pagos, $ruta_guardar, $nombre_archivo);
exit;
