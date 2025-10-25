<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conexion.php';

$gimnasio_id = isset($_GET['gimnasio']) ? (int)$_GET['gimnasio'] : 0;

// Obtener logo y nombre del gimnasio (consulta segura)
$nombre_gimnasio = 'Gimnasio';
$logo_gimnasio   = 'logo.png';
if ($gimnasio_id > 0) {
    $st = $conexion->prepare("SELECT nombre, logo FROM gimnasios WHERE id = ? LIMIT 1");
    $st->bind_param("i", $gimnasio_id);
    $st->execute();
    $res = $st->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $nombre_gimnasio = $row['nombre'] ?: $nombre_gimnasio;
        $logo_gimnasio   = $row['logo']   ?: $logo_gimnasio;
    }
    $st->close();
}

// Obtener disciplinas del gimnasio (mismo formato)
$disciplinas = [];
if ($gimnasio_id > 0) {
    $st = $conexion->prepare("SELECT id, nombre FROM disciplinas WHERE gimnasio_id = ? ORDER BY nombre");
    $st->bind_param("i", $gimnasio_id);
    $st->execute();
    $r = $st->get_result();
    while ($r && ($fila = $r->fetch_assoc())) {
        $disciplinas[] = $fila;
    }
    $st->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Hoja global -->
    <link rel="stylesheet" href="estilo_unificado.css">

    <!-- Ajustes mínimos, neutros y responsive que NO pisan tu estilo -->
    <style>
        /* Contenedor centrado y responsive */
        .contenedor{
            max-width: 560px;
            margin: 16px auto;
            padding: 0 16px 24px;
        }

        /* Logo responsivo */
        .logo{
            display: block;
            margin: 0 auto 16px auto;
            max-width: 160px;
            height: auto;
            object-fit: contain;
            background: var(--surface, #fff);
            border: 1px solid var(--stroke, #e5e7eb);
            border-radius: 12px;
            padding: 6px;
            box-shadow: var(--shadow, 0 1px 2px rgba(0,0,0,.06));
        }

        /* Títulos sin forzar colores, solo espaciado */
        h2{
            text-align: center;
            margin: 6px 0 4px;
            line-height: 1.2;
        }
        h3{
            text-align: center;
            margin: 0 0 14px;
            line-height: 1.2;
            color: var(--muted, #64748b);
            font-weight: 700;
        }

        /* Etiquetas e inputs fluidos */
        label{ display:block; margin-top: 10px; font-weight: 600; }
        input, select{
            width: 100%;
            padding: .65rem .75rem;
            margin-top: 6px;
            border-radius: 10px;
            border: 1px solid var(--stroke, #e5e7eb);
            background: var(--surface, #fff);
            color: var(--ink, #0f172a);
            outline: none;
        }
        input:focus, select:focus{
            box-shadow: 0 0 0 3px rgba(245,158,11,.18);
        }

        /* Botón principal alineado al diseño unificado */
        .btn{
            display: inline-block;
            width: 100%;
            margin-top: 18px;
            padding: .75rem 1rem;
            border-radius: 12px;
            border: 1px solid var(--stroke, #e5e7eb);
            background: var(--primary, #f59e0b);
            color: var(--on-primary, #111827);
            font-weight: 800;
            cursor: pointer;
        }
        .btn:hover{ filter: brightness(1.03); }

        /* Pequeñas mejoras móviles */
        @media (max-width: 420px){
            .contenedor{ padding: 0 12px 20px; }
            .logo{ max-width: 140px; }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <?php if (!empty($logo_gimnasio)): ?>
            <img src="<?= htmlspecialchars($logo_gimnasio) ?>" alt="Logo Gimnasio" class="logo">
        <?php endif; ?>

        <h2><?= htmlspecialchars($nombre_gimnasio) ?></h2>
        <h3>Registro de Cliente Online</h3>

        <form action="guardar_cliente_online.php" method="post" onsubmit="return redirigirDespues()">
            <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">

            <label>Apellido:</label>
            <input type="text" name="apellido" required>

            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>DNI:</label>
            <input type="number" name="dni" inputmode="numeric" required>

            <label>Fecha de nacimiento:</label>
            <input type="date" name="fecha_nacimiento" required>

            <label>Domicilio:</label>
            <input type="text" name="domicilio" required>

            <label>Teléfono:</label>
            <input type="text" name="telefono" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Disciplina:</label>
            <select name="disciplina" required>
                <option value="">Seleccionar...</option>
                <?php foreach ($disciplinas as $disciplina): ?>
                    <option value="<?= htmlspecialchars($disciplina['nombre']) ?>">
                        <?= htmlspecialchars($disciplina['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="btn" value="Registrar Cliente">
        </form>
    </div>

    <script>
        function redirigirDespues() {
            setTimeout(function () {
                window.location.href = "cliente_acceso.php";
            }, 1000);
            return true;
        }
    </script>
</body>
</html>
