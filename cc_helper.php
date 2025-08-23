<?php
// cc_helper.php
// Requiere $conexion (mysqli) ya conectado

/**
 * Devuelve un cliente_id válido. Si no viene, intenta:
 *  1) Buscar por (gimnasio_id, nombre exacto) si hay coincidencia única.
 *  2) Crear cliente temporal y devolver su ID.
 *
 * @param mysqli $conexion
 * @param int    $gimnasio_id
 * @param int    $cliente_id     // puede venir 0
 * @param string $cliente_nombre // requerido si hay que crear
 * @return int cliente_id (>0) o 0 si no se pudo resolver/crear
 */
function ensure_cliente_id(mysqli $conexion, int $gimnasio_id, int $cliente_id, string $cliente_nombre): int {
    if ($cliente_id > 0) return $cliente_id;

    $nombre = trim($cliente_nombre);
    if ($nombre === '' || $gimnasio_id <= 0) return 0;

    // 1) Buscar coincidencia única por nombre dentro del gimnasio
    $sql = "SELECT id FROM clientes WHERE gimnasio_id=? AND (CONCAT(TRIM(apellido), ' ', TRIM(nombre))=? OR nombre=?) LIMIT 2";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('iss', $gimnasio_id, $nombre, $nombre);
    $stmt->execute();
    $rs = $stmt->get_result();
    if ($rs && $rs->num_rows === 1) {
        $row = $rs->fetch_assoc();
        $stmt->close();
        return (int)$row['id'];
    }
    $stmt->close();

    // 2) Crear cliente temporal
    // Intentamos dividir "Apellido Nombre" si tiene espacio; si no, va en nombre.
    $parts = preg_split('/\s+/', $nombre);
    $apellido = '';
    $nombreSolo = $nombre;
    if (count($parts) >= 2) {
        $apellido   = array_shift($parts);
        $nombreSolo = implode(' ', $parts);
    }

    // Si tu tabla clientes NO tiene columna 'temporal', no la uses y sacá ese campo del INSERT.
    $sqlIns = "INSERT INTO clientes (nombre, apellido, gimnasio_id, temporal) VALUES (?, ?, ?, 1)";
    if (!column_exists($conexion, 'clientes', 'temporal')) {
        $sqlIns = "INSERT INTO clientes (nombre, apellido, gimnasio_id) VALUES (?, ?, ?)";
    }

    $stmt2 = $conexion->prepare($sqlIns);
    if (!$stmt2) {
        error_log("[CC] No se pudo preparar INSERT cliente temporal: ".$conexion->error);
        return 0;
    }
    $stmt2->bind_param('ssi', $nombreSolo, $apellido, $gimnasio_id);
    if (!$stmt2->execute()) {
        error_log("[CC] No se pudo crear cliente temporal: ".$stmt2->error);
        $stmt2->close();
        return 0;
    }
    $new_id = (int)$stmt2->insert_id;
    $stmt2->close();
    return $new_id;
}

/**
 * Inserta un DEBE en cc_movimientos, si el monto > 0.
 *
 * @param mysqli $conexion
 * @param int    $gimnasio_id
 * @param int    $cliente_id
 * @param int    $venta_id
 * @param string $fecha      // 'Y-m-d H:i:s'
 * @param string $concepto
 * @param float  $monto_debe
 * @return bool
 */
function cc_insert_debe(mysqli $conexion, int $gimnasio_id, int $cliente_id, int $venta_id, string $fecha, string $concepto, float $monto_debe): bool {
    if ($monto_debe <= 0 || $gimnasio_id <= 0 || $cliente_id <= 0) {
        error_log("[CC] cc_insert_debe: parámetros inválidos (gym:$gimnasio_id cli:$cliente_id debe:$monto_debe)");
        return false;
    }
    // Asegurar tabla
    if (!table_exists($conexion, 'cc_movimientos')) {
        $sql = "CREATE TABLE cc_movimientos (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  gimnasio_id INT NOT NULL,
                  cliente_id INT NOT NULL,
                  venta_id INT NULL,
                  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  concepto VARCHAR(255) NOT NULL,
                  debe DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  haber DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  INDEX (gimnasio_id, cliente_id),
                  INDEX (venta_id)
                )";
        if (!$conexion->query($sql)) {
            error_log("[CC] No se pudo crear cc_movimientos: ".$conexion->error);
            return false;
        }
    }

    $stmt = $conexion->prepare("
        INSERT INTO cc_movimientos (gimnasio_id, cliente_id, venta_id, fecha, concepto, debe, haber)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");
    if (!$stmt) {
        error_log("[CC] Prep cc_movimientos: ".$conexion->error);
        return false;
    }
    $monto = round(max(0, (float)$monto_debe), 2);
    $stmt->bind_param('iiissd', $gimnasio_id, $cliente_id, $venta_id, $fecha, $concepto, $monto);
    $ok = $stmt->execute();
    if (!$ok) { error_log("[CC] Exec cc_movimientos: ".$stmt->error); }
    $stmt->close();
    return $ok;
}

/* ===== Helpers de introspección ===== */
function table_exists(mysqli $conexion, string $table): bool {
    $rs = $conexion->query("SHOW TABLES LIKE '".$conexion->real_escape_string($table)."'");
    return $rs && $rs->num_rows > 0;
}
function column_exists(mysqli $conexion, string $table, string $column): bool {
    $rs = $conexion->query("SHOW COLUMNS FROM `".$conexion->real_escape_string($table)."` LIKE '".$conexion->real_escape_string($column)."'");
    return $rs && $rs->num_rows > 0;
}
