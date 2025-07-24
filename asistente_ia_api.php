<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = '';
$error = '';
$nombre_comida = '';
$calorias_detectadas = 0;
$mensaje_guardado = '';
$calorias_quemadas = 400;
$consumidas_dia = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imagen_base64'])) {
    $base64 = $_POST['imagen_base64'];
    $tipo = 'image/jpeg';

    $apiKey = 'TU_API_KEY'; // Reemplazá con tu API real

    $json_payload = json_encode([
        "contents" => [[
            "parts" => [
                [
                    "inlineData" => [
                        "mimeType" => $tipo,
                        "data" => str_replace('data:image/jpeg;base64,', '', $base64)
                    ]
                ],
                ["text" => "Describe esta comida e indica su valor nutricional en español. Solo menciona cuántas calorías tiene (kcal)."]
            ]
        ]]
    ]);

    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$apiKey");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($respuesta, true);
    if ($httpCode === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $resultado = $data['candidates'][0]['content']['parts'][0]['text'];

        if (preg_match('/([0-9]+)\s?k?cal/i', $resultado, $match)) {
            $calorias_detectadas = intval($match[1]);
        }

        if (preg_match('/(?<=comida|plato|es):?\s*([A-ZÁÉÍÓÚÑa-záéíóúñ ]{3,})/i', $resultado, $matchNombre)) {
            $nombre_comida = trim($matchNombre[1]);
        } else {
            $nombre_comida = 'Comida detectada';
        }
    } else {
        $error = "⚠️ No se pudo procesar la imagen. Intenta con otra foto.";
    }
}

if (isset($_POST['guardar']) && !empty($_POST['nombre']) && !empty($_POST['porciones']) && !empty($_POST['calorias'])) {
    $nombre = $conexion->real_escape_string($_POST['nombre']);
    $porciones = floatval($_POST['porciones']);
    $calorias = floatval($_POST['calorias']);
    $total = $porciones * $calorias;

    $conexion->query("INSERT INTO registro_comidas (cliente_id, gimnasio_id, fecha, comida, porciones, calorias, total_calorias)
        VALUES ($cliente_id, $gimnasio_id, CURDATE(), '$nombre', $porciones, $calorias, $total)");
    $mensaje_guardado = "✅ Comida registrada correctamente.";
}

$hoy = date('Y-m-d');
$res = $conexion->query("SELECT SUM(total_calorias) AS total FROM registro_comidas WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id AND fecha = '$hoy'");
if ($res && $fila = $res->fetch_assoc()) {
    $consumidas_dia = round($fila['total'] ?? 0);
}
$balance = $consumidas_dia - $calorias_quemadas;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📷 Escanear comida en vivo</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial;
            text-align: center;
            padding: 20px;
        }
        video, canvas {
            max-width: 90%;
            border: 2px solid gold;
            margin-bottom: 10px;
        }
        button {
            padding: 10px 20px;
            font-size: 18px;
            background: gold;
            color: black;
            border: none;
            cursor: pointer;
        }
        .alerta-mensaje {
            color: red;
        }
    </style>
</head>
<body>

<h2>📷 Escanear comida desde cámara</h2>

<video id="video" autoplay playsinline></video>
<br>
<button onclick="capturar()">📸 Tomar Foto</button>

<form method="POST" id="formulario" style="display:none;">
    <input type="hidden" name="imagen_base64" id="imagen_base64">
    <button type="submit">Enviar imagen</button>
</form>

<canvas id="canvas" style="display:none;"></canvas>

<?php if ($resultado): ?>
    <h3>📊 Resultado:</h3>
    <p><?= nl2br(htmlspecialchars($resultado)) ?></p>
    <form method="POST">
        <input type="hidden" name="guardar" value="1">
        <input type="hidden" name="nombre" value="<?= htmlspecialchars($nombre_comida) ?>">
        <input type="hidden" name="calorias" value="<?= $calorias_detectadas ?>">
        <label><strong>🍽 Comida:</strong> <?= htmlspecialchars($nombre_comida) ?></label><br>
        <label><strong>🔥 Calorías:</strong> <?= $calorias_detectadas ?> kcal</label><br>
        <label>Porciones:</label>
        <input type="number" name="porciones" min="0.1" step="0.1" required><br>
        <button type="submit">💾 Guardar comida</button>
    </form>
<?php elseif ($error): ?>
    <p class="alerta-mensaje"><?= $error ?></p>
<?php endif; ?>

<?php if ($mensaje_guardado): ?>
    <p style="color:lime;"><?= $mensaje_guardado ?></p>
<?php endif; ?>

<hr>
<p><strong>🍔 Calorías consumidas hoy:</strong> <?= $consumidas_dia ?> kcal</p>
<p><strong>🏋️ Calorías quemadas:</strong> <?= $calorias_quemadas ?> kcal</p>
<p><strong>⚖️ Balance:</strong> <?= $balance > 200 ? 'Subida' : ($balance < -200 ? 'Bajada' : 'Equilibrado') ?></p>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const imagen_base64 = document.getElementById('imagen_base64');
    const formulario = document.getElementById('formulario');

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
        .then(stream => { video.srcObject = stream; })
        .catch(err => { alert("No se pudo acceder a la cámara: " + err); });

    function capturar() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const base64 = canvas.toDataURL('image/jpeg', 0.8);
        imagen_base64.value = base64;
        formulario.style.display = 'block';
    }
</script>

</body>
</html>
