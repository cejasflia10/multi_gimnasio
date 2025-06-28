<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje = "";
$profesor_id = $_SESSION['profesor_id'] ?? null;

// LOGIN DEL PROFESOR
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = $_POST['dni'];
    $stmt = $conexion->prepare("SELECT * FROM profesores WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $profesor = $resultado->fetch_assoc();

    if ($profesor) {
        $_SESSION['profesor_id'] = $profesor['id'];
        $_SESSION['profesor_nombre'] = $profesor['nombre'] . ' ' . $profesor['apellido'];
        $_SESSION['profesor_dni'] = $profesor['dni'];
    } else {
        $mensaje = "❌ DNI no registrado.";
    }
    $profesor_id = $_SESSION['profesor_id'] ?? null;
}

// GENERAR QR MANUALMENTE
if (isset($_POST['generar_qr']) && $profesor_id) {
    include_once 'phpqrcode/qrlib.php';
    if (!is_dir('qrs')) mkdir('qrs');
    $qr_path = "qrs/qr_profesor_" . $profesor_id . ".png";
    if (!file_exists($qr_path)) {
        QRcode::png($_SESSION['profesor_dni'], $qr_path, QR_ECLEVEL_L, 4);
        echo "<script>alert('QR generado correctamente');</script>";
    }
}
if (isset($_POST['guardar_eval']) && $profesor_id) {
    $cid = intval($_POST['cliente_id_eval']);
    $fecha = $_POST['fecha_eval'];
    $peso = floatval($_POST['peso']);
    $altura = floatval($_POST['altura']);
    $edad = intval($_POST['edad']);
    $tipo = $_POST['tipo_control'];
    $observaciones = $_POST['observaciones_eval'];

    $imc = ($altura > 0) ? round($peso / pow($altura / 100, 2), 2) : 0;

    $conexion->query("INSERT INTO evaluaciones_fisicas 
        (cliente_id, profesor_id, fecha, peso, altura, edad, imc, observaciones, tipo_control)
        VALUES ($cid, $profesor_id, '$fecha', $peso, $altura, $edad, $imc, '$observaciones', '$tipo')");

    echo "<script>alert('Evaluación física guardada');</script>";
}
if (isset($_POST['guardar_graduacion']) && $profesor_id) {
    $cid = intval($_POST['cliente_id_grad']);
    $disciplina = $_POST['disciplina_grad'];
    $fecha = $_POST['fecha_grad'];
    $nivel = $_POST['nivel_grad'];
    $observaciones = $_POST['observaciones_grad'];

    $conexion->query("INSERT INTO graduaciones 
        (cliente_id, profesor_id, disciplina, fecha, nivel, observaciones)
        VALUES ($cid, $profesor_id, '$disciplina', '$fecha', '$nivel', '$observaciones')");

    echo "<script>alert('Graduación registrada correctamente');</script>";
}

// GUARDAR PROGRESO TECNICO
if (isset($_POST['guardar_progreso']) && $profesor_id) {
    $cid = intval($_POST['cliente_id']);
    $fecha = $_POST['fecha'];
    $tecnica = $_POST['tecnica'];
    $fuerza = $_POST['fuerza'];
    $resistencia = $_POST['resistencia'];
    $coordinacion = $_POST['coordinacion'];
    $velocidad = $_POST['velocidad'];
    $observaciones = $_POST['observaciones'];

    $disciplina_q = $conexion->query("SELECT d.nombre FROM clientes c JOIN disciplinas d ON c.disciplina_id = d.id WHERE c.id = $cid");
    $disciplina = $disciplina_q->fetch_assoc()['nombre'] ?? '';

    $conexion->query("INSERT INTO progreso_tecnico 
        (cliente_id, disciplina, profesor_id, fecha, tecnica, fuerza, resistencia, coordinacion, velocidad, observaciones)
        VALUES ($cid, '$disciplina', $profesor_id, '$fecha', $tecnica, $fuerza, $resistencia, $coordinacion, $velocidad, '$observaciones')");

    echo "<script>alert('Progreso técnico guardado correctamente');</script>";
}
if (isset($_POST['guardar_plan']) && $profesor_id) {
    $cid = intval($_POST['cliente_id_plan']);
    $disciplina = $_POST['disciplina_plan'];
    $fecha = $_POST['fecha_plan'];
    $contenido = $_POST['contenido_plan'];
    $archivo = '';

    if (!empty($_FILES['archivo_plan']['name'])) {
        $nombre_archivo = basename($_FILES['archivo_plan']['name']);
        $carpeta = "planes_archivos/$cid/";
        if (!file_exists($carpeta)) mkdir($carpeta, 0777, true);
        $ruta = $carpeta . $nombre_archivo;
        move_uploaded_file($_FILES['archivo_plan']['tmp_name'], $ruta);
        $archivo = $ruta;
    }

    $conexion->query("INSERT INTO planes_entrenamiento (cliente_id, profesor_id, disciplina, fecha, contenido, archivo)
                      VALUES ($cid, $profesor_id, '$disciplina', '$fecha', '$contenido', '$archivo')");
    
    echo "<script>alert('Plan cargado correctamente');</script>";
}
if (isset($_POST['guardar_foto']) && $profesor_id) {
    $cid = intval($_POST['cliente_id_foto']);
    $fecha = $_POST['fecha_foto'];
    $etapa = $_POST['etapa'];
    $archivo = '';

    if (!empty($_FILES['archivo_foto']['name'])) {
        $nombre = basename($_FILES['archivo_foto']['name']);
        $carpeta = "fotos_evolucion/$cid/";
        if (!file_exists($carpeta)) mkdir($carpeta, 0777, true);
        $ruta = $carpeta . $fecha . "_" . $nombre;
        move_uploaded_file($_FILES['archivo_foto']['tmp_name'], $ruta);
        $archivo = $ruta;
    }

    $conexion->query("INSERT INTO fotos_evolucion 
        (cliente_id, profesor_id, fecha, etapa, archivo) 
        VALUES ($cid, $profesor_id, '$fecha', '$etapa', '$archivo')");

    echo "<script>alert('Foto de evolución cargada correctamente');</script>";
}
if (isset($_POST['guardar_competencia']) && $profesor_id) {
    $cid = intval($_POST['cliente_id_comp']);
    $nombre = $_POST['nombre_competencia'];
    $lugar = $_POST['lugar'];
    $fecha = $_POST['fecha_comp'];
    $resultado = $_POST['resultado'];
    $observaciones = $_POST['observaciones_comp'];

    $conexion->query("INSERT INTO competencias 
        (cliente_id, profesor_id, nombre_competencia, lugar, fecha, resultado, observaciones)
        VALUES ($cid, $profesor_id, '$nombre', '$lugar', '$fecha', '$resultado', '$observaciones')");

    echo "<script>alert('Competencia registrada correctamente');</script>";
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #111;
            padding: 30px;
            border-radius: 10px;
        }

        h2, h3, h4 {
            text-align: center;
            color: #ffc107;
        }

        input, button, select, textarea {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
            border: none;
            background: #000;
            color: gold;
        }

        button {
            background: #ffc107;
            color: #000;
            font-weight: bold;
            cursor: pointer;
        }

        .qr, .datos, .card {
            text-align: center;
            margin-top: 20px;
        }

        img {
            max-width: 200px;
            margin-top: 10px;
        }

        .mensaje {
            color: red;
            text-align: center;
            margin-top: 10px;
        }

        .card {
            background-color: #111;
            padding: 20px;
            border-radius: 10px;
            margin: 30px auto;
            max-width: 700px;
        }
    </style>
</head>
<body>
    
<?php if (!$profesor_id): ?>
    <div class="container">
        <h2>Ingreso al Panel del Profesor</h2>
        <form method="POST">
            <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
            <button type="submit">Ingresar</button>
        </form>
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje"><?= $mensaje ?></div>
        <?php endif; ?>
    </div>
    
<?php else: ?>
    <div class="container">
        <h3><?= $_SESSION['profesor_nombre'] ?></h3>
        <p>DNI: <?= $_SESSION['profesor_dni'] ?></p>

        <div class="qr">
            <h4>Tu código QR personal</h4>
            <?php
            $qr_path = "qrs/qr_profesor_$profesor_id.png";
            if (file_exists($qr_path)) {
                echo "<img src='$qr_path' alt='QR del Profesor'>";
                echo "<p><small>Escanealo para registrar ingreso y egreso</small></p>";
            } else {
                echo "<form method='POST'>
                        <input type='hidden' name='generar_qr' value='1'>
                        <button type='submit'>📲 Generar QR</button>
                      </form>";
            }
            ?>
        </div>
    </div>
<div class="card">
    <h3>🥋 Registrar Graduación del Alumno</h3>
    <form method="POST">
        <label>Alumno:</label>
        <select name="cliente_id_grad" required>
            <option value="">Seleccionar</option>
            <?php
            $clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
            while ($c = $clientes->fetch_assoc()):
                echo "<option value='{$c['id']}'>{$c['apellido']} {$c['nombre']}</option>";
            endwhile;
            ?>
        </select>

        <label>Disciplina:</label>
        <input type="text" name="disciplina_grad" required>

        <label>Fecha:</label>
        <input type="date" name="fecha_grad" required>

        <label>Nivel / Graduación (ej: Cinturón Amarillo, Intermedio, etc):</label>
        <input type="text" name="nivel_grad" required>

        <label>Observaciones:</label>
        <textarea name="observaciones_grad" rows="3"></textarea>

        <button type="submit" name="guardar_graduacion">Guardar Graduación</button>
    </form>
</div>
<div class="card">
    <h3>🥇 Registrar Participación en Competencia</h3>
    <form method="POST">
        <label>Alumno:</label>
        <select name="cliente_id_comp" required>
            <option value="">Seleccionar</option>
            <?php
            $clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
            while ($c = $clientes->fetch_assoc()):
                echo "<option value='{$c['id']}'>{$c['apellido']} {$c['nombre']}</option>";
            endwhile;
            ?>
        </select>

        <label>Nombre del evento / competencia:</label>
        <input type="text" name="nombre_competencia" required>

        <label>Lugar:</label>
        <input type="text" name="lugar" required>

        <label>Fecha:</label>
        <input type="date" name="fecha_comp" required>

        <label>Resultado (ej: 1er puesto, participación, etc):</label>
        <input type="text" name="resultado">

        <label>Observaciones:</label>
        <textarea name="observaciones_comp" rows="3"></textarea>

        <button type="submit" name="guardar_competencia">Guardar Competencia</button>
    </form>
</div>

    <div class="card">
        <h3>📋 Evaluar Progreso Técnico</h3>
        <form method="POST">
            <label>Alumno:</label>
            <select name="cliente_id" required onchange="this.form.submit()">
                <option value="">Seleccionar</option>
                <?php
                $alumnos = $conexion->query("SELECT c.id, c.nombre, c.apellido FROM clientes c WHERE c.gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
                while ($a = $alumnos->fetch_assoc()):
                    $selected = isset($_POST['cliente_id']) && $_POST['cliente_id'] == $a['id'] ? 'selected' : '';
                    echo "<option value='{$a['id']}' $selected>{$a['apellido']} {$a['nombre']}</option>";
                endwhile;
                ?>
            </select>

            <?php
            $disciplina = '';
            if (isset($_POST['cliente_id'])) {
                $cid = intval($_POST['cliente_id']);
                $disciplina_q = $conexion->query("SELECT d.nombre FROM clientes c JOIN disciplinas d ON c.disciplina_id = d.id WHERE c.id = $cid");
                $disciplina = $disciplina_q->fetch_assoc()['nombre'] ?? '';
            }
            ?>

            <?php if (!empty($disciplina)): ?>
                <label>Disciplina:</label>
                <input type="text" value="<?= htmlspecialchars($disciplina) ?>" disabled>
            <?php endif; ?>

            <?php if (!empty($_POST['cliente_id'])): ?>
                <label>Fecha:</label>
                <input type="date" name="fecha" required>

                <label>Técnica (1-10):</label>
                <input type="number" name="tecnica" min="1" max="10" required>

                <label>Fuerza (1-10):</label>
                <input type="number" name="fuerza" min="1" max="10" required>

                <label>Resistencia (1-10):</label>
                <input type="number" name="resistencia" min="1" max="10" required>

                <label>Coordinación (1-10):</label>
                <input type="number" name="coordinacion" min="1" max="10" required>

                <label>Velocidad (1-10):</label>
                <input type="number" name="velocidad" min="1" max="10" required>

                <label>Observaciones:</label>
                <textarea name="observaciones" rows="3"></textarea>

                <button type="submit" name="guardar_progreso">Guardar Evaluación</button>
            <?php endif; ?>
        </form>
    </div>
<?php endif; ?>
<div class="card">
    <h3>📚 Historial de Progreso Técnico</h3>
    <?php
    $historial = $conexion->query("
        SELECT pt.*, c.nombre, c.apellido 
        FROM progreso_tecnico pt
        JOIN clientes c ON pt.cliente_id = c.id
        WHERE pt.profesor_id = $profesor_id
        ORDER BY pt.fecha DESC
        LIMIT 10
    ");
    ?>

    <?php if ($historial->num_rows > 0): ?>
        <table style="width:100%; border-collapse: collapse; margin-top: 15px;">
            <tr style="background-color: #222;">
                <th style="color: gold;">Alumno</th>
                <th style="color: gold;">Disciplina</th>
                <th style="color: gold;">Fecha</th>
                <th style="color: gold;">Técnica</th>
                <th style="color: gold;">Fuerza</th>
                <th style="color: gold;">Resistencia</th>
                <th style="color: gold;">Coordinación</th>
                <th style="color: gold;">Velocidad</th>
                <th style="color: gold;">Observaciones</th>
            </tr>
            <?php while ($fila = $historial->fetch_assoc()): ?>
                <tr>
                    <td><?= $fila['apellido'] . ' ' . $fila['nombre'] ?></td>
                    <td><?= $fila['disciplina'] ?></td>
                    <td><?= $fila['fecha'] ?></td>
                    <td><?= $fila['tecnica'] ?></td>
                    <td><?= $fila['fuerza'] ?></td>
                    <td><?= $fila['resistencia'] ?></td>
                    <td><?= $fila['coordinacion'] ?></td>
                    <td><?= $fila['velocidad'] ?></td>
                    <td><?= nl2br($fila['observaciones']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No hay evaluaciones registradas aún.</p>
    <?php endif; ?>
</div>
<div class="card">
    <h3>📄 Subir Rutina / Plan para Alumno</h3>
    <form method="POST" enctype="multipart/form-data">
        <label>Alumno:</label>
        <select name="cliente_id_plan" required>
            <option value="">Seleccionar</option>
            <?php
            $clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
            while ($c = $clientes->fetch_assoc()):
                echo "<option value='{$c['id']}'>{$c['apellido']} {$c['nombre']}</option>";
            endwhile;
            ?>
        </select>

        <label>Disciplina:</label>
        <input type="text" name="disciplina_plan" required>

        <label>Fecha:</label>
        <input type="date" name="fecha_plan" required>

        <label>Descripción del plan:</label>
        <textarea name="contenido_plan" rows="4"></textarea>

        <label>Subir archivo (opcional):</label>
        <input type="file" name="archivo_plan" accept=".pdf,.jpg,.jpeg,.png">

        <button type="submit" name="guardar_plan">Guardar Plan</button>
    </form>
</div>
<div class="card">
    <h3>🧍 Evaluación Física del Alumno</h3>
    <form method="POST">
        <label>Alumno:</label>
        <select name="cliente_id_eval" required>
            <option value="">Seleccionar</option>
            <?php
            $clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
            while ($c = $clientes->fetch_assoc()):
                echo "<option value='{$c['id']}'>{$c['apellido']} {$c['nombre']}</option>";
            endwhile;
            ?>
        </select>

        <label>Fecha:</label>
        <input type="date" name="fecha_eval" required>

        <label>Peso (kg):</label>
        <input type="number" name="peso" step="0.1" required>

        <label>Altura (cm):</label>
        <input type="number" name="altura" step="0.1" required>

        <label>Edad:</label>
        <input type="number" name="edad" required>

        <label>Tipo de control:</label>
        <select name="tipo_control">
            <option value="inicial">Inicial</option>
            <option value="semanal">Semanal</option>
            <option value="mensual">Mensual</option>
        </select>

        <label>Observaciones:</label>
        <textarea name="observaciones_eval" rows="3"></textarea>

        <button type="submit" name="guardar_eval">Guardar Evaluación</button>
    </form>
</div>
<div class="card">
    <h3>📸 Subir Foto de Evolución</h3>
    <form method="POST" enctype="multipart/form-data">
        <label>Alumno:</label>
        <select name="cliente_id_foto" required>
            <option value="">Seleccionar</option>
            <?php
            $clientes = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = (SELECT gimnasio_id FROM profesores WHERE id = $profesor_id)");
            while ($c = $clientes->fetch_assoc()):
                echo "<option value='{$c['id']}'>{$c['apellido']} {$c['nombre']}</option>";
            endwhile;
            ?>
        </select>

        <label>Fecha de la foto:</label>
        <input type="date" name="fecha_foto" required>

        <label>Etapa / Observación (ej: semana 4):</label>
        <input type="text" name="etapa" required>

        <label>Seleccionar imagen (JPG, PNG):</label>
        <input type="file" name="archivo_foto" accept=".jpg,.jpeg,.png" required>

        <button type="submit" name="guardar_foto">Subir Foto</button>
    </form>
</div>

</body>
</html>
