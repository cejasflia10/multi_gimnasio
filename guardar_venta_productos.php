<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
require_once __DIR__.'/factura_pdf.php'; // helper del PDF

date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha = date('Y-m-d H:i:s');

/* ===== Inputs ===== */
$cliente_id        = (int)($_POST['cliente_id'] ?? 0);
$cliente_temporal  = (int)($_POST['cliente_temporal'] ?? 0);
$cliente_nombre    = trim((string)($_POST['cliente_nombre'] ?? 'Cliente temporal')); // para PDF y crear temporal
$cliente_dni       = trim((string)($_POST['cliente_dni'] ?? ''));                   // opcional

$productos         = $_POST['producto_nombre'] ?? [];
$precios           = $_POST['precio'] ?? [];
$cantidades        = $_POST['cantidad'] ?? [];
$tipo_venta        = trim((string)($_POST['tipo_venta'] ?? ''));
$gimnasio_id       = (int)($_POST['gimnasio_id'] ?? 0);

$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);
$pago_cc_post       = (float)($_POST['pago_cuenta_corriente'] ?? 0);
$vuelto             = (float)($_POST['vuelto'] ?? 0);

$total_con_desc     = (float)($_POST['total_con_descuento'] ?? 0);
$descuento          = (float)($_POST['descuento'] ?? 0);

/* ===== Validaciones ===== */
if ($gimnasio_id <= 0) { exit("❌ Gimnasio inválido."); }
if ($cliente_id <= 0 && !$cliente_temporal) { exit("❌ Cliente no válido."); }
if (!is_array($productos) || count($productos) === 0) { exit("❌ No se seleccionaron productos."); }
if ($total_con_desc <= 0) { exit("❌ Total inválido."); }

/* ===== CC en servidor ===== */
$pagado_ahora = max(0, round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2));
$cc_calc      = max(0, round($total_con_desc - $pagado_ahora, 2));
$cc_final     = ($pago_cc_post > 0) ? min(max(0, round($pago_cc_post, 2)), round($total_con_desc, 2)) : $cc_calc;

/* ===== Texto medios ===== */
$metodo_pago = "Efectivo: $pago_efectivo, Transf: $pago_transferencia, Débito: $pago_debito, Crédito: $pago_credito, Cuenta Corriente: $cc_final";
if ($vuelto > 0) { $metodo_pago .= ", Vuelto: $vuelto"; }

/* ===== Helpers internos mínimos ===== */
function table_exists(mysqli $cx, string $t): bool {
  $rs = $cx->query("SHOW TABLES LIKE '".$cx->real_escape_string($t)."'");
  return $rs && $rs->num_rows > 0;
}
function column_exists(mysqli $cx, string $t, string $c): bool {
  $rs = $cx->query("SHOW COLUMNS FROM `".$cx->real_escape_string($t)."` LIKE '".$cx->real_escape_string($c)."'");
  return $rs && $rs->num_rows > 0;
}
/* Crea/obtiene un cliente_id si falta (para imputar CC) */
function ensure_cliente_id(mysqli $cx, int $gimnasio_id, int $cliente_id, string $cliente_nombre): int {
  if ($cliente_id > 0) return $cliente_id;
  $nombre = trim($cliente_nombre);
  if ($nombre === '' || $gimnasio_id <= 0) return 0;

  // buscar coincidencia única por nombre/apellido
  $sql = "SELECT id FROM clientes WHERE gimnasio_id=? AND (CONCAT(TRIM(apellido),' ',TRIM(nombre))=? OR nombre=?) LIMIT 2";
  $stmt = $cx->prepare($sql);
  $stmt->bind_param('iss', $gimnasio_id, $nombre, $nombre);
  $stmt->execute();
  $rs = $stmt->get_result();
  if ($rs && $rs->num_rows === 1) {
    $row = $rs->fetch_assoc(); $stmt->close();
    return (int)$row['id'];
  }
  $stmt->close();

  // crear temporal
  $parts = preg_split('/\s+/', $nombre);
  $apellido = '';
  $nombreSolo = $nombre;
  if (count($parts) >= 2) { $apellido = array_shift($parts); $nombreSolo = implode(' ', $parts); }

  $sqlIns = "INSERT INTO clientes (nombre, apellido, gimnasio_id".(column_exists($cx,'clientes','temporal')?', temporal':'').") VALUES (?, ?, ?, ".(column_exists($cx,'clientes','temporal')?'1':'?').")";
  if (column_exists($cx,'clientes','temporal')) {
    $stmt2 = $cx->prepare("INSERT INTO clientes (nombre, apellido, gimnasio_id, temporal) VALUES (?, ?, ?, 1)");
    if (!$stmt2) return 0;
    $stmt2->bind_param('ssi', $nombreSolo, $apellido, $gimnasio_id);
  } else {
    $stmt2 = $cx->prepare("INSERT INTO clientes (nombre, apellido, gimnasio_id) VALUES (?, ?, ?)");
    if (!$stmt2) return 0;
    $stmt2->bind_param('ssi', $nombreSolo, $apellido, $gimnasio_id);
  }
  if (!$stmt2->execute()) { $stmt2->close(); return 0; }
  $new_id = (int)$stmt2->insert_id; $stmt2->close();
  return $new_id;
}
/* Inserta DEBE cc_movimientos si corresponde */
function cc_insert_debe(mysqli $cx, int $gimnasio_id, int $cliente_id, int $venta_id, string $fecha, string $concepto, float $monto): bool {
  if ($monto <= 0 || $gimnasio_id <= 0 || $cliente_id <= 0) return false;
  if (!table_exists($cx, 'cc_movimientos')) {
    $sql = "CREATE TABLE cc_movimientos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      gimnasio_id INT NOT NULL,
      cliente_id INT NOT NULL,
      venta_id INT NULL,
      fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      concepto VARCHAR(255) NOT NULL,
      debe DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      haber DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX (gimnasio_id, cliente_id),
      INDEX (venta_id)
    )";
    if (!$cx->query($sql)) return false;
  }
  $stmt = $cx->prepare("INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber) VALUES (?, ?, ?, ?, ?, ?, 0)");
  if (!$stmt) return false;
  $m = round($monto, 2);
  $stmt->bind_param('iiissd', $gimnasio_id, $cliente_id, $venta_id, $fecha, $concepto, $m);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/* ===== Transacción ===== */
$conexion->begin_transaction();

try {
    // 1) Insertar factura
    $tipo = "venta";
    $detalle = "Venta de " . $tipo_venta;

    $stmt = $conexion->prepare("INSERT INTO facturas (tipo, cliente_id, total, metodo_pago, detalle, gimnasio_id, fecha_pago) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) { throw new Exception("Prep factura: ".$conexion->error); }
    $stmt->bind_param("sidssis", $tipo, $cliente_id, $total_con_desc, $metodo_pago, $detalle, $gimnasio_id, $fecha);
    if (!$stmt->execute()) { throw new Exception("Exec factura: ".$stmt->error); }
    $factura_id = (int)$stmt->insert_id;
    $stmt->close();

    // 2) Insertar detalle de productos vendidos
    $stmtVenta = $conexion->prepare("
        INSERT INTO ventas_productos
        (cliente_id, producto_nombre, precio, cantidad, subtotal, total, metodo_pago, tipo_venta, fecha, gimnasio_id, factura_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmtVenta) { throw new Exception("Prep venta: ".$conexion->error); }

    // Actualizar stock solo para 'protecciones'
    $stmtStock = $conexion->prepare("UPDATE productos SET stock = stock - ? WHERE nombre = ? AND gimnasio_id = ?");

    for ($i = 0; $i < count($productos); $i++) {
        $nombre   = trim((string)$productos[$i]);
        $precio   = (float)($precios[$i] ?? 0);
        $cant     = (int)($cantidades[$i] ?? 0);
        if ($nombre === '' || $precio <= 0 || $cant <= 0) { continue; }

        $subtotal = round($precio * $cant, 2);

        // tipos: i s d i d d s s s i i  -> "isdiddsssii"
        $stmtVenta->bind_param(
            "isdiddsssii",
            $cliente_id, $nombre, $precio, $cant, $subtotal, $total_con_desc, $metodo_pago, $tipo_venta, $fecha, $gimnasio_id, $factura_id
        );
        if (!$stmtVenta->execute()) { throw new Exception("Exec venta: ".$stmtVenta->error); }

        if ($tipo_venta === 'protecciones' && $stmtStock) {
            $stmtStock->bind_param("isi", $cant, $nombre, $gimnasio_id);
            $stmtStock->execute(); // si falla, no abortamos toda la venta
        }
    }
    $stmtVenta->close();
    if ($stmtStock) { $stmtStock->close(); }

    // 3) CC → resolver cliente_id si falta y asentar DEBE
    if ($cc_final > 0.009) {
        $cliente_id_resuelto = ensure_cliente_id($conexion, $gimnasio_id, $cliente_id, $cliente_nombre);
        if ($cliente_id_resuelto > 0) {
            $concepto = "Deuda por factura #".$factura_id;
            cc_insert_debe($conexion, $gimnasio_id, $cliente_id_resuelto, /*venta_id*/ 0, $fecha, $concepto, $cc_final);
        } else {
            error_log("[CC] No se pudo resolver/crear cliente para imputar deuda. Nombre: ".$cliente_nombre);
        }
    }

    // 4) Commit
    $conexion->commit();

} catch (Throwable $e) {
    $conexion->rollback();
    http_response_code(500);
    echo "❌ Error al guardar la venta: " . htmlspecialchars($e->getMessage());
    exit;
}

/* ====== Generar y entregar PDF de factura ====== */

// Datos del gimnasio
$gym = ['nombre'=>'Gimnasio','direccion'=>'','cuit'=>'','logo'=>''];
$res = $conexion->query("SELECT nombre, direccion, cuit, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
if ($res && $res->num_rows) { $gym = $res->fetch_assoc(); }

// Cliente para PDF
$cli = [
  'nombre' => $cliente_nombre ?: 'Consumidor Final',
  'dni'    => $cliente_dni ?: null,
  'id'     => $cliente_id ?: null,
];

// Venta para PDF
$venta = [
  'id'        => $factura_id,
  'fecha'     => date('Y-m-d'),
  'hora'      => date('H:i'),
  'descuento' => $descuento,
  'total'     => $total_con_desc,
  'cc'        => $cc_final,
];

// Ítems para PDF (usamos lo que vino por POST)
$items = [];
for ($i=0; $i<count($productos); $i++){
  $nom = (string)($productos[$i] ?? '');
  $cant = (float)($cantidades[$i] ?? 0);
  $uni  = (float)($precios[$i] ?? 0);
  if ($nom==='' || $cant<=0 || $uni<=0) continue;
  $items[] = [
    'nombre'   => $nom,
    'cantidad' => $cant,
    'unitario' => $uni,
    'subtotal' => round($cant*$uni, 2),
  ];
}
if (empty($items)) {
  $items[] = ['nombre'=>'Productos', 'cantidad'=>1, 'unitario'=>$venta['total'], 'subtotal'=>$venta['total']];
}

// Pagos para PDF
$pagos = [
  'efectivo'      => $pago_efectivo,
  'transferencia' => $pago_transferencia,
  'debito'        => $pago_debito,
  'credito'       => $pago_credito,
  'vuelto'        => $vuelto,
];

// Generar y descargar
$nombre_archivo = 'factura_venta_'.$factura_id.'.pdf';
$ruta_guardar   = __DIR__.'/facturas/'.$nombre_archivo;

generar_y_entregar_factura_pdf($gym, $cli, $venta, $items, $pagos, $ruta_guardar, $nombre_archivo);
exit;
