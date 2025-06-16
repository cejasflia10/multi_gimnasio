<?php
include 'conexion.php';
include 'menu.php';

$consulta = "SELECT m.*, c.nombre AS nombre_cliente, c.apellido AS apellido_cliente, d.nombre AS disciplina, p.nombre AS plan
             FROM membresias m
             JOIN clientes c ON m.cliente_id = c.id
             JOIN disciplinas d ON m.disciplina_id = d.id
             JOIN planes p ON m.plan_id = p.id
             ORDER BY m.fecha_inicio DESC";

$resultado = mysqli_query($conexion, $consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <style>
        body { background-color: #111; color: #f1c40f; font-family: Arial, sans-serif; margin: 0; }
        .contenido { margin-left: 240px; padding: 20px; }
        h2 { color: #f1c40f; }
        input[type="text"] {
            width: 300px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #f1c40f;
            background-color: #1a1a1a;
            color: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1a1a1a;
            color: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #f1c40f;
        }
        th {
            background-color: #222;
            color: #f1c40f;
        }
    </style>
    <script>
        function filtrarTabla() {
            let input = document.getElementById("buscador").value.toLowerCase();
            let filas = document.querySelectorAll("tbody tr");
            filas.forEach(fila => {
                let texto = fila.textContent.toLowerCase();
                fila.style.display = texto.includes(input) ? "" : "none";
            });
        }
    </script>
</head>
<body>
<div class="contenido">
    <h2>Listado de Membresías</h2>
    <input type="text" id="buscador" onkeyup="filtrarTabla()" placeholder="Buscar cliente, disciplina o pago...">

    <table>
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
            <?php while ($row = mysqli_fetch_assoc($resultado)) { ?>
                <tr>
                    <td><?php echo $row['apellido_cliente'] . ', ' . $row['nombre_cliente']; ?></td>
                    <td><?php echo $row['disciplina']; ?></td>
                    <td><?php echo $row['plan']; ?></td>
                    <td><?php echo $row['fecha_inicio']; ?></td>
                    <td><?php echo $row['fecha_vencimiento']; ?></td>
                    <td><?php echo ucfirst($row['metodo_pago']); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
</body>
</html>
