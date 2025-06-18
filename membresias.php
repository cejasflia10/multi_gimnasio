<?php
include 'menu.php';
include 'conexion.php';

$gimnasio_id = $_SESSION["gimnasio_id"] ?? 0;

$resultado = $conexion->prepare("SELECT clientes.apellido, clientes.nombre, clientes.disciplina, membresias.plan, membresias.fecha_inicio, membresias.fecha_vencimiento, membresias.metodo_pago FROM membresias JOIN clientes ON membresias.cliente_id = clientes.id WHERE clientes.gimnasio_id = ?");
$resultado->bind_param("i", $gimnasio_id);
$resultado->execute();
$resultado = $resultado->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h2 {
            text-align: center;
            margin-top: 20px;
        }
        table {
            width: 95%;
            margin: auto;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        input[type="text"] {
            width: 95%;
            margin: 20px auto;
            display: block;
            padding: 8px;
            font-size: 16px;
        }
        @media screen and (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                display: none;
            }
            td {
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td::before {
                position: absolute;
                left: 10px;
                width: 45%;
                white-space: nowrap;
                color: #ccc;
            }
            td:nth-child(1)::before { content: "Cliente"; }
            td:nth-child(2)::before { content: "Disciplina"; }
            td:nth-child(3)::before { content: "Plan"; }
            td:nth-child(4)::before { content: "Inicio"; }
            td:nth-child(5)::before { content: "Vencimiento"; }
            td:nth-child(6)::before { content: "Método de Pago"; }
        }
    </style>
</head>
<body>

<h2>Listado de Membresías</h2>
<input type="text" id="buscador" placeholder="Buscar cliente, disciplina o pago...">

<table id="tabla">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Disciplina</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Método de Pago</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row["apellido"] . ", " . $row["nombre"]) ?></td>
            <td><?= htmlspecialchars($row["disciplina"] ?? '') ?></td>
            <td><?= htmlspecialchars($row["plan"] ?? '') ?></td>
            <td><?= htmlspecialchars($row["fecha_inicio"] ?? '') ?></td>
            <td><?= htmlspecialchars($row["fecha_vencimiento"] ?? '') ?></td>
            <td><?= ucfirst($row["metodo_pago"] ?? '') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<script>
document.getElementById("buscador").addEventListener("keyup", function() {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll("#tabla tbody tr");
    filas.forEach(fila => {
        const texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(filtro) ? "" : "none";
    });
});
</script>

</body>
</html>
