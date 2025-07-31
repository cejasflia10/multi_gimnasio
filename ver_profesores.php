<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$mensaje = '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$es_admin = ($_SESSION['rol'] ?? '') === 'admin';

// 🗑️ ELIMINAR PROFESOR Y TODO RELACIONADO
if (isset($_GET['eliminar'])) {
    $profesor_id = intval($_GET['eliminar']);
    $conexion->begin_transaction();

    try {
        // 🔹 Todas las tablas que tienen profesor_id
        $tablas = [
            "rfid_registros",
            "rfid_profesores",
            "rfid_profesores_registros",
            "registro_profesores",
            "registros_profesores",
            "registro_asistencias_profesores",
            "profesores_turnos",
            "turnos_profesor",
            "pagos_profesor",
            "controles_fisicos",
            "planes_entrenamiento",
            "progreso_tecnico",
            "evaluaciones_fisicas",
            "fotos_evolucion",
            "graduaciones",
            "competencias",
            "archivos_profesor",
            "mensajes_chat",
            "asistencias_profesor",
            "alumnos_profesor",
            "alumnos_asignados_profesor",
            "escaneos_profesor",
            "progreso_alumno",
            "membresias",
            "progreso_fisico",
            "tarifas_profesor",
            "datos_fisicos",
            "asistencias_profesores"
        ];

        // 🔹 Eliminar registros de todas las tablas relacionadas
        foreach ($tablas as $tabla) {
            if ($conexion->query("SHOW TABLES LIKE '$tabla'")->num_rows > 0) {
                $conexion->query("DELETE FROM $tabla WHERE profesor_id = $profesor_id");
            }
        }

        // 🔹 Eliminar reservas de los turnos de este profesor
        $conexion->query("DELETE FROM reservas 
                          WHERE turno_id IN (SELECT id FROM turnos WHERE profesor_id = $profesor_id)");

        // 🔹 Eliminar turnos del profesor
        $conexion->query("DELETE FROM turnos WHERE profesor_id = $profesor_id");

        // 🔹 Finalmente eliminar el profesor
        $conexion->query("DELETE FROM profesores WHERE id = $profesor_id");

        $conexion->commit();
        $mensaje = "✅ Profesor y todos sus registros fueron eliminados.";
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "❌ Error al eliminar: " . $e->getMessage();
    }
}

// 🟢 AGREGAR PROFESOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gimnasio_seleccionado = $es_admin ? intval($_POST['gimnasio_id']) : $gimnasio_id;

    if ($apellido && $nombre && $dni) {
        $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, dni, telefono, email, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $apellido, $nombre, $dni, $telefono, $email, $gimnasio_seleccionado);
        if ($stmt->execute()) {
            $mensaje = "✅ Profesor registrado correctamente.";
        } else {
            $mensaje = "❌ Error al registrar: " . $stmt->error;
        }
    } else {
        $mensaje = "⚠️ Completá todos los campos obligatorios.";
    }
}

// 🔹 OBTENER LISTA DE PROFESORES
$sql = $es_admin ? 
    "SELECT p.id, p.apellido, p.nombre, p.dni, g.nombre AS gimnasio 
     FROM profesores p 
     JOIN gimnasios g ON p.gimnasio_id = g.id 
     ORDER BY p.apellido" :
    "SELECT id, apellido, nombre, dni FROM profesores WHERE gimnasio_id = $gimnasio_id ORDER BY apellido";

$profesores = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Profesores</title>
    <style>
        body { background-color: #000; color: gold; font-family: Arial; padding: 20px; }
        .formulario, .tabla { max-width: 800px; margin: auto; background-color: #111; padding: 20px; border-radius: 10px; border: 1px solid #444; margin-bottom: 20px; }
        input, select, button { width: 100%; padding: 10px; margin-top: 10px; font-size: 16px; background-color: #222; color: gold; border: 1px solid #555; }
        button { background-color: #333; cursor: pointer; }
        .mensaje { text-align: center; margin-bottom: 15px; color: lime; }
        h2 { text-align: center; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: center; }
        a.eliminar { color: red; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>➕ Registrar Profesor</h2>
    <?php if ($mensaje): ?><div class="mensaje"><?= $mensaje ?></div><?php endif; ?>

    <form method="POST">
        <input type="text" name="apellido" placeholder="Apellido" required>
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="dni" placeholder="DNI" required>
        <input type="text" name="telefono" placeholder="Teléfono">
        <input type="email" name="email" placeholder="Email">

        <?php if ($es_admin): ?>
            <select name="gimnasio_id" required>
                <option value="">Seleccione gimnasio</option>
                <?php
                $gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios");
                while ($g = $gimnasios->fetch_assoc()):
                ?>
                    <option value="<?= $g['id'] ?>"><?= $g['nombre'] ?></option>
                <?php endwhile; ?>
            </select>
        <?php endif; ?>

        <button type="submit">Guardar Profesor</button>
    </form>
</div>

<div class="tabla">
    <h2>📋 Lista de Profesores</h2>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <?php if ($es_admin): ?><th>Gimnasio</th><?php endif; ?>
            <th>Acciones</th>
        </tr>
        <?php while ($p = $profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $p['apellido'] ?></td>
                <td><?= $p['nombre'] ?></td>
                <td><?= $p['dni'] ?></td>
                <?php if ($es_admin): ?><td><?= $p['gimnasio'] ?></td><?php endif; ?>
                <td><a class="eliminar" href="?eliminar=<?= $p['id'] ?>" onclick="return confirm('❗ Se eliminará el profesor y todos sus datos. ¿Continuar?')">🗑️ Eliminar</a></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>
