<style>
    body {
        background-color: #111;
        color: gold;
        font-family: Arial, sans-serif;
        padding: 20px;
        margin: 0;
    }
    h1, h2 {
        text-align: center;
    }
    .cuadro {
        background: #222;
        padding: 20px;
        margin: 10px auto;
        border-radius: 10px;
        width: 100%;
        max-width: 800px;
        box-shadow: 0 0 10px #000;
    }
    .cuadro h3 {
        margin-top: 0;
    }
    table {
        width: 100%;
        color: white;
        border-collapse: collapse;
    }
    table th, table td {
        padding: 8px;
        border-bottom: 1px solid #444;
        text-align: center;
    }
    .rojo { color: red; }

    @media screen and (max-width: 768px) {
        body {
            padding: 10px;
        }
        .cuadro {
            padding: 15px;
        }
        table, thead, tbody, th, td, tr {
            display: block;
        }
        table thead {
            display: none;
        }
        table tr {
            margin-bottom: 15px;
            border-bottom: 2px solid #f1c40f;
        }
        table td {
            position: relative;
            padding-left: 50%;
            text-align: left;
        }
        table td::before {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 45%;
            white-space: nowrap;
            color: #f1c40f;
            font-weight: bold;
        }
        td:nth-of-type(1)::before { content: "Nombre"; }
        td:nth-of-type(2)::before { content: "DNI"; }
        td:nth-of-type(3)::before { content: "Disciplina"; }
        td:nth-of-type(4)::before { content: "Hora"; }
        td:nth-of-type(5)::before { content: "Vencimiento"; }
    }
</style>
