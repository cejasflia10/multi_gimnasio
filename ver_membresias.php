<?php
// ver_membresias.php — listado responsivo con acciones
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { http_response_code(403); die('Acceso denegado'); }

// Consulta principal
$sql = "
SELECT m.id, m.cliente_id,
       c.apellido, c.nombre,
       p.nombre AS plan,
       m.fecha_inicio, m.fecha_vencimiento,
       m.clases_disponibles,
       m.total,
       m.total_pagado,
       m.saldo_cc
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
  <!-- Look unificado del index -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    /* ====== Ajustes de página alineados al index ====== */
    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
    .page-card{
      background:var(--card); border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
    }
    .page-title{
      margin:0 0 12px 0; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }

    /* Buscador coherente con inputs del unificado */
    .toolbar{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .buscador{ width:280px; max-width:100%; }

    /* ====== Tabla “tipo index” ======
       Usamos paleta clara, bordes sutiles y hover suave.
       Nota: el unificado ya estiliza table/th/td; aquí solo extendemos.
    */
    .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
    table.tabla{
      width:100%; min-width:1000px; border-collapse:collapse; background:#fff;
    }
    .tabla thead th{
      position:sticky; top:0; z-index:1;
      background:#f7fafc; color:#0f172a;
      border-bottom:1px solid var(--stroke);
    }
    .tabla th, .tabla td{
      padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center;
      white-space:nowrap;
    }
    .tabla tbody tr:hover{ background:#f9fafb; }

    /* Acciones */
    .acciones{ display:flex; gap:8px; align-items:center; justify-content:center; }
    .btn{
      background:linear-gradient(180deg,#fff,#f7fafc);
      border:1px solid var(--stroke); border-radius:10px;
      color:var(--ink); padding:8px 12px; font-weight:700; cursor:pointer;
      text-decoration:none; display:inline-block;
    }
    .btn:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }
    .btn-renovar{ border-color:rgba(22,163,74,.35); }
    .btn-editar { border-color:rgba(14,165,233,.35); }
    .btn-eliminar{ border-color:rgba(239,68,68,.35); }

    /* Badges y subinfo */
    .badge-deuda{
      background:#ef4444; color:#fff; border-radius:999px; padding:2px 8px;
      font-size:12px; margin-left:6px;
    }
    .subinfo{ font-size:12px; color:#64748b; margin-top:4px; white-space:nowrap; }

    @media (max-width:768px){
      .page-card{ padding:12px; }
      .tabla{ min-width:900px; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h1 class="page-title">Listado de Membresías</h1>

      <div class="toolbar">
        <input id="q" class="buscador" type="text" placeholder="Buscar membresía... (cliente/plan)" oninput="filtrar()">
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
              <td style="text-align:right">
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
                  <a class="btn" href="detalle_membresia.php?id=<?= (int)$row['id'] ?>">Ver</a>
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
  </div>

<script>
function filtrar() {
  const q = (document.getElementById('q').value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#tabla-membresias tbody tr');
  rows.forEach(tr => {
    const cliente = (tr.children[1]?.innerText || '').toLowerCase();
    const plan    = (tr.children[2]?.innerText || '').toLowerCase();
    tr.style.display = (cliente.includes(q) || plan.includes(q)) ? '' : 'none';
  });
}
</script>
</body>
</html>
