
<?php
require_once "conexion.php";

// =========================================
// 1. CONSULTAR LOS CATÁLOGOS
// =========================================

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error al consultar datos: " .
            mysqli_error($conexion));
    }

    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

// Consultar trabajos
$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo ORDER BY nombre_t"
);

// Consultar obras sociales activas
$obras_sociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os
     FROM obra_social
     WHERE activa = 1
     ORDER BY nombre_os"
);

// Consultar exposiciones
$exposiciones = consultarTodos(
    $conexion,
    "SELECT id_exposicion, nombre_ex
     FROM exposicion ORDER BY nombre_ex"
);

// Consultar estudios
$estudios = consultarTodos(
    $conexion,
    "SELECT id_estudio, nombre_es
     FROM estudio ORDER BY nombre_es"
);

// =========================================
// 2. RELACIONES DE LOS TRABAJOS
// =========================================

$exposiciones_trabajo = [];
$estudios_trabajo = [];

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_exposicion
     FROM trabajo_exposicion"
);

foreach ($filas as $fila) {
    $exposiciones_trabajo[$fila["id_trabajo"]][] =
        (int)$fila["id_exposicion"];
}

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_estudio
     FROM trabajo_estudio"
);

foreach ($filas as $fila) {
    $estudios_trabajo[$fila["id_trabajo"]][] =
        (int)$fila["id_estudio"];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Alta de paciente</title>
</head>

<body>

<h1>Agregar paciente</h1>

<p>
    <a href="listar.php">Volver al listado</a>
</p>

<form action="guardar.php" method="POST">

    <!-- ================================= -->
    <!-- 3. DATOS PERSONALES -->
    <!-- ================================= -->

    <h2>Datos personales</h2>

    <p>
        DNI:
        <input
            type="text"
            name="dni_p"
            maxlength="8"
            inputmode="numeric"
            required
        >
    </p>

    <p>
        Nombre:
        <input
            type="text"
            name="nombre_p"
            maxlength="30"
            required
        >
    </p>

    <p>
        Apellido:
        <input
            type="text"
            name="apellido"
            maxlength="30"
            required
        >
    </p>

    <p>
        CUIL:
        <input
            type="text"
            name="cuil"
            maxlength="11"
        >
    </p>

    <p>
        Teléfono:
        <input
            type="text"
            name="telefono"
            maxlength="20"
            required
        >
    </p>

    <p>
        Mail:
        <input
            type="email"
            name="mail"
            maxlength="100"
            required
        >
    </p>

    <!-- ================================= -->
    <!-- 4. OBRA SOCIAL -->
    <!-- ================================= -->

    <p>
        Obra social:

        <select name="id_obra_social" required>

            <option value="" disabled selected>
                Seleccionar obra social
            </option>

            <option value="particular">
                Particular / Sin obra social
            </option>

            <?php foreach ($obras_sociales as $obra) { ?>

                <option
                    value="<?php echo (int)$obra['id_obra_social']; ?>"
                >
                    <?php
                        echo htmlspecialchars(
                            $obra["nombre_os"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>
                </option>

            <?php } ?>

        </select>
    </p>

    <hr>

    <!-- ================================= -->
    <!-- 5. TRABAJO -->
    <!-- ================================= -->

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

                <option
                    value="<?php
                        echo htmlspecialchars(
                            $trabajo["nombre_t"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>"
                >

            <?php } ?>

        </datalist>
    </p>

    <p>
        Si el trabajo no existe, podés escribir
        uno nuevo. Se guardará automáticamente.
    </p>

    <hr>

    <!-- ================================= -->
    <!-- 6. EXPOSICIONES -->
    <!-- ================================= -->

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

            <p
                data-elemento-exposicion="<?php
                    echo htmlspecialchars(
                        mb_strtolower($exposicion["nombre_ex"]),
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
            >
                <label>
                    <input
                        type="checkbox"
                        name="exposiciones[]"
                        value="<?php echo (int)$exposicion['id_exposicion']; ?>"
                        data-exposicion="<?php echo (int)$exposicion['id_exposicion']; ?>"
                    >

                    <?php
                        echo htmlspecialchars(
                            $exposicion["nombre_ex"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>
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

        <button
            type="button"
            onclick="agregarExposicion()"
        >
            Agregar exposición
        </button>
    </p>

    <div id="exposiciones_nuevas"></div>

    <hr>

    <!-- ================================= -->
    <!-- 7. ESTUDIOS -->
    <!-- ================================= -->

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

            <p
                data-elemento-estudio="<?php
                    echo htmlspecialchars(
                        mb_strtolower($estudio["nombre_es"]),
                        ENT_QUOTES,
                        "UTF-8"
                    );
                ?>"
            >
                <label>
                    <input
                        type="checkbox"
                        name="estudios[]"
                        value="<?php echo (int)$estudio['id_estudio']; ?>"
                        data-estudio="<?php echo (int)$estudio['id_estudio']; ?>"
                    >

                    <?php
                        echo htmlspecialchars(
                            $estudio["nombre_es"],
                            ENT_QUOTES,
                            "UTF-8"
                        );
                    ?>
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

        <button
            type="button"
            onclick="agregarEstudio()"
        >
            Agregar estudio
        </button>
    </p>

    <div id="estudios_nuevos"></div>

    <br>

    <input
        type="submit"
        value="Guardar paciente"
    >

</form>

<!-- ================================= -->
<!-- 8. JAVASCRIPT -->
<!-- ================================= -->

<script>

// Datos enviados desde PHP
const trabajos = <?php
    echo json_encode(
        $trabajos,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_HEX_AMP
    );
?>;

const exposicionesPorTrabajo = <?php
    echo json_encode($exposiciones_trabajo);
?>;

const estudiosPorTrabajo = <?php
    echo json_encode($estudios_trabajo);
?>;

// Normalizar textos
function normalizar(texto) {
    return texto.trim().toLocaleLowerCase("es");
}

// =========================================
// 9. SELECCIÓN AUTOMÁTICA POR TRABAJO
// =========================================

const campoTrabajo =
    document.getElementById("trabajo_nombre");

campoTrabajo.addEventListener("change", function () {

    const nombreTrabajo =
        normalizar(campoTrabajo.value);

    const trabajoSeleccionado = trabajos.find(
        trabajo =>
            normalizar(trabajo.nombre_t) === nombreTrabajo
    );

    let exposicionesSeleccionadas = [];
    let estudiosSeleccionados = [];

    if (trabajoSeleccionado) {

        const idTrabajo = trabajoSeleccionado.id_trabajo;

        exposicionesSeleccionadas =
            exposicionesPorTrabajo[idTrabajo] || [];

        estudiosSeleccionados =
            estudiosPorTrabajo[idTrabajo] || [];
    }

    // Marcar exposiciones
    document.querySelectorAll(
        "[data-exposicion]"
    ).forEach(casilla => {

        casilla.checked =
            exposicionesSeleccionadas.includes(
                Number(casilla.dataset.exposicion)
            );
    });

    // Marcar estudios
    document.querySelectorAll(
        "[data-estudio]"
    ).forEach(casilla => {

        casilla.checked =
            estudiosSeleccionados.includes(
                Number(casilla.dataset.estudio)
            );
    });
});

// =========================================
// 10. BUSCAR EXPOSICIONES Y ESTUDIOS
// =========================================

function configurarBuscador(campoId, atributo) {

    document.getElementById(campoId)
        .addEventListener("input", function () {

            const busqueda = normalizar(this.value);

            document.querySelectorAll(
                "[" + atributo + "]"
            ).forEach(elemento => {

                const nombre =
                    elemento.getAttribute(atributo);

                elemento.style.display =
                    nombre.includes(busqueda)
                    ? ""
                    : "none";
            });
        });
}

configurarBuscador(
    "buscar_exposiciones",
    "data-elemento-exposicion"
);

configurarBuscador(
    "buscar_estudios",
    "data-elemento-estudio"
);

// =========================================
// 11. AGREGAR EXPOSICIONES Y ESTUDIOS
// =========================================

function agregarElemento(
    campoId,
    atributoExistente,
    atributoNuevo,
    contenedorId,
    nombreInput,
    etiquetaTipo
) {

    const campo = document.getElementById(campoId);
    const nombre = campo.value.trim();

    if (nombre === "") {
        alert("Escribí un nombre.");
        return;
    }

    const nombreNormalizado = normalizar(nombre);

    // Revisar si ya existe en el catálogo
    const existente = Array.from(
        document.querySelectorAll(
            "[" + atributoExistente + "]"
        )
    ).find(elemento =>
        normalizar(
            elemento.getAttribute(atributoExistente)
        ) === nombreNormalizado
    );

    if (existente) {

        existente.querySelector(
            "input[type='checkbox']"
        ).checked = true;

        campo.value = "";
        return;
    }

    // Evitar agregar dos veces un elemento nuevo
    const nuevos = document.querySelectorAll(
        "[" + atributoNuevo + "]"
    );

    for (const elemento of nuevos) {

        if (
            elemento.getAttribute(atributoNuevo)
            === nombreNormalizado
        ) {
            campo.value = "";
            return;
        }
    }

    // Crear nueva casilla
    const contenedor =
        document.getElementById(contenedorId);

    const fila = document.createElement("p");

    fila.setAttribute(
        atributoNuevo,
        nombreNormalizado
    );

    const etiqueta =
        document.createElement("label");

    const casilla =
        document.createElement("input");

    casilla.type = "checkbox";
    casilla.name = nombreInput;
    casilla.value = nombre;
    casilla.checked = true;

    etiqueta.appendChild(casilla);

    etiqueta.appendChild(
        document.createTextNode(
            " " + nombre + " (" + etiquetaTipo + ")"
        )
    );

    fila.appendChild(etiqueta);
    contenedor.appendChild(fila);

    campo.value = "";
}

// Agregar exposición
function agregarExposicion() {

    agregarElemento(
        "nueva_exposicion",
        "data-elemento-exposicion",
        "data-nueva-exposicion",
        "exposiciones_nuevas",
        "nueva_exposicion[]",
        "nueva"
    );
}

// Agregar estudio
function agregarEstudio() {

    agregarElemento(
        "nuevo_estudio",
        "data-elemento-estudio",
        "data-nuevo-estudio",
        "estudios_nuevos",
        "nuevo_estudio[]",
        "nuevo"
    );
}

</script>

</body>
</html>

<?php
mysqli_close($conexion);
?>
