<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'];

if (!isset($_GET['id'])) {
    die("ID de disciplina no especificado.");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM disciplinas WHERE id = $id AND id_gimnasio = $gimnasio_id";
$resultado = $conexion->query($query);

if ($resultado->num_rows === 0) {
    die("Disciplina no encontrada o no pertenece a este gimnasio.");
}

$disciplina = $resultado->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    $stmt = $conexion->prepare("UPDATE disciplinas SET nombre = ?, descripcion = ? WHERE id = ? AND id_gimnasio = ?");
    $stmt->bind_param("ssii", $nombre, $descripcion, $id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Disciplina actualizada.'); window.location.href='disciplinas.php';</script>";
    } else {
        echo "<script>alert('Error al actualizar.');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px gold;
        }
        h2 {
            text-align: center;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background: #333;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background: gold;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>
<div class="container">
    <h2>Editar Disciplina</h2>
    <form method="POST">
        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" value="<?= htmlspecialchars($disciplina['nombre']) ?>" required>

        <label for="descripcion">Descripción:</label>
        <textarea name="descripcion" id="descripcion" rows="4"><?= htmlspecialchars($disciplina['descripcion']) ?></textarea>

        <button type="submit">Guardar Cambios</button>
    </form>
</div>
</body>
</html>
