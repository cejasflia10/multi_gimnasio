<?php
/* admin_cena.php — Gestión de Cena de Fin de Año
   Pestañas: Evento | Medios de pago | Concurrencia | Exportar
*/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { header('Location: login.php'); exit; }

/* CSRF */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ----- ACCIONES POST ----- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__form'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf'] ?? '')) { http_response_code(400); exit('❌ CSRF'); }

  $form = $_POST['__form'];

  // Crear/editar evento
  if ($form==='save_evento') {
    $id     = (int)($_POST['id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $fecha  = trim($_POST['fecha'] ?? '');
    $hora   = trim($_POST['hora'] ?? '');
    $lugar  = trim($_POST['lugar'] ?? '');
    $precio = (float)($_POST['precio_cubierto'] ?? 0);
    $sena   = (float)($_POST['sena_minima'] ?? 0);
    $cupo   = (int)($_POST['cupo_total'] ?? 0);
    $estado = ($_POST['estado'] ?? 'activo')==='activo' ? 'activo' : 'inactivo';

    if ($id>0) {
      $stmt=$conexion->prepare("UPDATE cenas_eventos
        SET titulo=?, fecha=?, hora=?, lugar=?, precio_cubierto=?, sena_minima=?, cupo_total=?, estado=?
        WHERE id=? AND gimnasio_id=?");
      if (!$stmt) { die('❌ SQL UPDATE: '.$conexion->error); }
      // s s s s d d i s i i
      $stmt->bind_param('ssssddisii', $titulo,$fecha,$hora,$lugar,$precio,$sena,$cupo,$estado,$id,$gimnasio_id);
      $stmt->execute(); $stmt->close();
    } else {
      $stmt=$conexion->prepare("INSERT INTO cenas_eventos
        (gimnasio_id,titulo,fecha,hora,lugar,precio_cubierto,sena_minima,cupo_total,estado)
        VALUES (?,?,?,?,?,?,?,?,?)");
      if (!$stmt) { die('❌ SQL INSERT: '.$conexion->error); }
      // i s s s s d d i s
      $stmt->bind_param('issssddis', $gimnasio_id,$titulo,$fecha,$hora,$lugar,$precio,$sena,$cupo,$estado);
      $stmt->execute(); $stmt->close();
    }
    header('Location: admin_cena.php?tab=evento&ok=1'); exit;
  }

  // Crear/editar medio de pago
  if ($form==='save_medio') {
    $evento_id = (int)($_POST['evento_id'] ?? 0);
    $mid       = (int)($_POST['id'] ?? 0);
    $nombre    = trim($_POST['nombre'] ?? '');
    $detalle   = trim($_POST['detalle'] ?? '');
    $activo    = isset($_POST['activo']) ? 1 : 0;

    if ($mid>0) {
      $stmt=$conexion->prepare("UPDATE cenas_medios_pago SET nombre=?, detalle=?, activo=? WHERE id=? AND evento_id=? AND gimnasio_id=?");
      if (!$stmt) { die('❌ SQL medios UPDATE: '.$conexion->error); }
      // s s i i i i
      $stmt->bind_param('ssiiii', $nombre,$detalle,$activo,$mid,$evento_id,$gimnasio_id);
      $stmt->execute(); $stmt->close();
    } else {
      $stmt=$conexion->prepare("INSERT INTO cenas_medios_pago (evento_id,gimnasio_id,nombre,detalle,activo) VALUES (?,?,?,?,?)");
      if (!$stmt) { die('❌ SQL medios INSERT: '.$conexion->error); }
      // i i s s i
      $stmt->bind_param('iissi', $evento_id,$gimnasio_id,$nombre,$detalle,$activo);
      $stmt->execute(); $stmt->close();
    }
    header('Location: admin_cena.php?tab=medios&evento_id='.$evento_id.'&ok=1'); exit;
  }

  // Marcar asistió / pago desde listado
  if ($form==='toggle_flags') {
    $rid = (int)($_POST['reserva_id'] ?? 0);
    $asistio = isset($_POST['asistio']) ? 1 : 0;
    $pago    = $_POST['estado_pago'] ?? '';
    if (!in_array($pago, ['pendiente','sena','pagado'], true)) $pago = null;

    if ($rid>0) {
      if ($pago) {
        $stmt=$conexion->prepare("UPDATE cenas_reservas SET asistio=?, estado_pago=?,
          monto_pagado = CASE WHEN ?='pagado' THEN total WHEN ?='sena' AND monto_pagado=0 THEN 0.00 ELSE monto_pagado END
          WHERE id=? AND gimnasio_id=?");
        if (!$stmt) { die('❌ SQL toggle_flags: '.$conexion->error); }
        $stmt->bind_param('isssii', $asistio,$pago,$pago,$pago,$rid,$gimnasio_id);
      } else {
        $stmt=$conexion->prepare("UPDATE cenas_reservas SET asistio=? WHERE id=? AND gimnasio_id=?");
        if (!$stmt) { die('❌ SQL toggle_flags 2: '.$conexion->error); }
        $stmt->bind_param('iii', $asistio,$rid,$gimnasio_id);
      }
      $stmt->execute(); $stmt->close();
    }
    header('Location: admin_cena.php?tab=concurrencia&ok=1'); exit;
  }
}

/* ----- DATA PARA VISTAS ----- */
$tab = $_GET['tab'] ?? 'evento';

/* Eventos del gimnasio (para selects) */
$eventos = [];
$q=$conexion->prepare("SELECT id,titulo,fecha,hora,lugar,precio_cubierto,sena_minima,cupo_total,cupo_reservado,estado
                       FROM cenas_eventos WHERE gimnasio_id=? ORDER BY fecha DESC, hora DESC");
if (!$q) { die('❌ SQL eventos: '.$conexion->error); }
$q->bind_param('i',$gimnasio_id);
$q->execute();
$eventos = $q->get_result()->fetch_all(MYSQLI_ASSOC);
$q->close();

$evento_id_sel = (int)($_GET['evento_id'] ?? ($eventos[0]['id'] ?? 0));
$evento_sel = null;
foreach($eventos as $ev){ if ((int)$ev['id']===$evento_id_sel) { $evento_sel=$ev; break; }}

/* Medios de pago del evento seleccionado */
$medios=[];
if ($evento_id_sel>0) {
  $s=$conexion->prepare("SELECT id,nombre,detalle,activo FROM cenas_medios_pago WHERE evento_id=? AND gimnasio_id=? ORDER BY id DESC");
  if (!$s) { die('❌ SQL medios: '.$conexion->error); }
  $s->bind_param('ii', $evento_id_sel,$gimnasio_id);
  $s->execute();
  $medios=$s->get_result()->fetch_all(MYSQLI_ASSOC);
  $s->close();
}

/* Reservas para concurrencia */
$reservas=[];
if ($evento_id_sel>0) {
  $sql = "SELECT r.id, r.cantidad, r.total, r.estado_pago, r.asistio, r.creado_en,
                 CASE
                   WHEN TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,''))) <> ''
                     THEN TRIM(CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')))
                   ELSE CONCAT('Cliente #', c.id)
                 END AS nombre_cliente,
                 e.titulo, e.fecha, e.hora, e.lugar
          FROM cenas_reservas r
          JOIN cenas_eventos e ON e.id=r.evento_id
          JOIN clientes c ON c.id=r.cliente_id
          WHERE r.gimnasio_id=? AND r.evento_id=?
          ORDER BY r.creado_en DESC";
  $s=$conexion->prepare($sql);
  if (!$s) { die('❌ SQL concurrencia: '.$conexion->error); }
  $s->bind_param('ii', $gimnasio_id,$evento_id_sel);
  $s->execute();
  $reservas=$s->get_result()->fetch_all(MYSQLI_ASSOC);
  $s->close();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Admin Cena de Fin de Año</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0f1320;color:#fff;margin:0}
  .wrap{max-width:1080px;margin:24px auto;padding:16px}
  .tabs{display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid #24314d}
  .tab{padding:10px 14px;border-radius:10px 10px 0 0;background:#141a2a;color:#cbd5e1;cursor:pointer;text-decoration:none;border:1px solid #24314d;border-bottom:0}
  .tab.active{background:#1b243a;color:#fff}
  .card{background:#141a2a;border:1px solid #24314d;border-radius:14px;padding:16px;margin-top:16px}
  label{display:block;margin:.5rem 0 .25rem}
  input,select,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid #2a3550;background:#0d1322;color:#fff}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .btn{padding:10px 14px;border-radius:10px;border:0;background:#3b82f6;color:#fff;cursor:pointer;font-weight:700}
  .btn.alt{background:#475569}
  .mini{font-size:12px;color:#9fb0d3}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #22304d}
  th{text-align:left;color:#cfe0ff}
  .ok{color:#34d399}.warn{color:#fbbf24}.danger{color:#ff6b6b}
  a{color:#93c5fd}
</style>
</head>
<body>
<div class="wrap">
  <h1>🍽️ Administración — Cena de Fin de Año</h1>

  <div class="tabs">
    <a class="tab <?=($tab==='evento'?'active':'')?>" href="?tab=evento">Evento</a>
    <a class="tab <?=($tab==='medios'?'active':'')?>" href="?tab=medios&evento_id=<?=$evento_id_sel?>">Medios de pago</a>
    <a class="tab <?=($tab==='concurrencia'?'active':'')?>" href="?tab=concurrencia&evento_id=<?=$evento_id_sel?>">Concurrencia</a>
    <a class="tab <?=($tab==='exportar'?'active':'')?>" href="?tab=exportar&evento_id=<?=$evento_id_sel?>">Exportar</a>
  </div>

  <!-- SELECTOR DE EVENTO RÁPIDO -->
  <form method="get" class="card" style="display:flex;gap:10px;align-items:end">
    <input type="hidden" name="tab" value="<?=h($tab)?>">
    <div style="flex:1">
      <label>Evento</label>
      <select name="evento_id" onchange="this.form.submit()">
        <?php if(empty($eventos)): ?>
          <option value="">(Sin eventos aún)</option>
        <?php else: foreach($eventos as $ev): ?>
          <option value="<?=$ev['id']?>" <?=$ev['id']==$evento_id_sel?'selected':''?>>
            <?=date('d/m/Y', strtotime($ev['fecha']))?> — <?=h($ev['titulo'])?>
          </option>
        <?php endforeach; endif; ?>
      </select>
    </div>
    <noscript><button class="btn">Cambiar</button></noscript>
  </form>

  <?php if ($tab==='evento'): ?>
    <div class="card">
      <h2>Datos del evento</h2>
      <?php $edit = $evento_sel ?: ['id'=>0,'titulo'=>'','fecha'=>'','hora'=>'21:30:00','lugar'=>'','precio_cubierto'=>0,'sena_minima'=>0,'cupo_total'=>0,'cupo_reservado'=>0,'estado'=>'activo']; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="__form" value="save_evento">
        <input type="hidden" name="id" value="<?=$edit['id']?>">
        <div class="row">
          <div style="flex:2">
            <label>Título</label>
            <input name="titulo" required value="<?=h($edit['titulo'])?>">
          </div>
          <div style="flex:1">
            <label>Fecha</label>
            <input type="date" name="fecha" required value="<?=h($edit['fecha'])?>">
          </div>
          <div style="flex:1">
            <label>Hora</label>
            <input type="time" name="hora" required value="<?=h(substr((string)$edit['hora'],0,5))?>">
          </div>
        </div>
        <div class="row">
          <div style="flex:2">
            <label>Lugar</label>
            <input name="lugar" required value="<?=h($edit['lugar'])?>">
          </div>
          <div style="flex:1">
            <label>Precio cubierto</label>
            <input type="number" step="0.01" name="precio_cubierto" value="<?=h($edit['precio_cubierto'])?>">
          </div>
          <div style="flex:1">
            <label>Seña mínima</label>
            <input type="number" step="0.01" name="sena_minima" value="<?=h($edit['sena_minima'])?>">
          </div>
          <div style="flex:1">
            <label>Cupo total</label>
            <input type="number" name="cupo_total" value="<?=h($edit['cupo_total'])?>">
          </div>
          <div style="flex:1">
            <label>Estado</label>
            <select name="estado">
              <option value="activo" <?=$edit['estado']==='activo'?'selected':''?>>Activo</option>
              <option value="inactivo" <?=$edit['estado']==='inactivo'?'selected':''?>>Inactivo</option>
            </select>
          </div>
        </div>
        <div style="margin-top:12px">
          <button class="btn">Guardar</button>
          <span class="mini">Cupo reservado actual: <strong><?= (int)($edit['cupo_reservado'] ?? 0) ?></strong></span>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($tab==='medios'): ?>
    <div class="card">
      <h2>Medios de pago</h2>
      <?php if($evento_id_sel<=0): ?>
        <p class="mini">Creá primero un evento en la pestaña “Evento”.</p>
      <?php else: ?>
      <form method="post" class="row" style="align-items:flex-end">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="__form" value="save_medio">
        <input type="hidden" name="evento_id" value="<?=$evento_id_sel?>">
        <div style="flex:1">
          <label>Nombre</label>
          <input name="nombre" placeholder="Efectivo / Transferencia / MercadoPago" required>
        </div>
        <div style="flex:2">
          <label>Detalle</label>
          <input name="detalle" placeholder="CBU/Alias o URL de pago">
        </div>
        <div>
          <label>&nbsp;</label>
          <label style="display:flex;gap:6px;align-items:center">
            <input type="checkbox" name="activo" checked> Activo
          </label>
        </div>
        <div><button class="btn">Agregar</button></div>
      </form>

      <table style="margin-top:12px">
        <thead><tr><th>#</th><th>Nombre</th><th>Detalle</th><th>Estado</th><th>Editar</th></tr></thead>
        <tbody>
        <?php if(empty($medios)): ?>
          <tr><td colspan="5" class="mini">Sin medios cargados</td></tr>
        <?php else: foreach($medios as $m): ?>
          <tr>
            <td><?=$m['id']?></td>
            <td><?=h($m['nombre'])?></td>
            <td><?=h($m['detalle'])?></td>
            <td><?= $m['activo']?'<span class="ok">Activo</span>':'<span class="danger">Inactivo</span>' ?></td>
            <td>
              <form method="post" class="row" style="gap:6px;align-items:center">
                <input type="hidden" name="csrf" value="<?=$csrf?>">
                <input type="hidden" name="__form" value="save_medio">
                <input type="hidden" name="id" value="<?=$m['id']?>">
                <input type="hidden" name="evento_id" value="<?=$evento_id_sel?>">
                <input name="nombre" value="<?=h($m['nombre'])?>" style="width:140px">
                <input name="detalle" value="<?=h($m['detalle'])?>" style="width:260px">
                <label class="mini"><input type="checkbox" name="activo" <?=$m['activo']?'checked':''?>> activo</label>
                <button class="btn alt">Guardar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($tab==='concurrencia'): ?>
    <div class="card">
      <h2>Concurrencia / Reservas</h2>
      <table>
        <thead>
          <tr>
            <th>#</th><th>Cliente</th><th>Cant.</th><th>Total</th>
            <th>Pago</th><th>Asistió</th><th>Creado</th><th>Acción</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($reservas)): ?>
          <tr><td colspan="8" class="mini">Sin reservas para este evento.</td></tr>
        <?php else: foreach($reservas as $r): ?>
          <tr>
            <td><?=$r['id']?></td>
            <td><?=h($r['nombre_cliente'])?></td>
            <td><?=$r['cantidad']?></td>
            <td>$<?=number_format($r['total'],2,',','.')?></td>
            <td>
              <form method="post" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="csrf" value="<?=$csrf?>">
                <input type="hidden" name="__form" value="toggle_flags">
                <input type="hidden" name="reserva_id" value="<?=$r['id']?>">
                <select name="estado_pago">
                  <option value="pendiente" <?=$r['estado_pago']==='pendiente'?'selected':''?>>Pendiente</option>
                  <option value="sena" <?=$r['estado_pago']==='sena'?'selected':''?>>Seña</option>
                  <option value="pagado" <?=$r['estado_pago']==='pagado'?'selected':''?>>Pagado</option>
                </select>
            </td>
            <td>
              <label style="display:flex;gap:6px;align-items:center">
                <input type="checkbox" name="asistio" <?=$r['asistio']?'checked':''?>>
                <span class="<?=$r['asistio']?'ok':'warn'?>"><?=$r['asistio']?'Sí':'No'?></span>
              </label>
            </td>
            <td><?=date('d/m/Y H:i', strtotime($r['creado_en']))?></td>
            <td><button class="btn">Guardar</button></td>
              </form>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($tab==='exportar'): ?>
    <div class="card">
      <h2>Exportar</h2>
      <p class="mini">Si tenés <strong>FPDF</strong> en <code>/fpdf/fpdf.php</code>, generamos PDF. Si no, también podés CSV o imprimir.</p>
      <div class="row">
        <form action="export_cena_pdf.php" method="get">
          <input type="hidden" name="evento_id" value="<?=$evento_id_sel?>">
          <button class="btn">Exportar PDF</button>
        </form>
        <form method="post" action="" onsubmit="return exportCSV()">
          <button type="submit" class="btn alt">Exportar CSV</button>
        </form>
        <button class="btn" onclick="window.print()">Imprimir (HTML)</button>
      </div>

      <script>
      function exportCSV(){
        const rows=[["ID","Cliente","Cantidad","Total","EstadoPago","Asistio","Creado"]];
        <?php if(!empty($reservas)): ?>
        const data = <?=json_encode($reservas, JSON_UNESCAPED_UNICODE)?>;
        data.forEach(r=>{
          rows.push([r.id, r.nombre_cliente, r.cantidad, r.total, r.estado_pago, r.asistio? "SI":"NO", r.creado_en]);
        });
        <?php endif; ?>
        let csv = rows.map(r=>r.map(v=>`"${String(v).replaceAll('"','""')}"`).join(",")).join("\n");
        const blob = new Blob([csv], {type:"text/csv;charset=utf-8;"});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href=url; a.download='cena_reservas.csv'; a.click();
        URL.revokeObjectURL(url);
        return false;
      }
      </script>

      <div class="card" style="margin-top:12px">
        <h3>Vista previa</h3>
        <table>
          <thead><tr><th>#</th><th>Cliente</th><th>Cant.</th><th>Total</th><th>Pago</th><th>Asistió</th><th>Creado</th></tr></thead>
          <tbody>
            <?php if(empty($reservas)): ?>
              <tr><td colspan="7" class="mini">Sin datos</td></tr>
            <?php else: foreach($reservas as $r): ?>
              <tr>
                <td><?=$r['id']?></td>
                <td><?=h($r['nombre_cliente'])?></td>
                <td><?=$r['cantidad']?></td>
                <td>$<?=number_format($r['total'],2,',','.')?></td>
                <td><?=$r['estado_pago']?></td>
                <td><?=$r['asistio']?'Sí':'No'?></td>
                <td><?=date('d/m/Y H:i', strtotime($r['creado_en']))?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
