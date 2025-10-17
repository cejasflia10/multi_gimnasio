<?php
// tienda_indumentaria.php — Tienda/venta de indumentaria (lee de ind_productos + ind_talles)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { echo "<div style='color:red;text-align:center;'>❌ Acceso denegado</div>"; exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}

/* ===== Estado / CSRF / Cart ===== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ===== Verificar tablas reales del admin ===== */
$have_prod  = db_has_table($conexion, 'ind_productos');
$have_talle = db_has_table($conexion, 'ind_talles');

/* ===== Parámetros ===== */
$search = trim((string)($_GET['q'] ?? ''));
$catfil = trim((string)($_GET['cat'] ?? ''));
$page   = max(1, (int)($_GET['p'] ?? 1));
$perp   = 12;
$offset = ($page-1)*$perp;

$productos = [];
$categorias = [];
$total_rows = 0;

/* ===== DATA desde ind_productos / ind_talles ===== */
if ($have_prod) {
  // Categorías
  $whereCat = " WHERE gimnasio_id=? AND activo=1";
  $bindCatT = 'i';
  $bindCat  = [$gimnasio_id];

  $sqlCat = "SELECT DISTINCT categoria AS cat
             FROM ind_productos
             {$whereCat}
             ORDER BY cat ASC";
  if ($stC = @$conexion->prepare($sqlCat)) {
    $stC->bind_param($bindCatT, ...$bindCat);
    if ($stC->execute()) {
      $r = $stC->get_result();
      while($row=$r->fetch_assoc()){
        $catv = trim((string)$row['cat']);
        if ($catv!=='') $categorias[]=$catv;
      }
    }
    $stC->close();
  }

  // WHERE principal
  $where = ["p.gimnasio_id=?","p.activo=1"];
  $types = "i";
  $bind  = [$gimnasio_id];

  if ($search!=='') {
    $where[] = "(p.titulo LIKE ? OR p.descripcion LIKE ?)";
    $s = "%{$search}%";
    $bind[] = $s; $bind[] = $s;
    $types .= "ss";
  }
  if ($catfil!=='') {
    $where[] = "p.categoria = ?";
    $bind[] = $catfil;
    $types .= "s";
  }
  $where_sql = "WHERE ".implode(" AND ", $where);

  // Conteo (sin JOIN, más rápido)
  $sqlCnt = "SELECT COUNT(*) AS n FROM ind_productos p {$where_sql}";
  if ($stN = @$conexion->prepare($sqlCnt)) {
    $stN->bind_param($types, ...$bind);
    if ($stN->execute()) {
      $rr = $stN->get_result()->fetch_assoc();
      $total_rows = (int)($rr['n'] ?? 0);
    }
    $stN->close();
  }

  // Select con stock total (LEFT JOIN a talles)
  // Nota: si no existe ind_talles, stock_total = 0 via COALESCE
  $sql = "SELECT 
            p.id,
            p.titulo       AS nombre,
            p.precio       AS precio,
            p.descripcion  AS desctxt,
            p.foto_url     AS img,
            p.categoria    AS categoria,
            COALESCE(SUM(t.stock),0) AS stock_total,
            COUNT(t.id)              AS variantes
          FROM ind_productos p
          LEFT JOIN ind_talles t ON t.producto_id = p.id
          {$where_sql}
          GROUP BY p.id
          ORDER BY p.titulo
          LIMIT ? OFFSET ?";
  $typesPage = $types . "ii";
  $bindPage  = $bind; $bindPage[] = $perp; $bindPage[] = $offset;

  if ($st = @$conexion->prepare($sql)) {
    $st->bind_param($typesPage, ...$bindPage);
    if ($st->execute()) {
      $res = $st->get_result();
      while ($r = $res->fetch_assoc()) {
        $productos[] = [
          'id'       => (int)$r['id'],
          'nombre'   => (string)$r['nombre'],
          'precio'   => (float)$r['precio'],
          'stock'    => (int)$r['stock_total'],
          'img'      => (string)($r['img'] ?? ''),
          'desc'     => (string)($r['desctxt'] ?? ''),
          'talla'    => '', // se maneja por variante en el carrito si lo necesitás
          'color'    => '',
          'categoria'=> (string)($r['categoria'] ?? ''),
          'variantes'=> (int)$r['variantes'],
        ];
      }
    }
    $st->close();
  }
} else {
  // Fallback demo si aún no creaste ind_productos
  $total_rows = 4;
  $productos = [
    ['id'=>101, 'nombre'=>'Remera Dry-Fit', 'precio'=>11999, 'stock'=>20, 'img'=>'', 'desc'=>'Secado rápido, entrenamiento', 'talla'=>'', 'color'=>'', 'categoria'=>'Ropa', 'variantes'=>0],
    ['id'=>102, 'nombre'=>'Guantes Box 12oz', 'precio'=>45999, 'stock'=>12, 'img'=>'', 'desc'=>'Cuero sintético, velcro', 'talla'=>'', 'color'=>'', 'categoria'=>'Combate', 'variantes'=>0],
    ['id'=>103, 'nombre'=>'Short Muay Thai', 'precio'=>29999, 'stock'=>8,  'img'=>'', 'desc'=>'Corte clásico, liviano', 'talla'=>'', 'color'=>'', 'categoria'=>'Combate', 'variantes'=>0],
    ['id'=>104, 'nombre'=>'Zapatillas Training', 'precio'=>69999, 'stock'=>5, 'img'=>'', 'desc'=>'Suela antideslizante', 'talla'=>'', 'color'=>'', 'categoria'=>'Calzado', 'variantes'=>0],
  ];
}

/* ===== Acciones carrito ===== */
function require_csrf(){
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
    http_response_code(400); echo "CSRF inválido"; exit;
  }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['act'] ?? '';
  if ($act==='add') {
    require_csrf();
    $pid   = (int)($_POST['pid'] ?? 0);
    $nom   = trim((string)($_POST['nom'] ?? ''));
    $precio= (float)($_POST['precio'] ?? 0);
    $img   = trim((string)($_POST['img'] ?? ''));
    // Para una 2da versión podemos pedir talle/color antes de agregar:
    $talla = trim((string)($_POST['talla'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $q     = max(1, (int)($_POST['q'] ?? 1));

    if ($pid>0) {
      if (!isset($_SESSION['cart'][$pid])) {
        $_SESSION['cart'][$pid] = ['q'=>0,'precio'=>$precio,'nombre'=>$nom,'img'=>$img,'talla'=>$talla,'color'=>$color];
      }
      $_SESSION['cart'][$pid]['q'] += $q;
    }
    header("Location: tienda_indumentaria.php?ok=1#carrito"); exit;
  }
  if ($act==='chg') {
    require_csrf();
    $pid = (int)($_POST['pid'] ?? 0);
    $q   = max(0, (int)($_POST['q'] ?? 0));
    if ($pid>0 && isset($_SESSION['cart'][$pid])) {
      if ($q===0) unset($_SESSION['cart'][$pid]); else $_SESSION['cart'][$pid]['q']=$q;
    }
    header("Location: tienda_indumentaria.php#carrito"); exit;
  }
  if ($act==='del') {
    require_csrf();
    $pid = (int)($_POST['pid'] ?? 0);
    if ($pid>0) unset($_SESSION['cart'][$pid]);
    header("Location: tienda_indumentaria.php#carrito"); exit;
  }
  if ($act==='clear') {
    require_csrf();
    $_SESSION['cart'] = [];
    header("Location: tienda_indumentaria.php#carrito"); exit;
  }
  if ($act==='checkout') {
    require_csrf();

    $venta_id = 0;
    $ok_bd = db_has_table($conexion,'ventas_indumentaria') && db_has_table($conexion,'ventas_indumentaria_items');

    $total = 0.0;
    foreach($_SESSION['cart'] as $pid=>$it) $total += ((float)($it['precio'] ?? 0)) * ((int)($it['q'] ?? 0));

    if ($ok_bd && $total>0) {
      $sqlV = "INSERT INTO ventas_indumentaria (cliente_id, gimnasio_id, fecha, total) VALUES (?,?,NOW(),?)";
      if ($stV = @$conexion->prepare($sqlV)) {
        $stV->bind_param("iid", $cliente_id, $gimnasio_id, $total);
        if ($stV->execute()) $venta_id = (int)$stV->insert_id;
        $stV->close();
      }
      if ($venta_id>0) {
        $sqlI = "INSERT INTO ventas_indumentaria_items (venta_id, producto_id, nombre, cantidad, precio_unit, talla, color) VALUES (?,?,?,?,?,?,?)";
        if ($stI = @$conexion->prepare($sqlI)) {
          foreach($_SESSION['cart'] as $pid=>$it){
            $q=(int)($it['q'] ?? 0); if ($q<=0) continue;
            $nom=$it['nombre'] ?? ''; $p=(float)($it['precio'] ?? 0); $talla=$it['talla'] ?? ''; $color=$it['color'] ?? '';
            $stI->bind_param("iisidss", $venta_id, $pid, $nom, $q, $p, $talla, $color);
            @$stI->execute();
          }
          $stI->close();
        }
      }
    }
    $_SESSION['cart'] = [];
    header("Location: tienda_indumentaria.php?venta_ok=1".($venta_id?("&vid=".$venta_id):"")."#carrito"); exit;
  }
}

/* ===== Totales carrito ===== */
$cart = $_SESSION['cart'];
$cart_qty = 0;
$cart_total = 0.0;
foreach ($cart as $it) {
  $q = (int)($it['q'] ?? 0);
  $p = (float)($it['precio'] ?? 0);
  $cart_qty  += $q;
  $cart_total += $p * $q;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>🛍️ Indumentaria</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff; --mnu-fg-dim: #cbd5e1; --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16); --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; text-decoration:none; }
    .mnu-btn--ghost{ background:transparent; border:1px solid var(--mnu-border); color:#fff }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; }

    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1200px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .grid{ display:grid; gap:16px; grid-template-columns: 1fr; }
    @media (min-width:980px){ .grid{ grid-template-columns: 3fr 1.2fr; } }
    .card{ padding:18px }
    h2{ text-align:center; margin:10px 0 18px; }
    .muted{ color:var(--muted); }

    .filters{ display:grid; grid-template-columns: 1fr 160px; gap:10px; margin-bottom:12px }
    @media (max-width:520px){ .filters{ grid-template-columns: 1fr; } }
    .filters input, .filters select{
      width:100%; padding:12px; border:1px solid #333; border-radius:12px; background:#0f1115; color:#fff; font-size:16px;
    }
    .products{ display:grid; grid-template-columns: repeat(1,minmax(0,1fr)); gap:12px; }
    @media (min-width:620px){ .products{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (min-width:980px){ .products{ grid-template-columns: repeat(3, minmax(0,1fr)); } }
    .p-card{ display:flex; flex-direction:column; gap:10px; border:1px solid var(--border); border-radius:16px; padding:12px; background:#12141a; }
    .p-img{ width:100%; aspect-ratio: 4/3; background:#0f1115; border-radius:12px; display:grid; place-items:center; overflow:hidden }
    .p-img img{ width:100%; height:100%; object-fit:cover }
    .p-name{ font-weight:800; }
    .p-meta{ display:flex; gap:8px; flex-wrap:wrap; font-size:13px; color:#cbd5e1 }
    .price{ font-size:18px; font-weight:900; color:#ffd600 }
    .p-actions{ display:flex; gap:8px; align-items:center }
    .p-actions input{ width:76px; padding:10px; border-radius:10px; background:#0f1115; border:1px solid #333; color:#fff; text-align:center }
    .btn{ padding:10px 12px; border:none; border-radius:12px; background:var(--acc); color:#111; font-weight:800; cursor:pointer; }
    .btn-ghost{ background:transparent; border:1px solid var(--border); color:#fff }
    .badge{ border:1px solid var(--border); padding:4px 8px; border-radius:999px; font-size:12px; }

    .cart{ position:sticky; top:74px; align-self:start }
    .cart h3{ margin:0 0 6px }
    .cart-list{ display:flex; flex-direction:column; gap:10px; }
    .c-item{ display:grid; grid-template-columns: 56px 1fr auto; gap:10px; border:1px solid var(--border); border-radius:12px; padding:8px; background:#12141a; }
    .c-img{ width:56px; height:56px; border-radius:10px; overflow:hidden; background:#0f1115; display:grid; place-items:center }
    .c-img img{ width:100%; height:100%; object-fit:cover }
    .c-name{ font-weight:700; }
    .c-meta{ font-size:12px; color:#cbd5e1 }
    .c-qty{ display:flex; gap:6px; align-items:center; justify-content:flex-end }
    .c-qty input{ width:64px; padding:8px; border-radius:10px; background:#0f1115; border:1px solid #333; color:#fff; text-align:center }
    .tot{ display:flex; justify-content:space-between; font-weight:900; margin-top:10px; padding-top:10px; border-top:1px dashed rgba(255,255,255,.2) }
    .alert{ background:#112b1a; border:1px solid #1f6f3d; color:#a7f3d0; padding:10px 12px; border-radius:10px; margin:8px 0 18px; }
    .note{ font-size:13px; color:#cbd5e1 }
    .pager{ display:flex; gap:8px; justify-content:center; margin-top:12px }
    .pager a{ color:#fff; text-decoration:none; border:1px solid var(--border); border-radius:10px; padding:8px 12px }
    .pager .cur{ background:#1a1d24; }
  </style>
</head>
<body>

  <!-- Menú simple (si querés, reusa tu menú unificado completo) -->
  <header>
    <div class="mnu-bar">
      <div class="mnu-title">Panel Cliente</div>
      <div class="mnu-spacer"></div>
      <a class="mnu-btn mnu-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
    </div>
  </header>

  <div class="container">
    <h2>🛍️ Indumentaria del Gimnasio</h2>

    <?php if (isset($_GET['ok'])): ?>
      <div class="alert glass">✅ Producto agregado al carrito.</div>
    <?php endif; ?>
    <?php if (isset($_GET['venta_ok'])): ?>
      <div class="alert glass">🎉 ¡Gracias! Compra registrada<?= isset($_GET['vid']) ? ' (#'.h($_GET['vid']).')' : '' ?>. Te contactaremos para la entrega.</div>
    <?php endif; ?>

    <div class="grid">
      <!-- CATÁLOGO -->
      <section class="glass card">
        <form class="filters" method="GET" action="tienda_indumentaria.php">
          <input type="text" name="q" value="<?= h($search) ?>" placeholder="Buscar producto..." />
          <select name="cat">
            <option value="">Todas las categorías</option>
            <?php foreach ($categorias as $c): ?>
              <option value="<?= h($c) ?>" <?= $c===$catfil?'selected':'' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
          <div style="grid-column: 1 / -1; display:flex; gap:8px;">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn btn-ghost" href="tienda_indumentaria.php">Limpiar</a>
          </div>
        </form>

        <?php if (!$productos): ?>
          <p class="muted">No hay productos para mostrar.</p>
        <?php else: ?>
          <div class="products">
            <?php foreach ($productos as $p): ?>
              <article class="p-card">
                <div class="p-img">
                  <?php if (!empty($p['img'])): ?>
                    <img src="<?= h($p['img']) ?>" alt="<?= h($p['nombre']) ?>">
                  <?php else: ?>
                    <span class="muted">Sin imagen</span>
                  <?php endif; ?>
                </div>
                <div class="p-name"><?= h($p['nombre']) ?></div>
                <div class="p-meta">
                  <?php if ($p['categoria']): ?><span class="badge">#<?= h($p['categoria']) ?></span><?php endif; ?>
                  <span class="badge">Stock: <?= (int)$p['stock'] ?></span>
                  <?php if ($p['variantes']>0): ?><span class="badge">Talles: <?= (int)$p['variantes'] ?></span><?php endif; ?>
                </div>
                <?php if ($p['desc']): ?><div class="muted" style="min-height:38px"><?= h($p['desc']) ?></div><?php endif; ?>
                <div class="price">$ <?= money($p['precio']) ?></div>
                <form class="p-actions" method="POST" action="tienda_indumentaria.php#carrito" autocomplete="off">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="act" value="add">
                  <input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="nom" value="<?= h($p['nombre']) ?>">
                  <input type="hidden" name="precio" value="<?= (float)$p['precio'] ?>">
                  <input type="hidden" name="img" value="<?= h($p['img']) ?>">
                  <input type="number" name="q" value="1" min="1" step="1" />
                  <button class="btn" type="submit">Agregar</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>

          <?php
            $total_pages = max(1, (int)ceil($total_rows / $perp));
            if ($total_pages>1):
          ?>
            <div class="pager">
              <?php for($i=1;$i<=$total_pages;$i++):
                $qs = http_build_query(['q'=>$search,'cat'=>$catfil,'p'=>$i]); ?>
                <?php if ($i===$page): ?>
                  <span class="cur" style="padding:8px 12px;border:1px solid var(--border);border-radius:10px;"><?= $i ?></span>
                <?php else: ?>
                  <a href="tienda_indumentaria.php?<?= $qs ?>#top"><?= $i ?></a>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </section>

      <!-- CARRITO -->
      <aside class="glass card cart" id="carrito">
        <h3>🧺 Carrito <span class="muted">(<?= (int)$cart_qty ?> ítems)</span></h3>
        <?php if (!$cart): ?>
          <p class="muted">Tu carrito está vacío.</p>
        <?php else: ?>
          <div class="cart-list">
            <?php foreach($cart as $pid=>$it): ?>
              <div class="c-item">
                <div class="c-img">
                  <?php if (!empty($it['img'])): ?>
                    <img src="<?= h($it['img']) ?>" alt="">
                  <?php else: ?>
                    <span class="muted">Img</span>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="c-name"><?= h($it['nombre']) ?></div>
                  <div class="c-meta">
                    <?php if (!empty($it['talla'])): ?>Talle: <?= h($it['talla']) ?> · <?php endif; ?>
                    <?php if (!empty($it['color'])): ?>Color: <?= h($it['color']) ?> · <?php endif; ?>
                    $ <?= money($it['precio']) ?> c/u
                  </div>
                </div>
                <div class="c-qty">
                  <form method="POST" action="tienda_indumentaria.php#carrito" style="display:flex; gap:6px; align-items:center">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="act" value="chg">
                    <input type="hidden" name="pid" value="<?= (int)$pid ?>">
                    <input type="number" name="q" value="<?= (int)($it['q'] ?? 1) ?>" min="0" step="1" />
                    <button class="btn" type="submit">Actualizar</button>
                  </form>
                  <form method="POST" action="tienda_indumentaria.php#carrito">
                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                    <input type="hidden" name="act" value="del">
                    <input type="hidden" name="pid" value="<?= (int)$pid ?>">
                    <button class="btn btn-ghost" type="submit" title="Quitar">✕</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="tot"><div>Total</div><div>$ <?= money($cart_total) ?></div></div>
          <div style="display:flex; gap:8px; margin-top:10px">
            <form method="POST" action="tienda_indumentaria.php#carrito">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="act" value="checkout">
              <button class="btn" type="submit">Finalizar compra</button>
            </form>
            <form method="POST" action="tienda_indumentaria.php#carrito">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="act" value="clear">
              <button class="btn btn-ghost" type="submit">Vaciar</button>
            </form>
          </div>
          <p class="note">Al finalizar la compra te contactamos por WhatsApp para coordinar entrega/pago.</p>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</body>
</html>
