<?php
include 'conexion.php';

$disciplinas = $conexion->query("SELECT * FROM disciplinas_evento");
$modalidades = $conexion->query("SELECT * FROM modalidades_evento");
$categorias_peso = $conexion->query("SELECT * FROM categorias_peso_evento");
$divisiones = ['Infantil', 'Juvenil', 'Adulto'];
$categorias_tecnicas = ['N', 'C', 'B', 'A'];

// Filtros
$filtro_disciplina = $_GET['disciplina'] ?? '';
$filtro_modalidad = $_GET['modalidad'] ?? '';
$filtro_categoria_peso = $_GET['categoria_peso'] ?? '';
$filtro_division = $_GET['division'] ?? '';
$filtro_categoria_tecnica = $_GET['categoria_tecnica'] ?? '';

// Consulta base
$sql = "SELECT * FROM competidores_evento WHERE 1=1";

if ($filtro_disciplina) $sql .= " AND disciplina = '$filtro_disciplina'";
if ($filtro_modalidad) $sql .= " AND modalidad LIKE '%$filtro_modalidad%'";
if ($filtro_categoria_peso) $sql .= " AND categoria_peso = '$filtro_categoria_peso'";
if ($filtro_division) $sql .= " AND division = '$filtro_division'";
if ($filtro_categoria_tecnica) $sql .= " AND categoria_tecnica = '$filtro_categoria_tecnica'";

$competidores = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Competidores del Evento</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>👊 Competidores Registrados en el Evento</h2>

    <form method="GET">
        <label>Disciplina:</label>
        <select name="disciplina">
            <option value="">Todas</option>
            <?php while ($d = $disciplinas->fetch_assoc()): ?>
                <option value="<?= $d['nombre'] ?>" <?= $filtro_disciplina == $d['nombre'] ? 'selected' : '' ?>><?= $d['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Modalidad:</label>
        <select name="modalidad">
            <option value="">Todas</option>
            <?php while ($m = $modalidades->fetch_assoc()): ?>
                <option value="<?= $m['nombre'] ?>" <?= $filtro_modalidad == $m['nombre'] ? 'selected' : '' ?>><?= $m['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Categoría Técnica:</label>
        <select name="categoria_tecnica">
            <option value="">Todas</option>
            <?php foreach ($categorias_tecnicas as $cat): ?>
                <option value="<?= $cat ?>" <?= $filtro_categoria_tecnica == $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
        </select>

        <label>Categoría de Peso:</label>
        <select name="categoria_peso">
            <option value="">Todas</option>
            <?php foreach ($categorias_peso as $peso): ?>
                <option value="<?= $peso['nombre'] ?>" <?= $filtro_categoria_peso == $peso['nombre'] ? 'selected' : '' ?>><?= $peso['nombre'] ?></option>
            <?php endforeach; ?>
        </select>

        <label>División:</label>
        <select name="division">
            <option value="">Todas</option>
            <?php foreach ($divisiones as $div): ?>
                <option value="<?= $div ?>" <?= $filtro_division == $div ? 'selected' : '' ?>><?= $div ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">🔍 Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Edad</th>
                <th>Disciplina</th>
                <th>Modalidad</th>
                <th>Cat. Técnica</th>
                <th>Peso</th>
                <th>División</th>
                <th>Escuela</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($c = $competidores->fetch_assoc()): ?>
                <tr>
                    <td><img src="<?= $c['foto_combate'] ?>" width="60" height="60" style="object-fit: cover;"></td>
                    <td><?= $c['apellido'] ?> <?= $c['nombre'] ?></td>
                    <td><?= $c['dni'] ?></td>
                    <td><?= $c['edad'] ?></td>
                    <td><?= $c['disciplina'] ?></td>
                    <td><?= $c['modalidad'] ?></td>
                    <td><?= $c['categoria_tecnica'] ?></td>
                    <td><?= $c['categoria_peso'] ?></td>
                    <td><?= $c['division'] ?></td>
                    <td><?= $c['nombre_escuela'] ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
