<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { die('Sin BD'); }
@$conexion->set_charset('utf8mb4');
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);

function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  $r=$db->query($sql); if($r){ $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}
function tryq(mysqli $db, string $sql){ @$db->query($sql); }

echo "<pre>== Migraciones ==\n";

/* --- Crear tablas si no existen --- */
tryq($conexion, "CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `evento_id` INT NOT NULL,
  `comprador_nombre` VARCHAR(150) NOT NULL,
  `comprador_email` VARCHAR(190) NOT NULL,
  `comprador_tel`   VARCHAR(50) NULL,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` ENUM('efectivo','transferencia','tarjeta','otro') NOT NULL DEFAULT 'transferencia',
  `alias_usado` VARCHAR(120) NULL,
  `cuenta_destino` VARCHAR(200) NULL,
  `comprobante_path` VARCHAR(255) NULL,
  `estado` ENUM('pendiente','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

tryq($conexion, "CREATE TABLE IF NOT EXISTS `pedidos_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `tipo_id` INT NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 0,
  `precio_unit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  INDEX (`pedido_id`), INDEX(`tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* --- Asegurar columnas nuevas por si tu esquema es viejo --- */
$cols = [
  ['pedidos','metodo_pago',"ALTER TABLE `pedidos` ADD COLUMN `metodo_pago` ENUM('efectivo','transferencia','tarjeta','otro') NOT NULL DEFAULT 'transferencia' AFTER `total`"],
  ['pedidos','alias_usado',"ALTER TABLE `pedidos` ADD COLUMN `alias_usado` VARCHAR(120) NULL AFTER `metodo_pago`"],
  ['pedidos','cuenta_destino',"ALTER TABLE `pedidos` ADD COLUMN `cuenta_destino` VARCHAR(200) NULL AFTER `alias_usado`"],
  ['pedidos','comprobante_path',"ALTER TABLE `pedidos` ADD COLUMN `comprobante_path` VARCHAR(255) NULL AFTER `cuenta_destino`"],
  ['pedidos','estado',"ALTER TABLE `pedidos` ADD COLUMN `estado` ENUM('pendiente','pagado','rechazado') NOT NULL DEFAULT 'pendiente' AFTER `comprobante_path`"],
  ['pedidos','comprador_tel',"ALTER TABLE `pedidos` ADD COLUMN `comprador_tel` VARCHAR(50) NULL AFTER `comprador_email`"],
];
foreach($cols as [$t,$c,$sql]){ if(!has_col($conexion,$t,$c)){ tryq($conexion,$sql); }}

/* --- Corregir FK mal apuntado en pedidos.evento_id --- */
$sql = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='pedidos'
          AND COLUMN_NAME='evento_id'
          AND REFERENCED_TABLE_NAME IS NOT NULL";
$r = $conexion->query($sql);
if ($r) {
  while($fk=$r->fetch_assoc()){
    $cst=$fk['CONSTRAINT_NAME']; $ref=$fk['REFERENCED_TABLE_NAME'];
    if ($ref !== 'eventos_deportivos') {
      // Drop FK que apunta a eventos_publicos
      tryq($conexion, "ALTER TABLE `pedidos` DROP FOREIGN KEY `{$cst}`");
    }
  }
  $r->close();
}
/* intentar crear FK correcto (si falla, lo dejamos sin FK) */
@ $conexion->query("ALTER TABLE `pedidos` ADD CONSTRAINT `pedidos_evento_fk`
                    FOREIGN KEY (`evento_id`) REFERENCES `eventos_deportivos`(`id`)
                    ON DELETE CASCADE");

echo "OK.\n</pre>";
