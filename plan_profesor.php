<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$profesores = $conexion->query("
    SELECT p.id, CONCAT(p.apellido, ' ', p.nombre) AS nombre, pl.valor_hora
    FROM profesores p
    LEFT JOIN plan_profesor pl ON p.id = pl.profesor_id
    ORDER BY p.apellido ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['valor_hora'] as $profesor_id => $valor) {
        $valor = floatval($valor);
        $profesor_id = intval($profesor_id);

        // Verificar si ya tiene un valor cargado
        $check = $conexion->query("SELECT * FROM plan_profesor WHERE profesor_id = $profesor_id")->num_rows;

        if ($check > 0) {
            $conexion->query("UPDATE plan_profesor SET valor_hora = $valor WHERE profesor_id = $profesor_id");
        } else {
            $conexion->query("INSERT INTO plan_profesor (profesor_id, valor_hora) VALUES ($profesor_id, $valor)");
        }
    }

    header("Location: plan_profesor.php?guardado=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Plan por Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
<div class="contenedor">
<h1>💸 Valor por Hora de Cada Profesor</h1>

<?php if (isset($_GET['guardado'])): ?>
    <div class="msg">✅ Valores guardados correctamente.</div>
<?php endif; ?>

<form method="POST">
    <table>
        <thead>
            <tr>
                <th>Profesor</th>
                <th>Valor por Hora ($)</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $profesores->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($fila['nombre']) ?></td>
                    <td>
                        <input type="number" name="valor_hora[<?= $fila['id'] ?>]" value="<?= $fila['valor_hora'] ?? '' ?>" step="0.01" required>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <input type="submit" value="Guardar Valores">
</form>
</div>
</body>
</html>
