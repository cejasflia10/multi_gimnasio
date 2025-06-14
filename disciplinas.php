<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso denegado');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$mensaje = "";

// Agregar nueva disciplina
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $nombre = $_POST['nombre'];
    $stmt = $conexion->prepare("INSERT INTO disciplinas (nombre, id_gimnasio) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $id_gimnasio);
    $stmt->execute();
    $mensaje = "Disciplina agregada correctamente.";
}

// Eliminar disciplina
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $conexion->prepare("DELETE FROM disciplinas WHERE id = ? AND id_gimnasio = ?");
    $stmt->bind_param("ii", $id, $id_gimnasio);
    $stmt->execute();
    $mensaje = "Disciplina eliminada.";
}

// Obtener disciplinas
$stmt = $conexion->prepare("SELECT * FROM disciplinas WHERE id_gimnasio = ?");
$stmt->bind_param("i", $id_gimnasio);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<?php include 'menu.php'; ?>
<div style="margin-left:240px; padding:20px; color:#fff;">
    <h2>Gestión de Disciplinas</h2>
    <?php if ($mensaje): ?>
        <p style="color:lime;"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    <form method="POST">
        <input type="text" name="nombre" placeholder="Nombre de la disciplina" required>
        <button type="submit">Agregar</button>
    </form>
    <br>
    <table border="1" cellpadding="10" style="width:60%; text-align:center;">
        <tr><th>Disciplina</th><th>Acción</th></tr>
        <?php while($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['nombre']; ?></td>
            <td><a href="?eliminar=<?php echo $row['id']; ?>" style="color:red;">Eliminar</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>