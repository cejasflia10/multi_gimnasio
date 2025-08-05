<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enlace = trim($_POST['enlace_whatsapp']);
    $stmt = $conexion->prepare("
        INSERT INTO links_gimnasio (gimnasio_id, enlace_whatsapp) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE enlace_whatsapp = VALUES(enlace_whatsapp)
    ");
    $stmt->bind_param("is", $gimnasio_id, $enlace);
    $stmt->execute();
    echo "<p style='color: green;'>✅ Enlace guardado correctamente</p>";
}

// Obtener enlace actual
$res = $conexion->query("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = $gimnasio_id");
$actual = $res->fetch_assoc()['enlace_whatsapp'] ?? "";
?>

<h2>🔗 Configurar enlace de WhatsApp</h2>
<form method="post">
    <label>Enlace del grupo:</label><br>
    <input type="url" name="enlace_whatsapp" value="<?= htmlspecialchars($actual) ?>" placeholder="https://chat.whatsapp.com/..." style="width: 100%;" required>
    <br><br>
    <button type="submit">💾 Guardar enlace</button>
</form>
