<?php
include 'conexion.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=clientes_exportados.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Apellido', 'Nombre', 'DNI', 'Fecha Nacimiento', 'Domicilio', 'Email', 'RFID', 'Gimnasio']);

$query = "SELECT c.apellido, c.nombre, c.dni, c.fecha_nacimiento, c.domicilio, c.email, c.rfid, g.nombre AS gimnasio
          FROM clientes c
          LEFT JOIN gimnasios g ON c.gimnasio_id = g.id
          ORDER BY c.apellido ASC";

$resultado = $conexion->query($query);

while ($fila = $resultado->fetch_assoc()) {
    fputcsv($output, [
        $fila['apellido'],
        $fila['nombre'],
        $fila['dni'],
        $fila['fecha_nacimiento'],
        $fila['domicilio'],
        $fila['email'],
        $fila['rfid'],
        $fila['gimnasio']
    ]);
}

fclose($output);
exit();
?>
