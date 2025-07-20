<?php
session_start();
include 'conexion.php';
include 'menu_eventos.php';

$evento_id = $_GET['evento_id'] ?? 0;
$evento = $conexion->query("SELECT * FROM eventos_deportivos WHERE id = $evento_id")->fetch_assoc();

if (!$evento) {
    echo "<h2 style='color: red;'>❌ Evento no encontrado.</h2>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🎟️ Comprar Entrada</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .contenedor {
            max-width: 600px;
            margin: 0 auto;
            background: #111;
            padding: 20px;
            border-radius: 10px;
            color: gold;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
        }
        select, input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 5px;
            border: none;
        }
        .precio {
            margin-top: 10px;
            font-size: 18px;
            color: lightgreen;
        }
        button {
            margin-top: 20px;
            padding: 10px 20px;
            background: gold;
            border: none;
            color: black;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🎫 Comprar Entrada - <?= htmlspecialchars($evento['titulo']) ?></h2>
    <p><strong>📅 Fecha:</strong> <?= date('d/m/Y', strtotime($evento['fecha'])) ?> - ⏰ <?= substr($evento['hora'], 0, 5) ?></p>
    <p><strong>📍 Lugar:</strong> <?= htmlspecialchars($evento['lugar']) ?></p>

    <form method="POST" action="guardar_venta_entrada.php">
        <input type="hidden" name="evento_id" value="<?= $evento['id'] ?>">

        <label>Nombre del Comprador:</label>
        <input type="text" name="nombre" required>

        <label>Tipo de Entrada:</label>
        <select name="tipo_entrada" onchange="actualizarPrecio(this.value)" required>
            <option value="">-- Selecciona --</option>
            <option value="general">General - $3000</option>
            <option value="vip">VIP - $6000</option>
            <option value="ringside">Ringside - $10000</option>
        </select>

        <div class="precio" id="precio"></div>

        <label>Cantidad:</label>
        <input type="number" name="cantidad" value="1" min="1" required>

        <label>Método de Pago:</label>
        <select name="metodo_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta">Tarjeta</option>
        </select>

        <button type="submit">✅ Confirmar Compra</button>
    </form>
</div>

<script>
function actualizarPrecio(tipo) {
    const precios = {
        general: 3000,
        vip: 6000,
        ringside: 10000
    };
    const precio = precios[tipo] || 0;
    document.getElementById('precio').innerText = precio ? "💰 Precio: $" + precio : "";
}
</script>

</body>
</html>
