<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date('Y-m-d');

// ---------- ASISTENCIA DE CLIENTES ----------
$consulta_clientes = "
    SELECT c.apellido, c.nombre, a.hora, a.fecha
    FROM asistencias a
    INNER JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = '$hoy' AND a.id_gimnasio = $gimnasio_id
    ORDER BY a.hora DESC
";

$resultado_clientes = $conexion->query($consulta_clientes);

// ---------- ASISTENCIA DE PROFESORES ----------
$consulta_profesores = "
    SELECT p.apellido, p.nombre, r.hora_ingreso AS ingreso, r.hora_egreso AS egreso
    FROM rfid_profesores_registros r
    INNER JOIN profesores p ON r.profesor_id = p.id
    WHERE r.fecha = '$hoy' AND r.gimnasio_id = $gimnasio_id
    ORDER BY r.hora_ingreso DESC
";

$resultado_profesores = $conexion->query($consulta_profesores);
?>

<!-- ESTILOS -->
<style>
    .seccion {
        margin-bottom: 30px;
    }

    .seccion h3 {
        color: gold;
        font-size: 1.3rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }

    .seccion h3 i {
        margin-right: 8px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background-color: #1a1a1a;
        color: #f1f1f1;
    }

    th, td {
        padding: 8px 10px;
        border: 1px solid #333;
        text-align: center;
    }

    th {
        background-color: #333;
        color: gold;
    }

    tr:nth-child(even) {
        background-color: #222;
    }

    @media (max-width: 768px) {
        table, thead, tbody, th, td, tr {
            display: block;
        }

        thead tr {
            display: none;
        }

        td {
            position: relative;
            padding-left: 50%;
        }

        td::before {
            position: absolute;
            top: 0;
            left: 10px;
            width: 45%;
            white-space: nowrap;
            font-weight: bold;
            color: gold;
        }

        td:nth-child(1)::before { content: "Hora"; }
        td:nth-child(2)::before { content: "Nombre"; }
        td:nth-child(3)::before { content: "Ingreso"; }
        td:nth-child(4)::before { content: "Egreso"; }
    }
</style>

<!-- ASISTENCIAS CLIENTES -->
<div class="seccion">
    <h3><i class="fas fa-users"></i> Clientes</h3>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Nombre</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado_clientes->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['hora'] ?></td>
                    <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- ASISTENCIAS PROFESORES -->
<div class="seccion">
    <h3><i class="fas fa-chalkboard-teacher"></i> Profesores</h3>
    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Ingreso</th>
                <th>Egreso</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado_profesores->fetch_assoc()) { ?>
                <tr>
                    <td><?= $fila['apellido']
