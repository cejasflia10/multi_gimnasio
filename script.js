
document.addEventListener('DOMContentLoaded', () => {
  const buscar = document.getElementById('buscar_cliente');
  const select = document.getElementById('cliente_id');
  const plan = document.getElementById('plan_id');
  const fechaInicio = document.getElementById('fecha_inicio');
  const fechaVenc = document.getElementById('fecha_vencimiento');
  const clases = document.getElementById('clases');
  const otros = document.getElementById('otros_pagos');
  const total = document.getElementById('total');

  buscar.addEventListener('keyup', () => {
    fetch(`buscar_cliente.php?q=${buscar.value}`)
    .then(res => res.json())
    .then(data => {
      select.innerHTML = "";
      data.forEach(cliente => {
        const opt = document.createElement('option');
        opt.value = cliente.id;
        opt.textContent = cliente.text;
        select.appendChild(opt);
      });
    });
  });

  plan.addEventListener('change', () => {
    fetch(`obtener_datos_plan.php?id=${plan.value}`)
    .then(res => res.json())
    .then(data => {
      clases.value = data.cantidad_clases;
      const fecha = new Date(fechaInicio.value);
      fecha.setMonth(fecha.getMonth() + parseInt(data.duracion_meses));
      fechaVenc.valueAsDate = fecha;
      total.value = parseFloat(data.precio);
    });
  });

  otros.addEventListener('input', () => {
    const base = parseFloat(total.value) || 0;
    const extra = parseFloat(otros.value) || 0;
    total.value = base + extra;
  });
});
