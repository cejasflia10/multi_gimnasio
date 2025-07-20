<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$id = $_GET['id'] ?? 0;
$id = intval($id);

$result = $conexion->query("SELECT * FROM usuarios WHERE id = $id LIMIT 1");
if ($result->num_rows === 0) {
    die("Usuario no encontrado.");
}
$usuario = $result->fetch_assoc();

// Obtener gimnasios disponibles
$gimnasios = $conexion->query("SELECT id, nombre FROM gimnasios ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        .permisos-box {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            background: #222;
            padding: 10px;
            border-radius: 10px;
        }
        .permisos-box label {
            color: gold;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>👤 Editar Usuario</h2>

    <form action="guardar_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">

        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars((string)($usuario['usuario'] ?? '')) ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars((string)($usuario['email'] ?? '')) ?>">

        <label>Contraseña nueva (opcional)</label>
        <input type="password" name="nueva_contrasena">

        <label>Rol</label>
        <select name="rol" required>
            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
            <option value="profesor" <?= $usuario['rol'] === 'profesor' ? 'selected' : '' ?>>Profesor</option>
        </select>

        <label>Gimnasio asignado</label>
        <select name="gimnasio_id" required>
            <option value="">-- Seleccionar Gimnasio --</option>
            <?php while ($gim = $gimnasios->fetch_assoc()): ?>
                <option value="<?= $gim['id'] ?>" <?= $gim['id'] == $usuario['gimnasio_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($gim['nombre']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Permisos habilitados:</label>
        <div class="permisos-box">
            <?php
            $checks = [
                'permiso_clientes' => 'Clientes',
                'permiso_membresias' => 'Membresías',
                'permiso_profesores' => 'Profesores',
                'permiso_ventas' => 'Ventas',
                'permiso_asistencias' => 'Asistencias',
                'permiso_panel' => 'Panel General',
                'permiso_qr' => 'QR',
                'permiso_turnos' => 'Turnos',
                'permiso_reservas' => 'Reservas',
                'permiso_configuracion' => 'Configuración',
                'permiso_usuarios' => 'Usuarios',
                'permiso_gimnasios' => 'Gimnasios',
                'permiso_pagos' => 'Pagos',
                'permiso_reportes' => 'Reportes',
                'permiso_accesos' => 'Accesos'
            ];
            foreach ($checks as $campo => $texto) {
                $checked = !empty($usuario[$campo]) ? 'checked' : '';
                echo "<label><input type='checkbox' name='$campo' value='1' $checked> $texto</label>";
            }
            ?>
        </div>

        <button type="submit">💾 Guardar Cambios</button>
    </form>

    <form action="eliminar_usuario.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?')">
        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
        <button type="submit" style="background:red; color:white;">🗑️ Eliminar Usuario</button>
    </form>

    <br>
    <a href="ver_usuarios.php" style="color:#ffd600;">⬅ Volver al listado</a>
</div>
</body>
</html>
