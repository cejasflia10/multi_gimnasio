<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';
require __DIR__.'/menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id'])) { http_response_code(403); exit('Acceso denegado'); }

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

/* CSRF */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ====== Prefill por GET (al elegir profesor) ====== */
$prof_sel = isset($_GET['profesor_id']) ? (int)$_GET['profesor_id'] : 0;
$prefill = [
  'valor_hora'   => '',
  'modo_pago'    => 'fijo',
  'porcentaje_1' => 50,
  'porcentaje_2' => 75,
  'porcentaje_3' => 100,
];

if ($prof_sel > 0) {
  $st = $conexion->prepare("SELECT valor_hora, modo_pago, porcentaje_1, porcentaje_2, porcentaje_3
                            FROM tarifas_profesor
                            WHERE profesor_id = ? AND gimnasio_id = ? LIMIT 1");
  if ($st){
    $st->bind_param('ii', $prof_sel, $gimnasio_id);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    if ($res){
      $prefill['valor_hora']   = (string)$res['valor_hora'];
      $prefill['modo_pago']    = (string)$res['modo_pago'];
      $prefill['porcentaje_1'] = (int)$res['porcentaje_1'];
      $prefill['porcentaje_2'] = (int)$res['porcentaje_2'];
      $prefill['porcentaje_3'] = (int)$res['porcentaje_3'];
    }
    $st->close();
  }
}

/* ====== Guardar ====== */
$mensaje = ''; $tipo_msg = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__a'] ?? '') === 'save') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $mensaje='❌ CSRF inválido.'; $tipo_msg='error';
  } else {
    $profesor_id = (int)($_POST['profesor_id'] ?? 0);
    $valor_hora  = (float)($_POST['valor_hora'] ?? 0);
    $modo_pago   = ($_POST['modo_pago'] ?? 'fijo');
    $p1 = (int)($_POST['porcentaje_1'] ?? 50);
    $p2 = (int)($_POST['porcentaje_2'] ?? 75);
    $p3 = (int)($_POST['porcentaje_3'] ?? 100);

    // Normalizaciones/validaciones
    $modo_pago = in_array($modo_pago, ['fijo','asistencia'], true) ? $modo_pago : 'fijo';
    $p1 = max(0, min(100, $p1));
    $p2 = max(0, min(100, $p2));
    $p3 = max(0, min(100, $p3));

    if ($profesor_id <= 0 || $valor_hora < 0.0) {
      $mensaje = '⚠️ Completá profesor y un valor válido.'; $tipo_msg='warn';
    } else {
      // ¿Existe tarifa?
      $st = $conexion->prepare("SELECT id FROM tarifas_profesor WHERE profesor_id=? AND gimnasio_id=? LIMIT 1");
      if ($st){
        $st->bind_param('ii', $profesor_id, $gimnasio_id);
        $st->execute();
        $ex = $st->get_result()->fetch_assoc();
        $st->close();

        if ($ex) {
          // UPDATE
          $sql = "UPDATE tarifas_profesor
                  SET valor_hora=?, modo_pago=?, porcentaje_1=?, porcentaje_2=?, porcentaje_3=?, monto_por_hora=?
                  WHERE profesor_id=? AND gimnasio_id=?";
          $monto = ($modo_pago==='fijo') ? $valor_hora : 0.0;
          $up = $conexion->prepare($sql);
          if ($up){
            $up->bind_param('dsiiidii', $valor_hora, $modo_pago, $p1, $p2, $p3, $monto, $profesor_id, $gimnasio_id);
            $ok = $up->execute();
            $up->close();
            if ($ok){ $mensaje='✅ Tarifa actualizada correctamente'; $tipo_msg='ok'; }
            else { $mensaje='❌ No se pudo actualizar.'; $tipo_msg='error'; }
          } else { $mensaje='❌ Error preparando UPDATE: '.h($conexion->error); $tipo_msg='error'; }
        } else {
          // INSERT
          $sql = "INSERT INTO tarifas_profesor
                  (profesor_id, valor_hora, monto_por_hora, modo_pago, porcentaje_1, porcentaje_2, porcentaje_3, gimnasio_id)
                  VALUES (?,?,?,?,?,?,?,?)";
          $monto = ($modo_pago==='fijo') ? $valor_hora : 0.0;
          $ins = $conexion->prepare($sql);
          if ($ins){
            $ins->bind_param('iddsiiii', $profesor_id, $valor_hora, $monto, $modo_pago, $p1, $p2, $p3, $gimnasio_id);
            $ok = $ins->execute();
            $ins->close();
            if ($ok){ $mensaje='✅ Tarifa creada correctamente'; $tipo_msg='ok'; }
            else { $mensaje='❌ No se pudo crear.'; $tipo_msg='error'; }
          } else { $mensaje='❌ Error preparando INSERT: '.h($conexion->error); $tipo_msg='error'; }
        }
      } else {
        $mensaje='❌ Error preparando SELECT: '.h($conexion->error); $tipo_msg='error';
      }

      // refrescar prefill si se guardó
      if ($tipo_msg!=='error'){
        $prof_sel = $profesor_id;
        $prefill['valor_hora']   = $valor_hora;
        $prefill['modo_pago']    = $modo_pago;
        $prefill['porcentaje_1'] = $p1;
        $prefill['porcentaje_2'] = $p2;
        $prefill['porcentaje_3'] = $p3;
      }
    }
  }
}

/* ====== Profesores ====== */
$profesores = $conexion->prepare("SELECT id, CONCAT(apellido,' ',nombre) AS nombre
                                  FROM profesores
                                  WHERE gimnasio_id = ?
                                  ORDER BY apellido, nombre");
$profesores->bind_param('i', $gimnasio_id);
$profesores->execute();
$lista = $profesores->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Tarifa Profesor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Tema unificado -->
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    .wrap{ max-width: 920px; margin:24px auto; padding:0 16px 40px; }
    .title{
      margin:0 0 14px; font-weight:900; letter-spacing:.5px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    .card{ background:var(--card); border:1px solid var(--stroke); border-radius:18px; padding:16px; box-shadow:var(--shadow); }

    .msg{ margin:12px 0; padding:12px; border-radius:14px; border:1px solid; }
    .msg.ok{ background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
    .msg.error{ background:#fef2f2; border-color:#fecaca; color:#991b1b; }
    .msg.warn{ background:#fff7ed; border-color:#fed7aa; color:#9a3412; }

    form .row{ display:grid; grid-template-columns:1fr; gap:12px; }
    @media(min-width:700px){ form .row.two{ grid-template-columns:1fr 1fr; } }

    label{ display:block; margin-bottom:6px; font-weight:700; color:#64748b; font-size:13px }
    select, input[type="number"]{
      width:100%; padding:12px; border-radius:12px;
      border:1px solid var(--stroke); background:linear-gradient(180deg,#fff,#f7fafc);
      color:var(--ink); font-size:15px; outline:none;
    }
    select:focus, input[type="number"]:focus{ outline:3px solid rgba(245,158,11,.15); box-shadow:0 0 0 3px rgba(245,158,11,.08); }

    .actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }
    .btn{
      padding:10px 14px; border-radius:12px; border:1px solid var(--stroke);
      background:linear-gradient(180deg,#fff,#f7fafc); color:var(--ink);
      font-weight:800; cursor:pointer; min-width:160px;
    }

    .help{ color:#64748b; font-size:13px; margin-top:6px }
  </style>
</head>
<body>
  <div class="wrap">
    <h1 class="title">💰 Editar Tarifa del Profesor</h1>

    <?php if ($mensaje): ?>
      <div class="msg <?= h($tipo_msg) ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <!-- Selector para prellenar -->
    <div class="card" style="margin-bottom:16px">
      <form method="get" class="row two" oninput="this.submit()">
        <div>
          <label for="profesor_id_sel">Profesor</label>
          <select id="profesor_id_sel" name="profesor_id" required>
            <option value="">Seleccionar…</option>
            <?php while($r = $lista->fetch_assoc()): ?>
              <option value="<?= (int)$r['id'] ?>" <?= $prof_sel===(int)$r['id']?'selected':''; ?>>
                <?= h($r['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <div class="help">Elegí el profesor para ver/editar su tarifa.</div>
        </div>
      </form>
    </div>

    <!-- Form de edición -->
    <div class="card">
      <form method="post" autocomplete="off" novalidate>
        <input type="hidden" name="__a" value="save">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div class="row two">
          <div>
            <label for="profesor_id">Profesor</label>
            <select id="profesor_id" name="profesor_id" required>
              <option value="">Seleccionar…</option>
              <?php
                // recargar lista para este form (el get_result anterior fue consumido)
                $profesores->execute();
                $lista2 = $profesores->get_result();
                while($r = $lista2->fetch_assoc()):
              ?>
                <option value="<?= (int)$r['id'] ?>" <?= $prof_sel===(int)$r['id']?'selected':''; ?>>
                  <?= h($r['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div>
            <label for="modo_pago">Modo de pago</label>
            <select id="modo_pago" name="modo_pago">
              <option value="fijo" <?= ($prefill['modo_pago']==='fijo')?'selected':''; ?>>Fijo por hora</option>
              <option value="asistencia" <?= ($prefill['modo_pago']==='asistencia')?'selected':''; ?>>Por asistencia</option>
            </select>
          </div>
        </div>

        <div class="row two">
          <div>
            <label for="valor_hora">Valor por hora / asistencia</label>
            <input id="valor_hora" name="valor_hora" type="number" step="0.01" min="0" value="<?= h($prefill['valor_hora']) ?>" required>
          </div>
          <div>
            <label for="porcentaje_1">% con 1 alumno</label>
            <input id="porcentaje_1" name="porcentaje_1" type="number" min="0" max="100" value="<?= (int)$prefill['porcentaje_1'] ?>">
          </div>
        </div>

        <div class="row two">
          <div>
            <label for="porcentaje_2">% con 2 alumnos</label>
            <input id="porcentaje_2" name="porcentaje_2" type="number" min="0" max="100" value="<?= (int)$prefill['porcentaje_2'] ?>">
          </div>
          <div>
            <label for="porcentaje_3">% con 3+ alumnos</label>
            <input id="porcentaje_3" name="porcentaje_3" type="number" min="0" max="100" value="<?= (int)$prefill['porcentaje_3'] ?>">
          </div>
        </div>

        <div class="help">Si el modo es <strong>Fijo</strong>, se guarda también en <em>monto_por_hora</em>. Con <strong>Por asistencia</strong>, ese campo queda en 0.</div>

        <div class="actions">
          <button class="btn" type="submit">💾 Guardar</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
