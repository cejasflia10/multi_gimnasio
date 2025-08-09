<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    die("Acceso denegado.");
}

include 'menu_cliente.php';

$mensaje = "";

// Obtener planes y adicionales del gimnasio logueado
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = isset($_POST['plan_id']) ? (int)$_POST['plan_id'] : 0;
    $monto   = isset($_POST['monto']) ? (float)$_POST['monto'] : 0;
    $fecha   = date('Y-m-d H:i:s');

    // Validación básica del archivo
    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        $cod = $_FILES['comprobante']['error'] ?? -1;
        $mensaje = "❌ Error al recibir el archivo (código {$cod}).";
    } else {
        $ruta_tmp = $_FILES['comprobante']['tmp_name'];
        $archivo  = $_FILES['comprobante']['name'];
        $tam      = (int)$_FILES['comprobante']['size'];

        // (opcional) límite 10 MB
        if ($tam <= 0 || $tam > 10 * 1024 * 1024) {
            $mensaje = "❌ El archivo supera el tamaño permitido (máx. 10 MB).";
        }

        // Ruta ABSOLUTA (y relativa para BD)
        $relDir = 'multi_gimnasio/comprobantes'; // se guarda en BD/servida por web
        $absDir = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relDir;

        if (empty($mensaje) && !is_dir($absDir)) {
            if (!mkdir($absDir, 0775, true)) {
                $mensaje = "❌ No se pudo crear la carpeta de destino.";
            }
        }
        if (empty($mensaje) && !is_writable($absDir)) {
            $mensaje = "❌ La carpeta no es escribible: {$absDir}";
        }

        if (empty($mensaje)) {
            // Sanitizar nombre y evitar colisiones entre gimnasios
            $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($archivo));
            $nombre_final = 'g'.$gimnasio_id.'_'.uniqid('', true).'_'.$base;

            $ruta_publica = '/' . $relDir . '/' . $nombre_final; // ruta web absoluta
            $ruta_fisica  = $absDir  . DIRECTORY_SEPARATOR . $nombre_final; // para mover

            // Adicionales como JSON
            $adicionales_seleccionados = isset($_POST['adicionales']) && is_array($_POST['adicionales'])
                ? array_map('intval', $_POST['adicionales'])
                : [];
            $adicionales_json = json_encode($adicionales_seleccionados);

            if (!is_uploaded_file($ruta_tmp) || !move_uploaded_file($ruta_tmp, $ruta_fisica)) {
                error_log('Fallo move_uploaded_file hacia: ' . $ruta_fisica);
                $mensaje = "❌ No se pudo guardar el comprobante en el servidor.";
            } else {
                $stmt = $conexion->prepare("INSERT INTO pagos_pendientes 
                    (cliente_id, gimnasio_id, plan_id, monto, archivo_comprobante, fecha_envio, estado, adicionales) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)");
                $stmt->bind_param(
                    "iiissss", 
                    $cliente_id, 
                    $gimnasio_id, 
                    $plan_id, 
                    $monto, 
                    $ruta_publica,  // <-- guardar ruta relativa (web)
                    $fecha, 
                    $adicionales_json
                );
                if ($stmt->execute()) {
                    $mensaje = "✅ Comprobante enviado correctamente. Será validado en breve.";
                } else {
                    @unlink($ruta_fisica); // limpiar si falla BD
                    $mensaje = "❌ Error al guardar el registro en la base de datos.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>💳 Pago Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <script>
        function calcularTotal() {
            let plan = document.getElementById('plan_id');
            let monto = 0;
            if (plan && plan.value !== "0") {
                monto += parseFloat(plan.selectedOptions[0].dataset.precio || 0);
            }
            document.querySelectorAll('input[name="adicionales[]"]:checked').forEach(el => {
                monto += parseFloat(el.dataset.precio || 0);
            });
            document.getElementById('monto').value = monto.toFixed(2);
            document.getElementById('total_mostrar').innerText = "$" + monto.toFixed(2);
        }
    </script>
</head>
<body>
<div class="contenedor">
<h1>💳 Pago por Transferencia</h1>

<?php
$alias = '';
$res_alias = $conexion->query("SELECT alias FROM gimnasios WHERE id = $gimnasio_id");
if ($res_alias && $row = $res_alias->fetch_assoc()) {
    $alias = $row['alias'];
}
?>
<div style="text-align:center; color:gold; font-size:18px; margin-top: 20px;">
    💰 Alias para transferencia:<br>
    <strong style="color:white;"><?= htmlspecialchars($alias ?: 'No disponible') ?></strong>
</div>

<form method="POST" enctype="multipart/form-data" oninput="calcularTotal()">
    <label>Seleccioná un plan:</label>
    <select name="plan_id" id="plan_id">
        <option value="0" data-precio="0">-- Solo adicionales --</option>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
                <?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2, ',', '.') ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Seleccioná adicionales (opcionales):</label><br>
    <?php while ($a = $adicionales->fetch_assoc()): ?>
        <label>
            <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
            <?= $a['nombre'] ?> ($<?= number_format($a['precio'], 2, ',', '.') ?>)
        </label><br>
    <?php endwhile; ?>

    <p><strong>Total a pagar: <span id="total_mostrar">$0.00</span></strong></p>
    <input type="hidden" name="monto" id="monto" value="0">

    <label>Comprobante (imagen o PDF):</label>
    <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>

    <button type="submit">Enviar Comprobante</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>
</div>
</body>
</html>
