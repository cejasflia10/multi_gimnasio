<?php
// ... (tu código de arriba)
$st=$conexion->prepare("SELECT p.*, e.nombre AS evento, e.fecha, e.hora, e.lugar FROM pedidos p JOIN eventos_publicos e ON e.id=p.evento_id WHERE p.id=? LIMIT 1");
$st->bind_param('i',$pedido_id); $st->execute(); $ped=$st->get_result()->fetch_assoc(); $st->close();
if (!$ped){ http_response_code(404); exit('Pedido no encontrado'); }
?>
<!-- dentro del HTML de compra_ok.php, donde listás “Tus entradas” o similar -->
<div style="margin:14px 0">
  <a class="btn" href="qr_evento.php?pedido_id=<?= (int)$pedido_id ?>" target="_blank">⬇ Descargar entrada (PDF)</a>
</div>
