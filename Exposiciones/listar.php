
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. INICIAR SESIÓN
// =========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token de seguridad para registrar exposiciones

if (empty($_SESSION["token_exposiciones"])) {
    $_SESSION["token_exposiciones"] = bin2hex(
        random_bytes(32)
    );
}

// =========================================
// 2. FUNCIONES
// =========================================

// Mostrar texto de manera segura

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Eliminar espacios innecesarios

function limpiarNombre($nombre) {

    $nombre = trim((string)$nombre);

    return preg_replace(
        '/\s+/u',
        ' ',
        $nombre
    );
}

// Comparar nombres sin importar mayúsculas

function normalizarNombre($nombre) {

    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

// Volver al listado con un mensaje

function volverAlListado($mensaje) {

    header(
        "Location: listar.php?mensaje=" .
        urlencode($mensaje)
    );

    exit;
}

// =========================================
// 3. REGISTRAR EXPOSICIÓN NUEVA
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // VALIDAR TOKEN

    $token = $_POST["token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["token_exposiciones"],
            $token
        )
    ) {

        http_response_code(403);

        die("Solicitud no válida.");
    }

    // RECIBIR NOMBRE

    $nombre = limpiarNombre(
        $_POST["nombre_ex"] ?? ""
    );

    // VALIDAR QUE NO ESTÉ VACÍO

    if ($nombre === "") {

        volverAlListado(
            "Tenés que escribir el nombre de la exposición."
        );
    }

    // VALIDAR LONGITUD

    if (mb_strlen($nombre, "UTF-8") > 50) {

        volverAlListado(
            "El nombre no puede superar los 50 caracteres."
        );
    }

    // =====================================
    // 4. VERIFICAR SI YA EXISTE
    // =====================================

    $sql = "SELECT id_exposicion, nombre_ex
            FROM exposicion";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {

        error_log(mysqli_error($conexion));

        die("No se pudieron consultar las exposiciones.");
    }

    $duplicado = false;

    while ($fila = mysqli_fetch_assoc($resultado)) {

        if (
            normalizarNombre($fila["nombre_ex"]) ===
            normalizarNombre($nombre)
        ) {

            $duplicado = true;
            break;
        }
    }

    mysqli_free_result($resultado);

    // NO REGISTRAR DUPLICADOS

    if ($duplicado) {

        volverAlListado(
            "Esa exposición ya está registrada."
        );
    }

    // =====================================
    // 5. GUARDAR EXPOSICIÓN EN MYSQL
    // =====================================

    $sql = "INSERT INTO exposicion (nombre_ex)
            VALUES (?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {

        error_log(mysqli_error($conexion));

        die("No se pudo preparar el registro.");
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

        if ($codigoError == 1062) {

            volverAlListado(
                "Esa exposición ya está registrada."
            );
        }

        error_log($mensajeError);

        die("No se pudo registrar la exposición.");
    }

    mysqli_stmt_close($stmt);

    // REGISTRO CORRECTO

    volverAlListado(
        "Exposición registrada correctamente: " .
        $nombre
    );
}

// =========================================
// 6. CONSULTAR EXPOSICIONES
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

$exposiciones = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

$cantidad = count($exposiciones);

// =========================================
// 7. MENSAJE DE RESULTADO
// =========================================

$mensaje = "";

if (
    isset($_GET["mensaje"]) &&
    is_string($_GET["mensaje"])
) {

    $mensaje = $_GET["mensaje"];
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Administrar exposiciones</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .buscador-exposicion {
            width: 100%;
            max-width: 450px;
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

        .acciones {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-editar,
        .btn-eliminar {
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

        .texto-ayuda {
            font-size: 14px;
            margin-top: 10px;
        }

        .sin-resultados {
            display: none;
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

    <!-- ================================= -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ================================= -->

    <main class="gestion-contenido">

        <section class="bienvenida">

            <h1>Administrar exposiciones</h1>

            <p>
                Buscá, registrá y mantené actualizado
                el catálogo de exposiciones.
            </p>

        </section>

        <!-- ================================= -->
        <!-- MENSAJES -->
        <!-- ================================= -->

        <?php if ($mensaje !== "") { ?>

            <section class="tarjeta">

                <p>
                    <?php echo escapar($mensaje); ?>
                </p>

            </section>

        <?php } ?>

        <!-- ================================= -->
        <!-- BUSCAR O REGISTRAR EXPOSICIÓN -->
        <!-- ================================= -->

        <section class="tarjeta">

            <h2>Buscar exposición</h2>

            <p>
                Escribí el nombre de una exposición
                para encontrarla en el listado.
            </p>

            <p class="texto-ayuda">
                Si no está registrada, escribí
                su nombre completo y presioná Enter
                para agregarla.
            </p>

            <br>

            <form
                action="listar.php"
                method="POST"
                id="formularioExposicion"
            >

                <!-- TOKEN DE SEGURIDAD -->

                <input
                    type="hidden"
                    name="token"
                    value="<?php
                        echo escapar(
                            $_SESSION["token_exposiciones"]
                        );
                    ?>"
                >

                <!-- BUSCADOR -->

                <input
                    type="text"
                    name="nombre_ex"
                    id="buscar_exposicion"
                    class="buscador-exposicion"
                    maxlength="50"
                    placeholder="Buscar o escribir exposición..."
                    autocomplete="off"
                    aria-label="Buscar o registrar exposición"
                >

            </form>

            <p
                id="mensaje_busqueda"
                class="mensaje-busqueda"
                aria-live="polite"
            ></p>

        </section>

        <!-- ================================= -->
        <!-- LISTADO DE EXPOSICIONES -->
        <!-- ================================= -->

        <section class="tarjeta">

            <h2>Exposiciones registradas</h2>

            <p>
                Cantidad de exposiciones:
                <strong>
                    <?php echo $cantidad; ?>
                </strong>
            </p>

            <p>
                Exposiciones encontradas:
                <strong id="cantidad_encontradas">
                    <?php echo $cantidad; ?>
                </strong>
            </p>

            <br>

            <?php if ($cantidad === 0) { ?>

                <p>
                    Todavía no hay exposiciones
                    registradas.
                </p>

            <?php } else { ?>

                <div class="tabla-contenedor">

                    <table class="tabla-gestion">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Nombre de la exposición</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($exposiciones as $exposicion) { ?>

                                <tr
                                    class="fila_exposicion"
                                    data-nombre="<?php
                                        echo escapar(
                                            $exposicion["nombre_ex"]
                                        );
                                    ?>"
                                >

                                    <!-- ID -->

                                    <td>
                                        <?php
                                            echo (int)$exposicion["id_exposicion"];
                                        ?>
                                    </td>

                                    <!-- NOMBRE -->

                                    <td>
                                        <?php
                                            echo escapar(
                                                $exposicion["nombre_ex"]
                                            );
                                        ?>
                                    </td>

                                    <!-- ACCIONES -->

                                    <td>

                                        <div class="acciones">

                                            <!-- EDITAR -->

                                            <a
                                                class="btn-editar"
                                                href="editar.php?id=<?php
                                                    echo (int)$exposicion["id_exposicion"];
                                                ?>"
                                            >
                                                Editar
                                            </a>

                                            <!-- ELIMINAR -->

                                            <a
                                                class="btn-eliminar"
                                                href="eliminar.php?id=<?php
                                                    echo (int)$exposicion["id_exposicion"];
                                                ?>"
                                                onclick="return confirm(
                                                    '¿Seguro que querés eliminar esta exposición?'
                                                );"
                                            >
                                                Eliminar
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            <?php } ?>

            <p
                id="sin_resultados"
                class="sin-resultados"
            >
                No se encontraron exposiciones
                con ese nombre.
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

        <span>Administración de exposiciones</span>

    </footer>

    <!-- ================================= -->
    <!-- JAVASCRIPT -->
    <!-- ================================= -->

    <script>

        // =================================
        // EXPOSICIONES DESDE MYSQL
        // =================================

        const exposicionesRegistradas = <?php echo json_encode(
            $exposiciones,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        ); ?>;

        // =================================
        // ELEMENTOS
        // =================================

        const buscador = document.getElementById(
            "buscar_exposicion"
        );

        const formulario = document.getElementById(
            "formularioExposicion"
        );

        const mensajeBusqueda = document.getElementById(
            "mensaje_busqueda"
        );

        const contador = document.getElementById(
            "cantidad_encontradas"
        );

        const filas = document.querySelectorAll(
            ".fila_exposicion"
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
        // BUSCAR EXPOSICIÓN EXACTA
        // =================================

        function buscarExposicionExacta(nombre) {

            const texto = normalizar(nombre);

            return exposicionesRegistradas.find(
                exposicion =>
                    normalizar(exposicion.nombre_ex) ===
                    texto
            ) || null;

        }

        // =================================
        // FILTRAR EXPOSICIONES
        // =================================

        function filtrarExposiciones() {

            const texto = normalizar(
                buscador.value
            );

            let encontradas = 0;

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
                    encontradas++;
                }

            });

            contador.textContent = encontradas;

            // MOSTRAR MENSAJE SIN RESULTADOS

            sinResultados.style.display =
                texto !== "" && encontradas === 0
                ? "block"
                : "none";

            // CAMPO VACÍO

            if (texto === "") {

                mensajeBusqueda.textContent = "";
                return;
            }

            // EXPOSICIÓN EXISTENTE

            const existente = buscarExposicionExacta(
                buscador.value
            );

            if (existente) {

                mensajeBusqueda.textContent =
                    "Esta exposición ya está registrada: " +
                    existente.nombre_ex;

                return;
            }

            // SIMILARES

            if (encontradas > 0) {

                mensajeBusqueda.textContent =
                    "Se encontraron " +
                    encontradas +
                    " exposiciones similares. " +
                    "Si el nombre es nuevo, " +
                    "presioná Enter para registrarlo.";

            } else {

                mensajeBusqueda.textContent =
                    "Esta exposición todavía no existe. " +
                    "Presioná Enter para registrarla.";

            }
        }

        // =================================
        // BUSCAR MIENTRAS ESCRIBE
        // =================================

        buscador.addEventListener(
            "input",
            filtrarExposiciones
        );

        // =================================
        // REGISTRAR AL PRESIONAR ENTER
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
                        "Escribí el nombre de la exposición.";

                    return;
                }

                // EVITAR DUPLICADOS

                const existente = buscarExposicionExacta(
                    nombre
                );

                if (existente) {

                    evento.preventDefault();

                    mensajeBusqueda.textContent =
                        "La exposición " +
                        existente.nombre_ex +
                        " ya está registrada.";

                    return;
                }

                // CONFIRMAR NUEVA EXPOSICIÓN

                const confirmado = confirm(
                    '¿Querés registrar la exposición "' +
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
