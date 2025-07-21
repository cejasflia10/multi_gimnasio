<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Obtener planes desde planes_gimnasio con precio
$planes = $conexion->query("SELECT id, nombre, precio FROM planes_gimnasio WHERE gimnasio_id = $gimnasio_id");

// Preparar datos para JS
$planes->data_seek(0);
$planes_data = [];
while ($p = $planes->fetch_assoc()) {
    $planes_data[$p['id']] = [
        'nombre' => $p['nombre'],
        'precio' => $p['precio']
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $fecha_inicio = $_POST["fecha_inicio"];
    $fecha_vencimiento = $_POST["fecha_vencimiento"];
    $monto_plan = floatval($_POST["monto_plan"]);
    $forma_pago = $_POST["forma_pago"];
    $plan_id = intval($_POST["plan_id"]);
    $usuario = trim($_POST["usuario"]);
    $clave = password_hash(trim($_POST["clave"]), PASSWORD_DEFAULT);
    $alias = $_POST["alias"];
    $cuit = $_POST["cuit"];
    $estado = $_POST["estado"];
    $nota_admin = $_POST["nota_admin"];
    $mensaje_alumno = $_POST["mensaje_alumno"];
    $redes_sociales = $_POST["redes_sociales"];

    $stmt = $conexion->prepare("INSERT INTO gimnasios 
        (nombre, direccion, telefono, email, fecha_inicio, fecha_vencimiento, monto_plan, forma_pago, plan_id, alias, cuit, estado, nota_admin, mensaje_alumno, redes_sociales) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssssdsissssss", 
        $nombre, $direccion, $telefono, $email, 
        $fecha_inicio, $fecha_vencimiento, $monto_plan, $forma_pago, $plan_id,
        $alias, $cuit, $estado, $nota_admin, $mensaje_alumno, $redes_sociales);
    $stmt->execute();
    $nuevo_gimnasio_id = $stmt->insert_id;
    $stmt->close();

    $stmt_user = $conexion->prepare("INSERT INTO usuarios_gimnasio (nombre, apellido, usuario, clave, gimnasio_id, rol) VALUES (?, ?, ?, ?, ?, 'cliente_gym')");
    $nombre_usuario = $nombre;
    $apellido_usuario = '';
    $stmt_user->bind_param("ssssi", $nombre_usuario, $apellido_usuario, $usuario, $clave, $nuevo_gimnasio_id);
    $stmt_user->execute();
    $stmt_user->close();
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM gimnasios WHERE id = $id");
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
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        h2 {
            color: #FFD700;
        }
        form input, form select, form button, form textarea {
            padding: 10px;
            margin: 5px;
            width: 95%;
            background-color: #222;
            color: #fff;
            border: 1px solid #444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            color: #fff;
        }
        th, td {
            padding: 12px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #333;
            color: #FFD700;
        }
        a.btn {
            padding: 6px 12px;
            text-decoration: none;
            color: black;
            background-color: #FFD700;
            border-radius: 5px;
        }
        .volver {
            margin-top: 20px;
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

<h2>🏢 Agregar Gimnasio</h2>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre del gimnasio" required>
    <input type="text" name="direccion" placeholder="Dirección" required>
    <input type="text" name="telefono" placeholder="Teléfono" required>
    <input type="email" name="email" placeholder="Email" required>

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
