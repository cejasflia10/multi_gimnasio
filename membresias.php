<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Gimnasio no especificado.");
}

$sql = "SELECT m.id, c.nombre, c.apellido, p.nombre AS plan_nombre, m.fecha_inicio, m.fecha_vencimiento, m.clases_disponibles, m.total 
        FROM membresias m
        JOIN clientes c ON m.cliente_id = c.id
        JOIN planes p ON m.plan_id = p.id
        WHERE m.gimnasio_id = $gimnasio_id
        ORDER BY m.fecha_vencimiento ASC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
   <!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Membresías</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Hojas de estilo externas -->
  <link rel="stylesheet" href="estilo_unificado.css">

</head>

<body>
<div class="contenedor">

<h2>Listado de Membresías</h2>

<input type="text" class="buscar" id="buscador" placeholder="Buscar cliente, plan o pago...">

<table id="tabla">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases Disponibles</th>
            <th>Total</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['apellido'] . ", " . $row['nombre']; ?></td>
                <td><?php echo $row['plan_nombre']; ?></td>
                <td><?php echo $row['fecha_inicio']; ?></td>
                <td><?php echo $row['fecha_vencimiento']; ?></td>
                <td><?php echo $row['clases_disponibles'] . " clases"; ?></td>
                <td>$<?php echo number_format($row['total'], 2, ',', '.'); ?></td>
                <td>
                    <a class="boton" href="editar_membresia.php?id=<?php echo $row['id']; ?>">Editar</a>
                    <a class="boton" href="eliminar_membresia.php?id=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar esta membresía?')">Eliminar</a>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<script>
    const buscador = document.getElementById('buscador');
    buscador.addEventListener('input', function () {
        const filtro = this.value.toLowerCase();
        const filas = document.querySelectorAll('#tabla tbody tr');
        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(filtro) ? '' : 'none';
        });
    });
</script>
</div>
</body>
</html>
