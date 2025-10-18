<?php
/* admin_pedidos_indum.php — Panel admin pedidos indumentaria
   - Alta manual con selector de talles (chips) y autollenado de medidas
   - Seña / Pago (efectivo, tarjeta, transferencia con comprobante)
   - Muestra pagos configurados por producto (Alias/CBU/QR/MP) y link para editarlos
   - Listado, filtros, CSV y eliminar
*/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_horizontal.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0){ http_response_code(403); exit('Gimnasio no identificado'); }

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function must_p(mysqli $db,string $sql){ $st=$db->prepare($sql); if(!$st) die('SQL: '.$db->error); return $st; }
function db_has_table(mysqli $db, string $t): bool { $t = $db->real_escape_string($t); $res = $db->query("SHOW TABLES LIKE '{$t}'"); return ($res && $res->num_rows > 0); }

/* ===== Migraciones mínimas ===== */
$conexion->query("CREATE TABLE IF NOT EXISTS ind_pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gimnasio_id INT NOT NULL,
  cliente_id INT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  sena_monto DECIMAL(12,2) NOT NULL DEFAULT 0,
  pago_tipo VARCHAR(40) NOT NULL DEFAULT 'pendiente',
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  comprobante_url VARCHAR(500) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (gimnasio_id), INDEX (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conexion->query("CREATE TABLE IF NOT EXISTS ind_pedido_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NULL,
  nombre VARCHAR(200) NOT NULL,
  talle VARCHAR(50) NULL,
  medidas VARCHAR(255) NULL,
  cantidad INT NOT NULL DEFAULT 1,
  precio_unit DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (pedido_id) REFERENCES ind_pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* Asegurar columnas (idempotentes) */
@$conexion->query("ALTER TABLE ind_pedidos ADD COLUMN sena_monto DECIMAL(12,2) NOT NULL DEFAULT 0");
@$conexion->query("ALTER TABLE ind_pedidos ADD COLUMN comprobante_url VARCHAR(500) NULL");
@$conexion->query("ALTER TABLE ind_pedidos ADD COLUMN pago_tipo VARCHAR(40) NOT NULL DEFAULT 'pendiente'");
@$conexion->query("ALTER TABLE ind_pedidos ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'pendiente'");

/* CSRF */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* ===== Productos (con pagos) para autocompletar en alta ===== */
$productos = [];
if (db_has_table($conexion,'ind_productos')) {
  // Traemos categoría para inferir talles + pagos (alias/QR/MP)
  $sqlp = "
    SELECT p.id, p.titulo, p.precio, p.foto_url, p.categoria,
           pay.alias_cbu, pay.qr_url, pay.mp_link
    FROM ind_productos p
    LEFT JOIN ind_producto_pagos pay ON pay.producto_id = p.id
    WHERE p.gimnasio_id=? AND p.activo=1
    ORDER BY p.titulo
  ";
  $st = must_p($conexion, $sqlp);
  $st->bind_param('i',$gimnasio_id);
  $st->execute();
  $productos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();
}

/* ====== Acciones POST ====== */
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
  $act = $_POST['__a'] ?? '';

  /* ---- Crear pedido ---- */
  if ($act === 'add_pedido') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0); // opcional
    $estado     = trim($_POST['estado'] ?? 'pendiente');
    $pago_tipo  = trim($_POST['pago_tipo'] ?? 'pendiente'); // sena_efectivo, total_transferencia, etc.
    $sena_monto = (float)($_POST['sena_monto'] ?? 0);

    // Ítems (arrays paralelos)
    $prod_id   = $_POST['item_producto_id'] ?? [];
    $nombre    = $_POST['item_nombre'] ?? [];
    $talle     = $_POST['item_talle'] ?? [];
    $medidas   = $_POST['item_medidas'] ?? [];
    $cantidad  = $_POST['item_cantidad'] ?? [];
    $precio    = $_POST['item_precio'] ?? [];

    // Validar al menos un ítem válido
    $items = [];
    $total = 0.0;
    for($i=0; $i<count($nombre); $i++){
      $n = trim((string)($nombre[$i] ?? ''));
      if ($n==='') continue;
      $cid = (int)($prod_id[$i] ?? 0);
      $tal = trim((string)($talle[$i] ?? ''));
      $med = trim((string)($medidas[$i] ?? ''));
      $qty = max(1, (int)($cantidad[$i] ?? 1));
      $pu  = (float)($precio[$i] ?? 0);
      $sub = $qty * $pu;
      $total += $sub;
      $items[] = [
        'producto_id'=>$cid?:null, 'nombre'=>$n, 'talle'=>$tal, 'medidas'=>$med,
        'cantidad'=>$qty, 'precio_unit'=>$pu, 'subtotal'=>$sub
      ];
    }
    if (empty($items)) {
      $err = 'Debes cargar al menos un ítem.';
    }

    // Comprobante (opcional si transferencia)
    $comprobante_url = null;
    $requiere_comprobante = in_array($pago_tipo, ['sena_transferencia','total_transferencia'], true);
    if (!$err && !empty($_FILES['comprobante']['name'])) {
      if ($_FILES['comprobante']['error']===UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','application/pdf'];
        $mime = mime_content_type($_FILES['comprobante']['tmp_name']);
        $size = (int)$_FILES['comprobante']['size'];
        if(!in_array($mime,$allowed,true))      $err='Comprobante: formato no permitido (JPG, PNG o PDF).';
        elseif($size > 5*1024*1024)             $err='Comprobante: tamaño máximo 5 MB.';
        else {
          $dir = __DIR__.'/uploads/comprobantes/';
          if(!is_dir($dir)) mkdir($dir,0777,true);
          $ext = ($mime==='application/pdf')?'.pdf':(($mime==='image/png')?'.png':'.jpg');
          $fname = 'adm_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).$ext;
          $dest = $dir.$fname;
          if(!move_uploaded_file($_FILES['comprobante']['tmp_name'],$dest)){
            $err='No se pudo guardar el comprobante.';
          }else{
            $comprobante_url = 'uploads/comprobantes/'.$fname;
          }
        }
      } else {
        $err = 'Error al subir el comprobante.';
      }
    } elseif ($requiere_comprobante && !$err) {
      $comprobante_url = null; // permitido dejar pendiente
    }

    if (!$err) {
      // Insert pedido
      $st = must_p($conexion, "INSERT INTO ind_pedidos (gimnasio_id, cliente_id, total, sena_monto, pago_tipo, estado, comprobante_url)
                               VALUES (?,?,?,?,?,?,?)");
      $st->bind_param('iiddsss', $gimnasio_id, $cliente_id, $total, $sena_monto, $pago_tipo, $estado, $comprobante_url);
      $ok = $st->execute();
      $pedido_id = $ok ? (int)$st->insert_id : 0;
      $st->close();

      if ($ok && $pedido_id>0) {
        $stI = must_p($conexion, "INSERT INTO ind_pedido_items (pedido_id, producto_id, nombre, talle, medidas, cantidad, precio_unit, subtotal)
                                  VALUES (?,?,?,?,?,?,?,?)");
        foreach($items as $it){
          $pid = $pedido_id;
          $cid = $it['producto_id'];
          $n   = $it['nombre'];
          $tal = $it['talle'];
          $med = $it['medidas'];
          $qty = $it['cantidad'];
          $pu  = $it['precio_unit'];
          $sub = $it['subtotal'];
          $stI->bind_param('iisssidd', $pid, $cid, $n, $tal, $med, $qty, $pu, $sub);
          $stI->execute();
        }
        $stI->close();

        $msg = '✅ Pedido creado (#'.$pedido_id.')';
      } else {
        $err = 'No se pudo crear el pedido.';
      }
    }
  }

  /* ---- Borrar pedido ---- */
  if ($act === 'del_pedido') {
    $pid = (int)($_POST['pedido_id'] ?? 0);
    if ($pid > 0) {
      $st = must_p($conexion, "SELECT comprobante_url FROM ind_pedidos WHERE id=? AND gimnasio_id=? LIMIT 1");
      $st->bind_param('ii', $pid, $gimnasio_id);
      $st->execute();
      $row = $st->get_result()->fetch_assoc();
      $st->close();

      $st = must_p($conexion, "DELETE FROM ind_pedidos WHERE id=? AND gimnasio_id=?");
      $st->bind_param('ii', $pid, $gimnasio_id);
      $ok = $st->execute();
      $st->close();

      if ($ok) {
        if (!empty($row['comprobante_url'])) {
          $url = (string)$row['comprobante_url'];
          if (strpos($url, 'uploads/')===0) {
            $abs = __DIR__ . '/' . $url;
            if (is_file($abs)) @unlink($abs);
          }
        }
        $msg = '✅ Pedido eliminado correctamente.';
      } else {
        $err = '❌ No se pudo eliminar el pedido.';
      }
    }
  }
}

/* ===== Filtros ===== */
$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']??'') ? $_GET['desde'] : date('Y-m-01');
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']??'') ? $_GET['hasta'] : date('Y-m-d');
$estado= trim($_GET['estado'] ?? '');
$pago  = trim($_GET['pago'] ?? '');

$where = "p.gimnasio_id=? AND DATE(p.creado_en) BETWEEN ? AND ?";
$params = [$gimnasio_id,$desde,$hasta]; $types='iss';

if ($estado!==''){ $where.=" AND p.estado=?"; $params[]=$estado; $types.='s'; }
if ($pago!==''){   $where.=" AND p.pago_tipo=?"; $params[]=$pago;   $types.='s'; }

/* ===== Consulta ===== */
$sql = "
 SELECT p.*, c.nombre AS cliente_nombre
 FROM ind_pedidos p
 LEFT JOIN clientes c ON c.id = p.cliente_id
 WHERE $where
 ORDER BY p.creado_en DESC, p.id DESC
";
$st = must_p($conexion,$sql);
$st->bind_param($types, ...$params);
$st->execute();
$pedidos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* ===== Totales ===== */
$total_facturado = 0.0;
$total_senas     = 0.0;
$total_cobrado   = 0.0;
foreach($pedidos as $p){
  $total_facturado += (float)$p['total'];
  $total_senas     += (float)$p['sena_monto'];
  $total_cobrado   += (strpos($p['pago_tipo']??'', 'total_')===0) ? (float)$p['total'] : (float)$p['sena_monto'];
}

/* ===== Export CSV ===== */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=pedidos_'.$desde.'_a_'.$hasta.'.csv');
  $out=fopen('php://output','w');
  fputcsv($out, ['ID','Fecha','Cliente','Estado','Pago','Total','Seña','Cobrado','Comprobante']);
  foreach($pedidos as $p){
    $cobrado = (strpos($p['pago_tipo'],'total_')===0) ? (float)$p['total'] : (float)$p['sena_monto'];
    fputcsv($out, [
      $p['id'], substr($p['creado_en'],0,19), $p['cliente_nombre']??$p['cliente_id'],
      $p['estado'], $p['pago_tipo'], number_format($p['total'],2,'.',''),
      number_format($p['sena_monto'],2,'.',''), number_format($cobrado,2,'.',''), $p['comprobante_url']
    ]);
  }
  fclose($out); exit;
}

/* ===== Productos → JSON para el front ===== */
$PROD_JSON = json_encode($productos, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>🧾 Pedidos Indumentaria</title>
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
  .page-title{ margin:0 0 10px 0; font-weight:900; letter-spacing:.4px;
    background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
    -webkit-background-clip:text; background-clip:text; color:transparent; }
  .card{ background:var(--card); border:1px solid var(--stroke); border-radius:18px; padding:16px; box-shadow:var(--shadow); margin:12px 0; }
  .muted{ color:#64748b; }
  .row{ display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
  input, select{ padding:10px 12px; border-radius:12px; border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink); font-size:14px; }
  .btn{ padding:10px 14px; border-radius:12px; border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink); font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; }
  .btn.danger{ background:linear-gradient(180deg,#fee2e2,#fecaca); }
  .btn.gray{ background:linear-gradient(180deg,#f1f5f9,#e2e8f0); }
  .table-wrap{ width:100%; overflow:auto; -webkit-overflow-scrolling:touch; }
  table.tabla{ width:100%; min-width:980px; border-collapse:collapse; background:#fff; }
  .tabla thead th{ background:#f7fafc; color:#0f172a; position:sticky; top:0; z-index:1; border-bottom:1px solid var(--stroke); text-align:left; }
  .tabla th, .tabla td{ padding:10px 12px; border-bottom:1px solid var(--stroke); vertical-align:middle; }
  .right{ text-align:right; }
  .nowrap{ white-space:nowrap; }
  .tabla tbody tr:hover{ background:#f9fafb; }

  /* Alta */
  .items-grid{ width:100%; border:1px solid var(--stroke); border-radius:12px; padding:10px; background:#fff; }
  .items-grid table{ width:100%; border-collapse:collapse; }
  .items-grid th, .items-grid td{ padding:8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
  .thumb{ width:44px; height:44px; border-radius:8px; object-fit:cover; border:1px solid #e5e7eb; background:#f8fafc; }
  .total-box{ display:flex; gap:14px; flex-wrap:wrap; align-items:center; }
  .total-box > div{ background:#fff; border:1px solid var(--stroke); border-radius:12px; padding:8px 10px; }

  .chips{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
  .chip{ padding:6px 10px; border-radius:999px; border:1px solid #d1d5db; background:#fff; cursor:pointer; font-size:12px; }
  .chip.active{ border-color:#111; box-shadow:0 0 0 2px #1111; }
  .mini{ color:#6b7280; font-size:12px; }
  .pay-box{ background:#f9fafb; border:1px dashed #d1d5db; border-radius:10px; padding:8px; margin-top:6px; }
  .xsmall{ font-size:11px; color:#4b5563; }
  .muted-link{ color:#334155; text-decoration:underline; }
</style>
</head>
<body>
<div class="wrap">
  <h1 class="page-title">🧾 Pedidos de Indumentaria</h1>

  <?php if ($msg): ?><div class="card" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:12px;"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="card" style="background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;border-radius:12px;">⚠️ <?= h($err) ?></div><?php endif; ?>

  <!-- ===== Alta manual de pedido ===== -->
  <div class="card">
    <h2>➕ Nuevo pedido</h2>
    <form method="post" enctype="multipart/form-data" id="frmNuevo">
      <input type="hidden" name="csrf" value="<?=h($csrf)?>">
      <input type="hidden" name="__a" value="add_pedido">

      <div class="row">
        <div>
          <label class="muted">Cliente (ID opcional)</label><br>
          <input type="number" name="cliente_id" placeholder="ID cliente (opcional)">
        </div>
        <div>
          <label class="muted">Estado</label><br>
          <select name="estado">
            <option value="pendiente">Pendiente</option>
            <option value="pagado">Pagado</option>
            <option value="entregado">Entregado</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div>
          <label class="muted">Pago</label><br>
          <select name="pago_tipo" id="pago_tipo">
            <option value="sena_efectivo">Seña (Efectivo)</option>
            <option value="total_efectivo">Total (Efectivo)</option>
            <option value="sena_tarjeta">Seña (Tarjeta 3–6 cuotas en local)</option>
            <option value="total_tarjeta">Total (Tarjeta 3–6 cuotas en local)</option>
            <option value="sena_transferencia">Seña (Transferencia)</option>
            <option value="total_transferencia">Total (Transferencia)</option>
          </select>
          <div class="xsmall">Si es transferencia podés adjuntar comprobante.</div>
        </div>
        <div>
          <label class="muted">Seña ($)</label><br>
          <input type="number" step="0.01" name="sena_monto" id="sena_monto" value="0">
        </div>
        <div id="compPane" style="display:none">
          <label class="muted">Comprobante (JPG/PNG/PDF)</label><br>
          <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
        </div>
      </div>

      <div style="height:8px"></div>
      <div class="items-grid">
        <table id="itemsTbl">
          <thead>
            <tr>
              <th style="min-width:180px">Producto</th>
              <th>Foto</th>
              <th>Título</th>
              <th style="min-width:100px">Talle</th>
              <th style="min-width:190px">Medidas</th>
              <th style="width:90px">Cant.</th>
              <th style="width:120px">Precio</th>
              <th style="width:120px">Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <button class="btn" type="button" onclick="addItem()">+ Agregar ítem</button>
      </div>

      <div style="height:8px"></div>
      <div class="total-box">
        <div><b>Total:</b> $ <span id="totLbl">0,00</span></div>
        <div class="muted">“Seña” se guarda y el reporte calcula “Cobrado” según pago total_* / sena_*.</div>
      </div>

      <div style="height:10px"></div>
      <button class="btn" type="submit">💾 Guardar pedido</button>
    </form>
  </div>

  <!-- ===== Filtros ===== -->
  <div class="card">
    <form class="row" method="get">
      <div>
        <label class="muted">Desde</label><br>
        <input type="date" name="desde" value="<?=h($desde)?>">
      </div>
      <div>
        <label class="muted">Hasta</label><br>
        <input type="date" name="hasta" value="<?=h($hasta)?>">
      </div>
      <div>
        <label class="muted">Estado</label><br>
        <select name="estado">
          <option value="">Todos</option>
          <option value="pendiente"   <?= $estado==='pendiente'?'selected':''; ?>>Pendiente</option>
          <option value="pagado"      <?= $estado==='pagado'?'selected':''; ?>>Pagado</option>
          <option value="entregado"   <?= $estado==='entregado'?'selected':''; ?>>Entregado</option>
          <option value="cancelado"   <?= $estado==='cancelado'?'selected':''; ?>>Cancelado</option>
        </select>
      </div>
      <div>
        <label class="muted">Pago</label><br>
        <select name="pago">
          <option value="">Todos</option>
          <option value="sena_efectivo"       <?= $pago==='sena_efectivo'?'selected':''; ?>>Seña (Efectivo)</option>
          <option value="total_efectivo"      <?= $pago==='total_efectivo'?'selected':''; ?>>Total (Efectivo)</option>
          <option value="sena_transferencia"  <?= $pago==='sena_transferencia'?'selected':''; ?>>Seña (Transferencia)</option>
          <option value="total_transferencia" <?= $pago==='total_transferencia'?'selected':''; ?>>Total (Transferencia)</option>
          <option value="sena_tarjeta"        <?= $pago==='sena_tarjeta'?'selected':''; ?>>Seña (Tarjeta)</option>
          <option value="total_tarjeta"       <?= $pago==='total_tarjeta'?'selected':''; ?>>Total (Tarjeta)</option>
        </select>
      </div>
      <div><br><button class="btn" type="submit">Filtrar</button></div>
      <div><br><a class="btn" href="?desde=<?=h($desde)?>&hasta=<?=h($hasta)?>&estado=<?=h($estado)?>&pago=<?=h($pago)?>&export=csv">⬇️ CSV</a></div>
    </form>
  </div>

  <!-- ===== Totales ===== -->
  <div class="card">
    <div class="total-box">
      <div><strong>Total facturado:</strong> $<?=number_format($total_facturado,2,',','.')?></div>
      <div><strong>Señas:</strong> $<?=number_format($total_senas,2,',','.')?></div>
      <div><strong>Cobrado (según pago):</strong> $<?=number_format($total_cobrado,2,',','.')?></div>
      <div class="muted">“Cobrado” suma Total para pagos “total_*” y Seña para “sena_*”.</div>
    </div>
  </div>

  <!-- ===== Listado ===== -->
  <div class="card">
    <?php if (empty($pedidos)): ?>
      <p class="muted">Sin pedidos en el rango.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tabla" aria-label="Listado de pedidos de indumentaria">
          <thead>
            <tr>
              <th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Pago</th>
              <th class="right">Total</th><th class="right">Seña</th><th>Comp.</th><th class="nowrap">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pedidos as $p): ?>
              <tr>
                <td class="nowrap"><?= (int)$p['id'] ?></td>
                <td class="nowrap"><?= h(substr((string)$p['creado_en'],0,19)) ?></td>
                <td><?= h($p['cliente_nombre'] ?: ('#'.$p['cliente_id'])) ?></td>
                <td><?= h($p['estado']) ?></td>
                <td><?= h(str_replace('_',' ',$p['pago_tipo'])) ?></td>
                <td class="right">$<?= number_format($p['total'],2,',','.') ?></td>
                <td class="right">$<?= number_format($p['sena_monto'],2,',','.') ?></td>
                <td>
                  <?php if (!empty($p['comprobante_url'])): ?>
                    <a class="btn gray" target="_blank" href="<?=h($p['comprobante_url'])?>">Ver</a>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="nowrap">
                  <a class="btn" href="factura_pedido.php?id=<?= (int)$p['id'] ?>">🧾 Imprimir</a>
                  <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar pedido #<?= (int)$p['id'] ?>? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="__a" value="del_pedido">
                    <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn danger" type="submit">🗑️ Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
const PROD = <?= $PROD_JSON ?: '[]' ?>;

// ===== Mostrar/ocultar comprobante según pago =====
const pagoSel = document.getElementById('pago_tipo');
const compPane= document.getElementById('compPane');
function syncComp(){
  const v = pagoSel.value || '';
  compPane.style.display = (v==='sena_transferencia' || v==='total_transferencia') ? 'block' : 'none';
}
pagoSel.addEventListener('change', syncComp); syncComp();

// ===== Utilidades =====
function money(n){ return (Number(n)||0).toLocaleString('es-AR',{minimumFractionDigits:2, maximumFractionDigits:2}); }
function prodById(id){ id=Number(id); return PROD.find(p=>Number(p.id)===id) || null; }
function escapeHtml(s){ const d=document.createElement('div'); d.textContent = s??''; return d.innerHTML; }

// ===== Plantillas de talles =====
function plantillaRemeraUnisex(){ return [
  {t:'XS', a:48, l:67}, {t:'S',a:50,l:70}, {t:'M',a:53,l:73},
  {t:'L',a:55,l:77}, {t:'XL',a:57,l:79}, {t:'2XL',a:63,l:82}, {t:'3XL',a:66,l:84}
];}
function plantillaRemeraMujer(){ return [
  {t:'XS', a:37, l:60}, {t:'S',a:39,l:62}, {t:'M',a:41,l:64},
  {t:'L',a:43,l:66}, {t:'XL',a:45,l:68}, {t:'2XL',a:47,l:70}, {t:'3XL',a:49,l:72}
];}
function plantillaMuay(){ return [
  {t:'XS', c:'77-81', lp:29, ap:29}, {t:'S',c:'82-86',lp:30,ap:29},
  {t:'M', c:'87-91', lp:32, ap:30}, {t:'L',c:'92-96',lp:34,ap:31},
  {t:'XL',c:'97-101',lp:35,ap:33}, {t:'2XL',c:'102-110',lp:37,ap:36}
];}
function plantillaHoodieBuzo(){ return [
  {t:'S', a:52, l:64}, {t:'M',a:54,l:64}, {t:'L',a:57,l:64}, {t:'XL',a:59,l:68}, {t:'XXL',a:61,l:70}
];}
function plantillaHoodiePantalon(){ return [
  {t:'S', c:38, lp:96}, {t:'M',c:44,lp:97}, {t:'L',c:45,lp:99}, {t:'XL',c:47,lp:100}, {t:'XXL',c:48,lp:101}
];}

function guessTemplate(categoria, mujer=false){
  const c = (categoria||'').toLowerCase();
  const isRemera = c.includes('remera') || c.includes('musculosa') || c.includes('camiseta');
  const isMuay   = c.includes('muay') || c.includes('thai');
  const isPant   = c.includes('pantal') || c.includes('short') || c.includes('jogger');
  const isHoodie = c.includes('hoodie') || c.includes('buzo');

  if (isHoodie && !isPant) return {kind:'buzo', data:plantillaHoodieBuzo()};
  if (isHoodie &&  isPant) return {kind:'jogger', data:plantillaHoodiePantalon()};
  if (isMuay)             return {kind:'muay', data:plantillaMuay()};
  if (isRemera)           return {kind:'remera', data: mujer ? plantillaRemeraMujer() : plantillaRemeraUnisex()};
  // fallback remera
  return {kind:'remera', data: mujer ? plantillaRemeraMujer() : plantillaRemeraUnisex()};
}

function formatMedidas(kind, item){
  // Construye string legible para guardar en "medidas"
  if (kind==='muay'){
    return `Cintura ${item.c||item.cintura||item.cintura_cm||''} · Largo pierna ${item.lp||item.largo_pierna||item.largo_pierna_cm||''} · Ancho pierna ${item.ap||item.ancho_pierna||item.ancho_pierna_cm||''} cm`;
  }
  if (kind==='jogger'){
    return `Cintura ${item.c||''} cm · Largo pierna ${item.lp||''} cm`;
  }
  // remera/buzo
  return `Ancho ${item.a||item.ancho||item.ancho_cm||''} cm · Largo ${item.l||item.largo||item.largo_cm||''} cm`;
}

// ===== UI Ítems =====
const tbody = document.querySelector('#itemsTbl tbody');

function recalc(){
  let tot=0; tbody.querySelectorAll('tr').forEach(tr=>{
    const qty = Number(tr.querySelector('.it-qty').value)||0;
    const pu  = Number(tr.querySelector('.it-precio').value)||0;
    const sub = qty*pu;
    tr.querySelector('.it-subtotal').textContent = money(sub);
    tot += sub;
  });
  document.getElementById('totLbl').textContent = money(tot);
}

function buildChips(tr, prod, mujer){
  const holder = tr.querySelector('.chips-holder');
  holder.innerHTML = ''; // reset
  if (!prod) return;

  const template = guessTemplate(prod.categoria, mujer).data;
  const kind     = guessTemplate(prod.categoria, mujer).kind;

  const chips = document.createElement('div');
  chips.className = 'chips';

  template.forEach(it=>{
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'chip';
    b.textContent = it.t;
    b.addEventListener('click', ()=>{
      // activar chip
      chips.querySelectorAll('.chip').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      // set valores a inputs
      tr.querySelector('.it-talle').value = it.t;
      tr.querySelector('.it-medidas').value = formatMedidas(kind, it);
    });
    chips.appendChild(b);
  });

  holder.appendChild(chips);

  const sw = document.createElement('label');
  sw.className = 'mini';
  sw.style.display='flex'; sw.style.alignItems='center'; sw.style.gap='6px'; sw.style.marginTop='6px';
  const chk = document.createElement('input');
  chk.type='checkbox'; chk.checked = !!mujer;
  chk.addEventListener('change', ()=>{
    buildChips(tr, prod, chk.checked);
  });
  sw.appendChild(chk);
  sw.appendChild(document.createTextNode(' Plantilla mujer (remeras)'));
  holder.appendChild(sw);
}

function showProductPayments(tr, prod){
  const box = tr.querySelector('.pay-box');
  if (!prod || (!prod.alias_cbu && !prod.mp_link && !prod.qr_url)) {
    box.innerHTML = '<span class="xsmall">Sin datos de pago configurados para este producto.</span>';
    return;
  }
  let html = '';
  if (prod.alias_cbu) html += `<div><b>Alias/CBU:</b> ${escapeHtml(prod.alias_cbu)}</div>`;
  if (prod.mp_link)  html += `<div><b>MP:</b> <a href="${escapeHtml(prod.mp_link)}" target="_blank" class="muted-link">Pagar aquí</a></div>`;
  if (prod.qr_url)   html += `<div><img src="${escapeHtml(prod.qr_url)}" style="width:90px;height:90px;border:1px solid #e5e7eb;border-radius:8px;object-fit:contain" alt="QR"></div>`;
  html += `<div class="xsmall">Editar pagos: <a class="muted-link" target="_blank" href="admin_indum.php?edit=${prod.id}#pagos">admin_indum.php</a></div>`;
  box.innerHTML = html;
}

function rowTpl(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="it-prod" name="item_producto_id[]">
        <option value="">—</option>
        ${PROD.map(p=>`<option value="${p.id}">${escapeHtml(p.titulo||('Prod #'+p.id))}</option>`).join('')}
      </select>
      <div class="pay-box"></div>
    </td>
    <td><img class="thumb it-foto" src="" alt=""></td>
    <td><input name="item_nombre[]" class="it-nombre" placeholder="Nombre prenda" style="width:220px"></td>
    <td>
      <input name="item_talle[]"  class="it-talle"  placeholder="XS/S/M/L/…">
      <div class="chips-holder"></div>
    </td>
    <td>
      <input name="item_medidas[]" class="it-medidas" placeholder="Ancho 48, Largo 67…">
      <div class="mini">Podés escribir manual o elegir un chip arriba.</div>
    </td>
    <td><input type="number" min="1" step="1" value="1" class="it-qty" name="item_cantidad[]" style="width:80px"></td>
    <td><input type="number" step="0.01" value="0" class="it-precio" name="item_precio[]" style="width:110px"></td>
    <td class="right">$ <span class="it-subtotal">0,00</span></td>
    <td><button class="btn gray it-del" type="button">✕</button></td>
  `;
  // Eventos
  const sel = tr.querySelector('.it-prod');
  const fot = tr.querySelector('.it-foto');
  const nom = tr.querySelector('.it-nombre');
  const qty = tr.querySelector('.it-qty');
  const pre = tr.querySelector('.it-precio');
  sel.addEventListener('change', ()=>{
    const p = prodById(sel.value);
    if (p){
      nom.value = p.titulo || ('Prod #'+p.id);
      pre.value = Number(p.precio||0);
      fot.src   = p.foto_url || '';
      fot.style.visibility = p.foto_url ? 'visible' : 'hidden';
      buildChips(tr, p, false);
      showProductPayments(tr, p);
    } else {
      fot.src=''; fot.style.visibility='hidden';
      tr.querySelector('.chips-holder').innerHTML='';
      showProductPayments(tr, null);
    }
    recalc();
  });
  qty.addEventListener('input', recalc);
  pre.addEventListener('input', recalc);
  tr.querySelector('.it-del').addEventListener('click', ()=>{ tr.remove(); recalc(); });

  // estado inicial
  showProductPayments(tr, null);
  return tr;
}
function addItem(){ tbody.appendChild(rowTpl()); }

// una fila por defecto
addItem();
function recalcAll(){ tbody.querySelectorAll('.it-qty,.it-precio').forEach(()=>{}); recalc(); }
recalcAll();
</script>
</body>
</html>
