<?php
include 'conexion.php';

$query = mysqli_real_escape_string($conexion, $_GET['query']);

$consulta = "SELECT c.*, d.nombre AS disciplina, d.id AS disciplina_id 
             FROM clientes c 
             LEFT JOIN disciplinas d ON c.disciplina_id = d.id 
             WHERE c.dni LIKE '%$query%' OR c.apellido LIKE '%$query%' OR c.rfid_uid LIKE '%$query%' 
             LIMIT 1";

$resultado = mysqli_query($conexion, $consulta);
$cliente = mysqli_fetch_assoc($resultado);

echo json_encode($cliente);
?>
