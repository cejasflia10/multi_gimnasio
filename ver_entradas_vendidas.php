<?php
/* ============================================================
   ver_entradas_vendidas.php — Listado y gestión de tickets por evento (responsive)
   Requiere login módulo eventos (evento_usuario_id)
   GET: evento_id (obligatorio), q, estado, usado, tipo_id, export=csv
   POST acciones: usar / revertir (marca uso del ticket)
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
/* Helper para bind dinámico compatible con PHP 7/8 */
function bind_all_params(mysqli_stmt $st, string $types, array &$vals): bool {
  $params = [];
  $params[] = &$types;
  foreach ($vals as $k => &$v) { $params[] = &$v; }
  return call_user_func_array([$st,'bind_param'], $params);
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

/* ===== Acciones (marcar uso / revertir) ===== */
$flash_ok=''; $flash_err='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion = $_POST['accion'] ?? '';
  $code   = trim((string)($_POST['code'] ?? ''));
  $gate   = trim((string)($_POST['gate'] ?? 'Acceso principal'));
  $uid    = (int)($_SESSION['evento_usuario_id'] ?? 0);

  if ($code===''){ $flash_err='Falta código.'; }
  else {
    if ($accion==='usar'){
      $sql = "UPDATE tickets t
              JOIN pedidos p ON p.id=t.pedido_id
              SET t.used_at=NOW(), t.used_by=?, t.used_gate=?
              WHERE t.code=? AND p.evento_id=?";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('issi',$uid,$gate,$code,$evento_id);
        if($st->execute() && $st->affected_rows>0){ $flash_ok='Ticket marcado como USADO.'; }
        else { $flash_err='No se pudo marcar como usado (código inválido o de otro evento).'; }
        $st->close();
      } else { $flash_err='Error interno (prep usar).'; }
    } elseif ($accion==='revertir'){
      $sql = "UPDATE tickets t
              JOIN pedidos p ON p.id=t.pedido_id
              SET t.used_at=NULL, t.used_by=NULL, t.used_gate=NULL
              WHERE t.code=? AND p.evento_id=?";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('si',$code,$evento_id);
        if($st->execute() && $st->affected_rows>0){ $flash_ok='Uso revertido.'; }
        else { $flash_err='No se pudo revertir (código inválido o de otro evento).'; }
        $st->close();
      } else { $flash_err='Error interno (prep revertir).'; }
    }
  }
}

/* ===== Filtros ===== */
$q       = trim((string)($_GET['q'] ?? ''));         // busca en code, email, nombre
$estado  = trim((string)($_GET['estado'] ?? ''));    // estado del pedido
$usado   = trim((string)($_GET['usado'] ?? ''));     // '1' usados, '0' sin usar, '' todos
$tipo_id = (int)($_GET['tipo_id'] ?? 0);

$where = ["p.evento_id = ?"];
$bindTy = "i";
$bindVl = [$evento_id];

if ($q!==''){
  $where[] = "(t.code LIKE CONCAT('%',?,'%') OR p.comprador_email LIKE CONCAT('%',?,'%') OR p.comprador_nombre LIKE CONCAT('%',?,'%'))";
  $bindTy .= "sss"; array_push($bindVl, $q,$q,$q);
}
if ($estado!==''){
  $where[] = "p.estado = ?";
  $bindTy .= "s"; $bindVl[] = $estado;
}
if ($usado==='1'){
  $where[] = "t.used_at IS NOT NULL";
}
if ($usado==='0'){
  $where[] = "t.used_at IS NULL";
}
if ($tipo_id>0){
  $where[] = "t.tipo_id = ?";
  $bindTy .= "i"; $bindVl[] = $tipo_id;
}
$wsql = implode(' AND ', $where);

/* Tipos (para filtro) */
$tipos = [];
if ($st=$conexion->prepare("SELECT id,nombre FROM tickets_tipos WHERE evento_id=? ORDER BY precio ASC, id ASC")){
  $st->bind_param('i',$evento_id); $st->execute();
  $tipos = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
}

/* Consulta principal */
$sql = "SELECT
          t.id, t.code, t.qr_path, t.used_at, t.used_by, t.used_gate,
          tt.nombre AS tipo_nombre, tt.precio,
          p.id AS pedido_id, p.estado AS pedido_estado, p.origen,
          p.comprador_nombre, p.comprador_email, p.created_at
        FROM tickets t
        JOIN pedidos p ON p.id=t.pedido_id
        LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id
        WHERE $wsql
        ORDER BY t.id DESC";

$st = $conexion->prepare($sql);
if (!$st) {
  http_response_code(500);
  exit("SQL prepare error: ".h($conexion->error)."<br><small>$sql</small>");
}
bind_all_params($st, $bindTy, $bindVl);
$st->execute(); $res = $st->get_result();
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$st->close();

/* Totales usados/no usados (sobre resultados filtrados) */
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
  fclose($out);
  exit;
}
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
    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}
    input,select{padding:.56rem .7rem;border-radius:10px;border:1px solid var(--bd);background:#101010;color:var(--fg)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}

    /* ===== Tabla (desktop) ===== */
    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:900px}
    thead th{
      position:sticky; top:0; background:#121212; color:var(--brand);
      text-align:left; padding:.7rem .65rem; border-bottom:1px solid var(--bd); z-index:1;
    }
    td{padding:.6rem .65rem;border-bottom:1px solid var(--bd);vertical-align:middle}
    code{background:#000; padding:.15rem .35rem; border-radius:6px; border:1px solid #333}

    @media(hover:hover){
      tbody tr:hover{background:#101010}
    }

    /* ===== Cards (mobile) ===== */
    @media (max-width: 860px){
      .table-wrap{border:0}
      table{border-collapse:separate;border-spacing:0 12px;min-width:0}
      thead{display:none}
      tbody tr{
        display:block;background:var(--card);border:1px solid var(--bd);
        border-radius:14px;padding:10px 10px 6px;
      }
      tbody td{
        display:flex;justify-content:space-between;gap:12px;
        padding:.55rem .3rem;border-bottom:0;font-size:.98rem;
      }
      tbody td::before{
        content:attr(data-label); color:var(--mut); min-width:40%;
      }
      td[data-key="id"]{display:block;font-weight:700}
      td[data-key="id"]::before{content:"Ticket #"}
      td[data-key="acciones"]{display:flex;gap:8px;flex-wrap:wrap}
      .btn{flex:1 1 48%}
      .table-wrap{overflow:visible}
    }

    /* Form filtros responsive */
    @media(max-width:900px){
      .row>*, .row form{flex:1 1 100%}
      .btn{width:auto}
    }
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

    <?php if($flash_ok): ?><div class="ok"><?= $flash_ok ?></div><?php endif; ?>
    <?php if($flash_err): ?><div class="bad"><?= $flash_err ?></div><?php endif; ?>

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
                    <button class="btn" type="submit">✅ Usar</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Revertir uso?');">
                    <input type="hidden" name="accion" value="revertir">
                    <input type="hidden" name="code" value="<?= h($r['code']) ?>">
                    <button class="btn red" type="submit">↩ Revertir</button>
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
