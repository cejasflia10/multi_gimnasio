<?php
include("conexion.php");

if (isset($_POST['query'])) {
    $query = trim($_POST['query']);
    $sql = "SELECT id, dni, CONCAT(nombre, ' ', apellido) AS nombre FROM clientes 
            WHERE dni LIKE '%$query%' OR nombre LIKE '%$query%' OR apellido LIKE '%$query%' OR rfid_uid LIKE '%$query%' LIMIT 10";
    $result = $conexion->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cliente = json_encode([
                "id" => $row["id"],
                "dni" => $row["dni"],
                "nombre" => $row["nombre"]
            ]);
            echo "<div data-cliente='$cliente'>{$row['nombre']} - DNI: {$row['dni']}</div>";
        }
    } else {
        echo "<div>No se encontraron coincidencias</div>";
    }
}
?>
