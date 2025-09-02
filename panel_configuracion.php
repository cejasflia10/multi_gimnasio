<?php
/* ===== DEBUG (desactivar en producción) ===== */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) exit("❌ Acceso denegado.");

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit("❌ No hay conexión a la base de datos.");
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ================= Helpers ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_venc($v){
  if (empty($v) || $v === '0000-00-00') return 'Sin fecha';
  $t = strtotime($v);
  return $t ? date('d/m/Y', $t) : 'Sin fecha';
}
function ensure_col(mysqli $db, string $table, string $col, string $definition): void {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $check = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}' LIMIT 1");
  if (!$check || $check->num_rows === 0) { @$db->query("ALTER TABLE {$t} ADD COLUMN {$col} {$definition}"); }
}

/* ====== Auto-migración: columnas de diseño + menús ====== */
ensure_col($conexion, 'configuracion_gimnasio', 'theme_palette',        "VARCHAR(50) NULL DEFAULT 'gold-dark'");
ensure_col($conexion, 'configuracion_gimnasio', 'primary_color',        "VARCHAR(7)  NULL DEFAULT '#FFD700'");
ensure_col($conexion, 'configuracion_gimnasio', 'secondary_color',      "VARCHAR(7)  NULL DEFAULT '#111111'");
ensure_col($conexion, 'configuracion_gimnasio', 'accent_color',         "VARCHAR(7)  NULL DEFAULT '#00D1B2'");
ensure_col($conexion, 'configuracion_gimnasio', 'bg_color',             "VARCHAR(7)  NULL DEFAULT '#000000'");
ensure_col($conexion, 'configuracion_gimnasio', 'text_color',           "VARCHAR(7)  NULL DEFAULT '#F5F5F5'");
ensure_col($conexion, 'configuracion_gimnasio', 'font_family',          "VARCHAR(50) NULL DEFAULT 'Inter'");
ensure_col($conexion, 'configuracion_gimnasio', 'font_size_base',       "INT NULL DEFAULT 16");
ensure_col($conexion, 'configuracion_gimnasio', 'layout_style',         "VARCHAR(30) NULL DEFAULT 'classic'");
ensure_col($conexion, 'configuracion_gimnasio', 'hero_title',           "VARCHAR(120) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'hero_subtitle',        "VARCHAR(200) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'hero_cta_text',        "VARCHAR(80) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'hero_cta_link',        "VARCHAR(255) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'hero_bg_image_url',    "VARCHAR(255) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'favicon_url',          "VARCHAR(255) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'logo_url',             "VARCHAR(255) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'icon_style',           "VARCHAR(20) NULL DEFAULT 'outline'");
ensure_col($conexion, 'configuracion_gimnasio', 'show_social_icons',    "TINYINT(1) NULL DEFAULT 1");
ensure_col($conexion, 'configuracion_gimnasio', 'footer_text',          "VARCHAR(255) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'custom_css',           "TEXT NULL");

/* === NUEVO: colores/textos de menús === */
ensure_col($conexion, 'configuracion_gimnasio', 'menu_top_bg_color',    "VARCHAR(7)  NULL DEFAULT '#0B0B0B'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_top_text_color',  "VARCHAR(7)  NULL DEFAULT '#FFFFFF'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_top_hover_color', "VARCHAR(7)  NULL DEFAULT '#FFD700'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_prof_bg_color',   "VARCHAR(7)  NULL DEFAULT '#0B1220'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_prof_text_color', "VARCHAR(7)  NULL DEFAULT '#E5E7EB'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_prof_hover_color',"VARCHAR(7)  NULL DEFAULT '#60A5FA'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_cli_bg_color',    "VARCHAR(7)  NULL DEFAULT '#111111'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_cli_text_color',  "VARCHAR(7)  NULL DEFAULT '#F5F5F5'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_cli_hover_color', "VARCHAR(7)  NULL DEFAULT '#A7F3D0'");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_top_brand_text',  "VARCHAR(60) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_prof_brand_text', "VARCHAR(60) NULL");
ensure_col($conexion, 'configuracion_gimnasio', 'menu_cli_brand_text',  "VARCHAR(60) NULL");

/* ---------- Datos del gimnasio ---------- */
$gimnasio = [];
if ($st = $conexion->prepare("SELECT * FROM gimnasios WHERE id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  $gimnasio = $res ? $res->fetch_assoc() : [];
  $st->close();
}

/* ---------- Config (crear si no existe) ---------- */
$config = null;
if ($st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $res = $st->get_result();
  $config = $res ? $res->fetch_assoc() : null;
  $st->close();
}
if (!$config) {
  $default_color = '#FFD700';
  $default_msg   = '';
  $default_web   = '';
  $default_fb    = '';
  $default_ig    = '';
  $ins = $conexion->prepare("
    INSERT INTO configuracion_gimnasio
      (gimnasio_id, color_encabezado, mostrar_logo_pdf, mostrar_cuit_pdf, mostrar_datos_contacto_pdf,
       mensaje_bienvenida, sitio_web, facebook, instagram)
    VALUES (?, ?, 1, 1, 1, ?, ?, ?, ?)
  ");
  if (!$ins) die("Error prepare INSERT config: ".$conexion->error);
  $ins->bind_param('isssss', $gimnasio_id, $default_color, $default_msg, $default_web, $default_fb, $default_ig);
  if (!$ins->execute()) die("Error exec INSERT config: ".$ins->error);
  $ins->close();

  $st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1");
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $config = $st->get_result()->fetch_assoc();
  $st->close();
}

/* ---------- Enlace WhatsApp ---------- */
$enlace_whatsapp_actual = '';
if ($st = $conexion->prepare("SELECT enlace_whatsapp FROM links_gimnasio WHERE gimnasio_id = ? LIMIT 1")) {
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $st->bind_result($enlace_whatsapp_actual);
  $st->fetch();
  $st->close();
}

/* ---------- POST ---------- */
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // existentes
  $color  = trim($_POST['color_encabezado'] ?? ($config['color_encabezado'] ?? '#FFD700'));
  $logo   = isset($_POST['mostrar_logo_pdf']) ? 1 : 0;
  $cuit   = isset($_POST['mostrar_cuit_pdf']) ? 1 : 0;
  $contact= isset($_POST['mostrar_datos_contacto_pdf']) ? 1 : 0;

  $mensaje_bienvenida = trim($_POST['mensaje_bienvenida'] ?? ($config['mensaje_bienvenida'] ?? ''));
  $sitio_web = trim($_POST['sitio_web'] ?? ($config['sitio_web'] ?? ''));
  $facebook  = trim($_POST['facebook']  ?? ($config['facebook']  ?? ''));
  $instagram = trim($_POST['instagram'] ?? ($config['instagram'] ?? ''));

  // diseño general
  $theme_palette   = trim($_POST['theme_palette'] ?? ($config['theme_palette'] ?? 'gold-dark'));
  $primary_color   = trim($_POST['primary_color'] ?? ($config['primary_color'] ?? '#FFD700'));
  $secondary_color = trim($_POST['secondary_color'] ?? ($config['secondary_color'] ?? '#111111'));
  $accent_color    = trim($_POST['accent_color'] ?? ($config['accent_color'] ?? '#00D1B2'));
  $bg_color        = trim($_POST['bg_color'] ?? ($config['bg_color'] ?? '#000000'));
  $text_color      = trim($_POST['text_color'] ?? ($config['text_color'] ?? '#F5F5F5'));
  $font_family     = trim($_POST['font_family'] ?? ($config['font_family'] ?? 'Inter'));
  $font_size_base  = (int)($_POST['font_size_base'] ?? ($config['font_size_base'] ?? 16));
  if ($font_size_base < 12) $font_size_base = 12;
  if ($font_size_base > 22) $font_size_base = 22;
  $layout_style    = trim($_POST['layout_style'] ?? ($config['layout_style'] ?? 'classic'));

  // hero
  $hero_title      = trim($_POST['hero_title'] ?? ($config['hero_title'] ?? 'Entrená sin excusas'));
  $hero_subtitle   = trim($_POST['hero_subtitle'] ?? ($config['hero_subtitle'] ?? 'Resultados reales, comunidad real.'));
  $hero_cta_text   = trim($_POST['hero_cta_text'] ?? ($config['hero_cta_text'] ?? 'Comenzar'));
  $hero_cta_link   = trim($_POST['hero_cta_link'] ?? ($config['hero_cta_link'] ?? '#'));
  $hero_bg_image   = trim($_POST['hero_bg_image_url'] ?? ($config['hero_bg_image_url'] ?? ''));

  // marca
  $favicon_url     = trim($_POST['favicon_url'] ?? ($config['favicon_url'] ?? ''));
  $logo_url        = trim($_POST['logo_url'] ?? ($config['logo_url'] ?? ''));
  $icon_style      = trim($_POST['icon_style'] ?? ($config['icon_style'] ?? 'outline'));
  $show_social     = isset($_POST['show_social_icons']) ? 1 : 0;
  $footer_text     = trim($_POST['footer_text'] ?? ($config['footer_text'] ?? ''));
  $custom_css      = (string)($_POST['custom_css'] ?? ($config['custom_css'] ?? ''));

  // MENÚS (nuevo)
  $menu_top_bg_color     = trim($_POST['menu_top_bg_color']     ?? ($config['menu_top_bg_color']     ?? '#0B0B0B'));
  $menu_top_text_color   = trim($_POST['menu_top_text_color']   ?? ($config['menu_top_text_color']   ?? '#FFFFFF'));
  $menu_top_hover_color  = trim($_POST['menu_top_hover_color']  ?? ($config['menu_top_hover_color']  ?? '#FFD700'));
  $menu_prof_bg_color    = trim($_POST['menu_prof_bg_color']    ?? ($config['menu_prof_bg_color']    ?? '#0B1220'));
  $menu_prof_text_color  = trim($_POST['menu_prof_text_color']  ?? ($config['menu_prof_text_color']  ?? '#E5E7EB'));
  $menu_prof_hover_color = trim($_POST['menu_prof_hover_color'] ?? ($config['menu_prof_hover_color'] ?? '#60A5FA'));
  $menu_cli_bg_color     = trim($_POST['menu_cli_bg_color']     ?? ($config['menu_cli_bg_color']     ?? '#111111'));
  $menu_cli_text_color   = trim($_POST['menu_cli_text_color']   ?? ($config['menu_cli_text_color']   ?? '#F5F5F5'));
  $menu_cli_hover_color  = trim($_POST['menu_cli_hover_color']  ?? ($config['menu_cli_hover_color']  ?? '#A7F3D0'));
  $menu_top_brand_text   = trim($_POST['menu_top_brand_text']   ?? ($config['menu_top_brand_text']   ?? ''));
  $menu_prof_brand_text  = trim($_POST['menu_prof_brand_text']  ?? ($config['menu_prof_brand_text']  ?? ''));
  $menu_cli_brand_text   = trim($_POST['menu_cli_brand_text']   ?? ($config['menu_cli_brand_text']   ?? ''));

  // UPDATE (incluye menús)
  $sql = "
    UPDATE configuracion_gimnasio
       SET color_encabezado=?, mostrar_logo_pdf=?, mostrar_cuit_pdf=?, mostrar_datos_contacto_pdf=?,
           mensaje_bienvenida=?, sitio_web=?, facebook=?, instagram=?,

           theme_palette=?, primary_color=?, secondary_color=?, accent_color=?, bg_color=?, text_color=?,
           font_family=?, font_size_base=?, layout_style=?,

           hero_title=?, hero_subtitle=?, hero_cta_text=?, hero_cta_link=?, hero_bg_image_url=?,

           favicon_url=?, logo_url=?, icon_style=?, show_social_icons=?, footer_text=?, custom_css=?,

           menu_top_bg_color=?, menu_top_text_color=?, menu_top_hover_color=?,
           menu_prof_bg_color=?, menu_prof_text_color=?, menu_prof_hover_color=?,
           menu_cli_bg_color=?, menu_cli_text_color=?, menu_cli_hover_color=?,
           menu_top_brand_text=?, menu_prof_brand_text=?, menu_cli_brand_text=?

     WHERE gimnasio_id=?
  ";
  $upd = $conexion->prepare($sql);
  if (!$upd) die("Error prepare UPDATE config: ".$conexion->error);

  $upd->bind_param(
    /* tipos */
    'siiissssssssssssisssssssisssssssssssssssssi',
    /* existentes */
    $color, $logo, $cuit, $contact,
    $mensaje_bienvenida, $sitio_web, $facebook, $instagram,

    $theme_palette, $primary_color, $secondary_color, $accent_color, $bg_color, $text_color,
    $font_family, $font_size_base, $layout_style,

    $hero_title, $hero_subtitle, $hero_cta_text, $hero_cta_link, $hero_bg_image,

    $favicon_url, $logo_url, $icon_style, $show_social, $footer_text, $custom_css,

    /* menús */
    $menu_top_bg_color, $menu_top_text_color, $menu_top_hover_color,
    $menu_prof_bg_color, $menu_prof_text_color, $menu_prof_hover_color,
    $menu_cli_bg_color, $menu_cli_text_color, $menu_cli_hover_color,
    $menu_top_brand_text, $menu_prof_brand_text, $menu_cli_brand_text,

    $gimnasio_id
  );
  if (!$upd->execute()) die("Error exec UPDATE config: ".$upd->error);
  $upd->close();

  // WhatsApp (upsert/delete)
  $enlace_whatsapp = trim($_POST['enlace_whatsapp'] ?? '');
  if ($enlace_whatsapp !== '') {
    $ck = $conexion->prepare("SELECT 1 FROM links_gimnasio WHERE gimnasio_id=? LIMIT 1");
    $ck->bind_param('i', $gimnasio_id);
    $ck->execute(); $ck->store_result();
    $exists = $ck->num_rows > 0; $ck->close();

    if ($exists) {
      $st = $conexion->prepare("UPDATE links_gimnasio SET enlace_whatsapp=? WHERE gimnasio_id=?");
      $st->bind_param('si', $enlace_whatsapp, $gimnasio_id);
      $st->execute(); $st->close();
    } else {
      $st = $conexion->prepare("INSERT INTO links_gimnasio (gimnasio_id, enlace_whatsapp) VALUES (?,?)");
      $st->bind_param('is', $gimnasio_id, $enlace_whatsapp);
      $st->execute(); $st->close();
    }
    $enlace_whatsapp_actual = $enlace_whatsapp;
  } else {
    $del = $conexion->prepare("DELETE FROM links_gimnasio WHERE gimnasio_id=?");
    $del->bind_param('i', $gimnasio_id);
    $del->execute(); $del->close();
    $enlace_whatsapp_actual = '';
  }

  // recargar config
  $st = $conexion->prepare("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = ? LIMIT 1");
  $st->bind_param('i', $gimnasio_id);
  $st->execute();
  $config = $st->get_result()->fetch_assoc() ?: $config;
  $st->close();

  $mensaje = "✅ Configuración guardada correctamente.";
}

/* ---------- Cargar valores ---------- */
$cfg = array_merge([
  'color_encabezado' => '#FFD700',
  'mensaje_bienvenida' => '',
  'sitio_web' => '', 'facebook' => '', 'instagram' => '',
  'theme_palette' => 'gold-dark',
  'primary_color' => '#FFD700',
  'secondary_color' => '#111111',
  'accent_color' => '#00D1B2',
  'bg_color' => '#000000',
  'text_color' => '#F5F5F5',
  'font_family' => 'Inter',
  'font_size_base' => 16,
  'layout_style' => 'classic',
  'hero_title' => 'Entrená sin excusas',
  'hero_subtitle' => 'Resultados reales, comunidad real.',
  'hero_cta_text' => 'Comenzar',
  'hero_cta_link' => '#',
  'hero_bg_image_url' => '',
  'favicon_url' => '',
  'logo_url' => '',
  'icon_style' => 'outline',
  'show_social_icons' => 1,
  'footer_text' => '',
  'custom_css' => '',
  'mostrar_logo_pdf' => 1,
  'mostrar_cuit_pdf' => 1,
  'mostrar_datos_contacto_pdf' => 1,
  /* menús */
  'menu_top_bg_color' => '#0B0B0B',
  'menu_top_text_color' => '#FFFFFF',
  'menu_top_hover_color'=> '#FFD700',
  'menu_prof_bg_color' => '#0B1220',
  'menu_prof_text_color'=> '#E5E7EB',
  'menu_prof_hover_color'=>'#60A5FA',
  'menu_cli_bg_color'  => '#111111',
  'menu_cli_text_color'=> '#F5F5F5',
  'menu_cli_hover_color'=>'#A7F3D0',
  'menu_top_brand_text'=> '',
  'menu_prof_brand_text'=> '',
  'menu_cli_brand_text'=> '',
], $config ?: []);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Configuración</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if (!empty($cfg['favicon_url'])): ?>
    <link rel="icon" href="<?= h($cfg['favicon_url']) ?>">
  <?php endif; ?>
  <style>
    :root{
      --c-primary: <?= h($cfg['primary_color']) ?>;
      --c-secondary: <?= h($cfg['secondary_color']) ?>;
      --c-accent: <?= h($cfg['accent_color']) ?>;
      --c-bg: <?= h($cfg['bg_color']) ?>;
      --c-text: <?= h($cfg['text_color']) ?>;
      --ff: <?= h($cfg['font_family']) ?>, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      --fz: <?= (int)$cfg['font_size_base'] ?>px;

      /* variables de menú (nuevo) */
      --menu-top-bg: <?= h($cfg['menu_top_bg_color']) ?>;
      --menu-top-text: <?= h($cfg['menu_top_text_color']) ?>;
      --menu-top-hover: <?= h($cfg['menu_top_hover_color']) ?>;

      --menu-prof-bg: <?= h($cfg['menu_prof_bg_color']) ?>;
      --menu-prof-text: <?= h($cfg['menu_prof_text_color']) ?>;
      --menu-prof-hover: <?= h($cfg['menu_prof_hover_color']) ?>;

      --menu-cli-bg: <?= h($cfg['menu_cli_bg_color']) ?>;
      --menu-cli-text: <?= h($cfg['menu_cli_text_color']) ?>;
      --menu-cli-hover: <?= h($cfg['menu_cli_hover_color']) ?>;
    }
    * { box-sizing: border-box; }
    html, body { margin:0; padding:0; background:var(--c-bg); color:var(--c-text); font-family:var(--ff); font-size:var(--fz); }
    a { color: var(--c-primary); }
    .panel { max-width:1100px; margin:24px auto; background: #0b0b0b; border:1px solid #222; border-radius:14px; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
    .head { padding:18px 20px; display:flex; align-items:center; gap:12px; border-bottom:1px solid #222; background: linear-gradient(90deg, var(--c-secondary), #0b0b0b); }
    .head h2 { margin:0; font-size:1.2rem; letter-spacing:.3px; }
    .wrap { padding:18px 20px; display:grid; grid-template-columns: 1.05fr .95fr; gap:16px; }
    .card { background:#111; border:1px solid #222; border-radius:14px; padding:16px; }
    .card h3 { margin:0 0 10px 0; font-size:1rem; color: var(--c-primary); }
    .row { display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    .row3{ display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; }
    label { display:block; font-size:.92rem; opacity:.9; margin-top:8px; }
    input[type="text"], input[type="url"], input[type="number"], select, textarea {
      width:100%; padding:10px 12px; background:#161616; color:var(--c-text);
      border:1px solid #2a2a2a; border-radius:10px; outline:none;
    }
    input[type="color"]{ width:56px; height:38px; padding:0; border:none; background:transparent; }
    .inline { display:flex; align-items:center; gap:8px; }
    .btn { background: var(--c-primary); color:#000; padding:10px 16px; border:0; border-radius:10px; font-weight:700; cursor:pointer; }
    .btn.sec { background: #252525; color:var(--c-text); border:1px solid #333; }
    .ok { background:#0f2418; border:1px solid #154f2a; color:#33dd88; padding:10px 12px; border-radius:10px; margin: 12px 20px; }
    .links-directos { display:flex; gap:10px; flex-wrap:wrap; }
    .hero-preview{ position:relative; min-height:240px; border-radius:16px; overflow:hidden; display:flex; align-items:center; justify-content:flex-start; background: radial-gradient(800px 300px at 10% 10%, rgba(255,255,255,.06), transparent 50%), linear-gradient(180deg, rgba(0,0,0,.0), rgba(0,0,0,.5)); border:1px solid #222;}
    .hero-bg{ position:absolute; inset:0; background-size:cover; background-position:center; opacity:.35; filter: saturate(1.1); }
    .hero-content{ position:relative; padding:24px; max-width:70%; }
    .badge { display:inline-block; padding:6px 10px; font-size:.8rem; background:var(--c-accent); color:#000; border-radius:999px; font-weight:700; }
    .cta { margin-top:14px; }
    .cta a { display:inline-block; padding:10px 16px; background:var(--c-primary); color:#000; border-radius:10px; text-decoration:none; font-weight:700; }
    .cards { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-top:12px; }
    .footer { margin-top:8px; opacity:.8; }
  </style>
</head>
<body>
  <div class="panel">
    <div class="head">
      <?php if (!empty($cfg['logo_url'])): ?>
        <img src="<?= h($cfg['logo_url']) ?>" alt="logo" style="height:32px;border-radius:8px;">
      <?php endif; ?>
      <h2>⚙️ Panel de Configuración del Gimnasio</h2>
    </div>

    <?php if (!empty($mensaje)): ?>
      <div class="ok"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <div class="wrap">
      <!-- Columna izquierda -->
      <div class="col">
        <div class="card">
          <h3>🧾 Datos del Gimnasio</h3>
          <p><strong><?= h($gimnasio['nombre'] ?? '') ?></strong></p>
          <p>Dirección: <?= h($gimnasio['direccion'] ?? '') ?></p>
          <p>CUIT: <?= h($gimnasio['cuit'] ?? '') ?></p>
          <p>Teléfono: <?= h($gimnasio['telefono'] ?? '') ?></p>
          <p>Email: <?= h($gimnasio['email'] ?? '') ?></p>
          <p>Vencimiento: <strong style="color:var(--c-primary);"><?= fmt_venc($gimnasio['fecha_vencimiento'] ?? '') ?></strong></p>
          <a href="editar_gimnasio.php?id=<?= (int)$gimnasio_id ?>" class="btn sec">✏️ Editar Datos</a>
        </div>

        <form method="POST" class="card" id="form-config">
          <h3>🎨 Diseño general</h3>
          <div class="row">
            <div>
              <label>Paleta</label>
              <select name="theme_palette" id="theme_palette">
                <option value="gold-dark"     <?= $cfg['theme_palette']==='gold-dark'?'selected':'' ?>>Gold / Dark</option>
                <option value="blue-dark"     <?= $cfg['theme_palette']==='blue-dark'?'selected':'' ?>>Azul / Dark</option>
                <option value="emerald-dark"  <?= $cfg['theme_palette']==='emerald-dark'?'selected':'' ?>>Esmeralda / Dark</option>
                <option value="rose-light"    <?= $cfg['theme_palette']==='rose-light'?'selected':'' ?>>Rosa / Light</option>
                <option value="graphite"      <?= $cfg['theme_palette']==='graphite'?'selected':'' ?>>Grafito</option>
              </select>
            </div>
            <div>
              <label>Layout</label>
              <select name="layout_style" id="layout_style">
                <option value="classic"     <?= $cfg['layout_style']==='classic'?'selected':'' ?>>Clásico</option>
                <option value="cards"       <?= $cfg['layout_style']==='cards'?'selected':'' ?>>Cards</option>
                <option value="glass"       <?= $cfg['layout_style']==='glass'?'selected':'' ?>>Glass</option>
                <option value="neumorphic"  <?= $cfg['layout_style']==='neumorphic'?'selected':'' ?>>Neumorphic</option>
              </select>
            </div>
          </div>

          <div class="row3">
            <div><label>Primario</label><div class="inline"><input type="color" name="primary_color" id="primary_color" value="<?= h($cfg['primary_color']) ?>"><input type="text" id="primary_txt" value="<?= h($cfg['primary_color']) ?>"></div></div>
            <div><label>Secundario</label><div class="inline"><input type="color" name="secondary_color" id="secondary_color" value="<?= h($cfg['secondary_color']) ?>"><input type="text" id="secondary_txt" value="<?= h($cfg['secondary_color']) ?>"></div></div>
            <div><label>Accent</label><div class="inline"><input type="color" name="accent_color" id="accent_color" value="<?= h($cfg['accent_color']) ?>"><input type="text" id="accent_txt" value="<?= h($cfg['accent_color']) ?>"></div></div>
          </div>
          <div class="row3">
            <div><label>Fondo</label><div class="inline"><input type="color" name="bg_color" id="bg_color" value="<?= h($cfg['bg_color']) ?>"><input type="text" id="bg_txt" value="<?= h($cfg['bg_color']) ?>"></div></div>
            <div><label>Texto</label><div class="inline"><input type="color" name="text_color" id="text_color" value="<?= h($cfg['text_color']) ?>"><input type="text" id="text_txt" value="<?= h($cfg['text_color']) ?>"></div></div>
            <div><label>Tamaño base</label><input type="number" name="font_size_base" id="font_size_base" min="12" max="22" value="<?= (int)$cfg['font_size_base'] ?>"></div>
          </div>
          <div class="row">
            <div><label>Tipografía</label>
              <select name="font_family" id="font_family">
                <?php foreach(['Inter','Poppins','Roboto','Oswald','Montserrat'] as $ff): ?>
                  <option value="<?= h($ff) ?>" <?= $cfg['font_family']===$ff?'selected':'' ?>><?= h($ff) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div><label>Logo (URL)</label><input type="url" name="logo_url" id="logo_url" placeholder="https://..." value="<?= h($cfg['logo_url']) ?>"></div>
          </div>

          <hr style="border-color:#222;margin:14px 0">

          <h3>🏁 Hero / Portada</h3>
          <label>Título</label><input type="text" name="hero_title" id="hero_title" value="<?= h($cfg['hero_title']) ?>">
          <label>Subtítulo</label><input type="text" name="hero_subtitle" id="hero_subtitle" value="<?= h($cfg['hero_subtitle']) ?>">
          <div class="row">
            <div><label>Texto CTA</label><input type="text" name="hero_cta_text" id="hero_cta_text" value="<?= h($cfg['hero_cta_text']) ?>"></div>
            <div><label>Link CTA</label><input type="url" name="hero_cta_link" id="hero_cta_link" value="<?= h($cfg['hero_cta_link']) ?>"></div>
          </div>
          <label>Fondo (imagen URL)</label><input type="url" name="hero_bg_image_url" id="hero_bg_image_url" value="<?= h($cfg['hero_bg_image_url']) ?>">

          <hr style="border-color:#222;margin:14px 0">

          <h3>🧭 Menús (colores y textos)</h3>
          <div class="row">
            <div>
              <strong>Menú Horizontal</strong>
              <label>Brand / Título</label><input type="text" name="menu_top_brand_text" value="<?= h($cfg['menu_top_brand_text']) ?>" placeholder="Ej: Fight Academy">
              <label>Fondo</label><div class="inline"><input type="color" name="menu_top_bg_color" value="<?= h($cfg['menu_top_bg_color']) ?>"><input type="text" value="<?= h($cfg['menu_top_bg_color']) ?>"></div>
              <label>Texto</label><div class="inline"><input type="color" name="menu_top_text_color" value="<?= h($cfg['menu_top_text_color']) ?>"><input type="text" value="<?= h($cfg['menu_top_text_color']) ?>"></div>
              <label>Hover</label><div class="inline"><input type="color" name="menu_top_hover_color" value="<?= h($cfg['menu_top_hover_color']) ?>"><input type="text" value="<?= h($cfg['menu_top_hover_color']) ?>"></div>
            </div>
            <div>
              <strong>Menú Profesor</strong>
              <label>Brand / Título</label><input type="text" name="menu_prof_brand_text" value="<?= h($cfg['menu_prof_brand_text']) ?>" placeholder="Ej: Panel Profesor">
              <label>Fondo</label><div class="inline"><input type="color" name="menu_prof_bg_color" value="<?= h($cfg['menu_prof_bg_color']) ?>"><input type="text" value="<?= h($cfg['menu_prof_bg_color']) ?>"></div>
              <label>Texto</label><div class="inline"><input type="color" name="menu_prof_text_color" value="<?= h($cfg['menu_prof_text_color']) ?>"><input type="text" value="<?= h($cfg['menu_prof_text_color']) ?>"></div>
              <label>Hover</label><div class="inline"><input type="color" name="menu_prof_hover_color" value="<?= h($cfg['menu_prof_hover_color']) ?>"><input type="text" value="<?= h($cfg['menu_prof_hover_color']) ?>"></div>
            </div>
          </div>
          <div class="row">
            <div>
              <strong>Menú Cliente</strong>
              <label>Brand / Título</label><input type="text" name="menu_cli_brand_text" value="<?= h($cfg['menu_cli_brand_text']) ?>" placeholder="Ej: Mi Cuenta">
              <label>Fondo</label><div class="inline"><input type="color" name="menu_cli_bg_color" value="<?= h($cfg['menu_cli_bg_color']) ?>"><input type="text" value="<?= h($cfg['menu_cli_bg_color']) ?>"></div>
              <label>Texto</label><div class="inline"><input type="color" name="menu_cli_text_color" value="<?= h($cfg['menu_cli_text_color']) ?>"><input type="text" value="<?= h($cfg['menu_cli_text_color']) ?>"></div>
              <label>Hover</label><div class="inline"><input type="color" name="menu_cli_hover_color" value="<?= h($cfg['menu_cli_hover_color']) ?>"><input type="text" value="<?= h($cfg['menu_cli_hover_color']) ?>"></div>
            </div>
          </div>

          <hr style="border-color:#222;margin:14px 0">

          <h3>💬 Contenido & Redes</h3>
          <label>Mensaje de bienvenida</label>
          <textarea name="mensaje_bienvenida" rows="2" id="mensaje_bienvenida"><?= h($cfg['mensaje_bienvenida']) ?></textarea>
          <div class="row">
            <div><label>Sitio Web</label><input type="url" name="sitio_web" value="<?= h($cfg['sitio_web']) ?>"></div>
            <div><label>Facebook</label><input type="text" name="facebook" value="<?= h($cfg['facebook']) ?>"></div>
          </div>
          <div class="row">
            <div><label>Instagram</label><input type="text" name="instagram" value="<?= h($cfg['instagram']) ?>"></div>
            <div class="inline" style="margin-top:28px">
              <input type="checkbox" name="show_social_icons" id="show_social_icons" <?= !empty($cfg['show_social_icons'])?'checked':'' ?>>
              <label for="show_social_icons">Mostrar íconos sociales</label>
            </div>
          </div>

          <label>Enlace de WhatsApp (grupo)</label>
          <input type="url" name="enlace_whatsapp" placeholder="https://chat.whatsapp.com/XXXXXX" value="<?= h($enlace_whatsapp_actual) ?>">

          <label>Texto de pie de página</label>
          <input type="text" name="footer_text" value="<?= h($cfg['footer_text']) ?>">

          <hr style="border-color:#222;margin:14px 0">
          <h3>🖨️ Facturación (PDF)</h3>
          <div class="inline"><input type="checkbox" name="mostrar_logo_pdf" id="pdf_logo" <?= !empty($cfg['mostrar_logo_pdf'])?'checked':'' ?>><label for="pdf_logo">Mostrar logo</label></div>
          <div class="inline"><input type="checkbox" name="mostrar_cuit_pdf" id="pdf_cuit" <?= !empty($cfg['mostrar_cuit_pdf'])?'checked':'' ?>><label for="pdf_cuit">Mostrar CUIT</label></div>
          <div class="inline"><input type="checkbox" name="mostrar_datos_contacto_pdf" id="pdf_cto" <?= !empty($cfg['mostrar_datos_contacto_pdf'])?'checked':'' ?>><label for="pdf_cto">Mostrar teléfono/email</label></div>

          <hr style="border-color:#222;margin:14px 0">
          <h3>🎯 CSS personalizado</h3>
          <textarea name="custom_css" rows="4" placeholder="/* CSS adicional */"><?= h($cfg['custom_css']) ?></textarea>

          <div style="margin-top:12px; display:flex; gap:10px;">
            <button type="submit" class="btn">💾 Guardar Configuración</button>
          </div>
        </form>

        <div class="card">
          <h3>📤 Exportar Información</h3>
          <div class="links-directos">
            <a href="exportar_clientes.php" class="btn sec">👥 Exportar Clientes</a>
            <a href="exportar_ventas.php" class="btn sec">💵 Exportar Ventas</a>
            <a href="exportar_membresias.php" class="btn sec">🏋️ Exportar Membresías</a>
            <a href="exportar_productos.php" class="btn sec">🛍️ Exportar Productos</a>
          </div>
        </div>

        <div class="card">
          <h3>🔐 Seguridad</h3>
          <a href="cambiar_password.php" class="btn sec">🔒 Cambiar Contraseña</a>
        </div>

        <div class="card">
          <h3>🔗 Enlaces Directos</h3>
          <div class="links-directos">
            <a href="cliente_acceso.php?id=<?= (int)$gimnasio_id ?>" class="btn sec" target="_blank">👤 Panel del Cliente</a>
            <a href="login_profesor.php?id=<?= (int)$gimnasio_id ?>" class="btn sec" target="_blank">👨‍🏫 Panel del Profesor</a>
            <a href="registrar_cliente_online.php?gimnasio=<?= (int)$gimnasio_id ?>" class="btn sec" target="_blank">📝 Registro Online</a>
          </div>
        </div>
      </div>

      <!-- Columna derecha: Vista previa mínima -->
      <div class="col">
        <div class="card">
          <h3>👀 Vista previa de Hero</h3>
          <div style="position:relative; min-height:240px; border:1px solid #222; border-radius:14px; overflow:hidden;">
            <div style="position:absolute; inset:0; background-image:url('<?= h($cfg['hero_bg_image_url']) ?>'); background-size:cover; background-position:center; opacity:.35"></div>
            <div style="position:relative; padding:24px; max-width:70%;">
              <span style="display:inline-block; padding:6px 10px; background:var(--c-accent); color:#000; border-radius:999px; font-weight:700;">Nuevo</span>
              <h2 style="margin:10px 0 6px 0; font-size:1.6rem;"><?= h($cfg['hero_title']) ?></h2>
              <p style="opacity:.9"><?= h($cfg['hero_subtitle']) ?></p>
              <div style="margin-top:14px;"><a href="<?= h($cfg['hero_cta_link']) ?>" style="display:inline-block; padding:10px 16px; background:var(--c-primary); color:#000; border-radius:10px; text-decoration:none; font-weight:700;"><?= h($cfg['hero_cta_text']) ?></a></div>
              <div style="margin-top:8px; opacity:.8;"><?= h($cfg['footer_text']) ?></div>
            </div>
          </div>
        </div>

        <div class="card">
          <h3>🎛️ Variables de Menú actuales</h3>
          <pre style="font-family:monospace; font-size:.9rem; background:#0e0e0e; border:1px solid #222; padding:12px; border-radius:10px; white-space:pre-line;">
menu-top:  bg <?= h($cfg['menu_top_bg_color']) ?> | text <?= h($cfg['menu_top_text_color']) ?> | hover <?= h($cfg['menu_top_hover_color']) ?>

menu-prof: bg <?= h($cfg['menu_prof_bg_color']) ?> | text <?= h($cfg['menu_prof_text_color']) ?> | hover <?= h($cfg['menu_prof_hover_color']) ?>

menu-cli:  bg <?= h($cfg['menu_cli_bg_color']) ?> | text <?= h($cfg['menu_cli_text_color']) ?> | hover <?= h($cfg['menu_cli_hover_color']) ?>
          </pre>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
