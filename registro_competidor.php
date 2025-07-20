<?php
session_start();
include 'conexion.php';

$disciplinas = $conexion->query("SELECT * FROM disciplinas_evento");
$modalidades = $conexion->query("SELECT * FROM modalidades_evento");
$categorias_peso = $conexion->query("SELECT * FROM categorias_peso_evento");
$tecnicas = $conexion->query("SELECT * FROM categorias_tecnicas_evento");

function calcularEdad($fecha) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha);
    $edad = $hoy->diff($nacimiento)->y;
    return $edad;
}

$mensaje = '';
$edad = '';
$division = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_nac = $_POST['fecha_nacimiento'];
    $edad = calcularEdad($fecha_nac);

    if ($edad < 13) $division = "Infantil";
    elseif ($edad <= 17) $division = "Juvenil";
    elseif ($edad <= 35) $division = "Adulto";
    else $division = "Master";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Competidor</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        input, select, textarea { width: 100%; margin-bottom: 10px; }
        label { font-weight: bold; }
    </style>
    <script>
    function calcularEdadAuto() {
        const fechaInput = document.getElementById('fecha_nacimiento').value;
        if (!fechaInput) return;
        const hoy = new Date();
        const fechaNac = new Date(fechaInput);
        let edad = hoy.getFullYear() - fechaNac.getFullYear();
        const m = hoy.getMonth() - fechaNac.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fechaNac.getDate())) edad--;
        document.getElementById('edad').value = edad;

        let division = '';
        if (edad < 13) division = 'Infantil';
        else if (edad <= 17) division = 'Juvenil';
        else if (edad <= 35) division = 'Adulto';
        else division = 'Master';
        document.getElementById('division').value = division;
    }
    </script>
</head>
<body style="background:black; color:gold;">
<div class="contenedor">
    <h2>🥋 Registro de Competidor</h2>
    <?php if ($mensaje) echo "<p style='color: gold;'>$mensaje</p>"; ?>
    <form method="POST" enctype="multipart/form-data">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Fecha de Nacimiento:</label>
        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdadAuto()" required>

        <label>Edad:</label>
        <input type="text" id="edad" name="edad" readonly>

        <label>División (automática):</label>
        <input type="text" id="division" name="division" readonly>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>Localidad / Provincia:</label>
        <input type="text" name="localidad" required>

        <label>Foto del Competidor (medio cuerpo en combate):</label>
        <input type="file" name="foto_competidor" accept="image/*" required>

        <hr>
        <label>Nombre de la Escuela:</label>
        <input type="text" name="escuela_nombre" required>

        <label>Logo de la Escuela (opcional):</label>
        <input type="file" name="logo_escuela" accept="image/*">

        <hr>
        <label>Disciplina:</label>
        <select name="disciplina_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($d = $disciplinas->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Modalidades (puede seleccionar hasta 3):</label>
        <select name="modalidades[]" multiple size="4" required>
            <?php while ($m = $modalidades->fetch_assoc()): ?>
                <option value="<?= $m['id'] ?>"><?= $m['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Categoría Técnica:</label>
        <select name="categoria_tecnica_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($t = $tecnicas->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>"><?= $t['codigo'] ?> - <?= $t['descripcion'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Categoría de Peso:</label>
        <select name="peso_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($p = $categorias_peso->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> (<?= $p['rango'] ?>)</option>
            <?php endwhile; ?>
        </select>

        <label>Pago de Inscripción ($):</label>
        <input type="number" step="0.01" name="pago_inscripcion" required>

        <button type="submit">✅ Registrar Competidor</button>
        <a href="menu_eventos.php" class="boton-volver">⬅ Volver</a>
    </form>
</div>
</body>
</html>
