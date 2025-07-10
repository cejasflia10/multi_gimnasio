<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

if (!isset($_SESSION['profesor_id']) || !isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// Obtener inscripciones
$query = $conexion->query("
    SELECT c.apellido, c.nombre, c.dni, c.fecha_nacimiento,
           TIMESTAMPDIFF(YEAR, c.fecha_nacimiento, CURDATE()) AS edad,
           c.domicilio, c.email,
           cmp.disciplina, cmp.division, cmp.peso, cmp.peleas, cmp.observaciones, cmp.fecha_registro
    FROM competidores cmp
    JOIN clientes c ON cmp.cliente_id = c.id
    WHERE cmp.gimnasio_id = $gimnasio_id
    ORDER BY cmp.fecha_registro DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Competidores</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
<h2>🏆 Listado de Competidores Registrados</h2>

<div class="centrado">
    <a href="exportar_competidores_excel.php" class="boton-descarga">📥 Exportar a Excel</a>
</div>

<table>
    <thead>
        <tr>
            <th>Apellido y Nombre</th>
            <th>DNI</th>
            <th>Edad</th>
            <th>Fecha Nac.</th>
            <th>Domicilio</th>
            <th>Email</th>
            <th>Disciplina</th>
            <th>División</th>
            <th>Peso (kg)</th>
            <th>Peleas</th>
            <th>Observaciones</th>
            <th>Fecha Registro</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $query->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] . ' ' . $row['nombre'] ?></td>
                <td><?= $row['dni'] ?></td>
                <td><?= $row['edad'] ?></td>
                <td><?= $row['fecha_nacimiento'] ?></td>
                <td><?= $row['domicilio'] ?></td>
                <td><?= $row['email'] ?></td>
                <td><?= $row['disciplina'] ?></td>
                <td><?= $row['division'] ?></td>
                <td><?= $row['peso'] ?></td>
                <td><?= $row['peleas'] ?></td>
                <td><?= $row['observaciones'] ?></td>
                <td><?= $row['fecha_registro'] ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>
</body>
</html>
