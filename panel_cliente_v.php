<?php
// panel_cliente.php — versión sin Tailwind, responsive y con estilos propios
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['gimnasio_id'])) {
  echo "<div style='color:red; font-size:20px; text-align:center;'>❌ Acceso denegado.</div>";
  exit;
}

$cliente_id   = (int)($_SESSION['cliente_id']);
$gimnasio_id  = (int)($_SESSION['gimnasio_id']);
$cliente_nombre = $_SESSION['cliente_nombre'] ?? 'Cliente';

require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_cliente.php';

$cliente = [
  'id'=>$cliente_id,'nombre'=>$cliente_nombre,'apellido'=>'','email'=>'','telefono'=>'',
  'direccion'=>'','fecha_alta'=>'','estado'=>'','foto'=>'','foto_base64'=>''
];

if ($conexion instanceof mysqli) {
  if ($stmt = @$conexion->prepare("SELECT id, nombre, apellido, email, telefono, direccion, fecha_alta, estado, foto, foto_base64 FROM clientes WHERE id=? LIMIT 1")) {
    $stmt->bind_param('i',$cliente_id);
    if ($stmt->execute()) {
      $res=$stmt->get_result();
      if ($res && $row=$res->fetch_assoc()) { $cliente=array_merge($cliente,$row); }
    }
    $stmt->close();
  } else {
    $q=@$conexion->query("SELECT * FROM clientes WHERE id={$cliente_id} LIMIT 1");
    if ($q && $row=$q->fetch_assoc()) { $cliente=array_merge($cliente,$row); }
  }
}

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }

// Resolver foto
$foto_url='';
if (!empty($cliente['foto_base64']) && str_starts_with((string)$cliente['foto_base64'],'data:image')) {
  $foto_url=$cliente['foto_base64'];
} else {
  $f=trim((string)($cliente['foto']??''));
  $path=__DIR__."/fotos_clientes/".$f;
  if ($f!=='' && is_file($path)) $foto_url="fotos_clientes/".rawurlencode($f);
  else $foto_url="fotos_clientes/default.png";
}

// Debug opcional
$show_debug = (isset($_GET['debug']) && $_GET['debug']=='1');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Panel del Cliente</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4;
    --acc:#f5c542; --ring:#2d323d; --border:rgba(255,255,255,.10);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    color:var(--fg);
    background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg);
  }
  .container{max-width:1100px; margin:0 auto; padding:16px 16px 48px}
  .glass{
    background: rgba(255,255,255,.05);
    border: 1px solid var(--border);
    border-radius: 20px;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 30px rgba(0,0,0,.35);
  }
  .header{display:flex; gap:20px; align-items:flex-end; padding:20px}
  .avatar-wrap{position:relative; width:110px; height:110px}
  .avatar{
    width:110px; height:110px; border-radius:50%; object-fit:cover; border:4px solid var(--acc);
  }
  .badge{
    position:absolute; left:50%; transform:translateX(-50%); bottom:-10px;
    font-size:12px; padding:4px 8px; border-radius:999px;
    background: rgba(245,197,66,.15); color:var(--acc); border:1px solid rgba(245,197,66,.35);
  }
  .title{margin:0; font-weight:800; line-height:1.1}
  .subtitle{margin:6px 0 0; color:var(--muted); font-size:14px}
  .btn{
    display:inline-block; padding:10px 16px; border-radius:14px; font-weight:600; font-size:14px;
    border:1px solid var(--border); color:var(--fg); text-decoration:none;
  }
  .btn:hover{border-color:#ffffff33}
  .btn-primary{
    background:var(--acc); color:#111; border:none;
  }
  .grid{
    display:grid; gap:16px; margin-top:18px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 740px){
    .grid{ grid-template-columns: repeat(3, 1fr); }
    .col-span-2{ grid-column: span 2; }
  }
  .card{ padding:18px }
  .card h2{ margin:0 0 10px; font-size:16px; font-weight:700 }
  .dl{ display:flex; flex-direction:column; gap:10px; font-size:14px; color:var(--muted) }
  .dl .row{ display:flex; justify-content:space-between; gap:12px }
  .dl .val{ color:var(--fg) }
  .quick{
    display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;
  }
  .quick a{
    display:block; text-align:center; padding:12px; border-radius:14px; border:1px solid var(--border);
    color:var(--fg); text-decoration:none; font-size:14px;
  }
  .quick a:hover{ border-color:#ffffff33 }
  .state ul{ list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; color:var(--muted); font-size:14px}
  .debug{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; color:#cde7cd; background:#0b0f0b; padding:14px; border-radius:14px; border:1px solid var(--border); overflow:auto; }
  /* Mobile tweaks */
  .header{ flex-direction:column; align-items:center; text-align:center }
  @media (min-width:740px){ .header{ flex-direction:row; text-align:left } }
</style>
</head>
<body>
  <div class="container">

    <!-- Encabezado -->
    <section class="glass header">
      <div class="avatar-wrap">
        <img class="avatar" src="<?= h($foto_url) ?>" alt="Foto de <?= h($cliente['nombre'] ?: $cliente_nombre) ?>">
        <span class="badge"><?= h($cliente['estado'] ?: 'Activo') ?></span>
      </div>
      <div style="flex:1 1 auto">
        <h1 class="title" style="font-size:28px">
          👋 Bienvenido, <span style="color:var(--acc)"><?= h($cliente['nombre'] ?: $cliente_nombre) ?></span>
        </h1>
        <p class="subtitle">
          Miembro desde: <?= h($cliente['fecha_alta'] ?: '—') ?> · Gimnasio #<?= (int)$gimnasio_id ?>
        </p>
      </div>
      <div>
        <a class="btn" href="cliente_editar.php">Editar perfil</a>
        <a class="btn" href="cliente_qr.php">Mi QR</a>
        <a class="btn" href="logout.php">Salir</a>
      </div>
    </section>

    <!-- Tarjetas -->
    <section class="grid">
      <!-- Datos personales -->
      <article class="glass card">
        <h2>Tus datos</h2>
        <div class="dl">
          <div class="row"><span>Nombre</span><span class="val"><?= h(trim(($cliente['nombre'] ?? '').' '.($cliente['apellido'] ?? ''))) ?></span></div>
          <div class="row"><span>Email</span><span class="val"><?= h($cliente['email'] ?: '—') ?></span></div>
          <div class="row"><span>Teléfono</span><span class="val"><?= h($cliente['telefono'] ?: '—') ?></span></div>
          <div class="row"><span>Dirección</span><span class="val"><?= h($cliente['direccion'] ?: '—') ?></span></div>
          <div class="row"><span>Estado</span><span class="val"><?= h($cliente['estado'] ?: '—') ?></span></div>
        </div>
      </article>

      <!-- Accesos rápidos -->
      <article class="glass card">
        <h2>Accesos rápidos</h2>
        <div class="quick">
          <a href="cliente_turnos.php">Mis turnos</a>
          <a href="cliente_pagos.php">Pagos</a>
          <a href="cliente_membresia.php">Membresía</a>
          <a href="cliente_historial.php">Historial</a>
        </div>
      </article>

      <!-- Estado -->
      <article class="glass card">
        <h2>Estado rápido</h2>
        <div class="state">
          <ul>
            <li>✔️ Acceso habilitado</li>
            <li>💳 Próximo vencimiento: <span style="color:var(--fg)">—</span></li>
            <li>📅 Próximo turno: <span style="color:var(--fg)">—</span></li>
          </ul>
          <p style="margin-top:8px; color:#9aa3b2; font-size:12px">
            * Podés reemplazar “—” con datos reales de tus tablas (pagos/turnos).
          </p>
        </div>
      </article>

      <!-- (Opcional) Noticias/Promos — ocupa 2 columnas en desktop -->
      <article class="glass card col-span-2">
        <h2>Novedades</h2>
        <p style="color:var(--muted); font-size:14px">Agregá promos, anuncios o recordatorios para el cliente.</p>
      </article>
    </section>

    <!-- Debug (solo ?debug=1) -->
    <?php if ($show_debug): ?>
    <section style="margin-top:18px">
      <div class="glass card">
        <h2>🧪 Sesión actual (debug)</h2>
        <pre class="debug"><?= h(print_r($_SESSION,true)) ?></pre>
      </div>
    </section>
    <?php endif; ?>

  </div>
</body>
</html>
