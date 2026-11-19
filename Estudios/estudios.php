
<?php

// =========================================
// DUCCA - ADMINISTRAR ESTUDIOS
// Un solo buscador para agregar y buscar
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
mysqli_set_charset($conexion, "utf8mb4");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// =========================================
// FUNCIONES
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function limpiarNombre($nombre) {
    return trim(
        preg_replace('/\s+/u', ' ', (string)$nombre)
    );
}

function normalizarNombre($nombre) {
    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

function redirigir($mensaje) {
    header(
        "Location: estudios.php?mensaje=" .
        urlencode($mensaje)
    );
    exit;
}

function obtenerId($valor) {
    $id = filter_var($valor, FILTER_VALIDATE_INT);

    return ($id !== false && $id > 0)
        ? $id
        : null;
}

// =========================================
// SEGURIDAD CSRF
// =========================================

if (empty($_SESSION["token_estudios"])) {
    $_SESSION["token_estudios"] =
        bin2hex(random_bytes(32));
}

$tokenEstudios = $_SESSION["token_estudios"];

// =========================================
// PROCESAR FORMULARIOS
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenRecibido = (string)(
        $_POST["token"] ?? ""
    );

    if (
        $tokenRecibido === "" ||
        !hash_equals($tokenEstudios, $tokenRecibido)
    ) {
        http_response_code(403);
        exit("Solicitud no válida. Volvé a Estudios.");
    }

    $accion = (string)($_POST["accion"] ?? "");

    try {

        // =====================================
        // AGREGAR ESTUDIO
        // =====================================

        if ($accion === "agregar") {

            $nombre = limpiarNombre(
                $_POST["nombre_es"] ?? ""
            );

            if (
                $nombre === "" ||
                mb_strlen($nombre, "UTF-8") > 50
            ) {
                redirigir(
                    "Ingresá un nombre válido de hasta 50 caracteres."
                );
            }

            // Evitar estudios repetidos.

            $resultado = mysqli_query(
                $conexion,
                "SELECT nombre_es FROM estudio"
            );

            while ($fila = mysqli_fetch_assoc($resultado)) {

                if (
                    normalizarNombre($fila["nombre_es"]) ===
                    normalizarNombre($nombre)
                ) {
                    mysqli_free_result($resultado);

                    redirigir(
                        "Ese estudio ya está registrado."
                    );
                }
            }

            mysqli_free_result($resultado);

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO estudio (nombre_es)
                 VALUES (?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $nombre
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            redirigir("Estudio agregado correctamente.");
        }

        // =====================================
        // EDITAR ESTUDIO
        // =====================================

        if ($accion === "editar") {

            $idEstudio = obtenerId(
                $_POST["id_estudio"] ?? null
            );

            $nombre = limpiarNombre(
                $_POST["nombre_es"] ?? ""
            );

            if (!$idEstudio) {
                redirigir(
                    "El estudio seleccionado no es válido."
                );
            }

            if (
                $nombre === "" ||
                mb_strlen($nombre, "UTF-8") > 50
            ) {
                redirigir(
                    "Ingresá un nombre válido de hasta 50 caracteres."
                );
            }

            // Evitar nombres duplicados.

            $resultado = mysqli_query(
                $conexion,
                "SELECT id_estudio, nombre_es
                 FROM estudio"
            );

            while ($fila = mysqli_fetch_assoc($resultado)) {

                if (
                    (int)$fila["id_estudio"] !== $idEstudio &&
                    normalizarNombre($fila["nombre_es"]) ===
                    normalizarNombre($nombre)
                ) {
                    mysqli_free_result($resultado);

                    redirigir(
                        "Ya existe otro estudio con ese nombre."
                    );
                }
            }

            mysqli_free_result($resultado);

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE estudio
                 SET nombre_es = ?
                 WHERE id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $nombre,
                $idEstudio
            );

            mysqli_stmt_execute($stmt);

            $filasAfectadas = mysqli_stmt_affected_rows($stmt);

            mysqli_stmt_close($stmt);

            redirigir(
                $filasAfectadas > 0
                    ? "Estudio actualizado correctamente."
                    : "No hubo cambios en el estudio."
            );
        }

        // =====================================
        // ELIMINAR ESTUDIO
        // =====================================

        if ($accion === "eliminar") {

            $idEstudio = obtenerId(
                $_POST["id_estudio"] ?? null
            );

            if (!$idEstudio) {
                redirigir(
                    "El estudio seleccionado no es válido."
                );
            }

            mysqli_begin_transaction($conexion);

            try {

                // Bloquear el estudio mientras
                // se comprueban sus relaciones.

                $stmt = mysqli_prepare(
                    $conexion,
                    "SELECT id_estudio
                     FROM estudio
                     WHERE id_estudio = ?
                     FOR UPDATE"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $idEstudio
                );

                mysqli_stmt_execute($stmt);

                $resultado = mysqli_stmt_get_result($stmt);
                $existe = mysqli_fetch_assoc($resultado);

                mysqli_stmt_close($stmt);

                if (!$existe) {
                    mysqli_rollback($conexion);
                    redirigir("El estudio no existe.");
                }

                // Comprobar si está asociado
                // a pacientes, trabajos o historiales.

                $tablas = [
                    "paciente_estudio",
                    "trabajo_estudio",
                    "historial_medico"
                ];

                $tieneRelaciones = false;

                foreach ($tablas as $tabla) {

                    $stmt = mysqli_prepare(
                        $conexion,
                        "SELECT COUNT(*) AS total
                         FROM $tabla
                         WHERE id_estudio = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "i",
                        $idEstudio
                    );

                    mysqli_stmt_execute($stmt);

                    $resultado = mysqli_stmt_get_result($stmt);
                    $fila = mysqli_fetch_assoc($resultado);

                    mysqli_stmt_close($stmt);

                    if ((int)$fila["total"] > 0) {
                        $tieneRelaciones = true;
                        break;
                    }
                }

                if ($tieneRelaciones) {

                    mysqli_rollback($conexion);

                    redirigir(
                        "No se puede eliminar este estudio " .
                        "porque tiene relaciones o registros médicos."
                    );
                }

                $stmt = mysqli_prepare(
                    $conexion,
                    "DELETE FROM estudio
                     WHERE id_estudio = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $idEstudio
                );

                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                mysqli_commit($conexion);

                redirigir(
                    "Estudio eliminado correctamente."
                );

            } catch (Throwable $e) {

                mysqli_rollback($conexion);
                throw $e;
            }
        }

        http_response_code(400);
        exit("Acción no válida.");

    } catch (Throwable $e) {

        error_log(
            "DUCCA - Error Estudios: " .
            $e->getMessage()
        );

        redirigir(
            "No se pudo completar la operación. " .
            "Verificá los datos e intentá nuevamente."
        );
    }
}

// =========================================
// CONSULTAR ESTUDIOS REGISTRADOS
// =========================================

try {

    $resultado = mysqli_query(
        $conexion,
        "SELECT id_estudio, nombre_es
         FROM estudio
         ORDER BY nombre_es ASC"
    );

    $catalogo = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_free_result($resultado);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    exit("No se pudieron consultar los estudios.");
}

$mensaje = "";

if (
    isset($_GET["mensaje"]) &&
    is_string($_GET["mensaje"])
) {
    $mensaje = mb_substr(
        $_GET["mensaje"],
        0,
        200,
        "UTF-8"
    );
}

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

    <title>Estudios - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        /* =====================================
           BUSCADOR ÚNICO
        ===================================== */

        .contenedor-buscador-estudios {
            width: 100%;
            max-width: 650px;
        }

        .buscador-estudio {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            font-size: 16px;
        }

        .ayuda-estudio {
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.85;
        }

        .sugerencias-estudios {
            display: none;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            margin-top: 6px;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .sugerencia-estudio {
            display: block;
            width: 100%;
            padding: 11px 12px;
            border: 0;
            border-bottom: 1px solid #ddd;
            background: transparent;
            color: inherit;
            font-family: inherit;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }

        .sugerencia-estudio:hover {
            background: rgba(180, 120, 150, 0.14);
        }

        .sugerencia-estudio:last-child {
            border-bottom: none;
        }

        .mensaje-resultado {
            padding: 13px;
            border-radius: 8px;
            background: rgba(40, 132, 91, 0.10);
            border: 1px solid #8dbca3;
            margin-bottom: 15px;
        }

        /* =====================================
           TABLA DE ESTUDIOS
        ===================================== */

        .tabla-contenedor {
            overflow-x: auto;
            margin-top: 15px;
        }

        .tabla-gestion {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-gestion th,
        .tabla-gestion td {
            border: 1px solid #ccd6db;
            padding: 12px;
            text-align: left;
            vertical-align: middle;
        }

        .campo-editar {
            width: 100%;
            min-width: 170px;
            box-sizing: border-box;
            padding: 9px;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            font-size: 15px;
        }

        .acciones-estudio {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .acciones-estudio form {
            margin: 0;
        }

        .btn-guardar,
        .btn-eliminar-estudio {
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
        }

        .btn-guardar {
            background: #28845b;
        }

        .btn-guardar:hover {
            background: #1e6545;
        }

        .btn-eliminar-estudio {
            background: #c94b4b;
        }

        .btn-eliminar-estudio:hover {
            background: #aa3535;
        }

        .sin-resultados {
            display: none;
            margin-top: 15px;
        }

        .fila-destacada {
            outline: 2px solid #28845b;
            outline-offset: -2px;
        }

        @media (max-width: 600px) {

            .tabla-gestion th,
            .tabla-gestion td {
                padding: 8px;
            }

            .acciones-estudio {
                flex-direction: column;
                align-items: stretch;
            }

        }

    </style>

</head>

<body>

<!-- =====================================
     ENCABEZADO
===================================== -->

<header>

    <a
        class="flecha-volver"
        href="../inicio%20sesion/index.php"
        aria-label="Volver al menú"
    >
        <img
            src="../general/flechita.png"
            alt="Volver"
            width="30"
            height="30"
        >
    </a>

    <div class="logo">

        <img
            id="logo"
            src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
            alt="Logo DUCCA"
            height="150"
            width="175"
        >

    </div>

    <nav>

        <a href="../inicio%20sesion/index.php">
            Menú principal
        </a>

        <a href="../Pacientes/listar.php">
            Pacientes
        </a>

        <a href="../Exposiciones/listar.php">
            Exposiciones
        </a>

        <a href="../Trabajos/listar.php">
            Trabajos
        </a>

        <a href="../Relaciones/listar.php">
            Relaciones
        </a>

    </nav>

    <button type="button" id="btn-tema">
        Modo oscuro
    </button>

</header>

<!-- =====================================
     CONTENIDO
===================================== -->

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Administrar estudios</h1>

        <p>
            Buscá, agregá, editá y eliminá los estudios
            del catálogo general de DUCCA.
        </p>

    </section>

    <?php if ($mensaje !== "") { ?>

        <section class="tarjeta">

            <div class="mensaje-resultado">
                <?php echo escapar($mensaje); ?>
            </div>

        </section>

    <?php } ?>

    <!-- =====================================
         ÚNICA BARRA PARA BUSCAR Y AGREGAR
    ===================================== -->

    <section class="tarjeta">

        <h2>Buscar o agregar estudio</h2>

        <p>
            Escribí el nombre de un estudio.
            Los similares aparecerán automáticamente.
            Si no existe, presioná Enter para registrarlo.
        </p>

        <div class="contenedor-buscador-estudios">

            <form
                id="form-estudio"
                action="estudios.php"
                method="POST"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo escapar($tokenEstudios); ?>"
                >

                <input
                    type="hidden"
                    name="accion"
                    value="agregar"
                >

                <input
                    type="text"
                    id="buscar_estudio"
                    name="nombre_es"
                    class="buscador-estudio"
                    maxlength="50"
                    placeholder="Buscar o escribir nuevo estudio..."
                    autocomplete="off"
                    required
                >

            </form>

            <div
                id="sugerencias_estudios"
                class="sugerencias-estudios"
                aria-label="Estudios similares"
            ></div>

            <p
                id="mensaje_buscador"
                class="ayuda-estudio"
                aria-live="polite"
            ></p>

        </div>

    </section>

    <!-- =====================================
         ESTUDIOS REGISTRADOS
    ===================================== -->

    <section class="tarjeta">

        <h2>Estudios registrados</h2>

        <p>
            Total de estudios:

            <strong>
                <?php echo count($catalogo); ?>
            </strong>
        </p>

        <p>
            Resultados encontrados:

            <strong id="cantidad_encontrados">
                <?php echo count($catalogo); ?>
            </strong>
        </p>

        <?php if (!$catalogo) { ?>

            <p>
                Todavía no hay estudios registrados.
                Escribí el nombre de uno arriba
                y presioná Enter para agregarlo.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Estudio</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($catalogo as $estudio) { ?>

                            <?php
                                $id = (int)$estudio["id_estudio"];
                                $nombre = (string)$estudio["nombre_es"];

                                $idFormulario = "editar_estudio_" . $id;
                            ?>

                            <tr
                                class="fila-estudio"
                                data-id="<?php echo $id; ?>"
                                data-nombre="<?php echo escapar($nombre); ?>"
                            >

                                <td>

                                    <form
                                        id="<?php echo escapar($idFormulario); ?>"
                                        action="estudios.php"
                                        method="POST"
                                    >

                                        <input
                                            type="hidden"
                                            name="token"
                                            value="<?php echo escapar($tokenEstudios); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="editar"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_estudio"
                                            value="<?php echo $id; ?>"
                                        >

                                        <input
                                            class="campo-editar"
                                            type="text"
                                            name="nombre_es"
                                            value="<?php echo escapar($nombre); ?>"
                                            maxlength="50"
                                            aria-label="Editar nombre del estudio"
                                            required
                                        >

                                    </form>

                                </td>

                                <td>

                                    <div class="acciones-estudio">

                                        <button
                                            type="submit"
                                            form="<?php echo escapar($idFormulario); ?>"
                                            class="btn-guardar"
                                        >
                                            Guardar cambios
                                        </button>

                                        <form
                                            action="estudios.php"
                                            method="POST"
                                            onsubmit="return confirm('¿Seguro que querés eliminar este estudio? No se eliminará si tiene relaciones o registros médicos.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="token"
                                                value="<?php echo escapar($tokenEstudios); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="eliminar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_estudio"
                                                value="<?php echo $id; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-eliminar-estudio"
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
            >
                No se encontraron estudios con ese nombre.
            </p>

        <?php } ?>

        <p class="ayuda-estudio">
            Los estudios que tengan relaciones
            con pacientes, trabajos o registros médicos
            no podrán eliminarse.
        </p>

    </section>

    <!-- =====================================
         VOLVER
    ===================================== -->

    <section class="tarjeta">

        <a
            href="../inicio%20sesion/index.php"
            class="btn-volver-pacientes"
        >
            Volver al menú principal
        </a>

    </section>

</main>

<footer>
    <span>DUCCA</span>
    <span>Administración de estudios</span>
</footer>

<!-- =====================================
     BUSCADOR INTELIGENTE
===================================== -->

<script>

// Estudios existentes en MySQL.

const estudios = <?php echo json_encode(
    $catalogo,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
); ?>;

const buscador = document.getElementById(
    "buscar_estudio"
);

const formulario = document.getElementById(
    "form-estudio"
);

const sugerencias = document.getElementById(
    "sugerencias_estudios"
);

const mensaje = document.getElementById(
    "mensaje_buscador"
);

const filas = document.querySelectorAll(
    ".fila-estudio"
);

const contador = document.getElementById(
    "cantidad_encontrados"
);

const sinResultados = document.getElementById(
    "sin_resultados"
);

// =====================================
// NORMALIZAR TEXTO
// =====================================

function normalizar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

// =====================================
// BUSCAR Y FILTRAR
// =====================================

function actualizarBusqueda() {

    const texto = normalizar(buscador.value);

    let encontrados = 0;

    filas.forEach(fila => {

        const nombre = normalizar(
            fila.dataset.nombre
        );

        const coincide = nombre.includes(texto);

        fila.style.display = coincide ? "" : "none";

        if (coincide) {
            encontrados++;
        }

        fila.classList.remove("fila-destacada");
    });

    if (contador) {
        contador.textContent = encontrados;
    }

    if (sinResultados) {
        sinResultados.style.display =
            texto !== "" && encontrados === 0
                ? "block"
                : "none";
    }

    sugerencias.replaceChildren();
    sugerencias.style.display = "none";

    if (!texto) {
        mensaje.textContent =
            "Escribí para buscar. Presioná Enter para agregar un estudio nuevo.";

        return;
    }

    // Estudios similares.

    const similares = estudios.filter(estudio =>
        normalizar(estudio.nombre_es).includes(texto)
    );

    const exacto = estudios.find(estudio =>
        normalizar(estudio.nombre_es) === texto
    );

    // Mostrar hasta 8 sugerencias.

    similares.slice(0, 8).forEach(estudio => {

        const boton = document.createElement(
            "button"
        );

        boton.type = "button";
        boton.className = "sugerencia-estudio";

        boton.textContent = estudio.nombre_es;

        boton.addEventListener("click", () => {
            seleccionarEstudio(estudio);
        });

        sugerencias.appendChild(boton);
    });

    if (similares.length > 0) {
        sugerencias.style.display = "block";
    }

    if (exacto) {

        mensaje.textContent =
            "Este estudio ya existe. Presioná Enter para seleccionarlo.";

    } else if (similares.length > 0) {

        mensaje.textContent =
            "Hay estudios similares. Elegí uno o presioná Enter para agregar uno nuevo.";

    } else {

        mensaje.textContent =
            "Estudio nuevo. Presioná Enter para guardarlo automáticamente.";
    }
}

// =====================================
// SELECCIONAR ESTUDIO EXISTENTE
// =====================================

function seleccionarEstudio(estudio) {

    buscador.value = estudio.nombre_es;

    actualizarBusqueda();

    sugerencias.style.display = "none";

    const fila = Array.from(filas).find(
        elemento =>
            Number(elemento.dataset.id) ===
            Number(estudio.id_estudio)
    );

    if (fila) {

        fila.classList.add("fila-destacada");

        fila.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }

    mensaje.textContent =
        "Estudio seleccionado. Podés editarlo o eliminarlo desde el listado.";
}

// =====================================
// ESCRIBIR EN EL BUSCADOR
// =====================================

buscador.addEventListener(
    "input",
    actualizarBusqueda
);

// =====================================
// ENTER: AGREGAR O SELECCIONAR
// =====================================

formulario.addEventListener(
    "submit",
    function(evento) {

        const nombre = buscador.value
            .trim()
            .replace(/\s+/g, " ");

        if (!nombre) {
            evento.preventDefault();
            mensaje.textContent =
                "Ingresá el nombre de un estudio.";
            return;
        }

        const exacto = estudios.find(
            estudio =>
                normalizar(estudio.nombre_es) ===
                normalizar(nombre)
        );

        if (exacto) {

            // Ya existe: no enviamos el formulario
            // y no generamos duplicados.

            evento.preventDefault();

            seleccionarEstudio(exacto);

            return;
        }

        // No existe: enviar el formulario
        // para guardarlo en MySQL.

        buscador.value = nombre;
    }
);

// =====================================
// CERRAR SUGERENCIAS AL HACER CLIC FUERA
// =====================================

document.addEventListener(
    "click",
    function(evento) {

        if (
            evento.target !== buscador &&
            !sugerencias.contains(evento.target)
        ) {
            sugerencias.style.display = "none";
        }
    }
);

// Inicializar.

actualizarBusqueda();

</script>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>
