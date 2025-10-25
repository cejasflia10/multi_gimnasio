<?php
/* ver_clientes.php — listado + modo impresión dentro del mismo archivo */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

date_default_timezone_set('America/Argentina/San_Luis');

/* ===== Depuración opcional =====
   Usar: ver_clientes.php?debug=1
   (NO activar en producción) */
if (isset($_GET['debug']) && $_GET['debug']=='1') {
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function is_abs_url(string $s): bool { return (bool)preg_match('~^https?://~i', $s); }
function resolve_public_path(string $pub): ?string {
  $pub = trim($pub);
  if ($pub === '') return null;

  // Root-relative
  if ($pub[0] === '/') {
    $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if ($root) { $full = $root.$pub; if (file_exists($full)) return $full; }
  }
  // Relativo al archivo actual
  $full2 = __DIR__ . '/' . ltrim($pub,'/');
  if (file_exists($full2)) return $full2;

  // Relativo al directorio padre (por si alguna estructura movida)
  $full3 = dirname(__DIR__) . '/' . ltrim($pub,'/');
  if (file_exists($full3)) return $full3;

  return null;
}

/* =========================================================================
   MODO IMPRESIÓN DEL DESLINDE (misma página)
   URL: ver_clientes.php?waiver=1&id=XXX&gimnasio=YYY
   ========================================================================= */
if (isset($_GET['waiver']) && (int)$_GET['waiver'] === 1) {
  $gimnasio_id = isset($_GET['gimnasio']) ? (int)$_GET['gimnasio'] : (int)($_SESSION['gimnasio_id'] ?? 0);
  $cliente_id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($gimnasio_id <= 0 || $cliente_id <= 0) {
    http_response_code(404);
    echo "No se pudo abrir el deslinde (faltan parámetros).";
    exit;
  }

  // Gym info
  $gym = ['nombre'=>'Gimnasio','logo'=>'logo.png'];
  $st = $conexion->prepare("SELECT nombre, logo FROM gimnasios WHERE id = ? LIMIT 1");
  if ($st) {
    $st->bind_param("i", $gimnasio_id);
    $st->execute();
    $r = $st->get_result()?->fetch_assoc();
    $st->close();
    if ($r) {
      if (!empty($r['nombre'])) $gym['nombre'] = $r['nombre'];
      if (!empty($r['logo']))   $gym['logo']   = $r['logo'];
    }
  }

  // Cliente
  $st = $conexion->prepare("SELECT dni, apellido, nombre, telefono, email, fecha_nacimiento, domicilio
                            FROM clientes WHERE gimnasio_id = ? AND id = ? LIMIT 1");
  if (!$st) {
    http_response_code(500);
    echo "Error interno (prepare cliente).";
    exit;
  }
  $st->bind_param("ii", $gimnasio_id, $cliente_id);
  $st->execute();
  $cl = $st->get_result()?->fetch_assoc();
  $st->close();

  if (!$cl) {
    http_response_code(404);
    echo "Cliente no encontrado.";
    exit;
  }

  $fecha = date('Y-m-d'); $hora = date('H:i');

  // Plantilla A4 imprimible:
  ?>
  <!DOCTYPE html>
  <html lang="es">
  <head>
    <meta charset="UTF-8">
    <title>Deslinde - <?= h($cl['apellido'].' '.$cl['nombre']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
      @page { size: A4; margin: 24mm 18mm; }
      @media print { .no-print { display: none !important; } }
      body { font-family: Arial, Helvetica, sans-serif; color:#111; }
      .toolbar { margin: 10px 0; }
      .btn { padding: .6rem .9rem; border-radius: 10px; border:1px solid #ccc; background:#f5f5f5; cursor:pointer; }
      .head { display: flex; gap:12px; align-items: center; }
      .logo { height: 70px; object-fit: contain; border:1px solid #eee; padding:6px; border-radius:8px; }
      h1 { margin:0 0 4px; font-size: 22px; }
      .muted { color:#555; }
      .hr { height:1px; background:#ddd; margin:10px 0 14px; }
      .dl { width: 100%; border-collapse: collapse; margin-top: 6px; }
      .dl td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
      .sec h2 { font-size: 16px; margin: 8px 0 6px; }
      .sec p  { margin: 6px 0; line-height: 1.5; font-size: 14px; }
      .firma-grid { display: table; width:100%; margin-top: 28px; }
      .firma-col { display: table-cell; width: 50%; vertical-align: bottom; padding-right: 12px; }
      .line { margin-top: 48px; border-top:1px solid #000; padding-top:4px; }
      .small { font-size: 12px; color:#444; }
    </style>
  </head>
  <body>
    <div class="no-print toolbar">
      <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
    </div>

    <div class="head">
      <?php if (!empty($gym['logo'])): ?>
        <img class="logo" src="<?= h($gym['logo']) ?>" alt="logo">
      <?php endif; ?>
      <div>
        <h1>Deslinde de responsabilidad</h1>
        <div class="muted"><?= h($gym['nombre']) ?></div>
      </div>
    </div>
    <div class="hr"></div>

    <table class="dl">
      <tr><td><strong>Cliente</strong></td><td><?= h($cl['apellido'].' '.$cl['nombre']) ?></td></tr>
      <tr><td><strong>DNI</strong></td><td><?= h($cl['dni']) ?></td></tr>
      <tr><td><strong>Fecha</strong></td><td><?= h($fecha.' '.$hora) ?></td></tr>
      <tr><td><strong>Teléfono</strong></td><td><?= h($cl['telefono'] ?? '') ?></td></tr>
      <tr><td><strong>Email</strong></td><td><?= h($cl['email'] ?? '') ?></td></tr>
      <tr><td><strong>Domicilio</strong></td><td><?= h($cl['domicilio'] ?? '') ?></td></tr>
      <tr><td><strong>Fecha de nacimiento</strong></td><td><?= h($cl['fecha_nacimiento'] ?? '') ?></td></tr>
    </table>

    <div class="sec">
      <h2>Declaración y aceptación</h2>
      <p>Declaro bajo juramento que me encuentro en <strong>buen estado de salud</strong> y que, en caso de corresponder, cuento con los <strong>controles y apto médico</strong> necesarios para realizar actividad física.</p>
      <p>Reconozco y acepto que la práctica de ejercicios y actividades deportivas implica <strong>riesgos inherentes</strong> de lesiones y/o accidentes. Asumo voluntariamente dichos riesgos y libero a <strong><?= h($gym['nombre']) ?></strong>, sus dueños, directivos, empleados y contratados de cualquier <strong>responsabilidad civil y/o penal</strong> por lesiones, daños o pérdidas que puedan derivarse directa o indirectamente de mi participación.</p>
      <p>Autorizo a que, en caso de emergencia, se me brinde la <strong>asistencia médica</strong> que corresponda, asumiendo los costos que pudieran derivarse.</p>
      <p>Autorizo el <strong>tratamiento de mis datos personales</strong> con fines de gestión de membresías, control de acceso y seguridad, conforme la normativa vigente. Podré ejercer mis derechos de acceso, rectificación y supresión según la ley aplicable.</p>
      <p>He leído y comprendo el presente deslinde, y manifiesto mi <strong>conformidad</strong>.</p>
    </div>

    <div class="firma-grid">
      <div class="firma-col">
        <div class="line">Firma del cliente</div>
        <div class="small">Aclaración: <?= h($cl['apellido'].' '.$cl['nombre']) ?></div>
        <div class="small">DNI: <?= h($cl['dni']) ?></div>
      </div>
      <div class="firma-col">
        <div class="line">Firma y sello del gimnasio</div>
        <div class="small"><?= h($gym['nombre']) ?></div>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* ========================================================================
   PÁGINA NORMAL (LISTADO)
   ======================================================================== */
$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : 0;

/* Ver si existe la tabla clientes_waiver */
$waiver_enabled = false;
$ck = $conexion->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'clientes_waiver' LIMIT 1");
if ($ck) {
  $ck->execute();
  $ckr = $ck->get_result();
  $waiver_enabled = (bool)($ckr && $ckr->fetch_row());
  $ck->close();
}

/* Traer clientes */
$st = $conexion->prepare("SELECT id, apellido, nombre, dni, disciplina FROM clientes WHERE gimnasio_id = ? ORDER BY apellido, nombre");
if (!$st) {
  // Mostrar error visible si hay problemas de prepare
  echo "<pre style='color:red'>Error preparando consulta de clientes: ".h($conexion->error)."</pre>";
  $resultado = false;
} else {
  $st->bind_param("i", $gimnasio_id);
  $st->execute();
  $resultado = $st->get_result();
}

require_once __DIR__.'/menu_horizontal.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listado de Clientes</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .contenedor{max-width:1100px;margin:0 auto;padding:1rem}
    table{width:100%;border-collapse:collapse;margin-top:.5rem}
    th,td{padding:.55rem;border-bottom:1px solid var(--stroke,#e5e7eb)}
    .buscador{width:100%;padding:.6rem .75rem;border:1px solid var(--stroke,#e5e7eb);border-radius:10px}
    .btn-qr, .btn-small{display:inline-flex;align-items:center;gap:.25rem;padding:.35rem .6rem;border:1px solid var(--stroke,#e5e7eb);border-radius:10px;text-decoration:none}
    .muted{color:var(--muted,#64748b)}
  </style>
  <script>
    function buscarCliente() {
      const input = (document.getElementById("buscador").value || "").toLowerCase();
      document.querySelectorAll("tbody tr").forEach(fila => {
        const texto = (fila.textContent || "").toLowerCase();
        fila.style.display = texto.includes(input) ? "" : "none";
      });
    }
  </script>
</head>
<body>
<div class="contenedor">
  <h2>Listado de Clientes</h2>

  <input type="text" id="buscador" class="buscador" placeholder="Buscar por nombre, apellido o DNI" onkeyup="buscarCliente()">

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>DNI</th>
        <th>Disciplina</th>
        <th>QR</th>
        <th>Deslinde</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php
    if (!$resultado) {
      echo "<tr><td colspan='8' class='muted'>No se pudo cargar el listado.</td></tr>";
    } else {
      $n = 1;
      while ($fila = $resultado->fetch_assoc()):
        $id  = (int)$fila['id'];
        $dni = (string)$fila['dni'];

        // QR (relativo)
        $qrRel  = "qr/qr_cliente_{$id}.png";
        $qrFull = resolve_public_path($qrRel);
        $qrHtml = $qrFull
          ? "<img src='".h($qrRel)."' alt='QR' width='40'>"
          : "<a class='btn-qr' href='generar_qr_individual.php?id={$id}'>Generar QR</a>";

        // Deslinde
        $deslindeHtml = "<a class='btn-small' href='ver_clientes.php?waiver=1&id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
        if ($waiver_enabled) {
          $wst = $conexion->prepare("SELECT pdf_path FROM clientes_waiver WHERE gimnasio_id=? AND cliente_id=? LIMIT 1");
          if ($wst) {
            $wst->bind_param("ii", $gimnasio_id, $id);
            $wst->execute();
            $wr = $wst->get_result();
            if ($wr && ($w = $wr->fetch_assoc())) {
              $pdf = trim((string)$w['pdf_path']);
              if ($pdf !== '') {
                if (is_abs_url($pdf)) {
                  $deslindeHtml = "<a class='btn-small' href='".h($pdf)."' target='_blank' rel='noopener'>📄 PDF</a>";
                } else {
                  $pdfFull = resolve_public_path($pdf);
                  if ($pdfFull) {
                    $href = ($pdf[0]==='/') ? $pdf : $pdf; // relativo a esta carpeta
                    $deslindeHtml = "<a class='btn-small' href='".h($href)."' target='_blank' rel='noopener'>📄 PDF</a>";
                  } else {
                    // si la ruta guardada no existe físicamente, ofrecemos imprimir
                    $deslindeHtml = "<a class='btn-small' href='ver_clientes.php?waiver=1&id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
                  }
                }
              }
            }
            $wst->close();
          }
        }
        ?>
        <tr>
          <td><?= $n ?></td>
          <td><?= h($fila['apellido']) ?></td>
          <td><?= h($fila['nombre']) ?></td>
          <td><?= h($dni) ?></td>
          <td><?= h($fila['disciplina']) ?></td>
          <td><?= $qrHtml ?></td>
          <td><?= $deslindeHtml ?></td>
          <td>
            <a href="editar_cliente.php?id=<?= $id ?>" class="btn-qr">✏️ Editar</a>
            <a href="eliminar_cliente.php?id=<?= $id ?>" class="btn-qr" onclick="return confirm('¿Seguro que querés eliminar este cliente?')">🗑️ Eliminar</a>
          </td>
        </tr>
        <?php
        $n++;
      endwhile;
      $st->close();
    }
    ?>
    </tbody>
  </table>
</div>
</body>
</html>
