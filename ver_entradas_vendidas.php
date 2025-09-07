<?php
/* ============================================================
   ver_entradas_vendidas.php — Listado y gestión de tickets por evento (responsive)
   Requiere login módulo eventos (evento_usuario_id)
   GET: evento_id (obligatorio), q, estado, usado, tipo_id, export=csv
   POST acciones: usar / revertir / set_estado (habilitar, pagado, rechazar, pendiente, cancelado) / eliminar_ticket
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();

/* Guardia de sesión */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'ver_entradas_vendidas.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

/* Conexión */
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function bind_all_params(mysqli_stmt $st, string $types, array &$vals): bool {
  $params = []; $params[] = &$types; foreach ($vals as $k=>&$v) { $params[] = &$v; }
  return call_user_func_array([$st,'bind_param'], $params);
}
function redirect_self(int $evento_id): void {
  $qs = ['evento_id' => $evento_id];
  foreach (['q','estado','usado','tipo_id'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') { $qs[$k] = $_GET[$k]; }
  }
  header('Location: ?'.http_build_query($qs));
  exit;
}

/* Evento */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }

/* Traer info del evento */
$ev = null;
if ($st=$conexion->prepare("SELECT id,titulo,fecha,hora,lugar FROM eventos_deportivos WHERE id=? LIMIT 1")){
  $st->bind_param('i',$evento_id); $st->execute(); $ev=$st->get_result()->fetch_assoc(); $st->close();
}
if (!$ev){ http_response_code(404); exit('Evento no encontrado.'); }

/* Asegurar columnas de control de acceso en tickets */
if (!has_col($conexion,'tickets','used_at'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_at DATETIME NULL"); }
if (!has_col($conexion,'tickets','used_by'))   { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_by INT NULL"); }
if (!has_col($conexion,'tickets','used_gate')) { @$conexion->query("ALTER TABLE tickets ADD COLUMN used_gate VARCHAR(60) NULL"); }

/* Asegurar que pedidos.estado acepte todos los estados usados (incluye 'pagado') */
$col = $conexion->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME='estado'");
$col = $col ? $col->fetch_assoc() : null;
if ($col) {
  $type = strtolower((string)$col['COLUMN_TYPE']); // ej: enum('pendiente','aprobado',...)
  $estados = ["'pendiente'","'aprobado'","'pagado'","'rechazado'","'cancelado'"];
  foreach ($estados as $e) {
    if (strpos($type, $e) === false) {
      @ $conexion->query("ALTER TABLE `pedidos`
        MODIFY `estado` ENUM('pendiente','aprobado','pagado','rechazado','cancelado')
        NOT NULL DEFAULT 'pendiente'");
      break;
    }
  }
}

/* ===== Acciones (POST) ===== */
$flash_ok=''; $flash_err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['accion'] ?? '';
  $uid    = (int)($_SESSION['evento_usuario_id'] ?? 0);

  /* usar / revertir (marcar uso del ticket) */
  if ($accion === 'usar' || $accion === 'revertir') {
    $code = trim((string)($_POST['code'] ?? ''));
    $gate = trim((string)($_POST['gate'] ?? 'Acceso principal'));
    if ($code===''){ $_SESSION['flash_err']='Falta código.'; redirect_self($evento_id); }

    if ($accion==='usar'){
      $sql = "UPDATE tickets t
              JOIN pedidos p ON p.id=t.pedido_id
              SET t.used_at=NOW(), t.used_by=?, t.used_gate=?
              WHERE t.code=? AND p.evento_id=?";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('issi',$uid,$gate,$code,$evento_id);
        if($st->execute() && $st->affected_rows>0){ $_SESSION['flash_ok']='Ticket marcado como USADO.'; }
        else { $_SESSION['flash_err']='No se pudo marcar como usado (código inválido o de otro evento).'; }
        $st->close();
      } else { $_SESSION['flash_err']='Error interno (prep usar).'; }
    } else {
      $sql = "UPDATE tickets t
              JOIN pedidos p ON p.id=t.pedido_id
              SET t.used_at=NULL, t.used_by=NULL, t.used_gate=NULL
              WHERE t.code=? AND p.evento_id=?";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('si',$code,$evento_id);
        if($st->execute() && $st->affected_rows>0){ $_SESSION['flash_ok']='Uso revertido.'; }
        else { $_SESSION['flash_err']='No se pudo revertir (código inválido o de otro evento).'; }
        $st->close();
      } else { $_SESSION['flash_err']='Error interno (prep revertir).'; }
    }
    redirect_self($evento_id);
  }

  /* Cambiar estado del pedido */
  if ($accion === 'set_estado') {
    $pedido_id   = (int)($_POST['pedido_id'] ?? 0);
    $nuevoEstado = strtolower(trim((string)($_POST['nuevo_estado'] ?? '')));
    $permitidos  = ['aprobado','pagado','rechazado','pendiente','cancelado'];
    if ($pedido_id<=0 || !in_array($nuevoEstado,$permitidos,true)) {
      $_SESSION['flash_err'] = 'Datos inválidos para cambiar estado.'; redirect_self($evento_id);
    }

    // Leer estado actual para evitar falso error por 0 filas afectadas
    $st = $conexion->prepare("SELECT estado FROM pedidos WHERE id=? AND evento_id=? LIMIT 1");
    if (!$st) { $_SESSION['flash_err']='Error interno (leer estado).'; redirect_self($evento_id); }
    $st->bind_param('ii',$pedido_id,$evento_id);
    $st->execute(); $res=$st->get_result(); $row=$res?$res->fetch_assoc():null; $st->close();
    if (!$row){ $_SESSION['flash_err']='Pedido no encontrado para este evento.'; redirect_self($evento_id); }

    $estado_actual = strtolower((string)$row['estado']);
    if ($estado_actual === $nuevoEstado) {
      $_SESSION['flash_ok'] = "El pedido #{$pedido_id} ya estaba en estado {$nuevoEstado}.";
      redirect_self($evento_id);
    }

    $sql="UPDATE pedidos SET estado=? WHERE id=? AND evento_id=?";
    if($st=$conexion->prepare($sql)){
      $st->bind_param('sii',$nuevoEstado,$pedido_id,$evento_id);
      if($st->execute()){
        $_SESSION['flash_ok'] = "Estado del pedido #{$pedido_id} actualizado a {$nuevoEstado}.";
      } else {
        $_SESSION['flash_err']='Error al actualizar: '.h($conexion->error);
      }
      $st->close();
    } else {
      $_SESSION['flash_err']='Error interno (prep estado).';
    }
    redirect_self($evento_id);
  }

  /* Eliminar ticket (no usado) */
  if ($accion === 'eliminar_ticket') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    if ($ticket_id<=0) { $_SESSION['flash_err']='Ticket inválido.'; redirect_self($evento_id); }

    $conexion->begin_transaction();
    try {
      $sql = "SELECT t.id, t.tipo_id, t.used_at, p.id AS pedido_id, tt.precio
              FROM tickets t
              JOIN pedidos p ON p.id=t.pedido_id
              LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
              WHERE t.id=? AND p.evento_id=? FOR UPDATE";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('Prep select ticket.');
      $st->bind_param('ii',$ticket_id,$evento_id);
      $st->execute(); $r=$st->get_result(); $tk=$r?$r->fetch_assoc():null; $st->close();

      if (!$tk) throw new Exception('Ticket no pertenece a este evento.');
      if (!empty($tk['used_at'])) throw new Exception('No se puede eliminar: el ticket ya fue usado.');

      $tipo_id   = (int)$tk['tipo_id'];
      $pedido_id = (int)$tk['pedido_id'];
      $precio    = (float)($tk['precio'] ?? 0);

      $st = $conexion->prepare("DELETE FROM tickets WHERE id=? LIMIT 1");
      if (!$st) throw new Exception('Prep delete.');
      $st->bind_param('i',$ticket_id);
      $st->execute();
      if ($st->affected_rows<=0) { $st->close(); throw new Exception('No se pudo eliminar el ticket.'); }
      $st->close();

      $st = $conexion->prepare("UPDATE tickets_tipos SET stock_disponible=stock_disponible+1 WHERE id=? AND evento_id=?");
      if (!$st) throw new Exception('Prep restock.');
      $st->bind_param('ii',$tipo_id,$evento_id);
      $st->execute(); $st->close();

      $st = $conexion->prepare("UPDATE pedidos SET total = GREATEST(0, total - ?) WHERE id=?");
      if (!$st) throw new Exception('Prep desc total.');
      $st->bind_param('di',$precio,$pedido_id);
      $st->execute(); $st->close();

      $conexion->commit();
      $_SESSION['flash_ok'] = "Ticket #{$ticket_id} eliminado. Stock repuesto y total actualizado.";
    } catch(Exception $e) {
      $conexion->rollback();
      $_SESSION['flash_err'] = h($e->getMessage());
    }
    redirect_self($evento_id);
  }
}

/* ===== Filtros ===== */
$q       = trim((string)($_GET['q'] ?? ''));
$estado  = trim((string)($_GET['estado'] ?? ''));
$usado   = trim((string)($_GET['usado'] ?? ''));
$tipo_id = (int)($_GET['tipo_id'] ?? 0);

$where = ["p.evento_id = ?"];
$bindTy = "i";
$bindVl = [$evento_id];

if ($q!==''){
  $where[] = "(t.code LIKE CONCAT('%',?,'%') OR p.comprador_email LIKE CONCAT('%',?,'%') OR p.comprador_nombre LIKE CONCAT('%',?,'%'))";
  $bindTy .= "sss"; array_push($bindVl, $q,$q,$q);
}
if ($estado!==''){
  $where[] = "p.estado = ?"; $bindTy .= "s"; $bindVl[] = $estado;
}
if ($usado==='1'){ $where[] = "t.used_at IS NOT NULL"; }
if ($usado==='0'){ $where[] = "t.used_at IS NULL"; }
if ($tipo_id>0){ $where[] = "t.tipo_id = ?"; $bindTy .= "i"; $bindVl[] = $tipo_id; }

$wsql = implode(' AND ', $where);

/* Tipos (para filtro) */
$tipos = [];
if ($st=$conexion->prepare("SELECT id,nombre FROM tickets_tipos WHERE evento_id=? ORDER BY precio ASC, id ASC")){
  $st->bind_param('i',$evento_id); $st->execute();
  $tipos = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}

/* Consulta principal (⚠️ sin t.qr_path para evitar error si no existe la columna) */
$sql = "SELECT
          t.id, t.code, t.used_at, t.used_by, t.used_gate,
          tt.nombre AS tipo_nombre, tt.precio,
          p.id AS pedido_id, p.estado AS pedido_estado, p.origen,
          p.comprador_nombre, p.comprador_email, p.created_at
        FROM tickets t
        JOIN pedidos p ON p.id=t.pedido_id
        LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
        WHERE $wsql
        ORDER BY t.id DESC";

$st = $conexion->prepare($sql);
if (!$st) { http_response_code(500); exit("SQL prepare error: ".h($conexion->error)."<br><small>$sql</small>"); }
bind_all_params($st, $bindTy, $bindVl);
$st->execute(); $res = $st->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* Totales usados/no usados */
$counts = ['todos'=>0,'usados'=>0,'libres'=>0];
if ($rows) {
  $counts['todos'] = count($rows);
  foreach($rows as $r){ if (!empty($r['used_at'])) $counts['usados']++; else $counts['libres']++; }
}

/* Export CSV */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="tickets_evento_'.$evento_id.'.csv"');
  $out = fopen('php://output','w');
  fputcsv($out, ['TicketID','Code','Tipo','Precio','PedidoID','EstadoPedido','Origen','Comprador','Email','Usado','UsedAt','Gate','CreatedAt']);
  foreach($rows as $r){
    fputcsv($out, [
      $r['id'], $r['code'], $r['tipo_nombre'],
      number_format((float)($r['precio'] ?? 0),2,'.',''),
      $r['pedido_id'], $r['pedido_estado'], $r['origen'],
      $r['comprador_nombre'], $r['comprador_email'],
      empty($r['used_at'])?'NO':'SI', $r['used_at'], $r['used_gate'] ?? '', $r['created_at']
    ]);
  }
  fclose($out); exit;
}

/* Flash (PRG) */
if (!empty($_SESSION['flash_ok']))  { $flash_ok  = $_SESSION['flash_ok'];  unset($_SESSION['flash_ok']); }
if (!empty($_SESSION['flash_err'])) { $flash_err = $_SESSION['flash_err']; unset($_SESSION['flash_err']); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Entradas vendidas — <?= h($ev['titulo']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0a0a0a; --fg:#f6f6f6; --mut:#c9c9c9; --brand:#d4af37;
      --card:#111; --bd:#222; --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
      --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    a:focus{outline:2px dashed var(--brand); outline-offset:2px}
    .wrap{max-width:1200px;margin:18px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--bd);background:#151515;color:var(--brand);text-decoration:none;cursor:pointer;font-weight:600}
    .btn.gray{background:#1b1b1b;color:#ddd}
    .btn.red{background:#7a1f1f;color:#fff;border-color:#8f2a2a}
    .btn.green{background:#0f3; color:#000; border-color:#0b0}
    .btn.gold{background:#332b00; color:#ffde6a; border-color:#665200}
    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}
    input,select,button{padding:.56rem .7rem;border-radius:10px;border:1px solid var(--bd);background:#101010;color:#fg}
    button.btn{border:1px solid var(--bd)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:900px}
    thead th{position:sticky; top:0; background:#121212; color:#brand; text-align:left; padding:.7rem .65rem; border-bottom:1px solid var(--bd); z-index:1;}
    td{padding:.6rem .65rem;border-bottom:1px solid var(--bd);vertical-align:middle}
    code{background:#000; padding:.15rem .35rem; border-radius:6px; border:1px solid #333}
    @media(hover:hover){ tbody tr:hover{background:#101010} }
    @media (max-width: 860px){
      .table-wrap{border:0}
      table{border-collapse:separate;border-spacing:0 12px;min-width:0}
      thead{display:none}
      tbody tr{display:block;background:var(--card);border:1px solid var(--bd); border-radius:14px;padding:10px 10px 6px;}
      tbody td{display:flex;justify-content:space-between;gap:12px; padding:.55rem .3rem;border-bottom:0;font-size:.98rem;}
      tbody td::before{content:attr(data-label); color:var(--mut); min-width:40%;}
      td[data-key="id"]{display:block;font-weight:700}
      td[data-key="id"]::before{content:"Ticket #"}
      td[data-key="acciones"]{display:flex;gap:8px;flex-wrap:wrap}
      .btn{flex:1 1 48%}
      .table-wrap{overflow:visible}
    }
    @media(max-width:900px){ .row>*, .row form{flex:1 1 100%} .btn{width:auto} }
  </style>
</head>
<body>
  <div class="wrap">
    <?php @include __DIR__.'/menu_eventos.php'; ?>

    <div class="row" style="margin-bottom:10px">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <span class="pill">Evento #<?= (int)$evento_id ?></span>
      <span class="pill">Total: <?= (int)$counts['todos'] ?></span>
      <span class="pill">Usados: <?= (int)$counts['usados'] ?></span>
      <span class="pill">Disponibles: <?= (int)$counts['libres'] ?></span>
    </div>

    <?php if(!empty($flash_ok)): ?><div class="ok"><?= $flash_ok ?></div><?php endif; ?>
    <?php if(!empty($flash_err)): ?><div class="bad"><?= $flash_err ?></div><?php endif; ?>

    <div class="card">
      <h3 style="margin:0 0 8px">🎟️ Entradas vendidas — <?= h($ev['titulo']) ?></h3>

      <!-- Filtros -->
      <form method="get" class="row" style="margin:8px 0">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">
        <input name="q" placeholder="Buscar código / email / nombre" value="<?= h($q) ?>" style="min-width:220px">
        <select name="estado">
          <option value="">Estado del pedido (todos)</option>
          <?php foreach (['pendiente','aprobado','pagado','rechazado','cancelado'] as $es): ?>
            <option value="<?= $es ?>" <?= $estado===$es?'selected':''; ?>><?= ucfirst($es) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="usado">
          <option value="">Uso (todos)</option>
          <option value="0" <?= $usado==='0'?'selected':''; ?>>Sin usar</option>
          <option value="1" <?= $usado==='1'?'selected':''; ?>>Usados</option>
        </select>
        <select name="tipo_id">
          <option value="0">Tipo (todos)</option>
          <?php foreach($tipos as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $tipo_id===(int)$t['id']?'selected':''; ?>><?= h($t['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn gray" href="?evento_id=<?= (int)$evento_id ?>">Limpiar</a>
        <a class="btn" href="?evento_id=<?= (int)$evento_id ?>&<?= http_build_query(['q'=>$q,'estado'=>$estado,'usado'=>$usado,'tipo_id'=>$tipo_id,'export'=>'csv']) ?>">⬇ Export CSV</a>
      </form>

      <div class="table-wrap" role="region" aria-label="Entradas vendidas" tabindex="0">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Tipo</th>
              <th>Código</th>
              <th>Pedido</th>
              <th>Comprador</th>
              <th>Estado</th>
              <th>Uso</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="8" style="color:#cfd7de">Sin resultados.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td data-key="id" data-label="#"><?= (int)$r['id'] ?></td>
              <td data-label="Tipo">
                <?= h((string)($r['tipo_nombre'] ?? '—')) ?>
                <br><small>$<?= h(number_format((float)($r['precio'] ?? 0),2,'.','')) ?></small>
              </td>
              <td data-label="Código"><code><?= h($r['code']) ?></code></td>
              <td data-label="Pedido">
                #<?= (int)$r['pedido_id'] ?><br><small><?= h((string)$r['origen']) ?></small>
              </td>
              <td data-label="Comprador">
                <?= h($r['comprador_nombre']) ?><br>
                <small><?= h($r['comprador_email']) ?></small>
              </td>
              <td data-label="Estado"><span class="pill"><?= h($r['pedido_estado']) ?></span></td>
              <td data-label="Uso">
                <?php if (!empty($r['used_at'])): ?>
                  <span class="pill">USADO</span><br>
                  <small><?= h($r['used_at']) ?><?= $r['used_gate']? ' · '.h($r['used_gate']) : '' ?></small>
                <?php else: ?>
                  <span class="pill">sin usar</span>
                <?php endif; ?>
              </td>
              <td data-key="acciones" data-label="Acciones" style="white-space:nowrap">
                <a class="btn" target="_blank" rel="noopener" href="ticket_pdf.php?code=<?= urlencode($r['code']) ?>">📄 PDF</a>
                <a class="btn gray" target="_blank" rel="noopener" href="mi_entrada.php?code=<?= urlencode($r['code']) ?>">👁️ Ver</a>

                <?php if (empty($r['used_at'])): ?>
                  <form method="post" action="" style="display:inline">
                    <input type="hidden" name="accion" value="usar">
                    <input type="hidden" name="code" value="<?= h($r['code']) ?>">
                    <input type="hidden" name="gate" value="Acceso principal">
                    <button class="btn gold" type="submit">✅ Usar</button>
                  </form>

                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Eliminar este ticket? Se repondrá el stock y se descontará del total del pedido.');">
                    <input type="hidden" name="accion" value="eliminar_ticket">
                    <input type="hidden" name="ticket_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn red" type="submit">🗑️ Eliminar</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Revertir uso?');">
                    <input type="hidden" name="accion" value="revertir">
                    <input type="hidden" name="code" value="<?= h($r['code']) ?>">
                    <button class="btn red" type="submit">↩ Revertir</button>
                  </form>
                <?php endif; ?>

                <?php $est = strtolower((string)$r['pedido_estado']); ?>
                <?php if (!in_array($est,['aprobado','pagado'],true)): ?>
                  <form method="post" action="" style="display:inline">
                    <input type="hidden" name="accion" value="set_estado">
                    <input type="hidden" name="pedido_id" value="<?= (int)$r['pedido_id'] ?>">
                    <input type="hidden" name="nuevo_estado" value="aprobado">
                    <button class="btn green" type="submit" title="Habilitar (aprobado)">✔ Habilitar</button>
                  </form>
                <?php endif; ?>
                <?php if ($est!=='pagado'): ?>
                  <form method="post" action="" style="display:inline">
                    <input type="hidden" name="accion" value="set_estado">
                    <input type="hidden" name="pedido_id" value="<?= (int)$r['pedido_id'] ?>">
                    <input type="hidden" name="nuevo_estado" value="pagado">
                    <button class="btn" type="submit" title="Marcar pagado">💵 Pagado</button>
                  </form>
                <?php endif; ?>
                <?php if (!in_array($est,['rechazado','cancelado'],true)): ?>
                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Rechazar este pedido?');">
                    <input type="hidden" name="accion" value="set_estado">
                    <input type="hidden" name="pedido_id" value="<?= (int)$r['pedido_id'] ?>">
                    <input type="hidden" name="nuevo_estado" value="rechazado">
                    <button class="btn gray" type="submit" title="Rechazar">⛔ Rechazar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:10px" class="row">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <a class="btn" href="scan_tickets.php?evento_id=<?= (int)$evento_id ?>">🔎 Escanear tickets (QR)</a>
    </div>
  </div>
</body>
</html>
