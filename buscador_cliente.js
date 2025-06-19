document.getElementById('buscar').addEventListener('input', function () {
  let term = this.value;
  if (term.length < 2) {
    document.getElementById('resultado_busqueda').innerHTML = '';
    return;
  }
  fetch('buscar_cliente.php?term=' + term)
    .then(response => response.json())
    .then(data => {
      let html = '';
      data.forEach(cliente => {
        html += `<div class="resultado-item" onclick="seleccionarCliente(${cliente.id}, '${cliente.nombre} ${cliente.apellido}')">
                  ${cliente.nombre} ${cliente.apellido} - DNI: ${cliente.dni}
                </div>`;
      });
      document.getElementById('resultado_busqueda').innerHTML = html;
    });
});

function seleccionarCliente(id, nombreCompleto) {
  document.getElementById('cliente_id_seleccionado').value = id;
  document.getElementById('buscar').value = nombreCompleto;
  document.getElementById('resultado_busqueda').innerHTML = '';
}
