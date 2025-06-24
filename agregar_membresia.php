<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener ID del gimnasio logueado
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener planes del gimnasio
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");

// Obtener planes adicionales
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Nueva Membresía</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: gold;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            background: #222;
            color: white;
            border: 1px solid gold;
            border-radius: 5px;
        }
        .boton {
            margin-top: 20px;
            background-color: gold;
            color: black;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            border-radius: 5px;
        }
        .total {
            font-size: 20px;
            font-weight: bold;
            margin-top: 10px;
            color: gold;
        }
    </style>
    <script>
        function buscarCliente(str) {
            if (str.length === 0) return;
            const xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    document.getElementById("resultado_busqueda").innerHTML = xhr.responseText;
                }
            };
            xhr.open("GET", "buscar_cliente_ajax.php?q=" + encodeURIComponent(str), true);
            xhr.send();
        }

        function calcularTotal() {
            const plan = document.getElementById("plan");
            const adicionales = document.querySelectorAll(".adicional:checked");
            const otros = parseFloat(document.getElementById("otros").value) || 0;
            let total = parseFloat(plan.options[plan.selectedIndex].dataset.precio || 0);
            adicionales.forEach(a => total += parseFloat(a.dataset.precio || 0));
            total += otros;
            document.getElementById("total").innerText = "$" + total.toFixed(2);
        }
    </script>
</head>
<body>
    <h2>Registrar Nueva Membresía</h2>

    <form action="guardar_membresia.php" method="POST">
        <label>Buscar cliente:</label>
        <input type="text" onkeyup="buscarCliente(this.value)" placeholder="Escriba DNI, nombre o apellido">
        <div id="resultado_busqueda"></div>

        <label>Seleccionar cliente:</label>
        <select name="cliente_id" required>
            <option value="">Seleccione un cliente</option>
        </select>

        <label>Plan:</label>
        <select name="plan_id" id="plan" onchange="calcularTotal()" required>
            <option value="">Seleccione un plan</option>
<?php while($p = $planes->fetch_assoc()): ?>
    <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>"><?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2, ',', '.') ?></option>
<?php endwhile; ?>
        </select>

        <label>Planes adicionales:</label>
<?php while($a = $adicionales->fetch_assoc()): ?>
    <label><input type="checkbox" name="adicionales[]" class="adicional" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>" onchange="calcularTotal()"> <?= $a['nombre'] ?> ($<?= number_format($a['precio'], 2, ',', '.') ?>)</label>
<?php endwhile; ?>

        <label>Otros pagos:</label>
        <input type="number" name="otros" id="otros" placeholder="0" oninput="calcularTotal()">

        <label>Método de pago:</label>
        <select name="metodo_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="cuenta_corriente">Cuenta Corriente</option>
            <option value="tarjeta_debito">Tarjeta Débito</option>
            <option value="tarjeta_credito">Tarjeta Crédito</option>
        </select>

        <div class="total">Total a pagar: <span id="total">$0.00</span></div>

        <button type="submit" class="boton">Registrar Membresía</button>
        <a href="index.php" class="boton">Volver al menú</a>
    </form>
</body>
</html>
