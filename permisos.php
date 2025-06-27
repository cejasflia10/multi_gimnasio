<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definición global de permisos por rol
$permisos = [
    'superadmin' => [ // Acceso total
        'clientes', 'membresias', 'qr', 'asistencias', 'profesores',
        'ventas', 'configuraciones', 'panel', 'usuarios_gimnasio','configurar_accesos',
        'planes', 'ver_usuarios', 'asistencia_profesor', 'gimnasios','configurar_planes', 'panel_cliente'
    ],
    'admin' => [ // Admin local de un gimnasio
        'clientes', 'membresias', 'qr', 'asistencias', 'profesores',
        'ventas', 'panel', 'usuarios_gimnasio', 'planes', 'ver_usuarios', 'asistencia_profesor'
        // ❌ No incluye: configuraciones, gimnasios
    ],
    'usuario' => [ // Acceso según lo autorizado por superadmin
        'clientes', 'membresias', 'profesores', 'qr', 'ventas', 'panel', 'panel_cliente'
    ],
    'profesor' => [ // Acceso muy limitado
        'clientes', 'membresias', 'qr'
    ],
    'cliente' => [
        'panel_cliente'
    ]
];

// Función para validar permiso por sección
function tiene_permiso($seccion) {
    global $permisos;
    $rol = $_SESSION['rol'] ?? '';

    // Superadmin tiene acceso total
    if ($rol === 'superadmin') return true;

    return in_array($seccion, $permisos[$rol] ?? []);
}
