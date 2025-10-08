(function(){
  const $ = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>[...r.querySelectorAll(s)];

  const drawer = $('.mnu-drawer');
  const backdrop = $('.mnu-backdrop');
  const openBtn  = $('.mnu-open');   // botón “Menú”
  const closeBtn = $('.mnu-close');  // botón “X”

  function lockScroll(lock){
    const b=document.body, h=document.documentElement;
    b.style.overflow = h.style.overflow = lock ? 'hidden' : '';
  }
  function open(){ drawer?.classList.add('open'); backdrop?.classList.add('show'); lockScroll(true); }
  function close(){ drawer?.classList.remove('open'); backdrop?.classList.remove('show'); lockScroll(false); }

  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);

  // cierra con ESC
  window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
})();
