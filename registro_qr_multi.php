<?php
include 'conexion.php';
session_start();

$codigo = $_GET['codigo'] ?? $_POST['codigo'] ?? '';
$codigo = trim($codigo);
$fecha = date('Y-m-d');
$hora = date('H:i:s');

function mostrar($msg, $color = "gold") {
    echo "<html><head><style>
        body { background:#000; color:$color; font-family:Arial; text-align:center; padding-top:100px; font-size:22px }
    </style></head><body>$msg<br><br><a href='scanner_qr.php' style='color:$color'>⬅ Volver</a></body></html>";
    exit;
}

if (!$codigo) {
    mostrar("❌ Código QR vacío", "red");
}

if (strpos($codigo, 'P-') === 0) {
    $dni = substr($codigo, 2);
    $profesor = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni'")->fetch_assoc();

    if (!$profesor) mostrar("❌ Profesor no encontrado", "red");

    $profesor_id = $profesor['id'];

    // Verificar si ya hay ingreso sin salida
    $registro = $conexion->query("
        SELECT * FROM asistencias_profesores
        WHERE profesor_id = $profesor_id AND fecha = '$fecha'
        ORDER BY id DESC LIMIT 1
    ")->fetch_assoc();

    if (!$registro || $registro['hora_egreso']) {
        // Registrar nuevo ingreso
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
        mostrar("✅ Ingreso registrado<br>DNI: $dni<br>Hora: $hora", "lime");
    } else {
        // Registrar egreso
        $id_asistencia = $registro['id'];
        $hora_ingreso = strtotime($registro['hora_ingreso']);
        $hora_egreso = strtotime($hora);
        $horas_trabajadas = round(($hora_egreso - $hora_ingreso) / 3600, 2);

        $conexion->query("
            UPDATE asistencias_profesores
            SET hora_egreso = '$hora', horas_trabajadas = $horas_trabajadas
            WHERE id = $id_asistencia
        ");

        mostrar("✅ Egreso registrado<br>DNI: $dni<br>Duración: $horas_trabajadas hs", "aqua");
    }
} else {
    mostrar("⚠️ QR no válido para profesor", "orange");
}
?>
