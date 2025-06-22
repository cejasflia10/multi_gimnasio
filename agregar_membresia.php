<script>
// Buscar cliente automáticamente
document.getElementById("buscar_cliente").addEventListener("input", function () {
  const valor = this.value;
  const clienteSelect = document.getElementById("cliente_id");

  if (valor.length >= 2) {
    fetch("buscar_cliente.php?q=" + valor)
      .then(res => res.json())
      .then(data => {
        clienteSelect.innerHTML = "<option value=''>Seleccione un cliente</option>";
        data.forEach(cliente => {
          const opt = document.createElement("option");
          opt.value = cliente.id;
          opt.textContent = cliente.text;
          clienteSelect.appendChild(opt);
        });
      });
  }
});

// Calcular total, clases y vencimiento
function actualizarTotal() {
  const plan = document.querySelector("#plan_id option:checked");
  const adicional = document.querySelector("#adicional_id option:checked");
  const otrosPagos = parseFloat(document.getElementById("otros_pagos").value || 0);

  const precioPlan = parseFloat(plan?.dataset.precio || 0);
  const precioAdicional = parseFloat(adicional?.dataset.precio || 0);
  const total = precioPlan + precioAdicional + otrosPagos;
  document.getElementById("total").value = total.toFixed(2);

  // Cargar clases disponibles
  const clases = plan?.dataset.clases || 0;
  document.getElementById("clases_disponibles").value = clases;

  // Calcular vencimiento = fecha inicio + 1 mes
  const inicio = document.getElementById("fecha_inicio").value;
  if (inicio) {
    const fecha = new Date(inicio);
    fecha.setMonth(fecha.getMonth() + 1);
    const vencimiento = fecha.toISOString().split('T')[0];
    document.getElementById("fecha_vencimiento").value = vencimiento;
  }
}

document.getElementById("plan_id").addEventListener("change", actualizarTotal);
document.getElementById("adicional_id").addEventListener("change", actualizarTotal);
document.getElementById("otros_pagos").addEventListener("input", actualizarTotal);
document.getElementById("fecha_inicio").addEventListener("change", actualizarTotal);

// 👉 Al cargar la página, ejecutar automáticamente
window.addEventListener("load", actualizarTotal);
</script>
