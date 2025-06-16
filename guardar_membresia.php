<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $disciplina_id = $_POST['disciplina_id'];
    $plan_id = $_POST['plan_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $metodo_pago = $_POST['metodo_pago'];

    // Calcular fecha de vencimiento (30 días desde el inicio)
    $vencimiento = date('Y-m-d', strtotime($fecha_inicio . ' +30 days'));

    $insertar = "INSERT INTO membresias (cliente_id, disciplina_id, plan_id, fecha_inicio, fecha_vencimiento, metodo_pago) 
                 VALUES ('$cliente_id', '$disciplina_id', '$plan_id', '$fecha_inicio', '$vencimiento', '$metodo_pago')";

    if (mysqli_query($conexion, $insertar)) {
        header("Location: membresias.php?mensaje=guardado");
    } else {
        echo "Error al guardar membresía: " . mysqli_error($conexion);
    }
}
?>
