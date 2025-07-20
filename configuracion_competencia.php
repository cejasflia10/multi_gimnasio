<?php
session_start();
include 'conexion.php';

$mensaje = '';

function guardarElemento($tabla, $campo, $valor) {
    global $conexion;
    $stmt = $conexion->prepare("INSERT INTO $tabla ($campo) VALUES (?)");
    $stmt->bind_param("s", $valor);
    return $stmt->execute();
}

function eliminarElemento($tabla, $id) {
    global $conexion;
    $conexion->query("DELETE FROM $tabla WHERE id = $id");
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tipo'])) {
        $tipo = $_POST['tipo'];
        if ($tipo === 'disciplina' && $_POST['nombre']) guardarElemento('disciplinas_evento', 'nombre', $_POST['nombre']);
        if ($tipo === 'modalidad' && $_POST['nombre']) guardarElemento('modalidades_evento', 'nombre', $_POST['nombre']);
        if ($tipo === 'peso' && $_POST['nombre'] && $_POST['rango']) {
            $stmt = $conexion->prepare("INSERT INTO categorias_peso_evento (nombre, rango) VALUES (?, ?)");
            $stmt->bind_param("ss", $_POST['nombre'], $_POST['rango']);
            $stmt->execute();
        }
        if ($tipo === 'division' && $_POST['nombre']) guardarElemento('divisiones_evento', 'nombre', $_POST['nombre']);
        if ($tipo === 'tecnica' && $_POST['codigo'] && $_POST['descripcion']) {
            $stmt = $conexion->prepare("INSERT INTO categorias_tecnicas_evento (codigo, descripcion) VALUES (?, ?)");
            $stmt->bind_param("ss", $_POST['codigo'], $_POST['descripcion']);
            $stmt->execute();
        }
    }

    if (isset($_POST['eliminar'])) {
        eliminarElemento($_POST['tabla'], intval($_POST['eliminar']));
    }
}

// Consultas
$disciplinas = $conexion->query("SELECT * FROM disciplinas_evento");
$modalidades = $conexion->query("SELECT * FROM modalidades_evento");
$pesos = $conexion->query("SELECT * FROM categorias_peso_evento");
$divisiones = $conexion->query("SELECT * FROM divisiones_evento");
$tecnicas = $conexion->query("SELECT * FROM categorias_tecnicas_evento");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración de Competencia</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body style="background:black; color:gold;">
<div class="contenedor">
    <h2>⚙️ Configuración de Categorías</h2>

    <div class="bloque">
        <h3>Disciplinas</h3>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nueva disciplina" required>
            <input type="hidden" name="tipo" value="disciplina">
            <button type="submit">➕ Agregar</button>
        </form>
        <ul>
            <?php while ($d = $disciplinas->fetch_assoc()): ?>
                <li><?= $d['nombre'] ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="tabla" value="disciplinas_evento">
                        <button name="eliminar" value="<?= $d['id'] ?>">🗑️</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="bloque">
        <h3>Modalidades</h3>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nueva modalidad" required>
            <input type="hidden" name="tipo" value="modalidad">
            <button type="submit">➕ Agregar</button>
        </form>
        <ul>
            <?php while ($m = $modalidades->fetch_assoc()): ?>
                <li><?= $m['nombre'] ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="tabla" value="modalidades_evento">
                        <button name="eliminar" value="<?= $m['id'] ?>">🗑️</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="bloque">
        <h3>Categorías de Peso</h3>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="rango" placeholder="Ej: 60-65kg" required>
            <input type="hidden" name="tipo" value="peso">
            <button type="submit">➕ Agregar</button>
        </form>
        <ul>
            <?php while ($p = $pesos->fetch_assoc()): ?>
                <li><?= $p['nombre'] ?> (<?= $p['rango'] ?>)
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="tabla" value="categorias_peso_evento">
                        <button name="eliminar" value="<?= $p['id'] ?>">🗑️</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="bloque">
        <h3>Divisiones</h3>
        <form method="POST">
            <input type="text" name="nombre" placeholder="Ej: Juvenil, Adulto" required>
            <input type="hidden" name="tipo" value="division">
            <button type="submit">➕ Agregar</button>
        </form>
        <ul>
            <?php while ($d = $divisiones->fetch_assoc()): ?>
                <li><?= $d['nombre'] ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="tabla" value="divisiones_evento">
                        <button name="eliminar" value="<?= $d['id'] ?>">🗑️</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <div class="bloque">
        <h3>Categorías Técnicas</h3>
        <form method="POST">
            <input type="text" name="codigo" placeholder="Código: N, C, B, A" maxlength="1" required>
            <input type="text" name="descripcion" placeholder="Descripción" required>
            <input type="hidden" name="tipo" value="tecnica">
            <button type="submit">➕ Agregar</button>
        </form>
        <ul>
            <?php while ($t = $tecnicas->fetch_assoc()): ?>
                <li><?= $t['codigo'] ?> - <?= $t['descripcion'] ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="tabla" value="categorias_tecnicas_evento">
                        <button name="eliminar" value="<?= $t['id'] ?>">🗑️</button>
                    </form>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <a href="menu_eventos.php" class="boton-volver">⬅ Volver al menú</a>
</div>
</body>
</html>
