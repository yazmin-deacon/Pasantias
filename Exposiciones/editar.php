
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. FUNCIONES
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// =========================================
// 2. OBTENER ID
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
// 3. CONSULTAR EXPOSICIÓN ACTUAL
// =========================================

$sql = "SELECT id_exposicion, nombre_ex
        FROM exposicion
        WHERE id_exposicion = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta.");
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    die("No se pudo consultar la exposición.");
}

$resultado = mysqli_stmt_get_result($stmt);
$exposicion = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$exposicion) {
    mysqli_close($conexion);
    header(
        "Location: listar.php?mensaje=" .
        urlencode("No se encontró la exposición.")
    );
    exit;
}

// =========================================
// 4. CONSULTAR OTRAS EXPOSICIONES
// =========================================

$sql = "SELECT id_exposicion, nombre_ex
        FROM exposicion
        ORDER BY nombre_ex ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die(
        "Error al consultar las exposiciones: " .
        mysqli_error($conexion)
    );
}

$exposicionesRegistradas = mysqli_fetch_all(
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Editar exposición - Clínica</title>

    <link rel="stylesheet"
          href="../general/estilos.css">

    <style>
        .campo-exposicion {
            width: 100%;
            max-width: 450px;
        }

        .campo-exposicion input {
            width: 100%;
            box-sizing: border-box;
        }

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

        .sugerencias-exposiciones {
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

        .acciones-formulario {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn-cancelar {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            background-color: #e5e5e5;
            color: #333;
            font-weight: 600;
        }

        .btn-cancelar:hover {
            background-color: #d0d0d0;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <!-- ENCABEZADO -->

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

            <a href="listar.php">
                Exposiciones
            </a>

            <a href="../Trabajos/listar.php">
                Trabajos
            </a>

            <a href="../gestionar_relaciones.html">
                Relaciones
            </a>

            <a href="../Estudios/estudios.php">
                Estudios
            </a>
        </nav>

        <button type="button" id="btn-tema">
            Modo oscuro
        </button>

    </header>

    <!-- CONTENIDO PRINCIPAL -->

    <main class="gestion-contenido">

        <section class="bienvenida">

            <h1>Editar exposición</h1>

            <p>
                Modificá el nombre de la exposición
                seleccionada.
            </p>

        </section>

        <section class="tarjeta">

            <form
                class="form-gestion"
                action="actualizar.php"
                method="POST"
                id="formEditarExposicion"
            >

                <!-- ID -->

                <input
                    type="hidden"
                    name="id_exposicion"
                    value="<?php
                        echo (int)$exposicion["id_exposicion"];
                    ?>"
                >

                <!-- NOMBRE -->

                <div class="campo campo-exposicion">

                    <label for="nombre_ex">
                        Nombre de la exposición
                    </label>

                    <input
                        type="text"
                        id="nombre_ex"
                        name="nombre_ex"
                        maxlength="50"
                        autocomplete="off"
                        required
                        value="<?php
                            echo escapar($exposicion["nombre_ex"]);
                        ?>"
                    >

                    <!-- SUGERENCIAS -->

                    <div
                        id="sugerencias_exposiciones"
                        class="sugerencias-exposiciones"
                    ></div>

                    <!-- MENSAJE -->

                    <p
                        id="mensaje_validacion"
                        class="mensaje-validacion"
                        aria-live="polite"
                    ></p>

                </div>

                <!-- BOTONES -->

                <div class="acciones-formulario">

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

                </div>

            </form>

        </section>

    </main>

    <!-- PIE DE PÁGINA -->

    <footer>
        <span>Clínica</span>
        <span>Administración de exposiciones</span>
    </footer>

    <!-- JAVASCRIPT -->

    <script>

        // EXPOSICIONES OBTENIDAS DE MYSQL

        const exposicionesRegistradas = <?php
            echo json_encode(
                $exposicionesRegistradas,
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            );
        ?>;

        const idExposicionActual = <?php
            echo (int)$exposicion["id_exposicion"];
        ?>;

        const nombreOriginal = <?php
            echo json_encode(
                $exposicion["nombre_ex"],
                JSON_HEX_TAG |
                JSON_HEX_APOS |
                JSON_HEX_QUOT |
                JSON_HEX_AMP
            );
        ?>;

        // ELEMENTOS DEL FORMULARIO

        const campoNombre = document.getElementById(
            "nombre_ex"
        );

        const sugerencias = document.getElementById(
            "sugerencias_exposiciones"
        );

        const mensaje = document.getElementById(
            "mensaje_validacion"
        );

        const botonGuardar = document.getElementById(
            "btnGuardar"
        );

        const formulario = document.getElementById(
            "formEditarExposicion"
        );

        // NORMALIZAR NOMBRES

        function normalizar(texto) {
            return String(texto)
                .trim()
                .replace(/\s+/g, " ")
                .toLocaleLowerCase("es");
        }

        // BUSCAR SI EXISTE OTRA EXPOSICIÓN

        function buscarDuplicado(nombre) {

            const texto = normalizar(nombre);

            return exposicionesRegistradas.find(
                exposicion => {

                    const mismoNombre =
                        normalizar(exposicion.nombre_ex) === texto;

                    const otroRegistro =
                        Number(exposicion.id_exposicion) !==
                        idExposicionActual;

                    return mismoNombre && otroRegistro;

                }
            ) || null;
        }

        // VALIDAR NOMBRE

        function validarNombre() {

            const nombre = campoNombre.value.trim();

            mensaje.className = "mensaje-validacion";

            if (nombre === "") {

                mensaje.textContent =
                    "El nombre de la exposición es obligatorio.";

                mensaje.classList.add("mensaje-error");
                botonGuardar.disabled = true;

                return false;
            }

            const duplicado = buscarDuplicado(nombre);

            if (duplicado) {

                mensaje.textContent =
                    "Ya existe otra exposición con ese nombre: " +
                    duplicado.nombre_ex;

                mensaje.classList.add("mensaje-error");
                botonGuardar.disabled = true;

                return false;
            }

            if (
                normalizar(nombre) ===
                normalizar(nombreOriginal)
            ) {

                mensaje.textContent =
                    "Estás editando la exposición actual.";

            } else {

                mensaje.textContent =
                    "Nombre disponible para actualizar.";
            }

            mensaje.classList.add("mensaje-correcto");
            botonGuardar.disabled = false;

            return true;
        }

        // SUGERENCIAS MIENTRAS ESCRIBE

        function mostrarSugerencias() {

            const texto = normalizar(campoNombre.value);

            sugerencias.replaceChildren();

            if (texto === "") {
                sugerencias.style.display = "none";
                return;
            }

            const coincidencias = exposicionesRegistradas
                .filter(exposicion =>
                    Number(exposicion.id_exposicion) !==
                        idExposicionActual &&
                    normalizar(exposicion.nombre_ex)
                        .includes(texto)
                )
                .sort((a, b) => {

                    const empiezaA = normalizar(
                        a.nombre_ex
                    ).startsWith(texto);

                    const empiezaB = normalizar(
                        b.nombre_ex
                    ).startsWith(texto);

                    if (empiezaA !== empiezaB) {
                        return empiezaA ? -1 : 1;
                    }

                    return a.nombre_ex.localeCompare(
                        b.nombre_ex,
                        "es"
                    );
                })
                .slice(0, 10);

            if (coincidencias.length === 0) {
                sugerencias.style.display = "none";
                return;
            }

            coincidencias.forEach(exposicion => {

                const opcion = document.createElement("div");

                opcion.className = "sugerencia";
                opcion.textContent = exposicion.nombre_ex;

                opcion.addEventListener("click", function () {

                    campoNombre.value = exposicion.nombre_ex;

                    sugerencias.style.display = "none";

                    validarNombre();
                });

                sugerencias.appendChild(opcion);
            });

            sugerencias.style.display = "block";
        }

        // EVENTOS

        campoNombre.addEventListener("input", function () {
            mostrarSugerencias();
            validarNombre();
        });

        campoNombre.addEventListener("focus", mostrarSugerencias);

        campoNombre.addEventListener("blur", function () {

            campoNombre.value =
                campoNombre.value
                    .trim()
                    .replace(/\s+/g, " ");

            validarNombre();
        });

        document.addEventListener("click", function (evento) {

            if (
                evento.target !== campoNombre &&
                !sugerencias.contains(evento.target)
            ) {
                sugerencias.style.display = "none";
            }
        });

        // VALIDACIÓN FINAL

        formulario.addEventListener("submit", function (evento) {

            if (!validarNombre()) {

                evento.preventDefault();

                alert("Revisá el nombre de la exposición.");
                return;
            }

            campoNombre.value =
                campoNombre.value
                    .trim()
                    .replace(/\s+/g, " ");
        });

        validarNombre();

    </script>

    <!-- SCRIPTS GENERALES -->

    <script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>
</html>
