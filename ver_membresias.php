<?php
// ver_membresias.php — listado responsivo con acciones
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); die('Acceso denegado'); }

// Consulta principal: trae total, pagado y saldo_cc (con signo)
$sql = "
SELECT m.id, m.cliente_id,
       c.apellido, c.nombre,
       p.nombre AS plan,
       m.fecha_inicio, m.fecha_vencimiento,
       m.clases_disponibles,
       m.total,              -- total facturado (lo que cuesta la membresía)
       m.total_pagado,       -- lo que entró hoy
       m.saldo_cc            -- negativo = deuda, positivo = a favor
FROM membresias m
JOIN clientes c ON c.id = m.cliente_id
JOIN planes   p ON p.id = m.plan_id
WHERE m.gimnasio_id = {$gimnasio_id}
ORDER BY m.id DESC
";
$res = $conexion->query($sql);
if (!$res) { die('Error al listar membresías: '.$conexion->error); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Listado de Membresías</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{ background:#000; color:gold; font-family:system-ui, Arial, sans-serif; }
    .contenedor{ max-width:1200px; margin:0 auto; padding:16px; }
    h1{ margin:0 0 12px 0; }

    .buscador-wrap{ margin:10px 0 16px; display:flex; gap:10px; align-items:center; }
    .buscador{ width:280px; max-width:100%; padding:10px 12px; border-radius:8px;
               border:1px solid #666; background:#111; color:#ddd; outline:none; }
    .buscador:focus{ border-color:gold; }

    .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{ min-width:1200px; width:100%; border-collapse:collapse; background:#111; }
    .tabla th,.tabla td{ border:1px solid #444; padding:10px; white-space:nowrap; text-align:center; }
    .tabla thead th{ background:#1a1a1a; position:sticky; top:0; z-index:1; }
    .acciones{ display:flex; gap:8px; align-items:center; justify-content:center; }

    .btn{ padding:6px 10px; border-radius:6px; font-weight:700; border:none; cursor:pointer;
          text-decoration:none; display:inline-block; }
    .btn-ver{ background:#334155; color:#fff; }
    .btn-renovar{ background:#16a34a; color:#fff; }
    .btn-editar{ background:#0ea5e9; color:#fff; }
    .btn-eliminar{ background:#ef4444; color:#fff; }

    .badge-deuda{ background:#ef4444; color:#fff; border-radius:999px; padding:2px 8px; font-size:12px; margin-left:6px; }
    .subinfo{ font-size:12px; color:#aab; margin-top:4px; }
  </style>
</head>
<body>
<div class="contenedor">

  <h1>Listado de Membresías</h1>

  <div class="buscador-wrap">
    <input id="q" class="buscador" type="text" placeholder="Buscar membresía... (cliente/plan)" oninput="filtrar()" />
  </div>

  <div class="table-wrap">
    <table class="tabla" id="tabla-membresias">
      <thead>
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th>Plan</th>
          <th>Inicio</th>
          <th>Vencimiento</th>
          <th>Clases</th>
          <th>Total</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $i = 1;
        while ($row = $res->fetch_assoc()):
          $cliente   = trim(($row['apellido'] ?? '').', '.($row['nombre'] ?? ''));
          $planName  = $row['plan'] ?? '';
          $total     = (float)$row['total'];
          $pagado    = (float)$row['total_pagado'];
          $saldo_cc  = (float)$row['saldo_cc'];  // negativo = deuda
          $deuda     = ($saldo_cc < 0);
        ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($cliente) ?></td>
          <td><?= htmlspecialchars($planName) ?></td>
          <td><?= htmlspecialchars($row['fecha_inicio']) ?></td>
          <td><?= htmlspecialchars($row['fecha_vencimiento']) ?></td>
          <td><?= (int)$row['clases_disponibles'] ?></td>
          <td>
            $<?= number_format($total, 2, ',', '.') ?>
            <?php if ($deuda): ?>
              <span class="badge-deuda">Deuda</span>
            <?php endif; ?>
            <div class="subinfo">
              Pagado: $<?= number_format($pagado, 2, ',', '.') ?>
              <?php if ($deuda): ?>
                 · Pendiente: $<?= number_format(abs($saldo_cc), 2, ',', '.') ?>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <div class="acciones">
              <a class="btn btn-ver" href="detalle_membresia.php?id=<?= (int)$row['id'] ?>">Ver</a>
              <a class="btn btn-renovar" href="renovar_membresia.php?id=<?= (int)$row['id'] ?>">Renovar</a>
              <a class="btn btn-editar" href="editar_membresia.php?id=<?= (int)$row['id'] ?>">Editar</a>
              <a class="btn btn-eliminar"
                 href="eliminar_membresia.php?id=<?= (int)$row['id'] ?>"
                 onclick="return confirm('¿Eliminar membresía #<?= (int)$row['id'] ?>?')">Eliminar</a>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function filtrar() {
  const q = (document.getElementById('q').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#tabla-membresias tbody tr');
  rows.forEach(tr => {
    // cliente y plan están en las columnas 2 y 3
    const cliente = (tr.children[1]?.innerText || '').toLowerCase();
    const plan    = (tr.children[2]?.innerText || '').toLowerCase();
    tr.style.display = (cliente.includes(q) || plan.includes(q)) ? '' : 'none';
  });
}
</script>

</body>
</html>
