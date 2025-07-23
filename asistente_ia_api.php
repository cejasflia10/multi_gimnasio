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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['imagen'])) {
        $archivo_tmp = $_FILES['imagen']['tmp_name'];
        $tipo = mime_content_type($archivo_tmp);
        $imagen_base64 = base64_encode(file_get_contents($archivo_tmp));

        $apiKey = 'TU_API_KEY'; // Reemplaza por tu API key real

        $json_payload = json_encode([
            "contents" => [[
                "parts" => [
                    [
                        "inlineData" => [
                            "mimeType" => $tipo,
                            "data" => $imagen_base64
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

            // Detectar calorías
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

    // Guardar comida
    if (isset($_POST['guardar']) && !empty($_POST['nombre']) && !empty($_POST['porciones']) && !empty($_POST['calorias'])) {
        $nombre = $conexion->real_escape_string($_POST['nombre']);
        $porciones = floatval($_POST['porciones']);
        $calorias = floatval($_POST['calorias']);
        $total = $porciones * $calorias;

        $conexion->query("INSERT INTO registro_comidas (cliente_id, gimnasio_id, fecha, comida, porciones, calorias, total_calorias)
            VALUES ($cliente_id, $gimnasio_id, CURDATE(), '$nombre', $porciones, $calorias, $total)");
        $mensaje_guardado = "✅ Comida registrada correctamente.";
    }
}

// Obtener total consumido hoy
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
    <title>📸 Analizar comida por foto</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>📷 Analizar comida por foto</h2>

    <form method="POST" enctype="multipart/form-data">
        <label>Seleccioná una foto de tu comida:</label><br>
        <input type="file" name="imagen" accept="image/*" required><br>
        <button type="submit">Enviar y analizar</button>
    </form>

    <?php if ($resultado): ?>
        <div class="contenedor-mensajes">
            <h4>📊 Resultado IA:</h4>
            <?= nl2br(htmlspecialchars($resultado)) ?>
        </div>

        <form method="POST">
            <input type="hidden" name="guardar" value="1">
            <input type="hidden" name="nombre" value="<?= htmlspecialchars($nombre_comida) ?>">
            <input type="hidden" name="calorias" value="<?= $calorias_detectadas ?>">
            <label><strong>🍽 Comida:</strong> <?= htmlspecialchars($nombre_comida) ?></label><br>
            <label><strong>🔥 Calorías estimadas:</strong> <?= $calorias_detectadas ?> kcal</label><br>
            <label>Porciones consumidas:</label>
            <input type="number" name="porciones" min="0.1" max="5" step="0.1" required><br>
            <button type="submit">💾 Guardar comida</button>
        </form>
    <?php elseif ($error): ?>
        <p class="alerta-mensaje"><?= $error ?></p>
    <?php endif; ?>

    <?php if ($mensaje_guardado): ?>
        <p class="alerta-mensaje" style="color:lime;"><?= $mensaje_guardado ?></p>
    <?php endif; ?>

    <hr>
    <h3>📅 Hoy</h3>
    <p><strong>🍔 Calorías consumidas:</strong> <?= $consumidas_dia ?> kcal</p>
    <p><strong>🏋️ Calorías quemadas:</strong> <?= $calorias_quemadas ?> kcal</p>
    <p><strong>⚖️ Balance:</strong> 
        <?= $balance > 200 ? 'Superávit (subida)' : ($balance < -200 ? 'Déficit (bajada)' : 'Equilibrado') ?>
    </p>

    <a href="asistente_ia_api.php" class="volver">← Volver al asistente</a>
</div>
</body>
</html>
