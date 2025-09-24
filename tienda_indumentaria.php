<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_cliente.php';


if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf_token'];
if (!isset($_SESSION['cart'])) $_SESSION['cart']=[];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function must_prepare(mysqli $db, string $sql){ $st=$db->prepare($sql); if(!$st) die('❌ SQL prepare error: '.$db->error.'<br><code>'.$sql.'</code>'); return $st; }

/* ===== CARRITO ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($csrf, $_POST['csrf'] ?? '')) {
  $act = $_POST['__a'] ?? '';

  if ($act === 'add') {
    $pid  = (int)($_POST['producto_id'] ?? 0);
    $talle= trim($_POST['talle'] ?? '');
    $qty  = max(1, (int)($_POST['cantidad'] ?? 1));
    $guia = $_POST['guia_tipo'] ?? null;
    $tcalc= $_POST['talle_calc'] ?? null;
    $mjson= $_POST['medidas_json'] ?? null;

    if ($pid <= 0 || $talle === '') { header('Location: tienda_indumentaria.php?err=datos'); exit; }

    $sql = "SELECT p.id, p.titulo, p.precio
            FROM ind_productos p
            WHERE p.id = ? AND p.gimnasio_id = ? AND p.activo = 1
            LIMIT 1";
    $st = must_prepare($conexion, $sql);
    $st->bind_param('ii', $pid, $gimnasio_id);
    $st->execute();
    $p = $st->get_result()->fetch_assoc();
    $st->close();

    if ($p) {
      $_SESSION['cart'][] = [
        'producto_id' => (int)$p['id'],
        'titulo'      => $p['titulo'],
        'talle'       => $talle,
        'cantidad'    => $qty,
        'precio'      => (float)$p['precio'],
        'guia_tipo'   => $guia,
        'talle_calc'  => $tcalc,
        'medidas_json'=> $mjson,
      ];
    }
    header('Location: tienda_indumentaria.php'); exit;
  }

  if ($act === 'remove') {
    $idx = (int)($_POST['idx'] ?? -1);
    if (isset($_SESSION['cart'][$idx])) unset($_SESSION['cart'][$idx]);
    header('Location: tienda_indumentaria.php'); exit;
  }
}

/* ===== LISTADO ===== */
$prods = [];
$st = must_prepare($conexion, "SELECT * FROM ind_productos WHERE gimnasio_id=? AND activo=1 ORDER BY id DESC");
$st->bind_param('i', $gimnasio_id);
$st->execute();
$prods = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* Talles por producto (BD) */
$talles_por_prod = [];
if (!empty($prods)) {
  $ids = array_column($prods, 'id');
  $in    = implode(',', array_fill(0, count($ids), '?'));
  $types = str_repeat('i', count($ids));
  $sql   = "SELECT producto_id, talle, stock FROM ind_talles WHERE producto_id IN ($in) ORDER BY talle";
  $stmt  = must_prepare($conexion, $sql);
  $stmt->bind_param($types, ...$ids);
  $stmt->execute();
  $rs = $stmt->get_result();
  while ($r = $rs->fetch_assoc()) $talles_por_prod[$r['producto_id']][] = $r;
  $stmt->close();
}

/* ===== Fallback de talles por defecto ===== */
$DEF_REMERA_UNISEX = ['XS','S','M','L','XL','2XL','3XL'];
$DEF_REMERA_MUJER  = ['XS','S','M','L','XL','2XL','3XL'];
$DEF_REMERA_NINOS  = ['2','4','6','8','10','12','14','16'];
$DEF_SHORTS        = ['XS','S','M','L','XL','2XL'];

function render_talle_select($pid, $categoria, $desde_bd){
  global $DEF_REMERA_UNISEX,$DEF_REMERA_MUJER,$DEF_REMERA_NINOS,$DEF_SHORTS;
  // Prioriza talles de BD si existen:
  if (!empty($desde_bd)) {
    foreach($desde_bd as $t){
      $stk = isset($t['stock']) ? " (stk: {$t['stock']})" : '';
      echo '<option value="'.h($t['talle']).'">'.h($t['talle']).$stk.'</option>';
    }
    return;
  }
  // Si no hay en BD, usar catálogo por defecto según categoría
  $cat = strtolower($categoria);
  if ($cat==='remera' || $cat==='otro'){
    echo '<optgroup label="Unisex">';
    foreach($DEF_REMERA_UNISEX as $t) echo '<option value="'.h($t).'">'.$t.'</option>';
    echo '</optgroup>';
    echo '<optgroup label="Mujer">';
    foreach($DEF_REMERA_MUJER as $t) echo '<option value="'.h($t).'">'.$t.'</option>';
    echo '</optgroup>';
    echo '<optgroup label="Niños/as">';
    foreach($DEF_REMERA_NINOS as $t) echo '<option value="'.h($t).'">'.$t.'</option>';
    echo '</optgroup>';
  } else { // short / pantalón
    foreach($DEF_SHORTS as $t) echo '<option value="'.h($t).'">'.$t.'</option>';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tienda — Indumentaria</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,Arial;background:#0f1320;color:#fff;margin:0}
 .wrap{max-width:1080px;margin:24px auto;padding:16px}
 .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px}
 .card{background:#141a2a;border:1px solid #24314d;border-radius:14px;padding:16px}
 img{width:100%;height:220px;object-fit:cover;border-radius:10px;border:1px solid #24314d}
 label{display:block;margin:.5rem 0 .25rem}
 input,select,textarea{width:100%;padding:12px;border-radius:12px;border:1px solid #2a3550;background:#0d1322;color:#fff}
 .row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
 .btn{padding:12px 16px;border-radius:12px;border:0;background:#3b82f6;color:#fff;cursor:pointer;font-weight:700}
 table{width:100%;border-collapse:collapse} th,td{padding:8px;border-bottom:1px solid #24314d}
 .mini{font-size:12px;color:#9fb0d3}
 .pill{display:inline-block;padding:6px 10px;border:1px solid #3b82f6;border-radius:999px;font-size:12px;cursor:pointer}
 .guide{background:#0b1220;border:1px solid #1c2a4d;border-radius:14px;padding:16px;margin-top:18px}
 .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
 .tab{padding:8px 12px;border:1px solid #334155;border-radius:10px;cursor:pointer}
 .tab.active{border-color:#3b82f6;background:#0f1a33}
 .hide{display:none}
 .sticky-top{position:sticky;top:0;background:#0f1320;padding:8px 0;z-index:5}
 @media (max-width:760px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="wrap">
  <div class="sticky-top">
    <button class="btn" onclick="document.getElementById('guia_talles').scrollIntoView({behavior:'smooth'})">📏 Guía de talles</button>
  </div>

  <h1>🛍️ Tienda — Indumentaria</h1>

  <div class="grid">
    <?php if(empty($prods)): ?>
      <div class="card"><div class="mini">No hay productos activos cargados.</div></div>
    <?php else: foreach($prods as $p): ?>
      <?php $pid=(int)$p['id']; $cat=strtolower($p['categoria']); ?>
      <div class="card">
        <?php if(!empty($p['foto_url'])): ?><img src="<?=h($p['foto_url'])?>" alt="Foto"><?php endif; ?>
        <h3 style="margin:10px 0 0"><?=h($p['titulo'])?></h3>
        <div class="mini"><?=h($p['categoria'])?></div>
        <div style="font-weight:700;margin:.25rem 0">$<?=number_format($p['precio'],2,',','.')?></div>

        <details style="margin:.5rem 0">
          <summary class="pill">📏 Medir y sugerir talle</summary>
          <div class="mini" style="margin:8px 0">Cargá medidas en cm. Usamos tus tablas.</div>

          <?php if ($cat==='remera' || $cat==='otro'): ?>
            <div class="row" style="align-items:center">
              <div class="mini" style="min-width:120px">Guía:</div>
              <label class="mini"><input type="radio" name="g<?=$pid?>" value="remera_unisex" checked onclick="setGuide('remera_unisex',<?=$pid?>)"> Unisex</label>
              <label class="mini"><input type="radio" name="g<?=$pid?>" value="remera_mujer" onclick="setGuide('remera_mujer',<?=$pid?>)"> Mujer</label>
              <label class="mini"><input type="radio" name="g<?=$pid?>" value="remera_ninos" onclick="setGuide('remera_ninos',<?=$pid?>)"> Niños/as</label>
            </div>
            <div class="row" style="margin-top:6px">
              <div style="flex:1;min-width:140px">
                <label for="ancho_<?=$pid?>">Ancho (A)</label>
                <input id="ancho_<?=$pid?>" type="number" min="0" step="1" placeholder="Ej: 53">
              </div>
              <div style="flex:1;min-width:140px">
                <label for="largo_<?=$pid?>">Largo (B)</label>
                <input id="largo_<?=$pid?>" type="number" min="0" step="1" placeholder="Ej: 73">
              </div>
              <div style="min-width:160px">
                <label>&nbsp;</label>
                <button type="button" class="btn" onclick="calcRemeraGeneric(<?=$pid?>)">Sugerir talle</button>
              </div>
            </div>
            <div class="mini" id="sugg_<?=$pid?>" style="margin-top:6px;color:#93c5fd"></div>

          <?php else: /* short/pantalón */ ?>
            <div class="row">
              <div style="flex:1;min-width:140px">
                <label for="cintura_<?=$pid?>">Cintura</label>
                <input id="cintura_<?=$pid?>" type="number" min="0" step="1" placeholder="Ej: 87">
              </div>
              <div style="flex:1;min-width:140px">
                <label for="largop_<?=$pid?>">Largo</label>
                <input id="largop_<?=$pid?>" type="number" min="0" step="1" placeholder="Ej: 32">
              </div>
              <div style="flex:1;min-width:140px">
                <label for="pierna_<?=$pid?>">Ancho pierna</label>
                <input id="pierna_<?=$pid?>" type="number" min="0" step="1" placeholder="Ej: 30">
              </div>
              <div style="min-width:160px">
                <label>&nbsp;</label>
                <button type="button" class="btn" onclick="calcShort(<?=$pid?>)">Sugerir talle</button>
              </div>
            </div>
            <div class="mini" id="sugg_<?=$pid?>" style="margin-top:6px;color:#93c5fd"></div>
          <?php endif; ?>
        </details>

        <form method="post" class="row">
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <input type="hidden" name="__a" value="add">
          <input type="hidden" name="producto_id" value="<?=$pid?>">

          <!-- hidden para enviar mediciones + sugerencia -->
          <input type="hidden" id="guia_tipo_<?=$pid?>" name="guia_tipo" value="">
          <input type="hidden" id="medidas_json_<?=$pid?>" name="medidas_json" value="">
          <input type="hidden" id="talle_calc_<?=$pid?>" name="talle_calc" value="">

          <div style="flex:2;min-width:160px">
            <label>Talle <span class="mini" id="hint_<?=$pid?>"></span></label>
            <select id="talle_sel_<?=$pid?>" name="talle" required>
              <option value="">Elegí…</option>
              <?php render_talle_select($pid,$p['categoria'],$talles_por_prod[$pid]??[]); ?>
            </select>
          </div>

          <div style="flex:1;min-width:120px">
            <label>Cant.</label>
            <input type="number" name="cantidad" min="1" value="1">
          </div>

          <div style="min-width:170px;display:flex;align-items:center;gap:6px">
            <input id="conf_<?=$pid?>" type="checkbox" required>
            <label for="conf_<?=$pid?>" class="mini">Confirmo que medí y elegí el talle</label>
          </div>

          <div style="min-width:140px"><button class="btn">Agregar</button></div>
        </form>

        <div class="mini" style="margin-top:6px">
          <span class="pill" onclick="document.getElementById('guia_talles').scrollIntoView({behavior:'smooth'})">📏 Ver guía de talles</span>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card" style="margin-top:18px">
    <h2>🧾 Carrito</h2>
    <?php $total=0; if (!empty($_SESSION['cart'])): ?>
      <table>
        <thead><tr><th>#</th><th>Producto</th><th>Talle</th><th>Cant.</th><th>Precio</th><th>Subtotal</th><th></th></tr></thead>
        <tbody>
          <?php foreach(array_values($_SESSION['cart']) as $i=>$it): $st=$it['cantidad']*$it['precio']; $total+=$st; ?>
            <tr>
              <td><?=$i+1?></td>
              <td><?=h($it['titulo'])?></td>
              <td><?=h($it['talle'])?></td>
              <td><?=$it['cantidad']?></td>
              <td>$<?=number_format($it['precio'],2,',','.')?></td>
              <td>$<?=number_format($st,2,',','.')?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="__a" value="remove">
                  <input type="hidden" name="idx" value="<?=$i?>">
                  <button class="btn" style="background:#475569">Quitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="5" style="text-align:right">TOTAL</th><th>$<?=number_format($total,2,',','.')?></th><th></th></tr></tfoot>
      </table>

      <form class="row" style="margin-top:12px" method="post" action="guardar_pedido_indumentaria.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <div style="flex:1;min-width:220px">
          <label>Forma de pago</label>
          <select name="pago" id="pago" required>
            <option value="sena_efectivo">Seña 30% (Efectivo)</option>
            <option value="total_efectivo">Total (Efectivo)</option>
            <option value="sena_transferencia">Seña 30% (Transferencia)</option>
            <option value="total_transferencia">Total (Transferencia)</option>
          </select>
        </div>
        <div style="flex:1;min-width:280px" id="wrap_comp" hidden>
          <label>Comprobante (si transferencia)</label>
          <input type="file" name="comprobante" accept="image/*,application/pdf">
          <div class="mini">Se sube a la nube.</div>
        </div>
        <div><button class="btn">Confirmar pedido</button></div>
      </form>
      <script>
        const pago=document.getElementById('pago'); const wc=document.getElementById('wrap_comp');
        function t(){ wc.hidden = !(pago.value.includes('transferencia')); } pago.addEventListener('change',t); t();
      </script>
    <?php else: ?>
      <p class="mini">Tu carrito está vacío.</p>
    <?php endif; ?>
  </div>

  <!-- ============ Guía de talles (informativa) ============ -->
  <div id="guia_talles" class="guide">
    <h2>📏 Guía de talles</h2>
    <p class="mini">Medidas orientativas en centímetros. Recomendamos medir una prenda propia.</p>
    <div class="tabs">
      <span class="tab active" data-tab="unisex">Remera Unisex</span>
      <span class="tab" data-tab="mujer">Remera Mujer</span>
      <span class="tab" data-tab="ninos">Remera Niños/as</span>
      <span class="tab" data-tab="short">Short Muay Thai</span>
    </div>

    <div id="tab-unisex">
      <table>
        <thead><tr><th>Talle</th><th>Ancho</th><th>Largo</th></tr></thead>
        <tbody>
          <tr><td>XS</td><td>48</td><td>67</td></tr><tr><td>S</td><td>50</td><td>70</td></tr>
          <tr><td>M</td><td>53</td><td>73</td></tr><tr><td>L</td><td>55</td><td>77</td></tr>
          <tr><td>XL</td><td>57</td><td>79</td></tr><tr><td>2XL</td><td>63</td><td>82</td></tr>
          <tr><td>3XL</td><td>66</td><td>84</td></tr>
        </tbody>
      </table>
    </div>
    <div id="tab-mujer" class="hide">
      <table>
        <thead><tr><th>Talle</th><th>Ancho</th><th>Largo</th></tr></thead>
        <tbody>
          <tr><td>XS</td><td>37</td><td>60</td></tr><tr><td>S</td><td>39</td><td>62</td></tr>
          <tr><td>M</td><td>41</td><td>64</td></tr><tr><td>L</td><td>43</td><td>66</td></tr>
          <tr><td>XL</td><td>45</td><td>68</td></tr><tr><td>2XL</td><td>47</td><td>70</td></tr>
          <tr><td>3XL</td><td>49</td><td>72</td></tr>
        </tbody>
      </table>
    </div>
    <div id="tab-ninos" class="hide">
      <table>
        <thead><tr><th>Talle</th><th>Ancho</th><th>Largo</th></tr></thead>
        <tbody>
          <tr><td>2</td><td>33</td><td>42</td></tr><tr><td>4</td><td>34</td><td>44</td></tr>
          <tr><td>6</td><td>36</td><td>46</td></tr><tr><td>8</td><td>39</td><td>51</td></tr>
          <tr><td>10</td><td>41</td><td>53</td></tr><tr><td>12</td><td>42</td><td>54</td></tr>
          <tr><td>14</td><td>45</td><td>59</td></tr><tr><td>16</td><td>45</td><td>63</td></tr>
        </tbody>
      </table>
    </div>
    <div id="tab-short" class="hide">
      <table>
        <thead><tr><th>Talle</th><th>Cintura</th><th>Largo</th><th>Ancho pierna</th></tr></thead>
        <tbody>
          <tr><td>XS</td><td>77–81</td><td>29</td><td>29</td></tr><tr><td>S</td><td>82–86</td><td>30</td><td>29</td></tr>
          <tr><td>M</td><td>87–91</td><td>32</td><td>30</td></tr><tr><td>L</td><td>92–96</td><td>34</td><td>31</td></tr>
          <tr><td>XL</td><td>97–101</td><td>35</td><td>33</td></tr><tr><td>2XL</td><td>102–110</td><td>37</td><td>36</td></tr>
        </tbody>
      </table>
    </div>
    <p class="mini">⚠️ Variación ±1–2 cm según lote/tejido.</p>
  </div>
</div>

<script>
// ===== Tablas para sugerencia =====
const REMERA_UNISEX = {XS:{ancho:48,largo:67}, S:{ancho:50,largo:70}, M:{ancho:53,largo:73}, L:{ancho:55,largo:77}, XL:{ancho:57,largo:79}, '2XL':{ancho:63,largo:82}, '3XL':{ancho:66,largo:84}};
const REMERA_MUJER  = {XS:{ancho:37,largo:60}, S:{ancho:39,largo:62}, M:{ancho:41,largo:64}, L:{ancho:43,largo:66}, XL:{ancho:45,largo:68}, '2XL':{ancho:47,largo:70}, '3XL':{ancho:49,largo:72}};
const REMERA_NINOS  = {'2':{ancho:33,largo:42}, '4':{ancho:34,largo:44}, '6':{ancho:36,largo:46}, '8':{ancho:39,largo:51}, '10':{ancho:41,largo:53}, '12':{ancho:42,largo:54}, '14':{ancho:45,largo:59}, '16':{ancho:45,largo:63}};
const SHORT = {XS:{cintura:[77,81], largo:29, pierna:29}, S:{cintura:[82,86], largo:30, pierna:29}, M:{cintura:[87,91], largo:32, pierna:30}, L:{cintura:[92,96], largo:34, pierna:31}, XL:{cintura:[97,101], largo:35, pierna:33}, '2XL':{cintura:[102,110], largo:37, pierna:36}};

const fmt = (n)=>Number(n||0);
const guides = {}; // pid -> guía actual

function setGuide(guide, pid){
  guides[pid] = guide;
  document.getElementById('guia_tipo_'+pid)?.setAttribute('value', guide);
  const h = document.getElementById('hint_'+pid); if(h) h.textContent='';
  const s = document.getElementById('sugg_'+pid); if(s) s.textContent='';
}
function suggestFrom(table, A,B){
  let best=null, score=1e9;
  Object.entries(table).forEach(([t,v])=>{
    const d = Math.abs(v.ancho-A) + Math.abs(v.largo-B);
    if (d<score){ score=d; best=t; }
  });
  return best;
}
function calcRemeraGeneric(pid){
  const A = fmt(document.getElementById('ancho_'+pid).value);
  const B = fmt(document.getElementById('largo_'+pid).value);
  if (!A || !B){ alert('Completá Ancho y Largo'); return; }
  const g = guides[pid] || 'remera_unisex';
  let tbl = REMERA_UNISEX; if (g==='remera_mujer') tbl=REMERA_MUJER; if (g==='remera_ninos') tbl=REMERA_NINOS;
  const talle = suggestFrom(tbl, A,B);
  document.getElementById('sugg_'+pid).textContent = 'Sugerencia: '+talle+' (A≈'+A+' / B≈'+B+')';
  const sel=document.getElementById('talle_sel_'+pid);
  // selecciona automáticamente si existe el talle entre las opciones (ya sea BD o fallback)
  [...sel.options].forEach(o=>{ if(o.value==talle) sel.value=o.value; });
  document.getElementById('guia_tipo_'+pid).value=g;
  document.getElementById('medidas_json_'+pid).value=JSON.stringify({ancho:A,largo:B});
  document.getElementById('talle_calc_'+pid).value=talle;
  const hint=document.getElementById('hint_'+pid); if(hint) hint.textContent='(sugerido: '+talle+')';
}
function suggestShort(C,D,E){
  let arr=[];
  Object.entries(SHORT).forEach(([t,v])=>{
    const [minC,maxC]=v.cintura;
    const inRange = (C>=minC && C<=maxC);
    const dist = Math.abs(v.largo-D) + Math.abs(v.pierna-E);
    arr.push({t,inRange,dist});
  });
  arr.sort((a,b)=> (b.inRange - a.inRange) || (a.dist - b.dist));
  return arr[0]?.t || 'M';
}
function calcShort(pid){
  const C = fmt(document.getElementById('cintura_'+pid).value);
  const D = fmt(document.getElementById('largop_'+pid).value);
  const E = fmt(document.getElementById('pierna_'+pid).value);
  if (!C || !D || !E){ alert('Completá Cintura, Largo y Pierna'); return; }
  const talle = suggestShort(C,D,E);
  document.getElementById('sugg_'+pid).textContent = 'Sugerencia: '+talle+' (C≈'+C+' / D≈'+D+' / E≈'+E+')';
  const sel=document.getElementById('talle_sel_'+pid); [...sel.options].forEach(o=>{ if(o.value==talle) sel.value=o.value; });
  document.getElementById('guia_tipo_'+pid).value='short_muay_thai';
  document.getElementById('medidas_json_'+pid).value=JSON.stringify({cintura:C,largo:D,pierna:E});
  document.getElementById('talle_calc_'+pid).value=talle;
  const hint=document.getElementById('hint_'+pid); if(hint) hint.textContent='(sugerido: '+talle+')';
}

// Tabs guía (informativas)
document.querySelectorAll('.tabs .tab').forEach(tab=>{
  tab.addEventListener('click', ()=>{
    document.querySelectorAll('.tabs .tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('#guia_talles > div[id^="tab-"]').forEach(p=>p.classList.add('hide'));
    tab.classList.add('active');
    document.getElementById('tab-'+tab.dataset.tab).classList.remove('hide');
  });
});
</script>
</body>
</html>
