<?php
session_start();
$gimnasio_id = isset($_GET['gimnasio_id']) ? intval($_GET['gimnasio_id']) : 0;
if (!$gimnasio_id) {
    die("Gimnasio no especificado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia QR - Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            background-color: #000;
            color: #FFD700;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        #logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
        #scanner, #dniInput {
            margin: 10px 0;
            font-size: 20px;
            padding: 10px;
            width: 80%;
            max-width: 400px;
            border: 2px solid #FFD700;
            background: #111;
            color: #FFD700;
            text-align: center;
        }
        #resultado {
            margin-top: 20px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <img id="logo" src="img/gimnasio<?php echo $gimnasio_id; ?>.png" alt="Logo Gimnasio">
    <h2>Registrar Asistencia</h2>
    <input type="text" id="dniInput" placeholder="Escanee QR o escriba DNI" autofocus onkeyup="if(event.key==='Enter') registrarAsistencia()">
    <div id="resultado"></div>

    <script>
    function registrarAsistencia() {
        const dni = document.getElementById("dniInput").value;
        const gimnasio_id = <?php echo $gimnasio_id; ?>;
        if (!dni) return;

        fetch("registrar_asistencia_qr.php", {
            method: "POST",
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: "dni=" + dni + "&gimnasio_id=" + gimnasio_id
        })
        .then(res => res.text())
        .then(data => document.getElementById("resultado").innerHTML = data)
        .catch(err => document.getElementById("resultado").innerHTML = "Error al registrar");
    }
    </script>
</body>
</html>
