
<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"] ?? '');
    if ($dni === '') {
        echo json_encode(["success" => false, "message" => "No se recibió el DNI."]);
        exit;
    }

    $sql = "SELECT id, nombre, apellido FROM clientes WHERE dni = ? OR rfid = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $dni, $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $id_cliente = $cliente["id"];

        $sql_membresia = "SELECT id, clases_disponibles, fecha_fin FROM membresias WHERE cliente_id = ? AND fecha_fin >= CURDATE() ORDER BY fecha_fin DESC LIMIT 1";
        $stmt_m = $conexion->prepare($sql_membresia);
        $stmt_m->bind_param("i", $id_cliente);
        $stmt_m->execute();
        $res_m = $stmt_m->get_result();

        if ($res_m->num_rows > 0) {
            $membresia = $res_m->fetch_assoc();
            $clases = (int)$membresia["clases_disponibles"];

            if ($clases > 0) {
                $sql_insert = "INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, CURDATE(), CURTIME())";
                $stmt_ins = $conexion->prepare($sql_insert);
                $stmt_ins->bind_param("i", $id_cliente);
                $stmt_ins->execute();

                $sql_update = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = ?";
                $stmt_upd = $conexion->prepare($sql_update);
                $stmt_upd->bind_param("i", $membresia["id"]);
                $stmt_upd->execute();

                echo json_encode(["success" => true, "nombre" => $cliente["nombre"], "apellido" => $cliente["apellido"], "clases_restantes" => $clases - 1, "vencimiento" => $membresia["fecha_fin"]]);
            } else {
                echo json_encode(["success" => false, "message" => "No tiene clases disponibles."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "No tiene membresía activa."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Cliente no encontrado."]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR para Ingreso</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background-color: #000; color: gold; font-family: sans-serif; text-align: center; }
        #reader { width: 300px; margin: auto; }
    </style>
</head>
<body>
    <h1>Escaneo QR para Ingreso</h1>
    <div id="reader"></div>
    <div id="resultado"></div>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.clear();

            fetch("registrar_asistencia_qr.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "dni=" + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                const res = document.getElementById("resultado");
                if (data.success) {
                    res.innerHTML = `<h2>✅ ${data.nombre} ${data.apellido}</h2><p>Clases restantes: ${data.clases_restantes}</p><p>Vence: ${data.vencimiento}</p>`;
                } else {
                    res.innerHTML = `<h2>⚠️ ${data.message}</h2>`;
                }
                setTimeout(() => location.reload(), 4000);
            });
        }

        const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>
