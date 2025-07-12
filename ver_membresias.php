<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$query = "SELECT m.*, c.nombre, c.apellido, p.nombre AS nombre_plan, m.total_pagado, m.id AS id_membresia, m.cliente_id
          FROM membresias m 
          JOIN clientes c ON m.cliente_id = c.id 
          JOIN planes p ON m.plan_id = p.id 
          WHERE m.gimnasio_id = $gimnasio_id 
          ORDER BY m.fecha_inicio DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h1>Listado de Membresías</h1>

    <div class="buscador-contenedor">
        <input type="text" id="buscador" placeholder="Buscar membresía...">
    </div>

    <div class="tabla-scroll">
    <table class="tabla">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Plan</th>
                <th>Inicio</th>
                <th>Vencimiento</th>
                <th>Clases</th>
                <th>Total</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $n = 1;
        while ($fila = $resultado->fetch_assoc()):
            $vencida = (strtotime($fila['fecha_vencimiento']) < strtotime(date("Y-m-d"))) ? 'vencida' : '';
        ?>
            <tr class="<?= $vencida ?>">
                <td><?= $n ?></td>
                <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
                <td><?= $fila['nombre_plan'] ?></td>
                <td><?= $fila['fecha_inicio'] ?></td>
                <td><?= $fila['fecha_vencimiento'] ?></td>
                <td><?= $fila['clases_disponibles'] ?></td>
                <td class="<?= ($fila['total_pagado'] == 0 ? 'texto-rojo' : '') ?>">
                    <?php 
                        if ($fila['total_pagado'] > 0) {
                            echo '$' . number_format($fila['total_pagado'], 2, ',', '.');
                        } elseif (!empty($fila['saldo_cc']) && $fila['saldo_cc'] > 0) {
                            echo 'Cuenta Corriente';
                        } else {
                            echo '$0,00';
                        }
                    ?>
                </td>

                <td class="acciones">
                    <a href="editar_membresia.php?id=<?= $fila['id_membresia'] ?>" class="boton-naranja">✏️</a>
                    <a href="eliminar_membresia.php?id=<?= $fila['id_membresia'] ?>" class="boton-rojo" onclick="return confirm('¿Eliminar esta membresía?')">❌</a>
                    <a href="renovar_membresia.php?id=<?= $fila['id_membresia'] ?>" class="boton-verde">♻️</a>
                    <a href="ver_historial_membresias.php?cliente_id=<?= $fila['cliente_id'] ?>" class="boton-azul">📜</a>
                </td>
            </tr>
        <?php
        $n++;
        endwhile;
        ?>
        </tbody>
    </table>
    </div>

    <a href="index.php" class="boton-volver">Volver al Menú</a>
</div>

<script>
document.getElementById("buscador").addEventListener("keyup", function() {
    let filtro = this.value.toLowerCase();
    document.querySelectorAll("table tbody tr").forEach(fila => {
        fila.style.display = fila.textContent.toLowerCase().includes(filtro) ? '' : 'none';
    });
});
</script>
</body>
</html>
co