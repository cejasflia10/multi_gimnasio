<?php
/* ============================================================
   eliminar_cliente.php — Borrado robusto de cliente
   • Verifica pertenencia (gimnasio_id).
   • Borra primero datos_fisicos (tu FK bloqueante) y valida.
   • Borra todas las tablas que referencian clientes(id).
   • Limpia tablas lógicas extra.
   • Borra el cliente. Todo en TRANSACCIÓN.
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function has_table(mysqli $db, string $t): bool {
  $t=$db->real_escape_string($t);
  $q=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='{$t}' LIMIT 1");
  return $q && $q->num_rows>0;
}
function col_exists(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $q=$db->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='{$t}' AND column_name='{$c}' LIMIT 1");
  return $q && $q->num_rows>0;
}
function first_col(mysqli $db, string $t, array $cands): ?string { foreach($cands as $c){ if(col_exists($db,$t,$c)) return $c; } return null; }

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$cliente_id  = (int)($_GET['id'] ?? 0);
if ($gimnasio_id<=0 || $cliente_id<=0){ http_response_code(400); exit('Parámetros inválidos.'); }

/* 0) Verificar que el cliente pertenece a este gimnasio */
$st=$conexion->prepare("SELECT id FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1");
$st->bind_param("ii",$cliente_id,$gimnasio_id);
$st->execute(); $st->store_result();
if ($st->num_rows===0){
  $st->close();
  echo "<div style='background:#111;color:gold;padding:28px;text-align:center;font-family:Arial'>
          <h2>❌ Cliente no encontrado</h2>
          <p>No pertenece a tu gimnasio.</p>
          <a href='ver_clientes.php' style='color:gold;font-weight:700'>🔙 Volver</a>
        </div>"; exit;
}
$st->close();

/* Helpers de borrado */
function delete_fk_children(mysqli $db, int $cliente_id, int $gimnasio_id): void {
  $rows=[];
  $q=$db->query("SELECT TABLE_NAME, COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA=DATABASE()
                   AND REFERENCED_TABLE_NAME='clientes'
                   AND REFERENCED_COLUMN_NAME='id'");
  while($q && ($r=$q->fetch_assoc())){
    if (strcasecmp($r['TABLE_NAME'],'clientes')!==0) $rows[] = [$r['TABLE_NAME'],$r['COLUMN_NAME']];
  }
  if ($q) $q->close();

  foreach($rows as [$tbl,$colFk]){
    if (!has_table($db,$tbl) || !col_exists($db,$tbl,$colFk)) continue;
    $colGym = first_col($db,$tbl,['gimnasio_id','id_gimnasio','gym_id']);
    if ($colGym && col_exists($db,$tbl,$colGym)) {
      $st=$db->prepare("DELETE FROM `{$tbl}` WHERE `{$colFk}`=? AND `{$colGym}`=?");
      $st->bind_param("ii",$cliente_id,$gimnasio_id);
    } else {
      $st=$db->prepare("DELETE FROM `{$tbl}` WHERE `{$colFk}`=?");
      $st->bind_param("i",$cliente_id);
    }
    $st->execute(); $st->close();
  }
}

function delete_if_present(mysqli $db, string $tabla, int $cliente_id, int $gimnasio_id): void {
  if (!has_table($db,$tabla)) return;
  $colCli = col_exists($db,$tabla,'cliente_id') ? 'cliente_id' : (col_exists($db,$tabla,'id_cliente')?'id_cliente':null);
  if (!$colCli) return;
  $colGym = first_col($db,$tabla,['gimnasio_id','id_gimnasio','gym_id']);

  if ($colGym && col_exists($db,$tabla,$colGym)) {
    $st=$db->prepare("DELETE FROM `{$tabla}` WHERE `{$colCli}`=? AND `{$colGym}`=?");
    $st->bind_param("ii",$cliente_id,$gimnasio_id);
  } else {
    $st=$db->prepare("DELETE FROM `{$tabla}` WHERE `{$colCli}`=?");
    $st->bind_param("i",$cliente_id);
  }
  $st->execute(); $st->close();
}

try{
  $conexion->begin_transaction();

  /* 1) BORRAR datos_fisicos primero y validar */
  if (has_table($conexion,'datos_fisicos')){
    $colCli = col_exists($conexion,'datos_fisicos','cliente_id') ? 'cliente_id'
            : (col_exists($conexion,'datos_fisicos','id_cliente') ? 'id_cliente' : null);
    if ($colCli){
      $st=$conexion->prepare("DELETE FROM datos_fisicos WHERE {$colCli}=?");
      $st->bind_param("i",$cliente_id);
      $st->execute(); $st->close();

      $st=$conexion->prepare("SELECT COUNT(*) c FROM datos_fisicos WHERE {$colCli}=?");
      $st->bind_param("i",$cliente_id);
      $st->execute(); $res=$st->get_result(); $quedan=(int)($res->fetch_assoc()['c']??0); $st->close();

      if ($quedan>0){
        // Diagnóstico claro y abortamos para no dejar nada a medias
        $conexion->rollback();
        echo "<div style='background:#111;color:#ffb4b4;padding:28px;text-align:center;font-family:Arial'>
                <h2>❌ Error</h2>
                <p>No se pudo eliminar el cliente porque <b>quedan {$quedan} filas</b> en <code>datos_fisicos</code> para ese cliente.</p>
                <p>Solución definitiva (recomendado): ejecutá el parche de FKs con CASCADE.</p>
                <p><code>patch_fks_clientes_cascade.php</code> (te lo dejé arriba)</p>
                <p style='margin-top:10px'>
                  O manualmente en SQL:<br>
                  <code>ALTER TABLE datos_fisicos DROP FOREIGN KEY datos_fisicos_ibfk_2;</code><br>
                  <code>ALTER TABLE datos_fisicos
                    ADD CONSTRAINT datos_fisicos_ibfk_2
                    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
                    ON DELETE CASCADE ON UPDATE CASCADE;</code>
                </p>
                <a href='ver_clientes.php' style='color:#fff;background:#b91c1c;padding:.5rem .8rem;border-radius:.5rem;text-decoration:none;font-weight:bold;'>🔙 Volver</a>
              </div>";
        exit;
      }
    }
  }

  /* 2) Borrar todas las tablas con FK a clientes(id) */
  delete_fk_children($conexion,$cliente_id,$gimnasio_id);

  /* 3) Tablas lógicas extra (por si no tienen FK físico) */
  $extras = [
    'membresia_consumos','accesos_gimnasio','gym_clientes_plan','reservas_turnos','reservas',
    'asistencias','pagos','pagos_pendientes','progreso_alumno','rutinas','archivos_clientes',
    'mensajes_chat','competencias','graduaciones','clientes_qr','ventas'
  ];
  foreach($extras as $t) delete_if_present($conexion,$t,$cliente_id,$gimnasio_id);

  /* 4) Membresías explícitamente */
  if (has_table($conexion,'membresias') && col_exists($conexion,'membresias','cliente_id')){
    $colGym = first_col($conexion,'membresias',['gimnasio_id','id_gimnasio','gym_id']);
    if ($colGym && col_exists($conexion,'membresias',$colGym)){
      $st=$conexion->prepare("DELETE FROM membresias WHERE cliente_id=? AND {$colGym}=?");
      $st->bind_param("ii",$cliente_id,$gimnasio_id);
    } else {
      $st=$conexion->prepare("DELETE FROM membresias WHERE cliente_id=?");
      $st->bind_param("i",$cliente_id);
    }
    $st->execute(); $st->close();
  }

  /* 5) Finalmente el cliente */
  $st=$conexion->prepare("DELETE FROM clientes WHERE id=? AND gimnasio_id=?");
  $st->bind_param("ii",$cliente_id,$gimnasio_id);
  $st->execute();
  if ($st->affected_rows===0) throw new Exception("No se pudo eliminar el cliente (probable FK pendiente).");
  $st->close();

  $conexion->commit();
  header("Location: ver_clientes.php?mensaje=".rawurlencode("Cliente eliminado correctamente"));
  exit;

}catch(Throwable $e){
  $conexion->rollback();
  $detalle = h($e->getMessage());
  echo "<div style='background:#111;color:#ffb4b4;padding:28px;text-align:center;font-family:Arial'>
          <h2>❌ Error</h2>
          <p>No se pudo eliminar el cliente.</p>
          <p style='color:#f99'><small>Detalles: {$detalle}</small></p>
          <a href='ver_clientes.php' style='color:#fff;background:#b91c1c;padding:.5rem .8rem;border-radius:.5rem;text-decoration:none;font-weight:bold;'>🔙 Volver</a>
        </div>";
  exit;
}
