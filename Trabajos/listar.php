
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. SESIÓN Y TOKEN DE SEGURIDAD
// =========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["token_trabajos"])) {
    $_SESSION["token_trabajos"] = bin2hex(
        random_bytes(32)
    );
}

// =========================================
// 2. FUNCIONES
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function limpiarNombre($nombre) {
    $nombre = trim((string)$nombre);

    return preg_replace(
        '/\s+/u',
        ' ',
        $nombre
    );
}

function normalizarNombre($nombre) {
    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

// =========================================
// 3. REGISTRAR TRABAJO NUEVO
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["token_trabajos"],
            $token
        )
    ) {
        http_response_code(403);
        die("Solicitud no válida.");
    }

    $nombre = limpiarNombre(
        $_POST["nombre_t"] ?? ""
    );

    if ($nombre === "") {
        header(
            "Location: listar.php?mensaje=" .
            urlencode("Escribí un nombre de trabajo.")
        );
        exit;
    }

    if (mb_strlen($nombre, "UTF-8") > 50) {
        header(
            "Location: listar.php?mensaje=" .
            urlencode(
                "El nombre no puede superar 50 caracteres."
            )
        );
        exit;
    }

    // COMPROBAR SI YA ESTÁ REGISTRADO

    $sql = "SELECT id_trabajo, nombre_t
            FROM trabajo";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error al consultar los trabajos.");
    }

    $duplicado = false;

    while ($fila = mysqli_fetch_assoc($resultado)) {

        if (
            normalizarNombre($fila["nombre_t"]) ===
            normalizarNombre($nombre)
        ) {
            $duplicado = true;
            break;
        }
    }

    mysqli_free_result($resultado);

    if ($duplicado) {

        mysqli_close($conexion);

        header(
            "Location: listar.php?mensaje=" .
            urlencode(
                "Ese trabajo ya está registrado."
            )
        );
        exit;
    }

    // GUARDAR EL TRABAJO EN MYSQL

    $sql = "INSERT INTO trabajo (nombre_t)
            VALUES (?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        die("Error al preparar el registro.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nombre
    );

    $guardado = mysqli_stmt_execute($stmt);

    if (!$guardado) {

        $codigoError = mysqli_stmt_errno($stmt);
        $mensajeError = mysqli_stmt_error($stmt);

        mysqli_stmt_close($stmt);
        mysqli_close($conexion);

        if ($codigoError == 1062) {

            header(
                "Location: listar.php?mensaje=" .
                urlencode(
                    "Ese trabajo ya está registrado."
                )
            );
            exit;
        }

        error_log($mensajeError);

        die("No se pudo guardar el trabajo.");
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header(
        "Location: listar.php?mensaje=" .
        urlencode(
            "Trabajo registrado correctamente: " .
            $nombre
        )
    );
    exit;
}

// =========================================
// 4. CONSULTAR TRABAJOS REGISTRADOS
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

$trabajos = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

$cantidad = count($trabajos);

// MENSAJE DE RESULTADO

$mensaje = "";

if (isset($_GET["mensaje"]) && is_string($_GET["mensaje"])) {
    $mensaje = $_GET["mensaje"];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Administrar trabajos - Clínica</title>

    <link rel="stylesheet"
          href="../general/estilos.css">

    <style>

        .acciones {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .acciones form {
            margin: 0;
            padding: 0;
        }

        .acciones a,
        .acciones button {
            display: inline-block;
            padding: 9px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: 0.3s;
        }

        .btn-editar {
            background-color: #e5e5e5;
            color: #333;
        }

        .btn-editar:hover {
            background-color: #d0d0d0;
        }

        .btn-eliminar {
            background-color: #c94b4b;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #a93636;
        }

        .buscador-trabajo {
            width: 100%;
            max-width: 460px;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .mensaje-busqueda {
            margin-top: 12px;
            font-size: 14px;
        }

        .texto-ayuda {
            margin-top: 8px;
            font-size: 14px;
        }

        .sin-resultados {
            margin-top: 15px;
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

            <h1>Administrar trabajos</h1>

            <p>
                Buscá, registrá y mantené actualizado
                el catálogo de trabajos.
            </p>

        </section>

        <!-- MENSAJES -->

        <?php if ($mensaje !== "") { ?>

            <section class="tarjeta">

                <p>
                    <?php echo escapar($mensaje); ?>
                </p>

            </section>

        <?php } ?>

        <!-- ================================= -->
        <!-- BUSCAR O REGISTRAR TRABAJO -->
        <!-- ================================= -->

        <section class="tarjeta">

            <h2>Buscar trabajo</h2>

            <p>
                Escribí el nombre de un trabajo
                para encontrarlo en el listado.
            </p>

            <p class="texto-ayuda">
                Si no existe, escribí su nombre
                completo y presioná Enter
                para registrarlo.
            </p>

            <br>

            <form
                action="listar.php"
                method="POST"
                id="formularioTrabajo"
            >

                <input
                    type="hidden"
                    name="token"
                    value="<?php
                        echo escapar(
                            $_SESSION["token_trabajos"]
                        );
                    ?>"
                >

                <input
                    type="text"
                    name="nombre_t"
                    id="buscar_trabajo"
                    class="buscador-trabajo"
                    maxlength="50"
                    placeholder="Buscar o escribir trabajo..."
                    autocomplete="off"
                    aria-label="Buscar o registrar trabajo"
                >

            </form>

            <p
                id="mensaje_busqueda"
                class="mensaje-busqueda"
                aria-live="polite"
            ></p>

        </section>

        <!-- ================================= -->
        <!-- TRABAJOS REGISTRADOS -->
        <!-- ================================= -->

        <section class="tarjeta">

            <h2>Trabajos registrados</h2>

            <p>
                Total de trabajos:
                <strong>
                    <?php echo $cantidad; ?>
                </strong>
            </p>

            <p>
                Trabajos encontrados:
                <strong id="cantidad_encontrados">
                    <?php echo $cantidad; ?>
                </strong>
            </p>

            <br>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>

                        <tr>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($trabajos as $trabajo) { ?>

                            <tr
                                class="fila_trabajo"
                                data-nombre="<?php
                                    echo escapar(
                                        $trabajo["nombre_t"]
                                    );
                                ?>"
                            >

                                <td>
                                    <?php
                                        echo escapar(
                                            $trabajo["nombre_t"]
                                        );
                                    ?>
                                </td>

                                <td>

                                    <div class="acciones">

                                        <!-- EDITAR -->

                                        <a
                                            class="btn-editar"
                                            href="editar.php?id=<?php
                                                echo (int)$trabajo["id_trabajo"];
                                            ?>"
                                        >
                                            Editar
                                        </a>

                                        <!-- ELIMINAR -->

                                        <form
                                            action="eliminar.php"
                                            method="POST"
                                            onsubmit="return confirm(
                                                '¿Seguro que querés eliminar este trabajo?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="id_trabajo"
                                                value="<?php
                                                    echo (int)$trabajo["id_trabajo"];
                                                ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-eliminar"
                                            >
                                                Eliminar
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <p
                id="sin_resultados"
                class="sin-resultados"
                style="display:none;"
            >
                No se encontraron trabajos con ese nombre.
            </p>

        </section>

        <!-- ================================= -->
        <!-- VOLVER A PACIENTES -->
        <!-- ================================= -->

        <section class="tarjeta">

            <a
                href="../Pacientes/listar.php"
                class="btn-secundario"
            >
                Volver al listado de pacientes
            </a>

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
    <!-- JAVASCRIPT DEL BUSCADOR -->
    <!-- ================================= -->

    <script>

        // TRABAJOS OBTENIDOS DE MYSQL

        const trabajosRegistrados = <?php echo json_encode(
            $trabajos,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ); ?>;

        // ELEMENTOS

        const buscador = document.getElementById(
            "buscar_trabajo"
        );

        const formulario = document.getElementById(
            "formularioTrabajo"
        );

        const mensajeBusqueda = document.getElementById(
            "mensaje_busqueda"
        );

        const contador = document.getElementById(
            "cantidad_encontrados"
        );

        const filas = document.querySelectorAll(
            ".fila_trabajo"
        );

        const sinResultados = document.getElementById(
            "sin_resultados"
        );

        // =================================
        // NORMALIZAR NOMBRES
        // =================================

        function normalizar(texto) {

            return String(texto)
                .trim()
                .replace(/\s+/g, " ")
                .toLocaleLowerCase("es");

        }

        // =================================
        // BUSCAR TRABAJO EXACTO
        // =================================

        function buscarTrabajoExacto(nombre) {

            const nombreNormalizado = normalizar(
                nombre
            );

            return trabajosRegistrados.find(
                trabajo =>
                    normalizar(trabajo.nombre_t) ===
                    nombreNormalizado
            ) || null;

        }

        // =================================
        // FILTRAR TRABAJOS
        // =================================

        function filtrarTrabajos() {

            const texto = normalizar(
                buscador.value
            );

            let encontrados = 0;

            filas.forEach(function(fila) {

                const nombre = normalizar(
                    fila.dataset.nombre
                );

                const coincide = nombre.includes(
                    texto
                );

                fila.style.display = coincide
                    ? ""
                    : "none";

                if (coincide) {
                    encontrados++;
                }

            });

            contador.textContent = encontrados;

            sinResultados.style.display =
                texto !== "" && encontrados === 0
                ? "block"
                : "none";

            // CAMPO VACÍO

            if (texto === "") {

                mensajeBusqueda.textContent = "";
                return;
            }

            // TRABAJO EXISTENTE

            const trabajoExistente =
                buscarTrabajoExacto(buscador.value);

            if (trabajoExistente) {

                mensajeBusqueda.textContent =
                    "Este trabajo ya está registrado: " +
                    trabajoExistente.nombre_t;

                return;
            }

            // COINCIDENCIAS PARCIALES

            if (encontrados > 0) {

                mensajeBusqueda.textContent =
                    "Se encontraron " +
                    encontrados +
                    " trabajos similares. " +
                    "Si el nombre es nuevo, " +
                    "presioná Enter para registrarlo.";

            } else {

                mensajeBusqueda.textContent =
                    "Este trabajo todavía no existe. " +
                    "Presioná Enter para registrarlo.";

            }
        }

        // =================================
        // BUSCAR MIENTRAS SE ESCRIBE
        // =================================

        buscador.addEventListener(
            "input",
            filtrarTrabajos
        );

        // =================================
        // GUARDAR AL PRESIONAR ENTER
        // =================================

        formulario.addEventListener(
            "submit",
            function(evento) {

                const nombre = buscador.value
                    .trim()
                    .replace(/\s+/g, " ");

                // VALIDAR CAMPO VACÍO

                if (nombre === "") {

                    evento.preventDefault();

                    mensajeBusqueda.textContent =
                        "Escribí un nombre de trabajo.";

                    return;
                }

                // EVITAR DUPLICADOS

                const existente =
                    buscarTrabajoExacto(nombre);

                if (existente) {

                    evento.preventDefault();

                    mensajeBusqueda.textContent =
                        "El trabajo " +
                        existente.nombre_t +
                        " ya está registrado.";

                    return;
                }

                // CONFIRMAR TRABAJO NUEVO

                const confirmado = confirm(
                    '¿Querés registrar el trabajo "' +
                    nombre +
                    '"?'
                );

                if (!confirmado) {

                    evento.preventDefault();
                    return;
                }

                buscador.value = nombre;
            }
        );

    </script>

    <script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>
</html>

<?php
mysqli_close($conexion);
?>
