<?php
// tienda_indumentaria.php — Catálogo + Talles por plantillas + Carrito + Checkout (MENÚ REUSABLE)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/menu_cliente.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { echo "<div style='color:#f66;text-align:center;'>❌ Acceso denegado</div>"; exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 2, ',', '.'); }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ===== Helpers DB ===== */
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}

/*** DEFAULT (solo backup si no hay datos por producto) ***/
$DEFAULT_PAGOS = [
  'alias'  => 'GIMNASIO.ALIAS.PAGO',
  'cbu'    => '0000003100087654321098',
  'banco'  => 'Banco Nación',
  'cuenta_tipo' => 'Caja de Ahorro',
  'cuenta_numero'=> '123-456789/0',
  'titular'=> 'Nombre Apellido',
  'cuit'   => '20-00000000-0',
  'nota'   => 'Podés enviar el comprobante por WhatsApp.',
  'mp_link'=> '',
  'qr_url' => '',
];

/* ===== Compatibilidad PHP 7 ===== */
if (!function_exists('str_contains_safe')) {
  function str_contains_safe($haystack,$needle){ if($needle==='')return true; return mb_stripos((string)$haystack,(string)$needle,0,'UTF-8')!==false; }
}

/* ===== PLANTILLAS: SETS (pestañas) por categoría ===== */
function plantillas_por_categoria(string $categoria): array {
  $c = mb_strtolower($categoria,'UTF-8');
  $is_remera = str_contains_safe($c,'remera') || str_contains_safe($c,'musculosa') || str_contains_safe($c,'camiseta');
  $is_mujer  = str_contains_safe($c,'mujer')  || str_contains_safe($c,'dama') || str_contains_safe($c,'lady');
  $is_muay   = str_contains_safe($c,'muay')   || str_contains_safe($c,'thai');
  $is_pant   = str_contains_safe($c,'pantal') || str_contains_safe($c,'short') || str_contains_safe($c,'jogger') || str_contains_safe($c,'pantalón');
  $is_hoodie = str_contains_safe($c,'hoodie') || str_contains_safe($c,'buzo');

  $remera_unisex = [
    ['talle'=>'XS','ancho'=>48,'largo'=>67],
    ['talle'=>'S', 'ancho'=>50,'largo'=>70],
    ['talle'=>'M', 'ancho'=>53,'largo'=>73],
    ['talle'=>'L', 'ancho'=>55,'largo'=>77],
    ['talle'=>'XL','ancho'=>57,'largo'=>79],
    ['talle'=>'2XL','ancho'=>63,'largo'=>82],
    ['talle'=>'3XL','ancho'=>66,'largo'=>84],
  ];
  $remera_mujer = [
    ['talle'=>'XS','ancho'=>37,'largo'=>60],
    ['talle'=>'S', 'ancho'=>39,'largo'=>62],
    ['talle'=>'M', 'ancho'=>41,'largo'=>64],
    ['talle'=>'L', 'ancho'=>43,'largo'=>66],
    ['talle'=>'XL','ancho'=>45,'largo'=>68],
    ['talle'=>'2XL','ancho'=>47,'largo'=>70],
    ['talle'=>'3XL','ancho'=>49,'largo'=>72],
  ];
  $muay = [
    ['talle'=>'XS','cintura'=>'77-81','largo'=>29,'ancho_pierna'=>29],
    ['talle'=>'S', 'cintura'=>'82-86','largo'=>30,'ancho_pierna'=>29],
    ['talle'=>'M', 'cintura'=>'87-91','largo'=>32,'ancho_pierna'=>30],
    ['talle'=>'L', 'cintura'=>'92-96','largo'=>34,'ancho_pierna'=>31],
    ['talle'=>'XL','cintura'=>'97-101','largo'=>35,'ancho_pierna'=>33],
    ['talle'=>'2XL','cintura'=>'102-110','largo'=>37,'ancho_pierna'=>36],
  ];
  $hoodie_buzo = [
    ['talle'=>'S','ancho'=>52,'largo'=>64],
    ['talle'=>'M','ancho'=>54,'largo'=>64],
    ['talle'=>'L','ancho'=>57,'largo'=>64],
    ['talle'=>'XL','ancho'=>59,'largo'=>68],
    ['talle'=>'XXL','ancho'=>61,'largo'=>70],
  ];
  $hoodie_pant = [
    ['talle'=>'S','cintura'=>38,'largo'=>96],
    ['talle'=>'M','cintura'=>44,'largo'=>97],
    ['talle'=>'L','cintura'=>45,'largo'=>99],
    ['talle'=>'XL','cintura'=>47,'largo'=>100],
    ['talle'=>'XXL','cintura'=>48,'largo'=>101],
  ];

  if ($is_muay) return ['Muay Thai'=>$muay];
  if ($is_hoodie && $is_pant) return ['Pantalón/Jogger'=>$hoodie_pant, 'Buzo'=>$hoodie_buzo];
  if ($is_hoodie) return ['Buzo'=>$hoodie_buzo, 'Pantalón/Jogger'=>$hoodie_pant];
  if ($is_remera) return $is_mujer ? ['Mujer'=>$remera_mujer, 'Unisex'=>$remera_unisex]
                                   : ['Unisex'=>$remera_unisex, 'Mujer'=>$remera_mujer];

  return ['Genérico'=>[
    ['talle'=>'S','ancho'=>50,'largo'=>70],
    ['talle'=>'M','ancho'=>53,'largo'=>73],
    ['talle'=>'L','ancho'=>55,'largo'=>77],
    ['talle'=>'XL','ancho'=>57,'largo'=>79],
  ]];
}

/* ===== Productos activos ===== */
$productos = [];
if (db_has_table($conexion,'ind_productos')) {
  if ($st=$conexion->prepare("SELECT id,titulo AS nombre,descripcion,precio,foto_url AS img,categoria 
                              FROM ind_productos WHERE gimnasio_id=? AND activo=1 ORDER BY id DESC")) {
    $st->bind_param('i',$gimnasio_id);
    $st->execute();
    $productos=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
  }
}

/* ===== Cargar pagos por producto (para el checkout) ===== */
function pagos_de_producto(mysqli $db, int $producto_id): array {
  if(!db_has_table($db,'ind_producto_pagos')) return [];
  $st = $db->prepare("SELECT mp_link, alias_cbu, alias, cbu, banco, cuenta_tipo, cuenta_numero, titular, cuit, nota, qr_url 
                      FROM ind_producto_pagos WHERE producto_id=? LIMIT 1");
  $st->bind_param('i',$producto_id);
  $st->execute();
  $row = $st->get_result()->fetch_assoc() ?: [];
  $st->close();
  if (!empty($row['alias_cbu'])) {
    if (empty($row['alias']) && strpos($row['alias_cbu'],'.')!==false) $row['alias']=$row['alias_cbu'];
    if (empty($row['cbu']) && preg_match('/\d{18,22}/',$row['alias_cbu'])) $row['cbu']=$row['alias_cbu'];
  }
  return $row;
}
function pagos_para_checkout(mysqli $db, array $cart, array $default): array {
  $firstPid = null;
  foreach($cart as $it){ $firstPid = (int)($it['pid']??0); if($firstPid) break; }
  if(!$firstPid) return $default;
  $p = pagos_de_producto($db,$firstPid);
  if(!$p) return $default;
  return [
    'alias'  => $p['alias'] ?? $default['alias'],
    'cbu'    => $p['cbu'] ?? $default['cbu'],
    'banco'  => $p['banco'] ?? $default['banco'],
    'cuenta_tipo' => $p['cuenta_tipo'] ?? $default['cuenta_tipo'],
    'cuenta_numero'=> $p['cuenta_numero'] ?? $default['cuenta_numero'],
    'titular'=> $p['titular'] ?? $default['titular'],
    'cuit'   => $p['cuit'] ?? $default['cuit'],
    'nota'   => $p['nota'] ?? $default['nota'],
    'mp_link'=> $p['mp_link'] ?? $default['mp_link'],
    'qr_url' => $p['qr_url'] ?? $default['qr_url'],
    '_origen'=> 'producto_'.$firstPid
  ];
}

/* ===== Carrito ===== */
function require_csrf(){ if(!isset($_POST['csrf'])||!hash_equals($_SESSION['csrf_token'],$_POST['csrf'])){ http_response_code(400); exit('CSRF'); } }
$flash_ok = $flash_err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $act=$_POST['act']??'';
  if($act==='add'){
    require_csrf();
    $pid=(int)$_POST['pid'];
    $nom=trim((string)($_POST['nom']??''));
    $precio=(float)($_POST['precio']??0);
    $img=trim((string)($_POST['img']??''));
    $talla=trim((string)($_POST['talla']??''));     // chip (opcional)
    $medidas=trim((string)($_POST['medidas']??'')); // manual o del chip (opcional)
    if($talla==='' && $medidas===''){ header("Location: tienda_indumentaria.php?err=talle#carrito");exit; }
    $key=$pid.'|'.($talla?:'manual');
    if(!isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key]=['q'=>0,'precio'=>$precio,'nombre'=>$nom,'img'=>$img,'talla'=>$talla,'medidas'=>$medidas,'pid'=>$pid];
    $_SESSION['cart'][$key]['q']+=1;
    header("Location: tienda_indumentaria.php?ok=1#carrito");exit;
  }
  if($act==='chg'){ require_csrf(); $key=(string)($_POST['key']??''); $q=max(0,(int)($_POST['q']??0)); if($key!==''&&isset($_SESSION['cart'][$key])){ if($q===0)unset($_SESSION['cart'][$key]); else $_SESSION['cart'][$key]['q']=$q; } header("Location: tienda_indumentaria.php#carrito");exit; }
  if($act==='del'){ require_csrf(); $key=(string)($_POST['key']??''); if($key!==''&&isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]); header("Location: tienda_indumentaria.php#carrito");exit; }
  if($act==='clear'){ require_csrf(); $_SESSION['cart']=[]; header("Location: tienda_indumentaria.php#carrito");exit; }

  // ===== Checkout =====
  if($act==='checkout'){
    require_csrf();
    if(empty($_SESSION['cart'])){ $flash_err='El carrito está vacío.'; }
    $pago = (string)($_POST['pago'] ?? '');
    if(!$flash_err && !in_array($pago,['efectivo','tarjeta','transferencia'],true)){
      $flash_err='Elegí una forma de pago.';
    }

    $comp_url = '';
    if(!$flash_err && $pago==='transferencia'){
      if(empty($_FILES['comprobante']['name']) || $_FILES['comprobante']['error']!==UPLOAD_ERR_OK){
        $flash_err='Debés adjuntar el comprobante de transferencia.';
      } else {
        $allowed = ['image/jpeg','image/png','application/pdf'];
        $mime = mime_content_type($_FILES['comprobante']['tmp_name']);
        $size = (int)$_FILES['comprobante']['size'];
        if(!in_array($mime,$allowed,true)){
          $flash_err='Formato no permitido. Usá JPG, PNG o PDF.';
        } elseif($size > 5*1024*1024){
          $flash_err='El archivo supera 5 MB.';
        } else {
          $dir = __DIR__.'/uploads/comprobantes/';
          if(!is_dir($dir)) @mkdir($dir,0777,true);
          $ext = ($mime==='application/pdf')?'.pdf':(($mime==='image/png')?'.png':'.jpg');
          $fname = 'comp_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).$ext;
          $dest = $dir.$fname;
          if(!@move_uploaded_file($_FILES['comprobante']['tmp_name'],$dest)){
            $flash_err='No se pudo guardar el comprobante.';
          }else{
            $comp_url = 'uploads/comprobantes/'.$fname;
          }
        }
      }
    }

    if(!$flash_err){
      $_SESSION['cart'] = [];
      $ticket = strtoupper(bin2hex(random_bytes(3)));
      $params = ['confirm'=>'1','modo'=>$pago,'ticket'=>$ticket];
      if($comp_url) $params['comp']=$comp_url;
      $q = http_build_query($params);
      header("Location: tienda_indumentaria.php?$q#carrito"); exit;
    }
  }
}
$cart = $_SESSION['cart'];
$cart_qty=0;$cart_total=0; foreach($cart as $it){$cart_qty+=(int)$it['q'];$cart_total+=(int)$it['q']*(float)$it['precio'];}

/* ===== Pagos a mostrar en checkout (desde DB por producto si hay) ===== */
$PAGOS_UI = pagos_para_checkout($conexion, $cart, $DEFAULT_PAGOS);

// ¿Carrito con más de un producto distinto?
$cart_product_ids = [];
foreach($cart as $it){ $cart_product_ids[(int)$it['pid']] = true; }
$multi_products = count($cart_product_ids) > 1;

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>🛍️ Indumentaria</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    /* ===== Garantía de legibilidad del MENÚ REUSABLE ===== */
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{
      color:#fff !important; -webkit-text-fill-color:#fff !important;
      text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important;
    }

    /* ===== Estilos propios de la tienda ===== */
    body{font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:#0b0b0b;color:#fff;margin:0}
    .container{max-width:1200px;margin:auto;padding:16px}
    .grid{display:grid;gap:16px}
    @media(min-width:980px){.grid{grid-template-columns:3fr 1.2fr}}
    .card{background:#12141a;border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px;box-shadow:0 8px 30px rgba(0,0,0,.35)}
    .products{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(300px,1fr))}
    .p-card{border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:10px;background:rgba(255,255,255,.05)}
    .p-img{width:100%;aspect-ratio:4/3;overflow:hidden;border-radius:10px;background:#0f1115;cursor:pointer}
    .p-img img{width:100%;height:100%;object-fit:cover;transition:transform .2s}
    .p-img img:hover{transform:scale(1.08)}
    .price{color:#ffd600;font-weight:800}
    .btn{background:#ffd600;color:#111;padding:8px 12px;border-radius:10px;border:0;cursor:pointer;font-weight:800}
    .btn-ghost{background:transparent;border:1px solid #555;color:#fff;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:700}
    .cart{position:sticky;top:90px;align-self:start}
    .alert{background:#113820;border:1px solid #1d7a3d;padding:8px;border-radius:10px;margin-bottom:10px;color:#a7f3d0}
    .alert-err{background:#3a1010;border:1px solid #7a2d2d;color:#f2bcbc;padding:8px;border-radius:10px;margin-bottom:10px}
    .alert-ok{background:#10371f;border:1px solid #2e7d32;color:#b6f0c9;padding:8px;border-radius:10px;margin-bottom:10px}
    img.guia{width:100%;max-width:300px;border:1px solid rgba(255,255,255,.12);border-radius:10px;margin-top:8px}
    .chips{display:flex;flex-wrap:wrap;gap:6px}
    .chips button{padding:6px 10px;border-radius:999px;border:1px solid #444;background:#0f1115;color:#fff;cursor:pointer}
    .chips button.active{border-color:#ffd600}
    .mini{color:#b4b4b4;font-size:12px}
    .sep{height:1px;background:#222;margin:8px 0}
    .tabs{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0}
    .tabs button{padding:6px 10px;border-radius:8px;border:1px solid #444;background:#0f1115;color:#fff;cursor:pointer}
    .tabs button.on{border-color:#ffd600}
    .payrow{display:flex;gap:10px;align-items:center;margin:6px 0}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #444;font-size:11px;color:#ddd}
    .kv{display:grid;grid-template-columns:120px 1fr;gap:4px 10px}
    .kv b{color:#ddd}
  </style>
</head>
<body>

  <?php render_menu_cliente('tienda_indumentaria'); ?>

  <div class="container">
    <h2>🛍️ Indumentaria del Gimnasio</h2>

    <?php if(isset($_GET['ok'])):?>
      <div class="alert">✅ Producto agregado al carrito.</div>
    <?php endif;?>

    <?php if(isset($_GET['err']) && $_GET['err']==='talle'):?>
      <div class="alert-err">⚠️ Elegí un talle o ingresá medidas.</div>
    <?php endif;?>

    <?php if(!empty($flash_err)):?>
      <div class="alert-err">⚠️ <?=h($flash_err)?></div>
    <?php endif;?>

    <?php if(isset($_GET['confirm'])): ?>
      <div class="alert-ok">
        ✅ Pedido confirmado (ticket <b><?=h($_GET['ticket']??'-')?></b>).
        <?php if(($_GET['modo']??'')==='efectivo'): ?> Pagás en el local en efectivo.<?php endif; ?>
        <?php if(($_GET['modo']??'')==='tarjeta'): ?> Pagás en el local con tarjeta (3 a 6 cuotas).<?php endif; ?>
        <?php if(($_GET['modo']??'')==='transferencia'): ?> Recibimos tu comprobante de transferencia. ¡Gracias!<?php endif; ?>
        <?php if(!empty($_GET['comp'])): ?><div class="mini">Comprobante: <a href="<?=h($_GET['comp'])?>" target="_blank" style="color:#ffd600">ver archivo</a></div><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid">
      <!-- CATALOGO -->
      <section class="card">
        <div class="products">
          <?php if(empty($productos)): ?>
            <div class="mini">No hay productos cargados.</div>
          <?php else: foreach($productos as $p):
            $pid   = (int)$p['id'];
            $sets  = plantillas_por_categoria((string)$p['categoria']);

            // Guías por producto (opcional)
            $guias = [];
            if (db_has_table($conexion,'ind_producto_guias')) {
              if ($st=$conexion->prepare("SELECT `orden`,img_url FROM ind_producto_guias WHERE producto_id=? ORDER BY `orden`")) {
                $st->bind_param('i',$pid); $st->execute(); $res=$st->get_result();
                if ($res) while($x=$res->fetch_assoc()){ $guias[(int)$x['orden']]=$x['img_url']; }
                $st->close();
              }
            }
          ?>
          <article class="p-card" id="prod-<?=$pid?>">
            <div class="p-img" onclick="verImagen('<?=h($p['img'])?>')">
              <?php if(!empty($p['img'])):?>
                <img src="<?=h($p['img'])?>" alt="<?=h($p['nombre'])?>">
              <?php else:?>
                <span class="mini" style="padding:8px">Sin imagen</span>
              <?php endif;?>
            </div>

            <div>
              <b><?=h($p['nombre'])?></b><br>
              <small class="mini"><?=h($p['descripcion'])?></small>
            </div>
            <div class="price">$ <?=money($p['precio'])?></div>

            <div class="mini" style="color:#ffd600">Guía de talles predeterminada.</div>
            <div class="tabs" id="tabs-<?=$pid?>"></div>
            <div class="chips" id="chips-<?=$pid?>"></div>
            <div id="info-<?=$pid?>" class="mini" style="margin-top:6px;display:none"></div>

            <!-- Medidas manuales -->
            <label class="mini" style="display:flex;gap:8px;align-items:center;margin-top:8px">
              <input type="checkbox" id="man-<?=$pid?>"> Cargar medidas manuales
            </label>
            <div id="form-man-<?=$pid?>" style="display:none;margin-top:6px">
              <div class="chips" style="gap:8px">
                <input type="number" step="0.01" id="m-ancho-<?=$pid?>" placeholder="Ancho (cm)" style="background:#0f1115;color:#fff;border:1px solid #333;border-radius:8px;padding:6px">
                <input type="number" step="0.01" id="m-largo-<?=$pid?>" placeholder="Largo (cm)" style="background:#0f1115;color:#fff;border:1px solid #333;border-radius:8px;padding:6px">
                <input type="number" step="0.01" id="m-cint-<?=$pid?>" placeholder="Cintura (cm)" style="background:#0f1115;color:#fff;border:1px solid #333;border-radius:8px;padding:6px">
                <input type="number" step="0.01" id="m-lpier-<?=$pid?>" placeholder="Largo pierna (cm)" style="background:#0f1115;color:#fff;border:1px solid #333;border-radius:8px;padding:6px">
                <input type="number" step="0.01" id="m-apier-<?=$pid?>" placeholder="Ancho pierna (cm)" style="background:#0f1115;color:#fff;border:1px solid #333;border-radius:8px;padding:6px">
              </div>
            </div>

            <!-- Form agregar -->
            <form method="post" action="#carrito" onsubmit="return prepSubmit(<?=$pid?>)">
              <input type="hidden" name="csrf" value="<?=$csrf?>">
              <input type="hidden" name="act" value="add">
              <input type="hidden" name="pid" value="<?=$pid?>">
              <input type="hidden" name="nom" value="<?=h($p['nombre'])?>">
              <input type="hidden" name="precio" value="<?=h($p['precio'])?>">
              <input type="hidden" name="img" value="<?=h($p['img'])?>">
              <input type="hidden" name="talla" id="hid-talla-<?=$pid?>">
              <input type="hidden" name="medidas" id="hid-medidas-<?=$pid?>">
              <button class="btn" type="submit" style="margin-top:6px">Agregar al carrito</button>
            </form>

            <!-- Guías -->
            <?php if($guias): ?>
              <div class="sep"></div>
              <div class="mini">📏 Guías de talles</div>
              <div class="chips" style="gap:10px">
                <?php for($i=1;$i<=3;$i++): if(!empty($guias[$i])): ?>
                  <img src="<?=h($guias[$i])?>" class="guia" onclick="verImagen('<?=h($guias[$i])?>')" alt="Guía <?=$i?>"><span class="badge">Guía <?=$i?></span>
                <?php endif; endfor; ?>
              </div>
            <?php endif; ?>

            <!-- JSON EMBEBIDO de talles -->
            <?php $json_sets = json_encode($sets, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>
            <script type="application/json" id="sizes-json-<?=$pid?>"><?=$json_sets?></script>
          </article>
          <?php endforeach; endif; ?>
        </div>
      </section>

      <!-- CARRITO + CHECKOUT -->
      <aside class="card cart" id="carrito">
        <h3>🧺 Carrito (<?= (int)$cart_qty ?> ítems)</h3>
        <?php if(!$cart): ?>
          <p class="mini" style="color:#888;margin:0">Tu carrito está vacío.</p>
        <?php else: foreach($cart as $key=>$it): ?>
          <div style="border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:8px;margin-bottom:8px;display:flex;gap:8px;align-items:center;background:rgba(255,255,255,.04)">
            <div style="width:60px;height:60px;border-radius:8px;overflow:hidden;background:#0f1115;display:grid;place-items:center">
              <?php if(!empty($it['img'])):?><img src="<?=h($it['img'])?>" style="width:100%;height:100%;object-fit:cover"><?php else:?><span style="color:#666">Img</span><?php endif;?>
            </div>
            <div style="flex:1">
              <b><?=h($it['nombre'])?></b><br>
              <?php if(!empty($it['talla'])):?><small class="mini">Talle: <?=h($it['talla'])?> · </small><?php endif; ?>
              <?php if(!empty($it['medidas'])):?><small class="mini">Medidas: <?=h($it['medidas'])?> · </small><?php endif; ?>
              <small>$ <?=money($it['precio'])?></small>
            </div>
            <form method="post" style="display:flex;gap:6px">
              <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="act" value="chg">
              <input type="hidden" name="key" value="<?=h($key)?>">
              <input type="number" name="q" value="<?= (int)$it['q']?>" min="0" step="1" style="width:64px;background:#0f1115;color:#fff;border:1px solid #333;border-radius:6px;text-align:center">
              <button class="btn" type="submit" title="Actualizar">↻</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="act" value="del">
              <input type="hidden" name="key" value="<?=h($key)?>"><button class="btn-ghost" type="submit" title="Quitar">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-weight:800;margin-top:10px">
          <div>Total</div><div>$ <?=money($cart_total)?></div>
        </div>
        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?=$csrf?>"><input type="hidden" name="act" value="clear">
          <button class="btn-ghost" type="submit">Vaciar carrito</button>
        </form>

        <!-- === CHECKOUT === -->
        <div class="sep"></div>
        <h4>💳 Formas de pago</h4>

        <?php if($multi_products): ?>
          <p class="mini" style="margin:6px 0 10px">
            ⚠️ Tenés <b>varios productos</b> en el carrito. Mostramos los datos bancarios del <b>primer producto</b>.
            Si querés que elijamos por producto o combinemos, decime y lo ajusto.
          </p>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <input type="hidden" name="act" value="checkout">

          <label class="payrow">
            <input type="radio" name="pago" value="efectivo" checked>
            <span>Efectivo (en el local)</span>
          </label>
          <div class="mini" style="margin:-2px 0 6px 26px;">Pagás cuando retirás en recepción.</div>

          <label class="payrow" style="margin-top:6px">
            <input type="radio" name="pago" value="tarjeta">
            <span>Tarjeta de crédito (en el local)</span>
          </label>
          <div class="mini" style="margin:-2px 0 6px 26px;">3 a 6 cuotas sin interés en el local.</div>

          <label class="payrow" style="margin-top:6px">
            <input type="radio" name="pago" value="transferencia" id="rb-transf">
            <span>Transferencia bancaria</span>
          </label>

          <div id="pane-transf" style="display:none;margin:6px 0 0 26px;border:1px dashed #444;border-radius:10px;padding:10px">
            <div class="kv">
              <b>Alias:</b>         <div><?=h($PAGOS_UI['alias'])?></div>
              <b>CBU:</b>           <div><?=h($PAGOS_UI['cbu'])?></div>
              <b>Banco:</b>         <div><?=h($PAGOS_UI['banco'])?></div>
              <b>Cuenta:</b>        <div><?=h($PAGOS_UI['cuenta_tipo'])?> <?=h($PAGOS_UI['cuenta_numero'])?></div>
              <b>Titular:</b>       <div><?=h($PAGOS_UI['titular'])?></div>
              <b>CUIT/CUIL:</b>     <div><?=h($PAGOS_UI['cuit'])?></div>
              <?php if(!empty($PAGOS_UI['mp_link'])): ?>
                <b>MP Link:</b>     <div><a href="<?=h($PAGOS_UI['mp_link'])?>" target="_blank" style="color:#ffd600">Pagar con Mercado Pago</a></div>
              <?php endif; ?>
            </div>
            <?php if(!empty($PAGOS_UI['nota'])): ?>
              <div class="mini" style="margin-top:6px"><?=h($PAGOS_UI['nota'])?></div>
            <?php endif; ?>
            <?php if(!empty($PAGOS_UI['qr_url'])): ?>
              <div style="margin-top:10px;display:flex;gap:10px;align-items:center">
                <img src="<?=h($PAGOS_UI['qr_url'])?>" alt="QR" style="width:110px;height:110px;border-radius:10px;border:1px solid #333;background:#0f1115;cursor:pointer" onclick="verImagen('<?=h($PAGOS_UI['qr_url'])?>')">
                <span class="mini">Escaneá el QR para pagar.</span>
              </div>
            <?php endif; ?>

            <div class="mini" style="margin-top:10px">Adjuntá el comprobante (JPG/PNG/PDF · máx 5 MB):</div>
            <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" style="margin-top:6px;color:#ccc">
          </div>

          <button class="btn" type="submit" style="margin-top:12px;width:100%">Confirmar pedido</button>
        </form>
        <?php endif; ?>

        <p class="mini" style="margin-top:10px">
          💡 Si pagás por transferencia, el equipo validará el comprobante antes de preparar tu pedido.
        </p>
      </aside>
    </div>
  </div>

  <!-- Lightbox simple -->
  <div id="lightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9999;justify-content:center;align-items:center">
    <img id="lightimg" src="" style="max-width:90%;max-height:90%;border:4px solid #fff;border-radius:10px">
  </div>

  <script>
  function verImagen(url){
    if(!url) return;
    const lb=document.getElementById('lightbox');
    const img=document.getElementById('lightimg');
    img.src=url; lb.style.display='flex'; lb.onclick=()=>lb.style.display='none';
  }

  // Mostrar/ocultar panel de transferencia (solo si hay checkout visible)
  (function(){
    const pane   = document.getElementById('pane-transf');
    const radios = document.querySelectorAll('input[name="pago"]');
    if (!pane || !radios.length) return;
    function syncPane(){
      let v=''; radios.forEach(r=>{ if(r.checked) v=r.value; });
      pane.style.display = (v==='transferencia') ? 'block' : 'none';
    }
    radios.forEach(r=>r.addEventListener('change', syncPane));
    syncPane();
  })();

  // ====== UI Talles: pestañas + chips + medidas manuales ======
  document.querySelectorAll('[id^="prod-"]').forEach(card=>{
    const pid   = card.id.split('-')[1];
    const tabsEl = card.querySelector('#tabs-'+pid);
    const chipsEl= card.querySelector('#chips-'+pid);
    const infoEl = card.querySelector('#info-'+pid);
    const hidTalle   = card.querySelector('#hid-talla-'+pid);
    const hidMedidas = card.querySelector('#hid-medidas-'+pid);

    const jsonEl = card.querySelector('#sizes-json-'+pid);
    let sets = {};
    if (jsonEl) { try { sets = JSON.parse(jsonEl.textContent); } catch(e){ sets = {}; } }
    const setNames = Object.keys(sets);

    function medidasTxtFrom(btn){
      const a=btn.dataset.ancho, l=btn.dataset.largo, c=btn.dataset.cintura, lp=btn.dataset.lpierna, ap=btn.dataset.apierna;
      const p=[]; if(a)p.push('Ancho '+a+' cm'); if(l)p.push('Largo '+l+' cm'); if(c)p.push('Cintura '+c+' cm'); if(lp)p.push('Largo pierna '+lp+' cm'); if(ap)p.push('Ancho pierna '+ap+' cm');
      return p.join(', ');
    }

    function renderTabs(active){
      tabsEl.innerHTML='';
      setNames.forEach(name=>{
        const b=document.createElement('button');
        b.type='button'; b.textContent=name; if(name===active) b.classList.add('on');
        b.addEventListener('click',()=>{ renderTabs(name); renderChips(name); infoEl.style.display='none'; });
        tabsEl.appendChild(b);
      });
    }

    function renderChips(active){
      chipsEl.innerHTML='';
      (sets[active]||[]).forEach(t=>{
        const b=document.createElement('button');
        b.type='button'; b.textContent=t.talle || '';
        b.dataset.talle = t.talle || '';
        b.dataset.ancho = (t.ancho ?? '');
        b.dataset.largo = (t.largo ?? '');
        b.dataset.cintura = (t.cintura ?? '');
        b.dataset.lpierna = (t.largo_pierna ?? '');
        b.dataset.apierna = (t.ancho_pierna ?? '');
        b.addEventListener('click',()=>{
          chipsEl.querySelectorAll('button').forEach(x=>x.classList.remove('active'));
          b.classList.add('active');
          const m = medidasTxtFrom(b);
          if(m){ infoEl.style.display='block'; infoEl.textContent='Talle '+(b.dataset.talle||'')+' · '+m; } else { infoEl.style.display='none'; }
          hidTalle.value   = b.dataset.talle || '';
          if(!document.getElementById('man-'+pid)?.checked){
            hidMedidas.value = m || '';
          }
        });
        chipsEl.appendChild(b);
      });
    }

    if (setNames.length){
      const first = setNames[0];
      renderTabs(first);
      renderChips(first);
    } else {
      tabsEl.innerHTML = '<span class="mini" style="color:#fbbf24">No hay guías disponibles.</span>';
    }

    // Medidas manuales
    const chkMan = card.querySelector('#man-'+pid);
    const formM  = card.querySelector('#form-man-'+pid);
    chkMan?.addEventListener('change', ()=>{
      const on = chkMan.checked;
      formM.style.display = on ? 'block' : 'none';
      if (!on){
        const act = chipsEl.querySelector('button.active');
        if(act){ hidMedidas.value = medidasTxtFrom(act); }
        else { hidMedidas.value=''; }
      }
    });

    const map = {
      ['m-ancho-'+pid] : 'Ancho',
      ['m-largo-'+pid] : 'Largo',
      ['m-cint-'+pid]  : 'Cintura',
      ['m-lpier-'+pid] : 'Largo pierna',
      ['m-apier-'+pid] : 'Ancho pierna'
    };
    Object.keys(map).forEach(id=>{
      const el = card.querySelector('#'+id);
      el?.addEventListener('input', ()=>{
        if (!chkMan || !chkMan.checked) return;
        const parts=[];
        Object.keys(map).forEach(k=>{
          const e=card.querySelector('#'+k);
          if(e && e.value) parts.push(map[k]+' '+e.value+' cm');
        });
        hidMedidas.value = parts.join(', ');
      });
    });
  });

  // Validación antes de enviar item
  function prepSubmit(pid){
    const t = document.getElementById('hid-talla-'+pid)?.value.trim() || '';
    const m = document.getElementById('hid-medidas-'+pid)?.value.trim() || '';
    if(!t && !m){
      alert('Elegí un talle o cargá medidas.');
      return false;
    }
    return true;
  }
  </script>
</body>
</html>
