<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Consultas corregidas
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas_evento");

$pesos = $conexion->query("
    SELECT DISTINCT cp.id, cp.nombre 
    FROM competidores_evento ce
    JOIN categorias_peso_evento cp ON ce.categoria_peso_id = cp.id
    ORDER BY cp.nombre
");

$divisiones = $conexion->query("SELECT DISTINCT division FROM competidores_evento");
$modalidades = $conexion->query("SELECT DISTINCT modalidad FROM competidores_evento");
$categorias_tecnicas = $conexion->query("SELECT DISTINCT categoria_tecnica FROM competidores_evento");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Organizar Peleas</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }
        .filtros label {
            font-weight: bold;
            color: gold;
        }
        .filtros select, .filtros button {
            padding: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🥊 Organización de Peleas</h2>

    <form method="GET" action="ver_grupo_competidores.php" class="filtros">
        <label>Disciplina:</label>
        <select name="disciplina_id">
            <option value="">Todas</option>
            <?php while($d = $disciplinas->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>División:</label>
        <select name="division">
            <option value="">Todas</option>
            <?php while($d = $divisiones->fetch_assoc()): ?>
                <option value="<?= $d['division'] ?>"><?= $d['division'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Peso:</label>
        <select name="peso">
            <option value="">Todas</option>
            <?php while($p = $pesos->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> kg</option>
            <?php endwhile; ?>
        </select>

        <label>Categoría Técnica:</label>
        <select name="categoria_tecnica">
            <option value="">Todas</option>
            <?php while($c = $categorias_tecnicas->fetch_assoc()): ?>
                <option value="<?= $c['categoria_tecnica'] ?>"><?= $c['categoria_tecnica'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Modalidad:</label>
        <select name="modalidad">
            <option value="">Todas</option>
            <?php while($m = $modalidades->fetch_assoc()): ?>
                <option value="<?= $m['modalidad'] ?>"><?= $m['modalidad'] ?></option>
            <?php endwhile; ?>
        </select>

        <button type="submit">🔍 Buscar Competidores</button>
    </form>

    <form method="POST" action="guardar_pelea_evento.php">
        <!-- Aquí se mostrarán los resultados para armar la pelea según filtros -->
        <!-- (agregá tú la lógica de mostrar competidores y el botón de confirmar) -->
        <button type="submit">✅ Confirmar y Agregar Pelea</button>
    </form>
</div>
</body>
</html>
