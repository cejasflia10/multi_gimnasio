<?php
session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_horizontal.php';

$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : 0;

/* ===== Helpers ===== */
function is_abs_url(string $s): bool {
    return (bool)preg_match('~^https?://~i', $s);
}

/** Resuelve una ruta pública a path físico para file_exists:
 *  - Root-relative: DOCUMENT_ROOT + ruta
 *  - Relativa al archivo actual (__DIR__)
 *  - Relativa al directorio padre
 */
function resolve_public_path(string $pub): ?string {
    $pub = trim($pub);
    if ($pub === '') return null;

    if ($pub[0] === '/') {
        $root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        if ($root) {
            $full = $root . $pub;
            if (file_exists($full)) return $full;
        }
    }
    $full2 = __DIR__ . '/' . ltrim($pub, '/');
    if (file_exists($full2)) return $full2;

    $full3 = dirname(__DIR__) . '/' . ltrim($pub, '/');
    if (file_exists($full3)) return $full3;

    return null;
}

/* ===== Existe la tabla clientes_waiver ===== */
$waiver_enabled = false;
$ck = $conexion->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'clientes_waiver' LIMIT 1");
$ck->execute();
$ckr = $ck->get_result();
$waiver_enabled = (bool)($ckr && $ckr->fetch_row());
$ck->close();

/* ===== Traer clientes ===== */
$st = $conexion->prepare("SELECT id, apellido, nombre, dni, disciplina FROM clientes WHERE gimnasio_id = ? ORDER BY apellido, nombre");
$st->bind_param("i", $gimnasio_id);
$st->execute();
$resultado = $st->get_result();
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
          $n = 1;
          while ($fila = $resultado->fetch_assoc()):
              $id  = (int)$fila['id'];
              $dni = (string)$fila['dni'];

              // ===== QR (enlace relativo)
              $qrRel  = "qr/qr_cliente_{$id}.png";
              $qrFull = resolve_public_path($qrRel);
              $qrHtml = $qrFull
                ? "<img src='".htmlspecialchars($qrRel, ENT_QUOTES)."' alt='QR' width='40'>"
                : "<a class='btn-qr' href='generar_qr_individual.php?id={$id}'>Generar QR</a>";

              // ===== Deslinde
              $deslindeHtml = "<span class='muted'>—</span>";
              if ($waiver_enabled) {
                  $wst = $conexion->prepare("SELECT pdf_path FROM clientes_waiver WHERE gimnasio_id=? AND cliente_id=? LIMIT 1");
                  $wst->bind_param("ii", $gimnasio_id, $id);
                  $wst->execute();
                  $wr = $wst->get_result();
                  if ($wr && ($w = $wr->fetch_assoc())) {
                      $pdf = trim((string)$w['pdf_path']);
                      if ($pdf !== '') {
                          if (is_abs_url($pdf)) {
                              // URL absoluta -> link directo
                              $deslindeHtml = "<a class='btn-small' href='".htmlspecialchars($pdf, ENT_QUOTES)."' target='_blank' rel='noopener'>📄 PDF</a>";
                          } else {
                              // Ruta pública -> validar físicamente (intenta raíz, actual y padre)
                              $pdfFull = resolve_public_path($pdf);
                              if ($pdfFull) {
                                  $href = ($pdf[0]==='/') ? $pdf : $pdf; // relativo a esta carpeta
                                  $deslindeHtml = "<a class='btn-small' href='".htmlspecialchars($href, ENT_QUOTES)."' target='_blank' rel='noopener'>📄 PDF</a>";
                              } else {
                                  // No está el archivo -> HTML imprimible (enlace relativo)
                                  $deslindeHtml = "<a class='btn-small' href='waiver_print.php?id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
                              }
                          }
                      } else {
                          // No hay pdf_path -> HTML imprimible (enlace relativo)
                          $deslindeHtml = "<a class='btn-small' href='waiver_print.php?id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
                      }
                  } else {
                      // Nunca cargado -> HTML imprimible
                      $deslindeHtml = "<a class='btn-small' href='waiver_print.php?id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
                  }
                  $wst->close();
              } else {
                  // Sin tabla -> HTML imprimible
                  $deslindeHtml = "<a class='btn-small' href='waiver_print.php?id={$id}&gimnasio={$gimnasio_id}' target='_blank' rel='noopener'>🖨️ Imprimir</a>";
              }
          ?>
          <tr>
              <td><?= $n ?></td>
              <td><?= htmlspecialchars($fila['apellido']) ?></td>
              <td><?= htmlspecialchars($fila['nombre']) ?></td>
              <td><?= htmlspecialchars($dni) ?></td>
              <td><?= htmlspecialchars($fila['disciplina']) ?></td>
              <td><?= $qrHtml ?></td>
              <td><?= $deslindeHtml ?></td>
              <td>
                  <a href="editar_cliente.php?id=<?= $id ?>" class="btn-qr">✏️ Editar</a>
                  <a href="eliminar_cliente.php?id=<?= $id ?>"
                     class="btn-qr"
                     onclick="return confirm('¿Seguro que querés eliminar este cliente?')">🗑️ Eliminar</a>
              </td>
          </tr>
          <?php
              $n++;
          endwhile;
          $st->close();
          ?>
      </tbody>
  </table>
</div>
</body>
</html>
