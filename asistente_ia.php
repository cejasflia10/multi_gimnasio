<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$cliente = $conexion->query("SELECT * FROM clientes WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

if (!$cliente) {
    echo "<p style='color:red; padding:20px;'>⚠️ No se encontró el cliente.</p>";
    exit;
}

$progreso = $conexion->query("SELECT * FROM progreso_cliente WHERE cliente_id = $cliente_id ORDER BY fecha DESC LIMIT 1")->fetch_assoc();

$nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
$peso = $progreso['peso_despues'] ?? ($cliente['peso'] ?? 70);
$altura = $cliente['altura'] ?? 1.70;
$enfermedades = $progreso['enfermedades'] ?? '';
$objetivo = strtolower($_GET['objetivo'] ?? 'bajar peso');

$imc = $altura > 0 ? round($peso / ($altura * $altura), 2) : 0;

$mensaje = "Hola $nombre. Tu IMC actual es $imc.\n";
if ($imc < 18.5) {
    $mensaje .= "Estás por debajo del peso recomendado.\n";
} elseif ($imc >= 25) {
    $mensaje .= "Estás por encima del peso recomendado.\n";
} else {
    $mensaje .= "Tu peso está en un rango saludable.\n";
}

$es_diabetico = stripos($enfermedades, 'diabetes') !== false;

// Plan de dieta semanal
function generar_dieta($objetivo, $es_diabetico) {
    $plan = [];

    $comidas = [
        'desayuno' => [
            'subir peso' => 'Tostadas con palta y huevo + batido con leche entera y banana.',
            'bajar peso' => 'Infusión sin azúcar + 2 tostadas integrales con mermelada sin azúcar.',
        ],
        'almuerzo' => [
            'subir peso' => 'Arroz integral con pollo al horno y ensalada + fruta.',
            'bajar peso' => 'Pechuga a la plancha + ensalada verde + gelatina light.',
        ],
        'merienda' => [
            'subir peso' => 'Yogur con granola + fruta seca.',
            'bajar peso' => 'Infusión con leche descremada + 2 galletas de arroz.',
        ],
        'cena' => [
            'subir peso' => 'Pasta con atún y aceite de oliva + pan integral.',
            'bajar peso' => 'Sopa de verduras + tortilla de espinaca al horno.',
        ],
    ];

    if ($es_diabetico) {
        $comidas['desayuno']['bajar peso'] = 'Mate cocido sin azúcar + pan integral con queso descremado.';
        $comidas['merienda']['bajar peso'] = 'Infusión con leche descremada + 1 manzana verde.';
    }

    foreach (['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'] as $dia) {
        $plan[$dia] = [
            'Desayuno' => $comidas['desayuno'][$objetivo],
            'Almuerzo' => $comidas['almuerzo'][$objetivo],
            'Merienda' => $comidas['merienda'][$objetivo],
            'Cena'     => $comidas['cena'][$objetivo],
        ];
    }

    return $plan;
}

$plan_dieta = generar_dieta($objetivo, $es_diabetico);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistente IA - Plan Nutricional</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        .mensaje {
            margin-bottom: 20px;
            background: #111;
            padding: 15px;
            border-radius: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #111;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #222;
        }
        select, button {
            padding: 8px;
            margin-top: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<h2>🤖 Asistente Nutricional con IA</h2>

<form method="GET">
    <label>Objetivo:
        <select name="objetivo">
            <option value="bajar peso" <?= $objetivo == 'bajar peso' ? 'selected' : '' ?>>Bajar peso</option>
            <option value="subir peso" <?= $objetivo == 'subir peso' ? 'selected' : '' ?>>Subir peso</option>
        </select>
    </label>
    <button type="submit">Actualizar Plan</button>
</form>

<div class="mensaje">
    <?= nl2br(htmlspecialchars($mensaje)) ?>
    <?= $es_diabetico ? "<br><strong>⚠️ Recomendaciones especiales para diabetes incluidas.</strong>" : "" ?>
</div>

<table>
    <thead>
        <tr>
            <th>Día</th>
            <th>Desayuno</th>
            <th>Almuerzo</th>
            <th>Merienda</th>
            <th>Cena</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($plan_dieta as $dia => $comidas): ?>
            <tr>
                <td><?= $dia ?></td>
                <td><?= $comidas['Desayuno'] ?></td>
                <td><?= $comidas['Almuerzo'] ?></td>
                <td><?= $comidas['Merienda'] ?></td>
                <td><?= $comidas['Cena'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>
