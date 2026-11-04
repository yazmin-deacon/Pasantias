<?php
require_once "conexion.php";

// Consultar trabajos
$resultado = mysqli_query($conexion, "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY nombre_t");
$trabajos = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $trabajos[] = $fila;
}

// Consultar exposiciones
$resultado = mysqli_query($conexion, "SELECT id_exposicion, nombre_ex FROM exposicion ORDER BY nombre_ex");
$exposiciones = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones[] = $fila;
}

// Consultar estudios
$resultado = mysqli_query($conexion, "SELECT id_estudio, nombre_es FROM estudio ORDER BY nombre_es");
$estudios = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios[] = $fila;
}

// Consultar las exposiciones asociadas a cada trabajo
$resultado = mysqli_query($conexion, "SELECT id_trabajo, id_exposicion FROM trabajo_exposicion");
$exposiciones_trabajo = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones_trabajo[$fila["id_trabajo"]][] = (int)$fila["id_exposicion"];
}

// Consultar los estudios asociados a cada trabajo
$resultado = mysqli_query($conexion, "SELECT id_trabajo, id_estudio FROM trabajo_estudio");
$estudios_trabajo = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios_trabajo[$fila["id_trabajo"]][] = (int)$fila["id_estudio"];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alta de paciente</title>
</head>
<body>

    <h1>Agregar paciente</h1>

    <p><a href="listar.php">Volver al listado</a></p>

    <form action="guardar.php" method="POST">

        <h2>Datos personales</h2>

        <p>
            DNI:
            <input type="text" name="dni_p" maxlength="8" required>
        </p>

        <p>
            Nombre:
            <input type="text" name="nombre_p" maxlength="30" required>
        </p>

        <p>
            Apellido:
            <input type="text" name="apellido" maxlength="30" required>
        </p>

        <p>
            CUIL:
            <input type="text" name="cuil" maxlength="11">
        </p>

        <p>
            Teléfono:
            <input type="text" name="telefono" maxlength="20" required>
        </p>

        <p>
            Mail:
            <input type="email" name="mail" maxlength="100" required>
        </p>

        <p>
            Obra social:
            <input type="text" name="obra_social" maxlength="50" required>
        </p>

        <hr>

        <h2>Trabajo</h2>

        <p>
            <input
                type="text"
                name="trabajo_nombre"
                id="trabajo_nombre"
                list="lista_trabajos"
                maxlength="50"
                placeholder="Escribí o buscá un trabajo"
                autocomplete="off"
            >

            <datalist id="lista_trabajos">
                <?php foreach ($trabajos as $trabajo) { ?>
                    <option value="<?php echo htmlspecialchars($trabajo["nombre_t"]); ?>">
                <?php } ?>
            </datalist>
        </p>

        <p>
            Si el trabajo no existe, podés escribir su nombre. Se guardará para usarlo en próximos pacientes.
        </p>

        <hr>

        <h2>Exposiciones del paciente</h2>

        <p>
            <input
                type="text"
                id="buscar_exposiciones"
                placeholder="Buscar exposición..."
                autocomplete="off"
            >
        </p>

        <div id="lista_exposiciones">

            <?php foreach ($exposiciones as $exposicion) { ?>
                <p data-elemento-exposicion="<?php echo htmlspecialchars(mb_strtolower($exposicion["nombre_ex"])); ?>">
                    <label>
                        <input
                            type="checkbox"
                            name="exposiciones[]"
                            value="<?php echo $exposicion["id_exposicion"]; ?>"
                            data-exposicion="<?php echo $exposicion["id_exposicion"]; ?>"
                        >
                        <?php echo htmlspecialchars($exposicion["nombre_ex"]); ?>
                    </label>
                </p>
            <?php } ?>

        </div>

        <p>
            <input
                type="text"
                id="nueva_exposicion"
                maxlength="50"
                placeholder="Escribí una exposición nueva"
            >

            <button type="button" onclick="agregarExposicion()">Agregar exposición</button>
        </p>

        <div id="exposiciones_nuevas"></div>

        <hr>

        <h2>Estudios del paciente</h2>

        <p>
            <input
                type="text"
                id="buscar_estudios"
                placeholder="Buscar estudio..."
                autocomplete="off"
            >
        </p>

        <div id="lista_estudios">

            <?php foreach ($estudios as $estudio) { ?>
                <p data-elemento-estudio="<?php echo htmlspecialchars(mb_strtolower($estudio["nombre_es"])); ?>">
                    <label>
                        <input
                            type="checkbox"
                            name="estudios[]"
                            value="<?php echo $estudio["id_estudio"]; ?>"
                            data-estudio="<?php echo $estudio["id_estudio"]; ?>"
                        >
                        <?php echo htmlspecialchars($estudio["nombre_es"]); ?>
                    </label>
                </p>
            <?php } ?>

        </div>

        <p>
            <input
                type="text"
                id="nuevo_estudio"
                maxlength="50"
                placeholder="Escribí un estudio nuevo"
            >

            <button type="button" onclick="agregarEstudio()">Agregar estudio</button>
        </p>

        <div id="estudios_nuevos"></div>

        <br>

        <input type="submit" value="Guardar paciente">

    </form>

    <script>
        const trabajos = <?php echo json_encode($trabajos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        const exposicionesPorTrabajo = <?php echo json_encode($exposiciones_trabajo); ?>;
        const estudiosPorTrabajo = <?php echo json_encode($estudios_trabajo); ?>;

        const campoTrabajo = document.getElementById("trabajo_nombre");

        function normalizar(texto) {
            return texto.trim().toLocaleLowerCase("es");
        }

        // Al elegir un trabajo, marcar sus exposiciones y estudios habituales
        campoTrabajo.addEventListener("change", function () {

            const nombreTrabajo = normalizar(campoTrabajo.value);

            const trabajoSeleccionado = trabajos.find(trabajo =>
                normalizar(trabajo.nombre_t) === nombreTrabajo
            );

            let exposicionesSeleccionadas = [];
            let estudiosSeleccionados = [];

            if (trabajoSeleccionado) {
                const idTrabajo = trabajoSeleccionado.id_trabajo;

                exposicionesSeleccionadas = exposicionesPorTrabajo[idTrabajo] || [];
                estudiosSeleccionados = estudiosPorTrabajo[idTrabajo] || [];
            }

            document.querySelectorAll("[data-exposicion]").forEach(casilla => {
                casilla.checked = exposicionesSeleccionadas.includes(
                    Number(casilla.dataset.exposicion)
                );
            });

            document.querySelectorAll("[data-estudio]").forEach(casilla => {
                casilla.checked = estudiosSeleccionados.includes(
                    Number(casilla.dataset.estudio)
                );
            });
        });

        // Buscar exposiciones
        document.getElementById("buscar_exposiciones").addEventListener("input", function () {

            const busqueda = normalizar(this.value);

            document.querySelectorAll("[data-elemento-exposicion]").forEach(elemento => {
                const nombre = elemento.dataset.elementoExposicion;
                elemento.style.display = nombre.includes(busqueda) ? "" : "none";
            });
        });

        // Buscar estudios
        document.getElementById("buscar_estudios").addEventListener("input", function () {

            const busqueda = normalizar(this.value);

            document.querySelectorAll("[data-elemento-estudio]").forEach(elemento => {
                const nombre = elemento.dataset.elementoEstudio;
                elemento.style.display = nombre.includes(busqueda) ? "" : "none";
            });
        });

        // Agregar una exposición nueva a la selección
        function agregarExposicion() {

            const campo = document.getElementById("nueva_exposicion");
            const nombre = campo.value.trim();

            if (nombre === "") {
                alert("Escribí el nombre de la exposición.");
                return;
            }

            const nombreNormalizado = normalizar(nombre);

            // Si ya existe, marcar la exposición existente
            const existente = Array.from(
                document.querySelectorAll("[data-elemento-exposicion]")
            ).find(elemento =>
                normalizar(elemento.dataset.elementoExposicion) === nombreNormalizado
            );

            if (existente) {
                existente.querySelector("input[type='checkbox']").checked = true;
                campo.value = "";
                return;
            }

            // Evitar agregar dos veces la misma exposición nueva
            const nuevas = document.querySelectorAll("[data-nueva-exposicion]");

            for (const elemento of nuevas) {
                if (normalizar(elemento.dataset.nuevaExposicion) === nombreNormalizado) {
                    campo.value = "";
                    return;
                }
            }

            const contenedor = document.getElementById("exposiciones_nuevas");
            const fila = document.createElement("p");

            fila.dataset.nuevaExposicion = nombreNormalizado;

            const etiqueta = document.createElement("label");
            const casilla = document.createElement("input");

            casilla.type = "checkbox";
            casilla.name = "nueva_exposicion[]";
            casilla.value = nombre;
            casilla.checked = true;

            etiqueta.appendChild(casilla);
            etiqueta.appendChild(document.createTextNode(" " + nombre + " (nueva)"));

            fila.appendChild(etiqueta);
            contenedor.appendChild(fila);

            campo.value = "";
        }

        // Agregar un estudio nuevo a la selección
        function agregarEstudio() {

            const campo = document.getElementById("nuevo_estudio");
            const nombre = campo.value.trim();

            if (nombre === "") {
                alert("Escribí el nombre del estudio.");
                return;
            }

            const nombreNormalizado = normalizar(nombre);

            // Si ya existe, marcar el estudio existente
            const existente = Array.from(
                document.querySelectorAll("[data-elemento-estudio]")
            ).find(elemento =>
                normalizar(elemento.dataset.elementoEstudio) === nombreNormalizado
            );

            if (existente) {
                existente.querySelector("input[type='checkbox']").checked = true;
                campo.value = "";
                return;
            }

            // Evitar agregar dos veces el mismo estudio nuevo
            const nuevos = document.querySelectorAll("[data-nuevo-estudio]");

            for (const elemento of nuevos) {
                if (normalizar(elemento.dataset.nuevoEstudio) === nombreNormalizado) {
                    campo.value = "";
                    return;
                }
            }

            const contenedor = document.getElementById("estudios_nuevos");
            const fila = document.createElement("p");

            fila.dataset.nuevoEstudio = nombreNormalizado;

            const etiqueta = document.createElement("label");
            const casilla = document.createElement("input");

            casilla.type = "checkbox";
            casilla.name = "nuevo_estudio[]";
            casilla.value = nombre;
            casilla.checked = true;

            etiqueta.appendChild(casilla);
            etiqueta.appendChild(document.createTextNode(" " + nombre + " (nuevo)"));

            fila.appendChild(etiqueta);
            contenedor.appendChild(fila);

            campo.value = "";
        }
    </script>

</body>
</html>

<?php
mysqli_close($conexion);
?>