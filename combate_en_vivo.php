<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_eventos.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>⏱️ Cronómetro de Combate</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { text-align: center; background: #111; color: white; font-family: Arial, sans-serif; }
        .cronometro-box {
            display: flex; justify-content: space-between; align-items: center;
            margin: 40px auto; max-width: 800px;
        }
        .rincón {
            width: 25%; font-size: 24px; font-weight: bold; padding: 20px;
        }
        .rojo { background: #900; }
        .azul { background: #006; }
        .centro {
            width: 50%;
            text-align: center;
        }
        #tiempo {
            font-size: 80px;
            margin: 10px 0;
        }
        #estado {
            font-size: 30px;
            margin: 10px 0;
        }
        button {
            font-size: 20px;
            margin: 10px;
            padding: 10px 20px;
        }
        .config { margin-top: 20px; }
        .config input {
            width: 60px;
            padding: 5px;
        }
    </style>
</head>
<body>

<h1>🥊 Combate en Vivo</h1>

<div class="cronometro-box">
    <div class="rincón rojo">🔴 RINCÓN ROJO</div>
    <div class="centro">
        <div id="estado">Esperando...</div>
        <div id="tiempo">00:00</div>
        <div>Round <span id="round">0</span> / <span id="totalRounds">3</span></div>
        <div>
            <button onclick="toggleTimer()">▶️ Iniciar / Pausar</button>
            <button onclick="reiniciar()">🔁 Reiniciar</button>
        </div>
    </div>
    <div class="rincón azul">🔵 RINCÓN AZUL</div>
</div>

<div class="config">
    <label>⏱️ Min por round: <input type="number" id="minRound" value="2"></label>
    <label>😮 Min descanso: <input type="number" id="minDescanso" value="1"></label>
    <label>📦 Rounds totales: <input type="number" id="inputRounds" value="3"></label>
    <button onclick="guardarConfig()">✅ Aplicar</button>
</div>

<audio id="campana" src="campana_boxeo.mp3" preload="auto"></audio>

<script>
let tiempo = 0;
let intervalo = null;
let enDescanso = false;
let roundActual = 0;
let totalRounds = 3;
let minRound = 2;
let minDescanso = 1;

function guardarConfig() {
    minRound = parseInt(document.getElementById('minRound').value) || 2;
    minDescanso = parseInt(document.getElementById('minDescanso').value) || 1;
    totalRounds = parseInt(document.getElementById('inputRounds').value) || 3;
    document.getElementById('totalRounds').innerText = totalRounds;
    reiniciar();
}

function toggleTimer() {
    if (intervalo) {
        clearInterval(intervalo);
        intervalo = null;
    } else {
        if (roundActual === 0) nuevaRonda();
        intervalo = setInterval(tick, 1000);
    }
}

function tick() {
    if (tiempo > 0) {
        tiempo--;
        actualizarTiempo();
    } else {
        document.getElementById('campana').play();
        clearInterval(intervalo);
        intervalo = null;

        if (enDescanso) {
            nuevaRonda();
        } else {
            enDescanso = true;
            document.getElementById('estado').innerText = "😮 Descanso";
            tiempo = minDescanso * 60;
            actualizarTiempo();
            intervalo = setInterval(tick, 1000);
        }
    }
}

function nuevaRonda() {
    roundActual++;
    if (roundActual > totalRounds) {
        document.getElementById('estado').innerText = "✅ Finalizado";
        document.getElementById('round').innerText = totalRounds;
        return;
    }
    enDescanso = false;
    document.getElementById('estado').innerText = "🔥 ROUND " + roundActual;
    document.getElementById('round').innerText = roundActual;
    tiempo = minRound * 60;
    actualizarTiempo();
    intervalo = setInterval(tick, 1000);
    document.getElementById('campana').play();
}

function reiniciar() {
    clearInterval(intervalo);
    intervalo = null;
    roundActual = 0;
    tiempo = 0;
    enDescanso = false;
    document.getElementById('estado').innerText = "Esperando...";
    document.getElementById('tiempo').innerText = "00:00";
    document.getElementById('round').innerText = "0";
}

function actualizarTiempo() {
    const minutos = Math.floor(tiempo / 60).toString().padStart(2, '0');
    const segundos = (tiempo % 60).toString().padStart(2, '0');
    document.getElementById('tiempo').innerText = `${minutos}:${segundos}`;
}
</script>

</body>
</html>
