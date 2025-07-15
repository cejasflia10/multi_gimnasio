<?php
session_start();
include 'conexion.php';
require('fpdf/fpdf.php'); // Asegurate que esté en la carpeta "fpdf/"

$cliente_id = intval($_POST['cliente_id']);
$producto_id = intval($_POST['producto_id']);
$cantidad = intval($_POST['cantidad']);
$metodo_pago = trim($_POST['metodo_pago']);
$fecha_venta = date("Y-m-d");
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Verificar datos obligatorios
if ($cliente_id == 0 || $producto_id == 0 || $gimnasio_id == 0) {
    exit("❌ Faltan datos obligatorios para registrar la venta.");
}

// Buscar precio y nombre del producto desde cualquiera de las tablas
$query = "
    SELECT nombre, precio_venta FROM productos_proteccion WHERE id = $producto_id
    UNION
    SELECT nombre, precio_venta FROM productos_indumentaria WHERE id = $producto_id
    UNION
    SELECT nombre, precio_venta FROM productos_suplemento WHERE id = $producto_id
";
$result = $conexion->query($query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $producto_nombre = $row['nombre'];
    $precio_unitario = floatval($row['precio_venta']);
    $total = $precio_unitario * $cantidad;

    // Registrar venta
    $sql_venta = "INSERT INTO ventas_productos 
        (cliente_id, producto_id, cantidad, total, metodo_pago, fecha_venta, gimnasio_id)
        VALUES ($cliente_id, $producto_id, $cantidad, $total, '$metodo_pago', '$fecha_venta', $gimnasio_id)";

    if ($conexion->query($sql_venta)) {
        // Registrar en facturas
        $conexion->query("INSERT INTO facturas 
            (tipo, cliente_id, total, metodo_pago, detalle, gimnasio_id) 
            VALUES ('venta', $cliente_id, $total, '$metodo_pago', 'Venta de productos', $gimnasio_id)");

        // Obtener datos del cliente
        $res_cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id");
        $cliente = $res_cliente->fetch_assoc();

        // Obtener nombre del gimnasio
        $res_gym = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
        $gimnasio = $res_gym->fetch_assoc();
        $nombre_gimnasio = $gimnasio['nombre'] ?? 'Gimnasio';

        // Generar PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $nombre_gimnasio, 0, 1, 'C');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Factura de Venta - ' . $fecha_venta, 0, 1);
        $pdf->Ln(5);
        $pdf->Cell(0, 10, 'Cliente: ' . $cliente['apellido'] . ', ' . $cliente['nombre'], 0, 1);
        $pdf->Ln(5);

        $pdf->Cell(100, 10, 'Producto', 1);
        $pdf->Cell(30, 10, 'Cantidad', 1);
        $pdf->Cell(30, 10, 'Unitario', 1);
        $pdf->Cell(30, 10, 'Total', 1);
        $pdf->Ln();

        $pdf->Cell(100, 10, $producto_nombre, 1);
        $pdf->Cell(30, 10, $cantidad, 1);
        $pdf->Cell(30, 10, '$' . number_format($precio_unitario, 2), 1);
        $pdf->Cell(30, 10, '$' . number_format($total, 2), 1);
        $pdf->Ln(20);

        $pdf->Cell(0, 10, 'Método de pago: ' . $metodo_pago, 0, 1);
        $pdf->Output('I', 'Factura_venta_' . $cliente_id . '.pdf'); // Inline

    } else {
        echo "❌ Error al registrar la venta: " . $conexion->error;
    }
} else {
    echo "❌ Producto no encontrado.";
}
