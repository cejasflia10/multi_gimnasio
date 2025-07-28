<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_eventos.php';

$evento_id = $_GET['evento_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Competidor</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h2>🏅 Agregar Competidor al Evento</h2>
        <form action="guardar_competidor_evento.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="evento_id" value="<?= $evento_id ?>">

            <label>Apellido:</label>
            <input type="text" name="apellido" required>

            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>DNI:</label>
            <input type="text" name="dni" required>

            <label>Fecha de Nacimiento:</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" onchange="calcularEdad()" required>

            <label>Edad:</label>
            <input type="number" name="edad" id="edad" readonly required>

            <label>Sexo:</label>
            <select name="sexo" id="sexo" required>
                <option value="">Seleccionar</option>
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
            </select>

            <label>Escuela / Gimnasio:</label>
            <input type="text" name="escuela_nombre" required>

            <label>Logo de la Escuela (JPG/PNG):</label>
            <input type="file" name="escuela_logo" accept="image/*" required>

            <label>Foto del Competidor:</label>
            <input type="file" name="foto_competidor" accept="image/*" required>

            <label>Modalidad:</label>
            <select name="modalidad_id" required>
                <option value="1">Exhibición</option>
                <option value="2">Boxeo</option>
                <option value="3">Full Contact</option>
                <option value="4">Low Kick</option>
                <option value="5">K1</option>
                <option value="6">MMA</option>
            </select>

            <label>Categoría:</label>
            <select name="disciplina_id" required>
                <option value="1">Exhibiciones</option>
                <option value="2">Amateurs</option>
                <option value="3">Proam</option>
                <option value="4">Pro</option>
            </select>

            <label>Categoría Técnica:</label>
            <select name="categoria_tecnica_id" required>
                <option value="1">A - Más de 11 peleas</option>
                <option value="2">B - 4 a 10 peleas</option>
                <option value="3">C - 1 a 3 peleas</option>
                <option value="4">N - 0 peleas</option>
            </select>

            <label>División:</label>
            <select name="division_id" required>
                <option value="1">Infantil</option>
                <option value="2">Juvenil</option>
                <option value="3">Adulto</option>
                <option value="4">Master</option>
            </select>

            <label>Categoría por Peso:</label>
            <select name="categoria_peso_id" id="categoria_peso_id" required>
                <option value="">Seleccione edad y sexo primero</option>
            </select>

            <label>Pago inscripción ($):</label>
            <input type="number" name="pago_inscripcion" step="0.01" value="0.00">

            <button type="submit" class="btn-principal">✅ Guardar Competidor</button>
        </form>
    </div>

    <script>
    function calcularEdad() {
        const fechaNac = document.getElementById("fecha_nacimiento").value;
        if (!fechaNac) return;

        const hoy = new Date();
        const nac = new Date(fechaNac);
        let edad = hoy.getFullYear() - nac.getFullYear();
        const m = hoy.getMonth() - nac.getMonth();

        if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) {
            edad--;
        }

        document.getElementById("edad").value = edad;
        cargarCategoriasPeso();
    }

    function cargarCategoriasPeso() {
        const edad = document.getElementById("edad").value;
        const sexo = document.getElementById("sexo").value;

        if (!edad || !sexo) return;

        fetch('obtener_categorias_por_peso.php?edad=' + edad + '&sexo=' + sexo)
            .then(res => res.text())
            .then(data => {
                document.getElementById("categoria_peso_id").innerHTML = data;
            })
            .catch(err => {
                console.error("Error al cargar categorías:", err);
            });
    }

    document.getElementById("sexo").addEventListener("change", cargarCategoriasPeso);
    </script>
</body>
</html>
