<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Gimnasio</title>
    <style>
        body {
            margin: 0;
            background-color: #111;
            color: gold;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        header {
            background-color: #222;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        header h1 {
            font-size: 20px;
            margin: 0;
        }
        nav {
            background-color: #333;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        nav a {
            color: gold;
            padding: 12px 20px;
            text-decoration: none;
            display: block;
        }
        nav a:hover {
            background-color: #444;
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .card {
            background-color: #222;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 0 10px #000;
        }
        .bar-section {
            margin-top: 30px;
        }
        .bar-title {
            margin-bottom: 10px;
            font-weight: bold;
        }
        .bar {
            background-color: #333;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .bar-inner {
            height: 20px;
            background-color: gold;
        }
        footer {
            background-color: #222;
            color: gold;
            text-align: center;
            padding: 10px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        @media (max-width: 768px) {
            nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Fight Academy Scorpions</h1>
        <div>
            <strong>Próximo vencimiento:</strong> 28/06/2025
        </div>
    </header>

    <nav>
        <a href="#">Clientes</a>
        <a href="#">Membresías</a>
        <a href="#">Asistencias</a>
        <a href="#">Ventas</a>
        <a href="#">Profesores</a>
        <a href="#">Configuración</a>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="card">
                <h3>Ingresos del Día</h3>
                <p>$4,800</p>
            </div>
            <div class="card">
                <h3>Pagos del Día</h3>
                <p>$3,500</p>
            </div>
            <div class="card">
                <h3>Pagos del Mes</h3>
                <p>$27,400</p>
            </div>
            <div class="card">
                <h3>Ventas Totales</h3>
                <p>$15,000</p>
            </div>
        </div>

        <div class="bar-section">
            <div class="bar-title">Estadísticas por Disciplina</div>
            <div class="bar"><div class="bar-inner" style="width: 70%"></div></div>
            <div class="bar"><div class="bar-inner" style="width: 45%"></div></div>
        </div>

        <div class="bar-section">
            <div class="bar-title">Ventas Mensuales</div>
            <div class="bar"><div class="bar-inner" style="width: 80%"></div></div>
            <div class="bar"><div class="bar-inner" style="width: 30%"></div></div>
        </div>

        <div class="bar-section">
            <h3>Próximos Vencimientos</h3>
            <ul>
                <li>Lucia Ramírez - 28/06/2025</li>
                <li>Diego Martínez - 03/07/2025</li>
            </ul>

            <h3>Próximos Cumpleaños</h3>
            <ul>
                <li>Lucas Gómez - 25/06</li>
                <li>María Suárez - 28/06</li>
            </ul>
        </div>
    </div>

    <footer>
        Sistema de Gestión Multi-Gimnasio - Versión App Profesional
    </footer>
</body>
</html>
