<?php
include 'conexion.php';
include 'menu_horizontal.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];

    $conexion->query("INSERT INTO suplementos (nombre, tipo, precio_compra, precio_venta, stock)
                      VALUES ('$nombre', '$tipo', '$precio_compra', '$precio_venta', '$stock')");
    header("Location: ver_suplementos.php");
}
?>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre" required><br>
    <input type="text" name="tipo" placeholder="Tipo (Proteína, Creatina, etc.)"><br>
    <input type="number" step="0.01" name="precio_compra" placeholder="Precio compra"><br>
    <input type="number" step="0.01" name="precio_venta" placeholder="Precio venta"><br>
    <input type="number" name="stock" placeholder="Stock"><br>
    <button type="submit">Agregar</button>
</form>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>
