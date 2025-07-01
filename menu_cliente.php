
<style>
    .menu-cliente {
        background-color: #111;
        padding: 10px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        font-family: Arial, sans-serif;
        margin-bottom: 20px;
    }
    .menu-cliente a {
        color: gold;
        text-decoration: none;
        font-weight: bold;
        background-color: #222;
        padding: 10px 12px;
        border-radius: 6px;
        transition: background 0.3s;
        font-size: 14px;
    }
    .menu-cliente a:hover {
        background-color: gold;
        color: black;
    }
    @media (max-width: 600px) {
        .menu-cliente {
            flex-direction: column;
            align-items: center;
        }
        .menu-cliente a {
            width: 90%;
            text-align: center;
            font-size: 16px;
        }
    }
</style>

<div class="menu-cliente">
    <a href="panel_cliente.php">🏠 Inicio</a>
    <a href="reservar_turno.php">📅 Reservar Turno</a>
    <a href="ver_mis_turnos.php">📋 Ver Mis Turnos</a> <a href="ver_mis_pagos.php">Mis Pagos</a>
    <a href="pago_online.php">Pago Online</a>
    <a href="ver_mis_asistencias.php">🧾 Mis Asistencias</a>
    <a href="ver_mis_pagos.php">💳 Mis Pagos</a>
    <a href="ver_graduacion.php">🎓 Mi Graduación</a>
    <a href="ver_competencias.php">🥋 Mis Competencias</a>
    <a href="ver_progreso_fisico.php">📊 Progreso Físico</a>
    <a href="ver_archivos_entrenamiento.php">📄 Mis Archivos</a>
    <a href="ver_qr_cliente.php">📷 Ver mi QR</a>
    <a href="logout.php">⏪ Cerrar Sesión</a>
</div>
