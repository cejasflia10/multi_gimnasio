
CREATE TABLE pagos_pendientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    plan_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    archivo_comprobante VARCHAR(255) NOT NULL,
    fecha_envio DATETIME NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (plan_id) REFERENCES planes(id)
);
