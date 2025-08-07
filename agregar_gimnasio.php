<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'conexion.php';
include 'menu_horizontal.php';

// Cargamos todos los planes sin filtrar por gimnasio_id
$planes = $conexion->query("SELECT id, nombre, precio FROM planes_gimnasio");

if (!$planes) {
    die("Error en consulta de planes: " . $conexion->error);
}

// Preparar datos para JS
$planes_data = [];
while ($p = $planes->fetch_assoc()) {
    $planes_data[$p['id']] = [
        'nombre' => $p['nombre'],
        'precio' => $p['precio']
    ];
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mostrar lo recibido para debug
    //echo "<pre>POST recibido:\n";
    //print_r($_POST);
    //echo "</pre>";

    // Capturar datos
    $nombre = trim($_POST["nombre"] ?? '');
    $direccion = trim($_POST["direccion"] ?? '');
    $telefono = trim($_POST["telefono"] ?? '');
    $email = trim($_POST["email"] ?? '');

    $fecha_inicio = $_POST["fecha_inicio"] ?? '';
    $fecha_vencimiento = $_POST["fecha_vencimiento"] ?? '';
    $monto_plan = floatval($_POST["monto_plan"] ?? 0);
    $forma_pago = $_POST["forma_pago"] ?? '';
    $plan_id = intval($_POST["plan_id"] ?? 0);

    $usuario = trim($_POST["usuario"] ?? '');
    $email_usuario = trim($_POST["email_usuario"] ?? $email); // si no envían email_usuario, usa email gimnasio
    $clave_texto = trim($_POST["clave"] ?? '');

    $alias = trim($_POST["alias"] ?? '');
    $cuit = trim($_POST["cuit"] ?? '');
    $estado = $_POST["estado"] ?? '';
    $nota_admin = trim($_POST["nota_admin"] ?? '');
    $mensaje_alumno = trim($_POST["mensaje_alumno"] ?? '');
    $redes_sociales = trim($_POST["redes_sociales"] ?? '');

    if ($usuario === '' || $clave_texto === '' || $email_usuario === '') {
        $mensaje = "<p style='color: red;'>El usuario, email y la contraseña son obligatorios.</p>";
    } else {
        $clave = password_hash($clave_texto, PASSWORD_DEFAULT);

        // Insertar gimnasio
        $stmt = $conexion->prepare("INSERT INTO gimnasios 
            (nombre, direccion, telefono, email, fecha_inicio, fecha_vencimiento, monto_plan, forma_pago, plan_id, alias, cuit, estado, nota_admin, mensaje_alumno, redes_sociales) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            die("Error en prepare gimnasios: " . $conexion->error);
        }

        $stmt->bind_param("ssssssdsissssss", 
            $nombre, $direccion, $telefono, $email, 
            $fecha_inicio, $fecha_vencimiento, $monto_plan, $forma_pago, $plan_id,
            $alias, $cuit, $estado, $nota_admin, $mensaje_alumno, $redes_sociales);

        if (!$stmt->execute()) {
            die("Error al insertar gimnasio: " . $stmt->error);
        }

        // Obtenemos el ID del gimnasio recién creado (autoincremental)
        $nuevo_gimnasio_id = $conexion->insert_id;
        $stmt->close();

        // Verificar que usuario no exista ya
        $existe_usuario = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        if (!$existe_usuario) {
            die("Error en prepare existe_usuario: " . $conexion->error);
        }
        $existe_usuario->bind_param("s", $usuario);
        $existe_usuario->execute();
        $res_usuario = $existe_usuario->get_result();
        if ($res_usuario->num_rows > 0) {
            die("<p style='color: red;'>El usuario ya existe. Elige otro.</p>");
        }
        $existe_usuario->close();

        // Insertar usuario en tabla usuarios con gimnasio_id correcto
        $stmt_user = $conexion->prepare("INSERT INTO usuarios (usuario, email, contrasena, rol, gimnasio_id, debe_cambiar_contrasena) VALUES (?, ?, ?, 'cliente_gym', ?, 1)");
        if (!$stmt_user) {
            die("Error en prepare usuarios: " . $conexion->error);
        }
        $stmt_user->bind_param("sssi", $usuario, $email_usuario, $clave, $nuevo_gimnasio_id);
        if (!$stmt_user->execute()) {
            die("Error al insertar usuario: " . $stmt_user->error);
        }
        $stmt_user->close();

        $mensaje = "<p style='color: lime;'>Gimnasio y usuario creados correctamente.</p>";
    }
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($conexion->query("DELETE FROM gimnasios WHERE id = $id")) {
        $mensaje = "<p style='color: lime;'>Gimnasio eliminado correctamente.</p>";
    } else {
        $mensaje = "<p style='color: red;'>Error al eliminar gimnasio: " . $conexion->error . "</p>";
    }
}

$resultado = $conexion->query("SELECT g.*, p.nombre AS nombre_plan 
    FROM gimnasios g 
    LEFT JOIN planes_gimnasio p ON g.plan_id = p.id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gimnasios y Pagos</title>
    <style>
        /* tu CSS aquí */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #999;
            padding: 8px;
        }
        th {
            background-color: #444;
            color: #fff;
        }
        .btn {
            padding: 4px 8px;
            background-color: #666;
            color: #fff;
            text-decoration: none;
            margin-right: 5px;
            border-radius: 3px;
        }
        .btn:hover {
            background-color: #999;
        }
        .volver {
            margin-top: 15px;
            display: inline-block;
        }
    </style>
    <script>
        const planes = <?= json_encode($planes_data) ?>;

        function actualizarPrecio() {
            const select = document.getElementById('plan_id');
            const precio = planes[select.value]?.precio || 0;
            document.getElementById('monto_plan').value = precio;
        }
    </script>
</head>
<body>

<?= $mensaje ?>

<h2>🏢 Agregar Gimnasio</h2>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre del gimnasio" required>
    <input type="text" name="direccion" placeholder="Dirección" required>
    <input type="text" name="telefono" placeholder="Teléfono" required>
    <input type="email" name="email" placeholder="Email del gimnasio" required>

    <input type="email" name="email_usuario" placeholder="Email para usuario" required>

    <input type="text" name="usuario" placeholder="Usuario de acceso" required>
    <input type="password" name="clave" placeholder="Contraseña" required>

    <label>Fecha de Inicio:</label>
    <input type="date" name="fecha_inicio" required>

    <label>Fecha de Vencimiento del Plan:</label>
    <input type="date" name="fecha_vencimiento" required>

    <input type="number" step="0.01" name="monto_plan" id="monto_plan" placeholder="Monto del Plan" required>

    <select name="forma_pago" required>
        <option value="">Forma de Pago</option>
        <option value="Efectivo">Efectivo</option>
        <option value="Transferencia">Transferencia</option>
        <option value="Débito">Débito</option>
        <option value="Crédito">Crédito</option>
    </select>

    <select name="plan_id" id="plan_id" required onchange="actualizarPrecio()">
        <option value="">Seleccionar Plan</option>
        <?php foreach ($planes_data as $id => $p): ?>
            <option value="<?= $id ?>"><?= htmlspecialchars($p['nombre']) ?></option>
        <?php endforeach; ?>
    </select>

    <input type="text" name="alias" placeholder="Alias para transferencia">
    <input type="text" name="cuit" placeholder="CUIT">

    <select name="estado" required>
        <option value="">Estado del gimnasio</option>
        <option value="activo">Activo</option>
        <option value="vencido">Vencido</option>
        <option value="suspendido">Suspendido</option>
    </select>

    <textarea name="nota_admin" placeholder="Nota administrativa interna (solo visible por el admin)"></textarea>
    <textarea name="mensaje_alumno" placeholder="Mensaje visible por los alumnos en su panel"></textarea>
    <textarea name="redes_sociales" placeholder="Redes sociales (Facebook, Instagram, etc)"></textarea>

    <button type="submit">💾 Agregar Gimnasio</button>
</form>

<h2>📋 Listado de Gimnasios</h2>
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Monto</th>
            <th>Forma de Pago</th>
            <th>Plan</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($fila = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?= htmlspecialchars($fila["nombre"]) ?></td>
                <td><?= htmlspecialchars($fila["email"]) ?></td>
                <td><?= htmlspecialchars($fila["telefono"]) ?></td>
                <td><?= $fila["fecha_inicio"] ?? '-' ?></td>
                <td><?= !empty($fila["fecha_vencimiento"]) && $fila["fecha_vencimiento"] != '0000-00-00' ? date('d/m/Y', strtotime($fila["fecha_vencimiento"])) : 'Sin fecha' ?></td>
                <td>$<?= number_format((float)$fila["monto_plan"], 2, ',', '.') ?></td>
                <td><?= $fila["forma_pago"] ?? 'No especificado' ?></td>
                <td><?= $fila["nombre_plan"] ?? 'Sin plan' ?></td>
                <td><?= ucfirst($fila["estado"]) ?></td>
                <td>
                    <a class="btn" href="editar_gimnasio.php?id=<?= $fila['id'] ?>">Editar</a>
                    <a class="btn" href="agregar_gimnasio.php?eliminar=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar este gimnasio?')">Eliminar</a>
                    <a class="btn" href="renovar_gimnasio.php?id=<?= $fila['id'] ?>">Renovar</a>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<a href="index.php" class="btn volver">⬅️ Volver al Menú</a>

</body>
</html>
