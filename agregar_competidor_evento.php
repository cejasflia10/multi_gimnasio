<?php
session_start();
if (!isset($_SESSION['evento_usuario_id'])) {
    echo "Acceso denegado.";
    exit;
}
include 'conexion.php';

$disciplinas = $conexion->query("SELECT * FROM disciplinas_evento ORDER BY nombre");
$modalidades = $conexion->query("SELECT * FROM modalidades_evento ORDER BY nombre");
$pesos = $conexion->query("SELECT * FROM categorias_peso_evento ORDER BY peso_min");

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido']);
    $nombre = trim($_POST['nombre']);
    $dni = trim($_POST['dni']);
    $fecha_nac = $_POST['fecha_nacimiento'];
    $domicilio = trim($_POST['domicilio']);
    $localidad = trim($_POST['localidad']);
    $escuela = trim($_POST['escuela']);

    $disciplina_id = intval($_POST['disciplina_id']);
    $categoria_tecnica = $_POST['categoria_tecnica'];
    $peso_id = intval($_POST['peso_id']);
    $monto_pago = floatval($_POST['monto_pago']);

    $modalidades_elegidas = $_POST['modalidades'] ?? [];
    $modalidades_str = implode(',', $modalidades_elegidas);

    // Edad y división automática
    $edad = date_diff(date_create($fecha_nac), date_create('today'))->y;
    $division = ($edad < 13) ? 'Infantil' : (($edad < 18) ? 'Juvenil' : 'Adulto');

    // Subir fotos
    $foto_path = '';
    $logo_path = '';

    if (!empty($_FILES['foto']['tmp_name'])) {
        $foto_path = 'fotos/' . uniqid() . '_' . $_FILES['foto']['name'];
        move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path);
    }

    if (!empty($_FILES['logo']['tmp_name'])) {
        $logo_path = 'logos/' . uniqid() . '_' . $_FILES['logo']['name'];
        move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
    }

    $stmt = $conexion->prepare("INSERT INTO competidores_evento 
        (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, localidad, escuela, logo_escuela, foto, division, categoria_tecnica, disciplina_id, modalidad, categoria_peso_id, monto_pago) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssisssssssssid", 
        $apellido, $nombre, $dni, $fecha_nac, $edad,
        $domicilio, $localidad, $escuela, $logo_path, $foto_path,
        $division, $categoria_tecnica, $disciplina_id, $modalidades_str, $peso_id, $monto_pago);

    if ($stmt->execute()) {
        $mensaje = "✅ Competidor registrado correctamente.";
    } else {
        $mensaje = "❌ Error al registrar competidor.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Competidor</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
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
        <input type="date" name="fecha_nacimiento" required>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>Localidad / Provincia:</label>
        <input type="text" name="localidad" required>

        <label>Foto (medio cuerpo):</label>
        <input type="file" name="foto" accept="image/*" required>

        <label>Logo o Foto de Escuela:</label>
        <input type="file" name="logo" accept="image/*" required>

        <label>Nombre de la Escuela:</label>
        <input type="text" name="escuela" required>

        <label>Categoría Técnica:</label>
        <select name="categoria_tecnica" required>
            <option value="N">N (Sin peleas)</option>
            <option value="C">C (1 a 3 peleas)</option>
            <option value="B">B (4 a 10 peleas)</option>
            <option value="A">A (11 o más peleas)</option>
        </select>

        <label>Disciplina:</label>
        <select name="disciplina_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($d = $disciplinas->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>"><?= $d['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>Modalidades (máx. 3):</label>
        <div style="display: flex; flex-wrap: wrap;">
            <?php while ($m = $modalidades->fetch_assoc()): ?>
                <label style="width: 45%;"><input type="checkbox" name="modalidades[]" value="<?= $m['nombre'] ?>"> <?= $m['nombre'] ?></label>
            <?php endwhile; ?>
        </div>

        <label>Categoría de Peso:</label>
        <select name="peso_id" required>
            <option value="">-- Seleccionar --</option>
            <?php while ($p = $pesos->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> (<?= $p['peso_min'] ?>kg - <?= $p['peso_max'] ?>kg)</option>
            <?php endwhile; ?>
        </select>

        <label>Monto de Inscripción:</label>
        <input type="number" step="0.01" name="monto_pago" required>

        <br><br>
        <button type="submit">✅ Registrar Competidor</button>
        <a href="index.php" class="boton-volver">⬅ Volver</a>
    </form>
</div>
</body>
</html>
