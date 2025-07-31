<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// 📅 Tomar el mes actual
$mes_actual = date('Y-m');
$fecha_inicio = $mes_actual . "-01";
$fecha_fin = date("Y-m-t", strtotime($fecha_inicio));

// 🔹 Consultar asistencias solo del mes actual
$query = "SELECT c.nombre, c.apellido, c.dni, a.fecha, a.hora
          FROM asistencias a
          JOIN clientes c ON a.cliente_id = c.id
          WHERE c.gimnasio_id = $gimnasio_id
            AND a.fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
          ORDER BY a.fecha DESC, a.hora DESC";

$resultado = $conexion->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias Registradas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        #buscarInput {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>📋 Asistencias del Mes</h1>

    <input type="text" id="buscarInput" placeholder="🔍 Buscar por nombre, apellido o DNI...">

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody id="tablaAsistencias">
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila['nombre'] . " " . $fila['apellido']) ?></td>
                        <td><?= htmlspecialchars($fila['dni']) ?></td>
                        <td><?= htmlspecialchars($fila['fecha']) ?></td>
                        <td><?= htmlspecialchars($fila['hora']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No se encontraron asistencias este mes.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById('buscarInput').addEventListener('input', function() {
    const filtro = this.value.toLowerCase();
    const filas = document.querySelectorAll('#tablaAsistencias tr');
    filas.forEach(fila => {
        const texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(filtro) ? '' : 'none';
    });
});
</script>
</body>
</html>
