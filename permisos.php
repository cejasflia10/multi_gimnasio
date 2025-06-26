<?php
// Definición global de permisos por rol
$permisos = [
    'admin' => ['clientes', 'membresias', 'qr', 'asistencias', 'profesores', 'ventas', 'configuraciones', 'panel'],
    'usuario' => ['clientes', 'membresias', 'qr', 'ventas', 'panel'],
    'profesor' => ['asistencias', 'profesores', 'membresias', 'qr'],
    'cliente' => ['panel_cliente']
];

function tiene_permiso($seccion) {
    global $permisos;
    $rol = $_SESSION['rol'] ?? '';
    return in_array($seccion, $permisos[$rol] ?? []);
}
