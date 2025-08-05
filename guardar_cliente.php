<?php
// guardar_cliente.php (versión corregida)
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// Si menu_horizontal.php u otro include ya hizo session_start, la comprobación anterior evita warning.

// Obtener gimnasio_id de la sesión por defecto
$gimnasio_sesion = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso no permitido.");
}

// Recibir campos y sanitizar mínimamente
$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : null;
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');

// Determinar gimnasio_id: si el rol es admin puede venir por POST (selector), si no usamos la sesión
if ($rol === 'admin' && !empty($_POST['gimnasio_id'])) {
    $gimnasio_id = intval($_POST['gimnasio_id']);
} else {
    $gimnasio_id = intval($gimnasio_sesion);
}

// Validaciones básicas
if ($gimnasio_id <= 0) {
    die("Gimnasio inválido.");
}
if ($apellido === '' || $nombre === '' || $dni === '') {
    die("Apellido, nombre y DNI son obligatorios.");
}

// Opcional: comprobar formato fecha_nacimiento (YYYY-MM-DD) si viene
if ($fecha_nacimiento !== null) {
    $d = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
    if (!$d || $d->format('Y-m-d') !== $fecha_nacimiento) {
        // Invalidar fecha -> poner NULL
        $fecha_nacimiento = null;
    }
}

// Buscar si ya existe cliente con ese DNI en el mismo gimnasio
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ? LIMIT 1");
if (!$stmt) {
    die("Error DB: " . $conexion->error);
}
$stmt->bind_param("si", $dni, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$es_online = isset($_POST['es_online']) ? 1 : 0; // si tu formulario marca registro online

if ($row = $res->fetch_assoc()) {
    // Ya existe => actualizar
    $cliente_id = intval($row['id']);

    $upd = $conexion->prepare("
        UPDATE clientes SET
            apellido = ?, nombre = ?, fecha_nacimiento = ?, domicilio = ?, telefono = ?, email = ?, disciplina = ?, nuevo_online = ?
        WHERE id = ? AND gimnasio_id = ?
    ");
    if (!$upd) {
        die("Error prepare UPDATE: " . $conexion->error);
    }

    // Para bind_param, si fecha_nacimiento es null pasamos null (string) y MySQL lo convertirá a NULL si la columna permite NULL.
    // bind_param no acepta null para s — enviamos fecha o null como tipo s y luego en la query MySQL converterá '' a '' not NULL.
    // Para manejar NULL real en prepared statement debemos usar bind_param con variables y si queremos NULL usar $stmt->bind_param dinamico.
    // Aquí simplificamos: si fecha_nacimiento es null lo usamos como NULL por una query alternativa.

    if ($fecha_nacimiento === null) {
        // Ejecutar con una consulta diferente que pone fecha_nacimiento = NULL
        $upd2 = $conexion->prepare("
            UPDATE clientes SET
                apellido = ?, nombre = ?, fecha_nacimiento = NULL, domicilio = ?, telefono = ?, email = ?, disciplina = ?, nuevo_online = ?
            WHERE id = ? AND gimnasio_id = ?
        ");
        if (!$upd2) die("Error prepare UPDATE2: " . $conexion->error);
        $upd2->bind_param("sssssiiii",
            $apellido, $nombre, $domicilio, $telefono, $email, $disciplina, $es_online, $cliente_id, $gimnasio_id
        );
        if (!$upd2->execute()) {
            die("Error al actualizar cliente: " . $upd2->error);
        }
        $upd2->close();
    } else {
        $upd->bind_param("ssssssiii",
            $apellido, $nombre, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $es_online, $cliente_id, $gimnasio_id
        );
        if (!$upd->execute()) {
            die("Error al actualizar cliente: " . $upd->error);
        }
        $upd->close();
    }
} else {
    // No existe => insertar
    // Preparar insert adaptando si fecha_nacimiento es NULL
    if ($fecha_nacimiento === null) {
        $ins = $conexion->prepare("
            INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id, creado_en, nuevo_online)
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        if (!$ins) die("Error prepare INSERT: " . $conexion->error);
        $ins->bind_param("ssisssiii",
            $apellido, $nombre, $dni, $domicilio, $telefono, $email, $disciplina, $gimnasio_id, $es_online
        );
    } else {
        $ins = $conexion->prepare("
            INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id, creado_en, nuevo_online)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        if (!$ins) die("Error prepare INSERT2: " . $conexion->error);
        $ins->bind_param("sssssssss i",
            $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $gimnasio_id, $es_online
        );
        // Note: bind types above might be adjusted; we'll use an alternative to avoid type mismatch below.
        // Instead do a safe insert using real_escape_string to avoid complexity:
        $apellido_e = $conexion->real_escape_string($apellido);
        $nombre_e = $conexion->real_escape_string($nombre);
        $dni_e = $conexion->real_escape_string($dni);
        $fecha_nac_e = $conexion->real_escape_string($fecha_nacimiento);
        $domicilio_e = $conexion->real_escape_string($domicilio);
        $telefono_e = $conexion->real_escape_string($telefono);
        $email_e = $conexion->real_escape_string($email);
        $disciplina_e = $conexion->real_escape_string($disciplina);
        $es_online_int = $es_online ? 1 : 0;
        $sql_insert = "
            INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id, creado_en, nuevo_online)
            VALUES ('$apellido_e', '$nombre_e', '$dni_e', '$fecha_nac_e', '$domicilio_e', '$telefono_e', '$email_e', '$disciplina_e', $gimnasio_id, NOW(), $es_online_int)
        ";
        if (!$conexion->query($sql_insert)) {
            die("Error al insertar cliente: " . $conexion->error);
        }
        $cliente_id = $conexion->insert_id;
        // Saltamos el $ins->execute path
        if (isset($ins) && $ins) $ins->close();
    }

    // Si usamos la rama bind_param (fecha null) ejecutamos
    if (isset($ins) && $ins && $fecha_nacimiento === null) {
        if (!$ins->execute()) {
            die("Error al insertar cliente: " . $ins->error);
        }
        $cliente_id = $ins->insert_id;
        $ins->close();
    }

    // Nota: si entramos por la rama del real_escape_string ya tenemos $cliente_id
}

// --- Generar QR (opcional) ---
$qr_generado = false;
if (isset($cliente_id) && function_exists('QRcode::png')) {
    // crear carpeta qrcodes si no existe
    $qr_dir = __DIR__ . '/qrcodes';
    if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);

    $qr_file = $qr_dir . '/cliente_' . $cliente_id . '.png';
    $url_cliente = (isset($_SERVER['HTTP_HOST']) ? (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') : 'https') . '://' . $_SERVER['HTTP_HOST'] . '/cliente_acceso.php?id=' . $cliente_id;
    // usar phpqrcode si está disponible
    if (file_exists(__DIR__ . '/phpqrcode/qrlib.php')) {
        include_once __DIR__ . '/phpqrcode/qrlib.php';
        QRcode::png($url_cliente, $qr_file, QR_ECLEVEL_L, 4);
        $qr_generado = true;
    } else {
        // alternativa: si la librería no existe, no hacemos nada
        $qr_generado = false;
    }
}

// Redirigir o mostrar mensaje
// Si viene desde registro online, usamos bienvenida; si viene desde panel, ir a ver cliente
if (!empty($_POST['volver_a']) && $_POST['volver_a'] === 'panel') {
    header("Location: ver_cliente.php?id=" . intval($cliente_id));
    exit;
} else {
    header("Location: bienvenida_online.php?cliente_id=" . intval($cliente_id));
    exit;
}
