<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id_dia = $_POST['id_dia'];
$id_horario = $_POST['id_horario'];
$id_profesor = $_POST['id_profesor'];
$cupo_maximo = $_POST['cupo_maximo'];
$gimnasio_id = $_SESSION['gimnasio_id'];

// Crear tabla turnos si aún no existe
$conexion->query("CREATE TABLE IF NOT EXISTS turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_dia INT,
    id_horario INT,
    id_profesor INT,
    cupo_maximo INT,
    gimnasio_id INT,
    FOREIGN KEY (id_dia) REFERENCES dias(id),
    FOREIGN KEY (id_horario) REFERENCES horarios(id),
    FOREIGN KEY (id_profesor) REFERENCES profesores(id),
    FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id)
)");

$stmt = $conexion->prepare("INSERT INTO turnos (id_dia, id_horario, id_profesor, cupo_maximo, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiii", $id_dia, $id_horario, $id_profesor, $cupo_maximo, $gimnasio_id);
$stmt->execute();

header("Location: ver_turnos.php");
exit;
?>
