<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) { die("Acceso denegado"); }
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
include 'phpqrcode/qrlib.php';

$stmt = $conexion->prepare("
SELECT m.id, c.id AS cliente_id, c.apellido, c.nombre, c.dni, d.nombre AS disciplina, p.nombre AS plan, 
       m.fecha_inicio, m.fecha_vencimiento, m.metodo_pago, m.total_pago
FROM membresias m
INNER JOIN clientes c ON m.cliente_id = c.id
LEFT JOIN disciplinas d ON m.disciplina_id = d.id
LEFT JOIN planes p ON m.plan_id = p.id
WHERE m.gimnasio_id = ?
ORDER BY m.fecha_inicio DESC
");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();
?>
<html><head><style>
body { background:#000; color:#FFD700; font-family:Arial; padding:20px; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { border:1px solid #FFD700; padding:10px; text-align:left; }
th { background:#111; }
tr:hover { background:#222; }
tr.vencida { background:#440000 !important; }
img.qr { width:60px; height:60px; }
</style></head><body>
<h2>Membresías</h2>
<table><tr>
<th>Cliente</th><th>Disciplina</th><th>Plan</th><th>Inicio</th><th>Vencimiento</th>
<th>Días restantes</th><th>Método</th><th>Total</th><th>QR</th><th>Acciones</th>
</tr>
<?php
$hoy = new DateTime();
while ($fila = $resultado->fetch_assoc()) {
    $venc = new DateTime($fila['fecha_vencimiento']);
    $interval = $hoy->diff($venc);
    $dias = (int)$interval->format('%r%a');
    $clase = ($dias < 0) ? 'vencida' : '';
    $dias_texto = ($dias < 0) ? "Vencida hace ".abs($dias)." días" : "$dias días restantes";
    $qr = $fila['dni']; $qr_path = 'temp_qr/' . $qr . '.png';
    if (!file_exists($qr_path)) { QRcode::png($qr, $qr_path, QR_ECLEVEL_L, 3); }
    echo "<tr class='$clase'>";
    echo "<td>{$fila['apellido']}, {$fila['nombre']}</td>";
    echo "<td>".($fila['disciplina'] ?? 'Sin asignar')."</td>";
    echo "<td>".($fila['plan'] ?? 'Sin plan')."</td>";
    echo "<td>{$fila['fecha_inicio']}</td>";
    echo "<td>{$fila['fecha_vencimiento']}</td>";
    echo "<td>$dias_texto</td>";
    echo "<td>{$fila['metodo_pago']}</td>";
    echo "<td>${$fila['total_pago']}</td>";
    echo "<td><img src='$qr_path' class='qr'></td>";
    echo "<td>
        <a href='editar_membresia.php?id={$fila['id']}'>Editar</a> |
        <a href='eliminar_membresia.php?id={$fila['id']}' onclick='return confirm("¿Eliminar?")'>Eliminar</a>
    </td>";
    echo "</tr>";
}
?>
</table>
<a href='exportar_excel_membresias.php'>Exportar a Excel</a> |
<a href='exportar_pdf_membresias.php'>Exportar a PDF</a>
</body></html>
