
<?php include 'menu.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Prueba Menú</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #111;
      color: #f1f1f1;
      margin: 0;
      padding: 0;
    }
    #sidebar {
      width: 250px;
      background-color: #000;
      color: #FFD700;
      height: 100vh;
      position: fixed;
      overflow-y: auto;
      padding: 20px;
    }
    .dropdown-container {
      display: none;
      padding-left: 15px;
    }
    .dropdown-btn {
      background: none;
      border: none;
      color: #FFD700;
      padding: 10px;
      text-align: left;
      width: 100%;
      cursor: pointer;
    }
    .dropdown-btn:hover {
      background-color: #222;
    }
    .dropdown-btn.active + .dropdown-container {
      display: block;
    }
    a {
      color: #FFD700;
      text-decoration: none;
      display: block;
      padding: 6px 0;
    }
  </style>
</head>
<body>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var dropdowns = document.getElementsByClassName("dropdown-btn");
    for (let i = 0; i < dropdowns.length; i++) {
      dropdowns[i].addEventListener("click", function () {
        this.classList.toggle("active");
        var container = this.nextElementSibling;
        container.style.display = container.style.display === "block" ? "none" : "block";
      });
    }
  });
</script>

</body>
</html>
