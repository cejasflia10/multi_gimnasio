# 🏋️‍♂️ Sistema de Gestión Multi-Gimnasio - Fight Academy

Este paquete contiene todos los archivos necesarios para gestionar múltiples gimnasios desde un solo sistema web. Incluye módulos para clientes, membresías, asistencias, profesores, pagos, ventas y estadísticas.

---

## 📦 Archivos incluidos

- `index.php`: Panel de control con estadísticas.
- `login.php`: Inicio de sesión por gimnasio.
- `clientes.php`, `agregar_cliente.php`, etc.: Gestión de clientes.
- `agregar_membresia_multientrada.php`: Carga membresías por DNI, RFID o QR.
- `registrar_asistencia_mixto.php`: Asistencia para clientes y profesores.
- `menu_clientes.html`, `menu_ventas.html`, etc.: Navegación por secciones.
- `estructura_multi_gimnasio.sql`: Archivo SQL para importar la base de datos.

---

## 🛠️ Instalación

### 1. Requisitos

- Hosting con soporte PHP 7.4+
- Base de datos MySQL o MariaDB
- Editor (opcional): Visual Studio Code, Sublime, etc.

### 2. Subida al Hosting

1. Descomprimí el ZIP `sistema_multigimnasio.zip`.
2. Subí todos los archivos a tu servidor mediante FTP o gestor de archivos.
3. Importá el archivo `estructura_multi_gimnasio.sql` desde **phpMyAdmin**.
4. Configurá el archivo `conexion.php` con los datos de tu base de datos:

```php
$conexion = new mysqli("localhost", "usuario", "contraseña", "nombre_base");
```

---

### 3. Uso del Sistema

1. Accedé a `login.php` para iniciar sesión.
2. Cada usuario se vincula a un gimnasio y solo verá sus propios clientes, asistencias y ventas.
3. Navegá usando los menús: Clientes, Membresías, Profesores, Ventas.

---

## 🔐 Seguridad

- Asegurate de proteger `conexion.php` y deshabilitar `display_errors` en producción.
- Usá HTTPS para proteger contraseñas e ingresos por RFID/QR.

---

## 💬 Soporte

Si necesitás ayuda para instalar o ampliar el sistema, podés pedirlo directamente a través del desarrollador.

---

Desarrollado por: Fight Academy Scorpions  
