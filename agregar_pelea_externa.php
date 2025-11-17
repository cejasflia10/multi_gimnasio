<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Requiere login de escuela
if (empty($_SESSION['escuela_id']) || empty($_SESSION['escuela_nombre'])) {
  header('Location: escuela_login.php');
  exit;
}

$escuela_id     = (int)$_SESSION['escuela_id'];
$escuela_nombre = (string)$_SESSION['escuela_nombre'];

// Detectar cómo se llama la columna de escuela en competidores_evento
$colsCe = [];
if ($q = $conexion->query("SHOW COLUMNS FROM `competidores_evento`")){
  while($r = $q->fetch_assoc()){
    $colsCe[strtolower($r['Field'])] = $r['Field'];
  }
  $q->close();
}
$CE_ID      = $colsCe['id'] ?? 'id';
$CE_APE     = $colsCe['apellido'] ?? ($colsCe['apellidos'] ?? 'apellido');
$CE_NOM     = $colsCe['nombre']   ?? 'nombre';
$CE_ESC_NOM = $colsCe['escuela_nombre']
           ?? ($colsCe['gimnasio'] ?? ($colsCe['academia'] ?? 'escuela_nombre'));

// Traer competidores de esta escuela
$competidores = [];
$sqlCmp = "SELECT $CE_ID AS id,
                  CONCAT(TRIM($CE_APE),' ',TRIM($CE_NOM)) AS nom
           FROM competidores_evento
           WHERE $CE_ESC_NOM = ?
           ORDER BY nom ASC";
if ($st = $conexion->prepare($sqlCmp)){
  $st->bind_param('s', $escuela_nombre);
  $st->execute();
  $rs = $st->get_result();
  while($row = $rs->fetch_assoc()){ $competidores[] = $row; }
  $st->close();
}

$errores = [];
$ok_msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $competidor_id = (int)($_POST['competidor_id'] ?? 0);
  $fecha         = trim((string)($_POST['fecha'] ?? ''));
  $evento_nombre = trim((string)($_POST['evento_nombre'] ?? ''));
  $categoria_peso= trim((string)($_POST['categoria_peso'] ?? ''));
  $edad          = trim((string)($_POST['edad'] ?? ''));
  $resultado     = trim((string)($_POST['resultado'] ?? ''));
  $metodo        = trim((string)($_POST['metodo'] ?? ''));
  $detalle       = trim((string)($_POST['detalle'] ?? ''));

  if ($competidor_id <= 0)         $errores[] = 'Elegí un competidor.';
  if ($fecha === '')               $errores[] = 'Ingresá la fecha de la pelea.';
  if ($evento_nombre === '')       $errores[] = 'Ingresá el nombre del evento.';
  if (!in_array($resultado, ['victoria','derrota','empate','nc'], true)) {
    $errores[] = 'Resultado inválido.';
  }
  if ($edad !== '' && !ctype_digit($edad)) {
    $errores[] = 'La edad debe ser un número entero.';
  }

  // Validar que el competidor realmente pertenezca a esta escuela
  if (!$errores && $competidor_id > 0) {
    $sql = "SELECT COUNT(*) FROM competidores_evento 
            WHERE $CE_ID = ? AND $CE_ESC_NOM = ?";
    if ($st = $conexion->prepare($sql)){
      $st->bind_param('is', $competidor_id, $escuela_nombre);
      $st->execute();
      $st->bind_result($cnt);
      $st->fetch();
      $st->close();
      if ($cnt == 0) {
        $errores[] = 'No podés cargar peleas para un competidor que no es de tu escuela.';
      }
    }
  }

  if (!$errores) {
    $edadInt = ($edad === '') ? null : (int)$edad;

    $sqlIns = "INSERT INTO peleas_externas
      (competidor_id, escuela_id, fecha, evento_nombre, categoria_peso, edad, resultado, metodo, detalle)
      VALUES (?,?,?,?,?,?,?,?,?)";

    if ($st = $conexion->prepare($sqlIns)){
      $st->bind_param(
        'iisssisss',
        $competidor_id,
        $escuela_id,
        $fecha,
        $evento_nombre,
        $categoria_peso,
        $edadInt,
        $resultado,
        $metodo,
        $detalle
      );
      if ($st->execute()){
        $ok_msg = 'Pelea registrada correctamente.';
        // Si quisieras limpiar el formulario completo, podés descomentar esto:
        // $_POST = [];
      } else {
        $errores[] = 'No se pudo guardar la pelea: '.$conexion->error;
      }
      $st->close();
    } else {
      $errores[] = 'Error preparando el guardado: '.$conexion->error;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Agregar pelea externa</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css?v=3">
</head>
<body>
<?php @include __DIR__.'/menu_escuelas.php'; ?>
<div class="wrap">
  <div class="page-card">
    <h2>➕ Registrar pelea externa</h2>
    <p class="muted" style="font-size:13px">
      Escuela logueada: <strong><?= h($escuela_nombre) ?></strong><br>
      Solo podés cargar peleas de tus propios competidores.
    </p>

    <?php if ($errores): ?>
      <div class="bad">
        <?php foreach($errores as $e): ?>
          <div><?= h($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($ok_msg): ?>
      <div class="ok"><?= h($ok_msg) ?></div>
    <?php endif; ?>

    <?php if (!$competidores): ?>
      <p class="bad">
        No encontramos competidores vinculados a la escuela 
        <strong><?= h($escuela_nombre) ?></strong>.<br>
        Verificá que el nombre de la escuela en <code>competidores_evento</code> coincida exactamente.
      </p>
    <?php else: ?>
      <form method="post" class="grid">
        <label>Competidor
          <select name="competidor_id" class="input" required>
            <option value="">— Elegir —</option>
            <?php foreach($competidores as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (isset($_POST['competidor_id']) && (int)$_POST['competidor_id'] === (int)$c['id'] ? 'selected' : '') ?>>
                <?= (int)$c['id'] ?> — <?= h($c['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Fecha de la pelea
          <input type="date" name="fecha" class="input" required
                 value="<?= h($_POST['fecha'] ?? '') ?>">
        </label>

        <label>Evento
          <input type="text" name="evento_nombre" class="input" required
                 value="<?= h($_POST['evento_nombre'] ?? '') ?>">
        </label>

        <label>Categoría de peso (texto)
          <input type="text" name="categoria_peso" class="input"
                 placeholder="Ej.: -75 kg, Ligero, etc."
                 value="<?= h($_POST['categoria_peso'] ?? '') ?>">
        </label>

        <label>Edad al momento de la pelea (opcional)
          <input type="number" name="edad" class="input" min="0" max="99"
                 value="<?= h($_POST['edad'] ?? '') ?>">
        </label>

        <label>Resultado
          <select name="resultado" class="input" required>
            <option value="">— Elegir —</option>
            <option value="victoria" <?= (($_POST['resultado'] ?? '') === 'victoria') ? 'selected' : '' ?>>Victoria</option>
            <option value="derrota"  <?= (($_POST['resultado'] ?? '') === 'derrota')  ? 'selected' : '' ?>>Derrota</option>
            <option value="empate"   <?= (($_POST['resultado'] ?? '') === 'empate')   ? 'selected' : '' ?>>Empate</option>
            <option value="nc"       <?= (($_POST['resultado'] ?? '') === 'nc')       ? 'selected' : '' ?>>Sin decisión (NC)</option>
          </select>
        </label>

        <label>Método (KO, PTS, etc.) (opcional)
          <input type="text" name="metodo" class="input"
                 value="<?= h($_POST['metodo'] ?? '') ?>">
        </label>

        <label style="grid-column:1/-1">Detalle (opcional)
          <input type="text" name="detalle" class="input"
                 placeholder="Ej.: Decisión dividida, R3 1:15, etc."
                 value="<?= h($_POST['detalle'] ?? '') ?>">
        </label>

        <div style="grid-column:1/-1;text-align:right;margin-top:8px">
          <button type="submit" class="btn">Guardar pelea</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
