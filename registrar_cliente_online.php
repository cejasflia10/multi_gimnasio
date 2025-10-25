<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/conexion.php';

$gimnasio_id = isset($_GET['gimnasio']) ? (int)$_GET['gimnasio'] : 0;

/* ===== AJAX: Chequear DNI existente ===== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'dni') {
    header('Content-Type: application/json; charset=utf-8');
    $dni = trim((string)($_GET['dni'] ?? ''));
    $gym = isset($_GET['gimnasio']) ? (int)$_GET['gimnasio'] : 0;

    if ($dni === '' || $gym <= 0) {
        echo json_encode(['ok'=>false,'exists'=>false]); exit;
    }

    $st = $conexion->prepare("SELECT id, apellido, nombre, telefono, email, fecha_nacimiento, domicilio
                              FROM clientes WHERE gimnasio_id = ? AND dni = ? LIMIT 1");
    $st->bind_param("is", $gym, $dni);
    $st->execute();
    $res = $st->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        echo json_encode([
            'ok' => true,
            'exists' => true,
            'cliente' => [
                'id' => (int)$row['id'],
                'apellido' => (string)$row['apellido'],
                'nombre' => (string)$row['nombre'],
                'telefono' => (string)($row['telefono'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'fecha_nacimiento' => (string)($row['fecha_nacimiento'] ?? ''),
                'domicilio' => (string)($row['domicilio'] ?? ''),
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok'=>true,'exists'=>false]);
    }
    exit;
}

/* ===== Datos del gimnasio ===== */
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

/* ===== Disciplinas ===== */
$disciplinas = [];
if ($gimnasio_id > 0) {
    $st = $conexion->prepare("SELECT id, nombre FROM disciplinas WHERE gimnasio_id = ? ORDER BY nombre");
    $st->bind_param("i", $gimnasio_id);
    $st->execute();
    $r = $st->get_result();
    while ($r && ($fila = $r->fetch_assoc())) $disciplinas[] = $fila;
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

    <!-- Ajustes mínimos, neutros y responsive (no pisan tu estilo) -->
    <style>
        .contenedor{ max-width:560px; margin:16px auto; padding:0 16px 24px; }
        .logo{
            display:block; margin:0 auto 16px; max-width:160px; height:auto; object-fit:contain;
            background:var(--surface,#fff); border:1px solid var(--stroke,#e5e7eb); border-radius:12px; padding:6px;
            box-shadow:var(--shadow,0 1px 2px rgba(0,0,0,.06));
        }
        h2{ text-align:center; margin:6px 0 4px; line-height:1.2; }
        h3{ text-align:center; margin:0 0 14px; line-height:1.2; color:var(--muted,#64748b); font-weight:700; }
        label{ display:block; margin-top:10px; font-weight:600; }
        input, select{
            width:100%; padding:.65rem .75rem; margin-top:6px; border-radius:10px;
            border:1px solid var(--stroke,#e5e7eb); background:var(--surface,#fff); color:var(--ink,#0f172a); outline:none;
        }
        input:focus, select:focus{ box-shadow:0 0 0 3px rgba(245,158,11,.18); }
        .btn{
            display:inline-block; width:100%; margin-top:18px; padding:.75rem 1rem; border-radius:12px;
            border:1px solid var(--stroke,#e5e7eb); background:var(--primary,#f59e0b); color:var(--on-primary,#111827);
            font-weight:800; cursor:pointer;
        }
        .btn[disabled]{ opacity:.65; cursor:not-allowed; }

        /* Bloque “ya registrado” */
        .ya-registrado{
            margin-top:12px; border:1px solid #ef4444; background:rgba(239,68,68,.06);
            color:#991b1b; border-radius:12px; padding:10px 12px;
        }
        .ya-registrado h4{ margin:0 0 8px; }
        .ya-registrado dl{ display:grid; grid-template-columns: auto 1fr; gap:6px 10px; margin:0; }
        .ya-registrado dt{ font-weight:800; }
        .ya-registrado dd{ margin:0; }

        @media (max-width: 420px){
            .contenedor{ padding:0 12px 20px; }
            .logo{ max-width:140px; }
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

        <!-- Bloque informativo si el DNI ya existe (se rellena por JS) -->
        <div id="box-ya-registrado" class="ya-registrado" style="display:none;">
            <h4>❗ Este DNI ya está registrado</h4>
            <dl id="datos-ya-registrado"></dl>
            <small>Si necesitás actualizar datos, comunicate con recepción.</small>
        </div>

        <form id="form-registro" action="guardar_cliente_online.php" method="post" onsubmit="return redirigirDespues()">
            <input type="hidden" name="gimnasio_id" value="<?= (int)$gimnasio_id ?>">

            <label>Apellido:</label>
            <input type="text" name="apellido" required>

            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>DNI:</label>
            <input id="dni" type="number" name="dni" inputmode="numeric" required>

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

            <input id="btn-submit" type="submit" class="btn" value="Registrar Cliente">
        </form>
    </div>

    <script>
        const gymId = <?= (int)$gimnasio_id ?>;
        const inputDni = document.getElementById('dni');
        const btnSubmit = document.getElementById('btn-submit');
        const boxExist = document.getElementById('box-ya-registrado');
        const datosExist = document.getElementById('datos-ya-registrado');

        function setSubmitEnabled(enabled){
            btnSubmit.disabled = !enabled;
            if (!enabled) {
                // Evito submit por Enter accidental
                btnSubmit.setAttribute('aria-disabled','true');
            } else {
                btnSubmit.removeAttribute('aria-disabled');
            }
        }

        function renderDatosExistentes(c){
            if (!c) return;
            const fields = [
                ['Apellido', c.apellido || '—'],
                ['Nombre', c.nombre || '—'],
                ['Teléfono', c.telefono || '—'],
                ['Email', c.email || '—'],
                ['Nacimiento', c.fecha_nacimiento || '—'],
                ['Domicilio', c.domicilio || '—'],
            ];
            datosExist.innerHTML = fields.map(([k,v]) => `<dt>${k}</dt><dd>${String(v)}</dd>`).join('');
        }

        let lastQuery = '';
        async function checkDni(){
            const dni = String(inputDni.value || '').trim();
            if (!dni || !gymId){ 
                boxExist.style.display = 'none';
                setSubmitEnabled(true);
                inputDni.setCustomValidity('');
                return;
            }
            // evita llamadas redundantes
            if (dni === lastQuery) return;
            lastQuery = dni;

            try{
                const url = `<?= htmlspecialchars(basename(__FILE__)) ?>?ajax=dni&gimnasio=${encodeURIComponent(gymId)}&dni=${encodeURIComponent(dni)}`;
                const r = await fetch(url, {cache:'no-store'});
                const j = await r.json();
                if (j && j.ok && j.exists) {
                    // Mostrar datos y bloquear submit
                    renderDatosExistentes(j.cliente);
                    boxExist.style.display = '';
                    setSubmitEnabled(false);
                    inputDni.setCustomValidity('Este DNI ya está registrado en el sistema.');
                } else {
                    // Habilitar registro
                    boxExist.style.display = 'none';
                    setSubmitEnabled(true);
                    inputDni.setCustomValidity('');
                }
            } catch (e){
                // Ante error de red, no bloquear el alta (pero avisar visualmente si querés)
                boxExist.style.display = 'none';
                setSubmitEnabled(true);
                inputDni.setCustomValidity('');
            }
        }

        inputDni.addEventListener('change', checkDni);
        inputDni.addEventListener('input', checkDni);
        inputDni.addEventListener('blur', checkDni);

        function redirigirDespues() {
            // Evita enviar si quedó bloqueado por duplicado
            if (btnSubmit.disabled){
                inputDni.reportValidity();
                return false;
            }
            setTimeout(function () {
                window.location.href = "cliente_acceso.php";
            }, 1000);
            return true;
        }
    </script>
</body>
</html>
