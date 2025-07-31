<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    header("Location: cliente_acceso.php");
    exit;
}

// ✅ Validar cliente
$stmt = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=?");
$stmt->bind_param("ii", $cliente_id, $gimnasio_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
if (!$cliente) {
    header("Location: cliente_acceso.php");
    exit;
}

// ✅ Si no completó datos físicos
if ($cliente['datos_completos'] == 0) {
    $mensaje = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_datos_fisicos'])) {
        $peso = $_POST['peso'] ?? '';
        $altura = $_POST['altura'] ?? '';
        $remera = $_POST['talle_remera'] ?? '';
        $pantalon = $_POST['talle_pantalon'] ?? '';
        $calzado = $_POST['talle_calzado'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $enfermedades = $_POST['enfermedades'] ?? '';
        $medicacion = $_POST['medicacion'] ?? '';
        $fecha = date('Y-m-d');

        // ✅ Se agrega gimnasio_id en el INSERT
        $stmtInsert = $conexion->prepare(
            "INSERT INTO datos_fisicos 
            (cliente_id, gimnasio_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones, enfermedades, medicacion) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmtInsert->bind_param(
            "iisssssssss",
            $cliente_id,
            $gimnasio_id,
            $fecha,
            $peso,
            $altura,
            $remera,
            $pantalon,
            $calzado,
            $observaciones,
            $enfermedades,
            $medicacion
        );

        if ($stmtInsert->execute()) {
            $conexion->query("UPDATE clientes SET datos_completos=1 WHERE id=$cliente_id AND gimnasio_id=$gimnasio_id");
            header("Location: panel_cliente.php");
            exit;
        } else {
            $mensaje = "❌ Error al guardar los datos. Intente nuevamente.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8" />
        <title>Completar Datos Físicos</title>
        <style>
            body { background:black; color:gold; font-family:Arial; text-align:center; }
            .formulario { max-width:400px; margin:auto; background:#111; padding:20px; border-radius:10px; border:1px solid gold; }
            input, textarea { width:100%; padding:8px; margin-bottom:10px; }
            button { background:gold; border:none; padding:10px; font-weight:bold; cursor:pointer; width:100%; }
            .mensaje { margin-bottom: 15px; font-weight: bold; color: red; }
            label { display:block; margin-bottom:5px; font-weight:bold; text-align:left; }
        </style>
    </head>
    <body>
        <h2>📋 Completar Datos Físicos</h2>
        <div class="formulario">
            <?php if ($mensaje): ?>
                <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="guardar_datos_fisicos" value="1" />
                <label>Peso (kg):</label>
                <input type="text" name="peso" required />
                <label>Altura (cm):</label>
                <input type="text" name="altura" required />
                <label>Talle Remera:</label>
                <input type="text" name="talle_remera" />
                <label>Talle Pantalón:</label>
                <input type="text" name="talle_pantalon" />
                <label>Talle Calzado:</label>
                <input type="text" name="talle_calzado" />
                <label>Observaciones:</label>
                <textarea name="observaciones"></textarea>
                <label>Enfermedades (si tiene):</label>
                <textarea name="enfermedades"></textarea>
                <label>Medicaciones (si toma):</label>
                <textarea name="medicacion"></textarea>
                <button type="submit">Guardar Datos</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

include 'menu_cliente.php';

$cliente_nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
$hoy = date('Y-m-d');
$fecha_filtro = $_GET['fecha'] ?? $hoy;

// ✅ Consulta membresía filtrando por gimnasio
$stmtMemb = $conexion->prepare("SELECT clases_disponibles, fecha_vencimiento 
                                FROM membresias 
                                WHERE cliente_id=? AND gimnasio_id=? 
                                ORDER BY fecha_vencimiento DESC LIMIT 1");
$stmtMemb->bind_param("ii", $cliente_id, $gimnasio_id);
$stmtMemb->execute();
$membresia = $stmtMemb->get_result()->fetch_assoc();

$alerta_membresia = '';
if ($membresia) {
    $clases = intval($membresia['clases_disponibles']);
    $vencimiento = $membresia['fecha_vencimiento'];
    $dias_restantes = floor((strtotime($vencimiento) - strtotime($hoy)) / 86400);
    if ($clases <= 2 || $dias_restantes <= 3) {
        $alerta_membresia = "⚠️ ¡Atención! Te quedan <strong>$clases clase(s)</strong> y tu plan vence en <strong>$dias_restantes día(s)</strong>.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body { background:black; color:gold; font-family:Arial; padding:20px; }
        h1 { text-align:center; }
        .alerta { background:#ffcc00; color:black; padding:10px; border-radius:6px; max-width:600px; margin:20px auto; }
        .datos { background:#111; padding:15px; border-radius:10px; max-width:600px; margin:auto; border:1px solid gold; }
        .foto img { width:150px; height:150px; border-radius:50%; border:2px solid gold; }
        .btn-qr, .btn-salir { padding:10px 20px; background:#222; color:gold; border:1px solid gold; border-radius:5px; margin:5px; text-decoration:none; display:inline-block; }
        .box { background:#111; padding:15px; border-radius:8px; max-width:600px; margin:30px auto; }
    </style>
</head>
<body>

<h1>👋 Bienvenido <?= htmlspecialchars($cliente_nombre) ?></h1>

<?php if ($alerta_membresia): ?>
    <div class="alerta"><?= $alerta_membresia ?></div>
<?php endif; ?>

<div class="foto" style="text-align:center; margin:20px;">
    <?php
    $foto = $cliente['foto'] ?? '';
    $ruta_foto = "fotos_clientes/" . $foto;
    echo file_exists($ruta_foto) && !empty($foto)
        ? "<img src='$ruta_foto'>"
        : "<img src='fotos_clientes/default.png' style='opacity:0.7;'>";
    ?>
</div>

<div class="datos">
    <p><strong>DNI:</strong> <?= htmlspecialchars($cliente['dni']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
    <p><strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?></p>
    <p><strong>Disciplina:</strong> <?= htmlspecialchars($cliente['disciplina']) ?></p>
</div>

<div style="text-align:center; margin-top:20px;">
    <a class="btn-qr" href="generar_qr_individual.php?id=<?= $cliente['id'] ?>" target="_blank">📲 Generar QR</a>
    <a class="btn-salir" href="cliente_acceso.php?logout=1">🚪 Salir</a>
</div>

<div class="box">
    <form method="GET">
        <label>🗓 Ver reservas del día:</label>
        <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
    </form>
    <h2>📋 Reservas del día <?= htmlspecialchars($fecha_filtro) ?></h2>
    <ul id="contenedor-reservas">Cargando reservas...</ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ulReservas = document.getElementById('contenedor-reservas');
    const fecha = '<?= htmlspecialchars($fecha_filtro) ?>';

    fetch(`reservas_cliente_ajax.php?fecha=${fecha}`)
        .then(res => res.json())
        .then(data => {
            if (!data.length) {
                ulReservas.innerHTML = '<li style="color:gray;">No hay reservas para este día.</li>';
                return;
            }
            ulReservas.innerHTML = '';
            data.forEach(r => {
                const li = document.createElement('li');
                li.innerHTML = `
                    📅 ${r.dia_semana} - 🕒 ${r.hora_inicio}<br>
                    👤 ${r.cliente_apellido} ${r.cliente_nombre}<br>
                    👨‍🏫 ${r.profesor_apellido} ${r.profesor_nombre}
                `;
                ulReservas.appendChild(li);
            });
        })
        .catch(() => {
            ulReservas.innerHTML = '<li style="color:red;">Error al cargar reservas.</li>';
        });
});
</script>

</body>
</html>
