
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. OBTENER ID DEL TRABAJO
// =========================================

$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header("Location: listar.php");
    exit;
}

// =========================================
// 2. FUNCIÓN PARA MOSTRAR TEXTO SEGURO
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// =========================================
// 3. CONSULTAR TRABAJO ACTUAL
// =========================================

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

if (!mysqli_stmt_execute($stmt)) {
    die("No se pudo consultar el trabajo.");
}

$resultado = mysqli_stmt_get_result($stmt);

$trabajo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajo) {

    mysqli_close($conexion);

    header(
        "Location: listar.php?mensaje=" .
        urlencode("No se encontró el trabajo.")
    );

    exit;
}

// =========================================
// 4. CONSULTAR LOS DEMÁS TRABAJOS
// =========================================

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        ORDER BY nombre_t ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die(
        "Error al consultar los trabajos: " .
        mysqli_error($conexion)
    );
}

$trabajosRegistrados = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Editar trabajo - Clínica</title>

    <!-- ESTILOS GENERALES -->

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        /* BOTÓN CANCELAR */

        .btn-cancelar {
            display: inline-flex;
            width: auto;
            max-width: max-content;
            flex: 0 0 auto;
            align-self: flex-start;
            padding: 9px 16px;
            margin: 10px 0 0 10px;
            background-color: transparent;
            color: white;
            border: 1px solid white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: 0.3s;
        }

        .btn-cancelar:hover {
            background-color: #f4c6d5;
            color: #800020;
            border-color: #f4c6d5;
        }

        /* CAMPO DE TRABAJO */

        .campo-trabajo {
            width: 100%;
            max-width: 450px;
        }

        .campo-trabajo input {
            width: 100%;
            box-sizing: border-box;
        }

        /* MENSAJES DE VALIDACIÓN */

        .mensaje-validacion {
            margin-top: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .mensaje-error {
            color: #c94b4b;
        }

        .mensaje-correcto {
            color: #25814c;
        }

        /* SUGERENCIAS */

        .sugerencias-trabajos {
            display: none;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: white;
            color: #333;
        }

        .sugerencia {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .sugerencia:last-child {
            border-bottom: none;
        }

        .sugerencia:hover {
            background-color: #f4c6d5;
            color: #800020;
        }

        /* BOTÓN DESHABILITADO */

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

    </style>

</head>

<body>

    <!-- ================================= -->
    <!-- ENCABEZADO -->
    <!-- ================================= -->

    <header>

        <div class="logo">

            <img
                id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt="Logo DUCA"
                height="150"
                width="175"
            >

        </div>

        <nav>

            <a href="../inicio sesion/index.php">
                Menú principal
            </a>

            <a href="../gestionar_exposiciones.html">
                Exposiciones
            </a>

            <a href="listar.php">
                Trabajos
            </a>

            <a href="../gestionar_relaciones.html">
                Relaciones
            </a>

            <a href="../gestionar_estudios.html">
                Estudios
            </a>

        </nav>

        <button type="button" id="btn-tema">
            Modo oscuro
        </button>

    </header>

    <!-- ================================= -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ================================= -->

    <main class="gestion-contenido">

        <section class="bienvenida">

            <h1>Editar trabajo</h1>

            <p>
                Modificá el nombre del trabajo seleccionado.
            </p>

        </section>

        <!-- ================================= -->
        <!-- FORMULARIO -->
        <!-- ================================= -->

        <section class="tarjeta">

            <form
                class="form-gestion"
                action="actualizar.php"
                method="POST"
                id="formEditarTrabajo"
            >

                <!-- ID DEL TRABAJO -->

                <input
                    type="hidden"
                    name="id_trabajo"
                    value="<?php
                        echo (int)$trabajo["id_trabajo"];
                    ?>"
                >

                <!-- NOMBRE -->

                <div class="campo campo-trabajo">

                    <label for="nombre_t">
                        Nombre del trabajo
                    </label>

                    <input
                        type="text"
                        id="nombre_t"
                        name="nombre_t"
                        maxlength="50"
                        autocomplete="off"
                        required
                        value="<?php
                            echo escapar(
                                $trabajo["nombre_t"]
                            );
                        ?>"
                    >

                    <!-- SUGERENCIAS -->

                    <div
                        id="sugerencias_trabajos"
                        class="sugerencias-trabajos"
                    ></div>

                    <!-- MENSAJE -->

                    <p
                        id="mensaje_validacion"
                        class="mensaje-validacion"
                        aria-live="polite"
                    ></p>

                </div>

                <!-- ESTADO DEL FORMULARIO -->

                <button
                    type="submit"
                    id="btnGuardar"
                >
                    Guardar cambios
                </button>

                <a
                    href="listar.php"
                    class="btn-cancelar"
                >
                    Cancelar
                </a>

            </form>

        </section>

    </main>

    <!-- ================================= -->
    <!-- PIE DE PÁGINA -->
    <!-- ================================= -->

    <footer>

        <span>Clínica</span>

        <span>Administración de trabajos</span>

    </footer>

    <!-- ================================= -->
    <!-- JAVASCRIPT -->
    <!-- ================================= -->

    <script>

        // =================================
        // DATOS DESDE MYSQL
        // =================================

        const trabajosRegistrados = <?php
            echo json_encode(
                $trabajosRegistrados,
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            );
        ?>;

        const idTrabajoActual = <?php
            echo (int)$trabajo["id_trabajo"];
        ?>;

        const nombreOriginal = <?php
            echo json_encode(
                $trabajo["nombre_t"],
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            );
        ?>;

        // =================================
        // ELEMENTOS
        // =================================

        const campoNombre = document.getElementById(
            "nombre_t"
        );

        const sugerencias = document.getElementById(
            "sugerencias_trabajos"
        );

        const mensaje = document.getElementById(
            "mensaje_validacion"
        );

        const botonGuardar = document.getElementById(
            "btnGuardar"
        );

        const formulario = document.getElementById(
            "formEditarTrabajo"
        );

        // =================================
        // NORMALIZAR TEXTO
        // =================================

        function normalizar(texto) {

            return String(texto)
                .trim()
                .replace(/\s+/g, " ")
                .toLocaleLowerCase("es");

        }

        // =================================
        // BUSCAR OTRO TRABAJO DUPLICADO
        // =================================

        function buscarDuplicado(nombre) {

            const nombreNormalizado = normalizar(
                nombre
            );

            return trabajosRegistrados.find(
                trabajo => {

                    const mismoNombre =
                        normalizar(
                            trabajo.nombre_t
                        ) === nombreNormalizado;

                    const otroTrabajo =
                        Number(trabajo.id_trabajo) !==
                        idTrabajoActual;

                    return mismoNombre && otroTrabajo;

                }
            ) || null;

        }

        // =================================
        // VALIDAR NOMBRE
        // =================================

        function validarNombre() {

            const nombre = campoNombre.value.trim();

            mensaje.className =
                "mensaje-validacion";

            // CAMPO VACÍO

            if (nombre === "") {

                mensaje.textContent =
                    "El nombre del trabajo es obligatorio.";

                mensaje.classList.add(
                    "mensaje-error"
                );

                botonGuardar.disabled = true;

                return false;
            }

            // VERIFICAR DUPLICADOS

            const duplicado = buscarDuplicado(
                nombre
            );

            if (duplicado) {

                mensaje.textContent =
                    "Ya existe otro trabajo con este nombre: " +
                    duplicado.nombre_t;

                mensaje.classList.add(
                    "mensaje-error"
                );

                botonGuardar.disabled = true;

                return false;
            }

            // NOMBRE ORIGINAL

            if (
                normalizar(nombre) ===
                normalizar(nombreOriginal)
            ) {

                mensaje.textContent =
                    "Estás editando el trabajo actual.";

            } else {

                mensaje.textContent =
                    "Nombre disponible para actualizar.";

            }

            mensaje.classList.add(
                "mensaje-correcto"
            );

            botonGuardar.disabled = false;

            return true;
        }

        // =================================
        // MOSTRAR TRABAJOS SIMILARES
        // =================================

        function mostrarSugerencias() {

            const busqueda = normalizar(
                campoNombre.value
            );

            sugerencias.replaceChildren();

            if (busqueda === "") {

                sugerencias.style.display = "none";
                return;
            }

            const coincidencias = trabajosRegistrados

                .filter(trabajo =>

                    Number(trabajo.id_trabajo) !==
                        idTrabajoActual &&

                    normalizar(trabajo.nombre_t)
                        .includes(busqueda)

                )

                .sort((a, b) => {

                    const aEmpieza = normalizar(
                        a.nombre_t
                    ).startsWith(busqueda);

                    const bEmpieza = normalizar(
                        b.nombre_t
                    ).startsWith(busqueda);

                    if (aEmpieza !== bEmpieza) {
                        return aEmpieza ? -1 : 1;
                    }

                    return a.nombre_t.localeCompare(
                        b.nombre_t,
                        "es"
                    );
                })

                .slice(0, 10);

            if (coincidencias.length === 0) {

                sugerencias.style.display = "none";
                return;
            }

            coincidencias.forEach(trabajo => {

                const opcion = document.createElement(
                    "div"
                );

                opcion.className = "sugerencia";

                opcion.textContent =
                    trabajo.nombre_t;

                opcion.addEventListener(
                    "click",
                    function() {

                        campoNombre.value =
                            trabajo.nombre_t;

                        sugerencias.style.display =
                            "none";

                        validarNombre();
                    }
                );

                sugerencias.appendChild(
                    opcion
                );

            });

            sugerencias.style.display = "block";
        }

        // =================================
        // EVENTOS DEL CAMPO
        // =================================

        campoNombre.addEventListener(
            "input",
            function() {

                mostrarSugerencias();

                validarNombre();

            }
        );

        campoNombre.addEventListener(
            "focus",
            mostrarSugerencias
        );

        campoNombre.addEventListener(
            "blur",
            function() {

                campoNombre.value =
                    campoNombre.value
                        .trim()
                        .replace(/\s+/g, " ");

                validarNombre();

            }
        );

        // OCULTAR SUGERENCIAS AL SALIR

        document.addEventListener(
            "click",
            function(evento) {

                if (
                    evento.target !== campoNombre &&
                    !sugerencias.contains(
                        evento.target
                    )
                ) {

                    sugerencias.style.display =
                        "none";
                }
            }
        );

        // =================================
        // VALIDAR ANTES DE GUARDAR
        // =================================

        formulario.addEventListener(
            "submit",
            function(evento) {

                if (!validarNombre()) {

                    evento.preventDefault();

                    alert(
                        "Revisá el nombre del trabajo."
                    );

                    return;
                }

                campoNombre.value =
                    campoNombre.value
                        .trim()
                        .replace(/\s+/g, " ");

            }
        );

        // VALIDACIÓN INICIAL

        validarNombre();

    </script>

    <!-- SCRIPTS GENERALES -->

    <script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>
</html>
