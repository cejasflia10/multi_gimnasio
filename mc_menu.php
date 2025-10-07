<?php
/* mc_menu.php — Menú en iframe (aislado, sin conflictos) */
if (!defined('MC_MENU_LOADED')) {
  define('MC_MENU_LOADED', true);
}
?>
<style>
  /* Posiciona el iframe arriba a la derecha, siempre visible */
  #mc-menu-frame {
    position: fixed !important;
    top: 10px !important;
    right: 12px !important;
    width: 260px !important;
    height: 60px !important; /* se ajusta solo con postMessage */
    z-index: 2147483647 !important;
    border: 0 !important;
    background: transparent !important;
    overflow: hidden !important;
  }
</style>

<iframe id="mc-menu-frame"
        src="mc_menu_frame.php"
        title="Menú"
        scrolling="no"
        allowtransparency="true">
</iframe>

<script>
(function(){
  // Ajusta la altura del iframe según el contenido del hijo (dropdown abierto/cerrado)
  var fr = document.getElementById('mc-menu-frame');
  if(!fr) return;

  window.addEventListener('message', function(e){
    try{
      if(!e || !e.data || typeof e.data !== 'object') return;
      if(e.data.type === 'mc-menu-height' && typeof e.data.h === 'number'){
        fr.style.height = Math.max(40, Math.min(e.data.h, 420)) + 'px';
      }
    }catch(_){}
  }, false);
})();
</script>
