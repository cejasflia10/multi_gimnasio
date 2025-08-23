<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/factura_pdf.php'; // versión sin utf8_decode
date_default_timezone_set('America/Argentina/Buenos_Aires');

/* ===== Inputs ===== */
$cliente_nombre   = trim((string)($_POST['cliente_nombre'] ?? 'Cliente temporal'));
$cliente_temporal = (int)($_POST['cliente_temporal'] ?? 0);
$cliente_id       = (int)($_POST['cliente_id'] ?? 0); // si no viene, intentamos resolver/crear
$descuento        = (float)($_POST['descuento'] ?? 0);
$total_original   = (float)($_POST['total_original'] ?? 0);
$total_final      = (float)($_POST['total_con_descuento'] ?? 0);
$fecha            = date("Y-m-d");
$hora             = date("H:i");
$gimnasio_id      = (int)($_SESSION['gimnasio_id'] ?? 0);
$gimnasio_nombre  = (string)($_SESSION['gimnasio_nombre'] ?? 'Mi Gimnasio');

/* Métodos de pago */
$pago_efectivo      = (float)($_POST['pago_efectivo'] ?? 0);
$pago_transferencia = (float)($_POST['pago_transferencia'] ?? 0);
$pago_debito        = (float)($_POST['pago_debito'] ?? 0);
$pago_credito       = (float)($_POST['pago_credito'] ?? 0);
$pago_cc_post       = (float)($_POST['pago_cuenta_corriente'] ?? 0);
$vuelto             = (float)($_POST['vuelto'] ?? 0);

/* Ítems (si no vienen, se arma uno genérico para el PDF) */
$productos  = $_POST['producto_nombre'] ?? [];
$precios    = $_POST['precio'] ?? [];
$cantidades = $_POST['cantidad'] ?? [];

/* ===== Validaciones ===== */
if ($gimnasio_id <= 0) { http_response_code(403); exit("❌ Gimnasio inválido."); }
if ($total_final <= 0) { exit("❌ Total inválido."); }

/* ===== Helpers mínimos ===== */
function table_exists(mysqli $cx, string $t): bool {
  $rs = $cx->query("SHOW TABLES LIKE '".$cx->real_escape_string($t)."'");
  return $rs && $rs->num_rows > 0;
}
function column_exists(mysqli $cx, string $t, string $c): bool {
  $rs = $cx->query("SHOW COLUMNS FROM `".$cx->real_escape_string($t)."` LIKE '".$cx->real_escape_string($c)."'");
  return $rs && $rs->num_rows > 0;
}
/* Buscar o crear cliente si falta ID */
function ensure_cliente_id(mysqli $cx, int $gimnasio_id, int $cliente_id, string $cliente_nombre): int {
  if ($cliente_id > 0) return $cliente_id;
  $nombre = trim($cliente_nombre);
  if ($nombre === '' || $gimnasio_id <= 0) return 0;

  $q = $cx->prepare("SELECT id FROM clientes WHERE gimnasio_id=? AND (CONCAT(TRIM(apellido),' ',TRIM(nombre))=? OR nombre=?) LIMIT 2");
  $q->bind_param('iss', $gimnasio_id, $nombre, $nombre);
  $q->execute(); $rs = $q->get_result(); $q->close();
  if ($rs && $rs->num_rows === 1) { $row = $rs->fetch_assoc(); return (int)$row['id']; }

  // crear temporal
  $parts = preg_split('/\s+/', $nombre);
  $ape = ''; $nom = $nombre;
  if (count($parts) >= 2) { $ape = array_shift($parts); $nom = implode(' ', $parts); }
  if (column_exists($cx,'clientes','temporal')) {
    $ins = $cx->prepare("INSERT INTO clientes (nombre, apellido, gimnasio_id, temporal) VALUES (?, ?, ?, 1)");
  } else {
    $ins = $cx->prepare("INSERT INTO clientes (nombre, apellido, gimnasio_id) VALUES (?, ?, ?)");
  }
  if (!$ins) return 0;
  $ins->bind_param('ssi', $nom, $ape, $gimnasio_id);
  if (!$ins->execute()) { $ins->close(); return 0; }
  $nid = (int)$ins->insert_id; $ins->close();
  return $nid;
}
/* Insertar DEBE en cc_movimientos */
function cc_insert_debe(mysqli $cx, int $gimnasio_id, int $cliente_id, int $venta_id, string $concepto, float $monto): bool {
  if ($monto <= 0 || $gimnasio_id <= 0 || $cliente_id <= 0) return false;
  if (!table_exists($cx, 'cc_movimientos')) {
    $cx->query("CREATE TABLE cc_movimientos (
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
    )");
  }
  $stmt = $cx->prepare("INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber) VALUES (?, ?, ?, NOW(), ?, ?, 0)");
  if (!$stmt) return false;
  $m = round($monto,2);
  $stmt->bind_param('iiisd', $gimnasio_id, $cliente_id, $venta_id, $concepto, $m);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/* ===== CC en servidor ===== */
$pagado_ahora = max(0, round($pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito, 2));
$cc_calc      = max(0, round($total_final - $pagado_ahora, 2));
$pago_cc_seguro = ($pago_cc_post > 0) ? min(round($pago_cc_post,2), round($total_final,2)) : 0;
$pago_cuenta_corriente = (abs($pago_cc_seguro - $cc_calc) > 0.01) ? $cc_calc : $pago_cc_seguro;

/* ===== Transacción: venta + CC ===== */
$conexion->begin_transaction();
try {
  // 1) Guardar venta (tu tabla resumen)
  $stmt = $conexion->prepare("
    INSERT INTO ventas_productos
      (cliente_nombre, cliente_temporal, descuento, total, fecha, hora,
       efectivo, transferencia, debito, credito, cuenta_corriente, gimnasio_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) { throw new Exception("Prep ventas_productos: ".$conexion->error); }
  // tipos: s i d d s s d d d d d i => "siddssdddddi"
  $stmt->bind_param(
    "siddssdddddi",
    $cliente_nombre, $cliente_temporal, $descuento, $total_final, $fecha, $hora,
    $pago_efectivo, $pago_transferencia, $pago_debito, $pago_credito, $pago_cuenta_corriente, $gimnasio_id
  );
  if (!$stmt->execute()) { throw new Exception("Exec ventas_productos: ".$stmt->error); }
  $venta_id = (int)$stmt->insert_id;
  $stmt->close();

  // 2) Si hay CC, asegurar cliente y asentar DEBE
  if ($pago_cuenta_corriente > 0.009) {
    $cliente_id_ok = ensure_cliente_id($conexion, $gimnasio_id, $cliente_id, $cliente_nombre);
    if ($cliente_id_ok > 0) {
      $concepto = "Deuda por venta #".$venta_id;
      cc_insert_debe($conexion, $gimnasio_id, $cliente_id_ok, $venta_id, $concepto, $pago_cuenta_corriente);
      $cliente_id = $cliente_id_ok; // para el PDF
    } else {
      error_log("[CC] No se pudo resolver/crear cliente para imputar deuda. Venta #{$venta_id} Nombre: ".$cliente_nombre);
    }
  }

  $conexion->commit();

} catch (Throwable $e) {
  $conexion->rollback();
  http_response_code(500);
  exit("❌ Error al guardar: ".htmlspecialchars($e->getMessage()));
}

/* ===== PDF con factura_pdf.php ===== */
/* Datos del gimnasio */
$gym = ['nombre'=>$gimnasio_nombre,'direccion'=>'','cuit'=>'','logo'=>''];
$resGym = $conexion->query("SELECT nombre, direccion, cuit, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
if ($resGym && $resGym->num_rows) { $gym = $resGym->fetch_assoc(); }

/* Cliente */
$cli = [
  'nombre' => $cliente_nombre ?: 'Consumidor Final',
  'dni'    => null,
  'id'     => $cliente_id ?: null,
];

/* Venta */
$venta = [
  'id'        => $venta_id,
  'fecha'     => date('Y-m-d'),
  'hora'      => date('H:i'),
  'descuento' => $descuento,
  'total'     => $total_final,
  'cc'        => $pago_cuenta_corriente,
];

/* Ítems para PDF */
$items = [];
if (is_array($productos) && count($productos)) {
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
}
if (empty($items)) {
  $items[] = ['nombre'=>'Productos', 'cantidad'=>1, 'unitario'=>$venta['total'], 'subtotal'=>$venta['total']];
}

/* Pagos para PDF */
$pagos = [
  'efectivo'      => $pago_efectivo,
  'transferencia' => $pago_transferencia,
  'debito'        => $pago_debito,
  'credito'       => $pago_credito,
  'vuelto'        => $vuelto,
];

/* Generar y descargar PDF */
$nombre_archivo = 'factura_venta_'.$venta_id.'.pdf';
$ruta_guardar   = __DIR__.'/facturas/'.$nombre_archivo;
generar_y_entregar_factura_pdf($gym, $cli, $venta, $items, $pagos, $ruta_guardar, $nombre_archivo);
exit;
