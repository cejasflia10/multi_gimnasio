<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Intenta conectar con opciones dadas.
 * $opts = ['host','port','user','pass','db','use_ssl'=>bool]
 */
function try_connect(array $opts): mysqli {
    $cx = mysqli_init();
    mysqli_options($cx, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
    @mysqli_options($cx, MYSQLI_OPT_READ_TIMEOUT, 10);

    // Fuerza IPv4 para evitar issues con IPv6
    $host_ip = gethostbyname($opts['host']) ?: $opts['host'];

    if (!empty($opts['use_ssl'])) {
        mysqli_ssl_set($cx, null, null, null, null, null); // SSL sin certs
        mysqli_real_connect(
            $cx, $host_ip, $opts['user'], $opts['pass'], $opts['db'], (int)$opts['port'],
            null, MYSQLI_CLIENT_SSL
        );
    } else {
        mysqli_real_connect(
            $cx, $host_ip, $opts['user'], $opts['pass'], $opts['db'], (int)$opts['port']
        );
    }

    $cx->set_charset('utf8mb4');
    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $cx->query("SET time_zone = '-03:00'");
    return $cx;
}

/** Lee credenciales desde MYSQL_PUBLIC_URL si existe */
function from_public_url(): ?array {
    $url = getenv('MYSQL_PUBLIC_URL'); // mysql://user:pass@host:port/db
    if (!$url) return null;
    $u = parse_url($url);
    return [
        'host' => $u['host'] ?? 'shuttle.proxy.rlwy.net',
        'port' => isset($u['port']) ? (int)$u['port'] : 3306,
        'user' => $u['user'] ?? 'root',
        'pass' => isset($u['pass']) ? urldecode($u['pass']) : '',
        'db'   => isset($u['path']) ? ltrim($u['path'], '/') : 'railway',
    ];
}

/** Candidatos de conexión en orden de preferencia */
$candidates = [];

/* 1) Interno (para app dentro de Railway/Render en el mismo proyecto) */
$candidates[] = [
    'host' => getenv('MYSQLHOST') ?: 'mysql.railway.internal',
    'port' => (int)(getenv('MYSQLPORT') ?: 3306),
    'user' => getenv('MYSQLUSER') ?: 'root',
    'pass' => getenv('MYSQLPASSWORD') ?: 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt',
    'db'   => getenv('MYSQLDATABASE') ?: 'railway',
    'use_ssl' => false,
];

/* 2) Proxy público (desde fuera de Railway): primero por MYSQL_PUBLIC_URL si existe */
if ($p = from_public_url()) {
    $p['use_ssl'] = true;        // muchos proxies piden SSL
    $candidates[] = $p;
} else {
    $candidates[] = [
        'host' => 'shuttle.proxy.rlwy.net',
        'port' => 51676,          // ACTUALIZA si cambia en Railway
        'user' => 'root',
        'pass' => 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt',
        'db'   => 'railway',
        'use_ssl' => true,
    ];
}

/* 3) Proxy público sin SSL como último intento (por si tu proxy no exige SSL) */
$candidates[] = [
    'host' => 'shuttle.proxy.rlwy.net',
    'port' => 51676,
    'user' => 'root',
    'pass' => 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt',
    'db'   => 'railway',
    'use_ssl' => false,
];

/* Ejecuta intentos en cadena hasta conectar */
$errores = [];
foreach ($candidates as $opt) {
    try {
        $conexion = try_connect($opt);
        // echo "✅ Conectado a {$opt['host']}:{$opt['port']} (SSL: " . ($opt['use_ssl']?'sí':'no') . ")";
        return; // listo: $conexion disponible
    } catch (Throwable $e) {
        $errores[] = "{$opt['host']}:{$opt['port']} (SSL: ".($opt['use_ssl']?'sí':'no').") -> ".$e->getMessage();
        // sigue al siguiente candidato
    }
}

/* Si ninguno funcionó, mostrar diagnóstico */
http_response_code(500);
die("❌ No se pudo conectar a MySQL.\n" . implode("\n", $errores));
