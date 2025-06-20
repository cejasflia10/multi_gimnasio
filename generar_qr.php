
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Generar QR</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background-color: #111;
      color: #FFD700;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h1 {
      text-align: center;
    }
    form {
      max-width: 400px;
      margin: auto;
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }
    input[type="text"], input[type="number"] {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      background-color: #222;
      border: 1px solid #FFD700;
      color: #FFD700;
      border-radius: 5px;
    }
    button {
      margin-top: 20px;
      background-color: #FFD700;
      color: #111;
      padding: 10px 15px;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      width: 100%;
    }
  </style>
</head>
<body>

  <h1>Generar código QR para cliente</h1>

  <form method="post" action="generar_qr_final.php">
    <label>DNI:</label>
    <input type="text" id="dni" name="dni" required>

    <label>Nombre y Apellido:</label>
    <input type="text" id="nombre_apellido" readonly>

    <label>ID del Cliente:</label>
    <input type="text" id="cliente_id" name="cliente_id" readonly>

    <button type="submit">Generar QR</button>
  </form>

  <script>
    document.getElementById("dni").addEventListener("input", function () {
      const dni = this.value;
      if (dni.length >= 6) {
        fetch("buscar_cliente_qr.php?dni=" + dni)
          .then(response => response.json())
          .then(data => {
            if (!data.error) {
              document.getElementById("nombre_apellido").value = data.nombre + " " + data.apellido;
              document.getElementById("cliente_id").value = data.id;
            } else {
              document.getElementById("nombre_apellido").value = "";
              document.getElementById("cliente_id").value = "";
            }
          });
      } else {
        document.getElementById("nombre_apellido").value = "";
        document.getElementById("cliente_id").value = "";
      }
    });
  </script>

</body>
</html>
