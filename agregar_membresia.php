<?php
include 'conexion.php';

$planes = $conexion->query("SELECT id, nombre, precio FROM planes");
$adicionales = $conexion->query("SELECT id, nombre, precio FROM planes_adicionales");
$clientes = $conexion->query("SELECT id, dni, rfid, nombre, apellido FROM clientes");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $adicionales_seleccionados = $_POST['adicionales'] ?? [];
    $metodo_pago = $_POST['metodo_pago'];
    $fecha_inicio = date('Y-m-d');
    $fecha_vencimiento = date('Y-m-d', strtotime('+30 days'));

    // Obtener precio del plan
    $res_plan = $conexion->query("SELECT precio FROM planes WHERE id = $plan_id");
    $plan = $res_plan->fetch_assoc();
    $total = $plan['precio'];

    // Sumar adicionales
    foreach ($adicionales_seleccionados as $aid) {
        $res = $conexion->query("SELECT precio FROM planes_adicionales WHERE id = $aid");
        if ($fila = $res->fetch_assoc()) {
            $total += $fila['precio'];
        }
    }

    // Si es cuenta corriente, monto negativo
    $total_final = ($metodo_pago === 'cuenta corriente') ? -$total : $total;

    $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, metodo_pago, monto_pagado) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssd", $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $metodo_pago, $total_final);
    $stmt->execute();

    $membresia_id = $stmt->insert_id;

    foreach ($adicionales_seleccionados as $adicional_id) {
        $conexion->query("INSERT INTO membresias_adicionales (membresia_id, adicional_id) VALUES ($membresia_id, $adicional_id)");
    }

    header("Location: ver_membresias.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: none; }
        .btn { margin-top: 15px; padding: 10px 20px; background: #ffc107; color: #111; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #e0a800; }
        #total { font-weight: bold; margin-top: 10px; font-size: 18px; }
    </style>
    <script>
    function filtrarClientes() {
        let texto = document.getElementById("buscar_cliente").value.toLowerCase();
        let opciones = document.getElementById("cliente_id").options;
        for (let i = 0; i < opciones.length; i++) {
            let opt = opciones[i];
            let val = opt.text.toLowerCase();
            opt.style.display = val.includes(texto) ? "" : "none";
        }
    }

    function calcularTotal() {
        let plan = document.getElementById("plan_id");
        let precioPlan = parseFloat(plan.selectedOptions[0]?.getAttribute("data-precio") || 0);
        let adicionales = document.querySelectorAll("input[name='adicionales[]']:checked");
        let sumaAdicionales = 0;
        adicionales.forEach(a => {
            sumaAdicionales += parseFloat(a.getAttribute("data-precio") || 0);
        });
        let total = precioPlan + sumaAdicionales;
        document.getElementById("total").innerText = "Monto total a pagar: $" + total.toFixed(2);
    }
    </script>
</head>
<body onload="calcularTotal()">
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Agregar Membresía</h1>
    <form method="POST">
        <label>Buscar cliente (DNI / RFID / nombre / apellido):</label>
        <input type="text" id="buscar_cliente" onkeyup="filtrarClientes(); seleccionarClienteAuto();" placeholder="Buscar...">

        <label>Datos del cliente:</label>
        <select name="cliente_id" id="cliente_id" required>
            <option value="">-- Seleccionar cliente --</option>
            <?php while ($c = $clientes->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>">
                    <?= $c['apellido'] ?> <?= $c['nombre'] ?> (DNI: <?= $c['dni'] ?> - RFID: <?= $c['rfid'] ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <label>Plan:</label>
        <select name="plan_id" id="plan_id" onchange="calcularTotal()" required>
            <option value="">-- Seleccionar plan --</option>
            <?php while ($p = $planes->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
                    <?= $p['nombre'] ?> ($<?= $p['precio'] ?>)
                </option>
            <?php endwhile; ?>
        </select>

        <label>Planes adicionales:</label>
        <?php while ($a = $adicionales->fetch_assoc()): ?>
            <label>
                <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>" onchange="calcularTotal()">
                <?= $a['nombre'] ?> ($<?= $a['precio'] ?>)
            </label>
        <?php endwhile; ?>

        <label>Método de pago:</label>
        <select name="metodo_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta">Tarjeta</option>
            <option value="cuenta corriente">Cuenta corriente</option>
        </select>

        <div id="total">Monto total a pagar: $0.00</div>
        <button type="submit" class="btn">Guardar Membresía</button>
    </form>
</div>
</body>
</html>


<script>
function seleccionarClienteAuto() {
    let texto = document.getElementById("buscar_cliente").value.toLowerCase();
    let opciones = document.getElementById("cliente_id").options;
    for (let i = 0; i < opciones.length; i++) {
        let opt = opciones[i];
        let val = opt.text.toLowerCase();
        if (val.includes(texto)) {
            opt.selected = true;
            break;
        }
    }
}
</script>
