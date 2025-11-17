<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { 
  http_response_code(500); 
  exit('❌ Sin conexión a BD.'); 
}
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/*
 * Ranking simple por categoría de peso + edad aproximada
 * basado en peleas_externas (puede extenderse después a resultados_combates).
 */

// Detectar columnas base competidores
$colsCe=[]; 
if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){
  while($r=$q->fetch_assoc()){ 
    $colsCe[strtolower($r['Field'])]=$r['Field']; 
  }
  $q->close();
}

$CE_ID      = $colsCe['id'] ?? 'id';
$CE_APE     = $colsCe['apellido'] ?? ($colsCe['apellidos'] ?? 'apellido');
$CE_NOM     = $colsCe['nombre']   ?? 'nombre';
$CE_ESC_NOM = $colsCe['escuela_nombre'] 
           ?? ($colsCe['gimnasio'] ?? ($colsCe['academia'] ?? 'escuela_nombre'));

// Obtenemos un resumen de peleas_externas por competidor
$sql = "SELECT 
          c.$CE_ID AS comp_id,
          CONCAT(TRIM(c.$CE_APE),' ',TRIM(c.$CE_NOM)) AS nombre,
          c.$CE_ESC_NOM AS escuela,
          x.categoria_peso,
          x.edad,
          SUM(x.resultado='victoria') AS W,
          SUM(x.resultado='derrota')  AS L,
          SUM(x.resultado='empate')   AS D,
          SUM(x.resultado='nc')       AS NC
        FROM peleas_externas x
        JOIN competidores_evento c ON c.$CE_ID = x.competidor_id
        GROUP BY comp_id, nombre, escuela, x.categoria_peso, x.edad
        HAVING (W+L+D+NC) > 0
        ORDER BY x.categoria_peso, x.edad, W DESC, (W+L+D+NC) DESC";

$rows = [];
if ($r=$conexion->query($sql)){
  while($row=$r->fetch_assoc()){ 
    $rows[]=$row; 
  }
  $r->close();
}

// Agrupar por categoria_peso
$grupos=[];
foreach($rows as $row){
  $cat = $row['categoria_peso'] ?: 'Sin categoría';
  $grupos[$cat][] = $row;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ranking de competidores</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css?v=3">
<style>
  .rank-table table{
    border-collapse:collapse;
    width:100%;
    background:#ffffff;
  }
  .rank-table th, .rank-table td{
    padding:6px 8px;
    border-bottom:1px solid #e5e7eb;
    font-size:13px;
  }
  .rank-table thead th{
    background:#f3f4f6;
    position:sticky;top:0;z-index:1;
  }
  .pill-cat{
    display:inline-block;
    background:#111827;
    color:#fff;
    border-radius:999px;
    padding:3px 10px;
    font-size:12px;
    margin-bottom:4px;
  }
  .btn-mini{
    display:inline-block;
    padding:4px 8px;
    font-size:11px;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#fff;
    color:#111827;
    text-decoration:none;
  }
  .btn-mini:hover{
    background:#f3f4f6;
  }
</style>
</head>
<body>

<?php
// 🔹 Menú según desde dónde vengas
if (!empty($_SESSION['escuela_id'])) {
  @include __DIR__.'/menu_escuelas.php';
} else {
  @include __DIR__.'/menu_eventos.php';
}
?>

<div class="wrap">
  <div class="page-card">
    <h2>🏆 Ranking de competidores por categoría</h2>
    <p class="muted" style="font-size:13px">
      Ranking armado con las <strong>peleas externas</strong> cargadas por las escuelas (tabla <code>peleas_externas</code>).<br>
      Se ordena por categoría de peso, edad, y cantidad de victorias.
    </p>

    <?php if (!$grupos): ?>
      <p class="bad">Todavía no hay peleas registradas en <code>peleas_externas</code>.</p>
    <?php else: ?>
      <?php foreach($grupos as $cat => $lista): ?>
        <h3 style="margin-top:18px">
          <span class="pill-cat"><?= h($cat) ?></span>
        </h3>
        <div class="rank-table">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Competidor</th>
                <th>Escuela</th>
                <th>Edad</th>
                <th>W</th>
                <th>L</th>
                <th>D</th>
                <th>NC</th>
                <th>Total</th>
                <th>% Vict.</th>
                <th>Ficha</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $pos = 1;
              foreach($lista as $row):
                $W = (int)$row['W'];
                $L = (int)$row['L'];
                $D = (int)$row['D'];
                $NC = (int)$row['NC'];
                $total = $W+$L+$D+$NC;
                $pct = $total>0 ? round($W*100/$total) : 0;
              ?>
                <tr>
                  <td><?= $pos++ ?></td>
                  <td><?= h($row['nombre']) ?></td>
                  <td><?= h($row['escuela']) ?></td>
                  <td><?= h($row['edad'] !== null ? $row['edad'] : '—') ?></td>
                  <td><?= $W ?></td>
                  <td><?= $L ?></td>
                  <td><?= $D ?></td>
                  <td><?= $NC ?></td>
                  <td><?= $total ?></td>
                  <td><?= $pct ?>%</td>
                  <td>
                    <a class="btn-mini" href="ver_competidor_evento.php?id=<?= (int)$row['comp_id'] ?>">
                      Ver ficha
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
