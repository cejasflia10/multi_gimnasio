<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

function getMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $rango = 'DIA', $columna = 'precio_venta') {
    $filtro_fecha = ($rango === 'DIA')
        ? "DATE($campo_fecha) = CURDATE()"
        : "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())";

    $sql = "SELECT SUM($columna) AS total FROM $tabla WHERE $filtro_fecha AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($sql);
    if ($fila = $resultado->fetch_assoc()) {
        return $fila['total'] ?? 0;
    }
    return 0;
}

// Totales
$ventasDia = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventasMes = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$pagosDia = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA', 'monto');
$pagosMes = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES', 'monto');
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control - Fight Academy Scorpions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #111;
      color: #f1f1f1;
    }

    .contenido {
      margin-left: 260px;
      padding: 20px;
    }

    .tarjetas {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .tarjeta {
      background-color: #222;
      border-left: 5px solid #f7d774;
      padding: 20px;
      border-radius: 10px;
      bo
