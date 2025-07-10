<?php
session_start();
include 'conexion.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=competidores_" . date('Ymd_His') . ".xls");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("
    SELECT c.apellido, c.nombre, c.dni, c.fecha_nacimiento,
           TIMESTAMPDIFF(YEAR, c.fecha_nacimiento, CURDATE()) AS edad,
           c.domicilio, c.email,
           cmp.disciplina, cmp.division, cmp.peso, cmp.peleas, cmp.observaciones, cmp.fecha_registro
    FROM competidores cmp
    JOIN clientes c ON cmp.cliente_id = c.id
    WHERE cmp.gimnasio_id = $gimnasio_id
");

echo "<table border='1'>";
echo "<tr>
        <th>Apellido y Nombre</th><th>DNI</th><th>Edad</th><th>Fecha Nac.</th><th>Domicilio</th><th>Email</th>
        <th>Disciplina</th><th>División</th><th>Peso</th><th>Peleas</th><th>Observaciones</th><th>Fecha Registro</th>
      </tr>";

while ($row = $resultado->fetch_assoc()) {
    echo "<tr>
            <td>{$row['apellido']} {$row['nombre']}</td>
            <td>{$row['dni']}</td>
            <td>{$row['edad']}</td>
            <td>{$row['fecha_nacimiento']}</td>
            <td>{$row['domicilio']}</td>
            <td>{$row['email']}</td>
            <td>{$row['disciplina']}</td>
            <td>{$row['division']}</td>
            <td>{$row['peso']}</td>
            <td>{$row['peleas']}</td>
            <td>{$row['observaciones']}</td>
            <td>{$row['fecha_registro']}</td>
          </tr>";
}

echo "</table>";
?>
