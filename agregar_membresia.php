<?php
include 'conexion.php';
include 'menu.php';

// Consultar clientes y disciplinas
$clientes = mysqli_query($conexion, "SELECT * FROM clientes");
$planes = mysqli_query($conexion, "SELECT * FROM planes");
$disciplinas = mysqli_query($conexion, "SELECT * FROM disciplinas");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Membresía</title>
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .formulario {
            margin-left: 240px;
            padding: 20px;
            max-width: 700px;
        }
        h2 {
            color: #f1c40f;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #222;
            color: white;
            border: 1px solid #f1c40f;
            border-radius: 4px;
        }
        button {
            margin-top: 20px;
            background: #f1c40f;
            color: #111;
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #d4ac0d;
        }
    </style>
</head>
<body>
<div class="formulario">
    <h2>Agregar Membresía</h2>
    <form action="guardar_membresia.php" method="POST">

        <label>Cliente:</label>
        <select name="cliente_id" required>
            <option value="">Seleccionar cliente</option>
            <?php while ($cliente = mysqli_fetch_assoc($clientes)) { ?>
                <option value="<?php echo $cliente['id']; ?>">
                    <?php echo $cliente['apellido'] . ', ' . $cliente['nombre'] . ' - DNI: ' . $cliente['dni']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Disciplina:</label>
        <select name="disciplina_id" required>
            <option value="">Seleccionar disciplina</option>
            <?php while ($disciplina = mysqli_fetch_assoc($disciplinas)) { ?>
                <option value="<?php echo $disciplina['id']; ?>">
                    <?php echo $disciplina['nombre']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Plan:</label>
        <select name="plan_id" required>
            <option value="">Seleccionar plan</option>
            <?php while ($plan = mysqli_fetch_assoc($planes)) { ?>
                <option value="<?php echo $plan['id']; ?>">
                    <?php echo $plan['nombre'] . ' - $' . $plan['precio']; ?>
                </option>
            <?php } ?>
        </select>

        <label>Fecha de inicio:</label>
        <input type="date" name="fecha_inicio" required>

        <label>Método de pago:</label>
        <select name="metodo_pago" required>
            <option value="efectivo">Efectivo</option>
            <option value="transferencia">Transferencia</option>
            <option value="tarjeta_debito">Tarjeta Débito</option>
            <option value="tarjeta_credito">Tarjeta Crédito</option>
            <option value="cuenta_corriente">Cuenta Corriente</option>
        </select>

        <button type="submit">Guardar Membresía</button>
    </form>
</div>
</body>
</html>
