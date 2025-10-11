<?php
// theme.php — CSS dinámico por gimnasio (compat. sin mysqlnd + modo inline)
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/conexion.php';

// ---- Modo inline (no enviar headers) ----
$INLINE = isset($_GET['inline']) || defined('THEME_INLINE');

// **No muestres errores en CSS**
@ini_set('display_errors', '0');
@error_reporting(E_ALL);

// Si algún include tiró salida previa, limpiamos
while (ob_get_level()) { ob_end_clean(); }

// Headers solo si NO es inline
if (!$INLINE) {
  header('Content-Type: text/css; charset=utf-8');
  header('Cache-Control: max-age=120, must-revalidate');
}

$gimnasio_id = isset($_GET['g']) ? (int)$_GET['g'] : (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id <= 0) { $gimnasio_id = 0; }

$cfg = [
  'primary_color'        => '#FFD700',
  'secondary_color'      => '#111111',
  'accent_color'         => '#00D1B2',
  'bg_color'             => '#000000',
  'text_color'           => '#F5F5F5',
  'font_family'          => 'Inter',
  'font_size_base'       => 16,
  'menu_top_bg_color'    => '#0B0B0B',
  'menu_top_text_color'  => '#FFFFFF',
  'menu_top_hover_color' => '#FFD700',
  'menu_prof_bg_color'   => '#0B1220',
  'menu_prof_text_color' => '#E5E7EB',
  'menu_prof_hover_color'=> '#60A5FA',
  'menu_cli_bg_color'    => '#111111',
  'menu_cli_text_color'  => '#F5F5F5',
  'menu_cli_hover_color' => '#A7F3D0',
];

// Leer config (sin get_result)
if ($gimnasio_id > 0 && isset($conexion) && $conexion instanceof mysqli) {
  $id  = (int)$gimnasio_id;
  $sql = "
    SELECT
      primary_color, secondary_color, accent_color, bg_color, text_color,
      font_family, font_size_base,
      menu_top_bg_color, menu_top_text_color, menu_top_hover_color,
      menu_prof_bg_color, menu_prof_text_color, menu_prof_hover_color,
      menu_cli_bg_color, menu_cli_text_color, menu_cli_hover_color
    FROM configuracion_gimnasio
    WHERE gimnasio_id = {$id}
    LIMIT 1
  ";
  if ($rs = $conexion->query($sql)) {
    if ($row = $rs->fetch_assoc()) {
      foreach ($cfg as $k => $v) {
        if (isset($row[$k]) && $row[$k] !== '') $cfg[$k] = (string)$row[$k];
      }
      if (!empty($row['font_size_base'])) $cfg['font_size_base'] = (int)$row['font_size_base'];
      if (!empty($row['font_family'])) {
        $cfg['font_family'] = preg_replace('~[^a-zA-Z0-9\-\s]~', '', $row['font_family']);
      }
    }
    $rs->free();
  }
}

$sanitize_color = function($c) {
  if (!is_string($c)) return '#000000';
  $c = trim($c);
  if (preg_match('~^#[0-9a-fA-F]{6}$~', $c)) return $c;
  return '#000000';
};
foreach ([
  'primary_color','secondary_color','accent_color','bg_color','text_color',
  'menu_top_bg_color','menu_top_text_color','menu_top_hover_color',
  'menu_prof_bg_color','menu_prof_text_color','menu_prof_hover_color',
  'menu_cli_bg_color','menu_cli_text_color','menu_cli_hover_color'
] as $ck) {
  $cfg[$ck] = $sanitize_color($cfg[$ck]);
}

$fz = max(12, min(22, (int)$cfg['font_size_base']));
$ff = $cfg['font_family'];
?>

:root{
  --brand: <?= $cfg['primary_color'] ?>;
  --brand-2: <?= $cfg['accent_color'] ?>;
  --brand-3: <?= $cfg['secondary_color'] ?>;

  --primary: <?= $cfg['primary_color'] ?>;
  --accent:  <?= $cfg['accent_color'] ?>;
  --bg:      <?= $cfg['bg_color'] ?>;
  --ink:     <?= $cfg['text_color'] ?>;
  --card:    #111214;
  --stroke:  #2a2f39;
  --shadow:  0 6px 30px rgba(0,0,0,.35);

  --ff: "<?= $ff ?>", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
  --fz: <?= $fz ?>px;

  /* Menús */
  --menu-top-bg:    <?= $cfg['menu_top_bg_color'] ?>;
  --menu-top-text:  <?= $cfg['menu_top_text_color'] ?>;
  --menu-top-hover: <?= $cfg['menu_top_hover_color'] ?>;

  --menu-prof-bg:    <?= $cfg['menu_prof_bg_color'] ?>;
  --menu-prof-text:  <?= $cfg['menu_prof_text_color'] ?>;
  --menu-prof-hover: <?= $cfg['menu_prof_hover_color'] ?>;

  --menu-cli-bg:    <?= $cfg['menu_cli_bg_color'] ?>;
  --menu-cli-text:  <?= $cfg['menu_cli_text_color'] ?>;
  --menu-cli-hover: <?= $cfg['menu_cli_hover_color'] ?>;
}

/* ====== Base ====== */
html,body{background:var(--bg);color:var(--ink);font-family:var(--ff);font-size:var(--fz);}

/* Enlaces y botones base */
a{color:var(--primary);text-decoration:none}
.btn{padding:.6rem .9rem;border-radius:12px;border:1px solid var(--stroke);background:linear-gradient(180deg,#fff,#f7fafc);color:#0f172a;font-weight:800;cursor:pointer}
.btn-primary{background:var(--primary);color:#000;border:0}
.btn-danger{background:#ef4444;color:#fff;border:0}

/* ====== Tarjetas & contenedores ====== */
.card{background:var(--card);border:1px solid var(--stroke);border-radius:18px;box-shadow:var(--shadow);padding:16px}

/* ====== Tablas unificadas ====== */
.table-wrap{width:100%;overflow:auto}
table{width:100%;border-collapse:collapse;background:#0f1115}
th,td{border:1px solid var(--stroke);padding:10px;text-align:center;white-space:nowrap}
thead th{background:#151922;position:sticky;top:0;z-index:1;color:var(--ink)}
tbody tr:nth-child(even){background:#0e1014}
tbody tr:hover{background:#121626}

/* Badges & estados */
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:700}
.badge-deuda{background:#ef4444;color:#fff}
.badge-ok{background:#22c55e;color:#052e16}

/* Inputs unificados */
input,select,textarea{background:#0d0f14;color:var(--ink);border:1px solid var(--stroke);border-radius:12px;padding:10px}
input:focus,select:focus,textarea:focus{outline:3px solid rgba(245,158,11,.15);box-shadow:0 0 0 3px rgba(245,158,11,.08)}

/* ====== Menú superior ====== */
.topbar{background:var(--menu-top-bg);color:var(--menu-top-text);border-bottom:1px solid var(--stroke)}
.topbar a{color:var(--menu-top-text)}
.topbar a:hover{color:var(--menu-top-hover)}
.topbar .brand{font-weight:900;letter-spacing:.5px}

/* Menú profesor */
.profbar{background:var(--menu-prof-bg);color:var(--menu-prof-text);border-bottom:1px solid var(--stroke)}
.profbar a{color:var(--menu-prof-text)}
.profbar a:hover{color:var(--menu-prof-hover)}

/* Menú cliente */
.clibar{background:var(--menu-cli-bg);color:var(--menu-cli-text);border-bottom:1px solid var(--stroke)}
.clibar a{color:var(--menu-cli-text)}
.clibar a:hover{color:var(--menu-cli-hover)}

/* Mensajes */
.msg{margin:12px 0;padding:12px;border-radius:14px;border:1px solid}
.msg.ok{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.msg.warn{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
.msg.error{background:#fef2f2;border-color:#fecaca;color:#991b1b}
