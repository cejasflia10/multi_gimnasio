CREATE TABLE permisos_usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  gimnasio_id INT NOT NULL,
  puede_ver_clientes TINYINT(1) DEFAULT 0,
  puede_editar_clientes TINYINT(1) DEFAULT 0,
  puede_ver_membresias TINYINT(1) DEFAULT 0,
  puede_editar_membresias TINYINT(1) DEFAULT 0,
  puede_ver_ventas TINYINT(1) DEFAULT 0,
  puede_editar_ventas TINYINT(1) DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (gimnasio_id) REFERENCES gimnasios(id)
);
