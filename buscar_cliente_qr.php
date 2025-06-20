
<?php
include 'conexion.php';

if (isset($_GET['dni'])) {
    $dni = $_GET['dni'];
    $stmt = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        echo json_encode([
            'id' => $cliente['id'],
            'nombre' => $cliente['nombre'],
            'apellido' => $cliente['apellido']
        ]);
    } else {
        echo json_encode(['error' => 'No encontrado']);
    }

    $stmt->close();
}
?>
