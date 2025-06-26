<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definición global de permisos por rol
$permisos = [
    'admin' => [
        'clientes', 'membresias', 'qr', 'agregar_gimnasio','asistencias', 'profesores',
        'ventas', 'configuraciones', 'panel', 'usuarios','ver_gimnasios',
        'planes', 'ver_usuarios', 'asistencia_profesor', 'gimnasios'
    ],
    'usuario' => [
        'clientes', 'membresias', 'qr', 'ventas', 'panel'
    ],
    'profesor' => [
        'asistencias', 'profesores', 'membresias', 'qr'
    ],
    'cliente' => [
        'panel_cliente'
    ]
];

// Función para validar permiso por sección
function tiene_permiso($seccion) {
    global $permisos;
    $rol = $_SESSION['rol'] ?? '';
    return in_array($seccion, $permisos[$rol] ?? []);
}
