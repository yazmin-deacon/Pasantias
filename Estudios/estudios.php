
<?php
// =========================================
// DUCCA - CRUD GENERAL DE ESTUDIOS
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

// =========================================
// SEGURIDAD CSRF
// =========================================

if (empty($_SESSION["token_estudios"])) {
    $_SESSION["token_estudios"] =
        bin2hex(random_bytes(32));
}

$tokenEstudios = $_SESSION["token_estudios"];

// =========================================
// PROCESAR ACCIONES DEL CRUD
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

            // Comprobar si ya existe.
            $resultado = mysqli_query(
                $conexion,
                "SELECT nombre_es FROM estudio"
            );

            while ($fila = mysqli_fetch_assoc($resultado)) {
                if (
                    normalizarNombre($fila["nombre_es"]) ===
                    normalizarNombre($nombre)
                ) {
                    redirigir("Ese estudio ya está registrado.");
                }
            }

            mysqli_free_result($resultado);

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO estudio (nombre_es) VALUES (?)"
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

            $idEstudio = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            $nombre = limpiarNombre(
                $_POST["nombre_es"] ?? ""
            );

            if (!$idEstudio || $idEstudio <= 0) {
                redirigir("El estudio seleccionado no es válido.");
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
                "SELECT id_estudio, nombre_es FROM estudio"
            );

            while ($fila = mysqli_fetch_assoc($resultado)) {

                if (
                    (int)$fila["id_estudio"] !== $idEstudio &&
                    normalizarNombre($fila["nombre_es"]) ===
                    normalizarNombre($nombre)
                ) {
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
            mysqli_stmt_close($stmt);

            redirigir("Estudio actualizado correctamente.");
        }

        // =====================================
        // ELIMINAR ESTUDIO
        // =====================================

        if ($accion === "eliminar") {

            $idEstudio = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$idEstudio || $idEstudio <= 0) {
                redirigir("El estudio seleccionado no es válido.");
            }

            mysqli_begin_transaction($conexion);

            try {

                // Bloquear el estudio durante la operación.
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

                // Comprobar relaciones existentes.
                $tablas = [
                    "paciente_estudio",
                    "trabajo_estudio",
                    "historial_medico"
                ];

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
                        mysqli_rollback($conexion);

                        redirigir(
                            "No se puede eliminar este estudio " .
                            "porque tiene relaciones o registros médicos. " .
                            "Primero deben conservarse o resolverse esas referencias."
                        );
                    }
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

                redirigir("Estudio eliminado correctamente.");

            } catch (Throwable $e) {

                mysqli_rollback($conexion);
                throw $e;
            }
        }

        http_response_code(400);
        exit("Acción no válida.");

    } catch (Throwable $e) {

        error_log(
            "DUCCA - Error CRUD Estudios: " .
            $e->getMessage()
        );

        redirigir(
            "No se pudo completar la operación. " .
            "Comprobá que el estudio no tenga relaciones asociadas."
        );
    }
}

// =========================================
// CONSULTAR CATÁLOGO
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
    $mensaje = $_GET["mensaje"];
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

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .buscador-estudio {
            width: 100%;
            max-width: 450px;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-agregar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 15px;
        }

        .form-agregar input {
            flex: 1;
            min-width: 180px;
            padding: 12px;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            font-size: 15px;
        }

        .btn-agregar,
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

        .btn-agregar {
            background: #287b99;
        }

        .btn-agregar:hover {
            background: #195d77;
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

        .mensaje-resultado {
            padding: 13px;
            border-radius: 8px;
            background: rgba(40, 132, 91, 0.10);
            border: 1px solid #8dbca3;
            margin-bottom: 15px;
        }

        .ayuda-estudio {
            margin-top: 10px;
            font-size: 14px;
            opacity: 0.85;
        }

        .sin-resultados {
            display: none;
            margin-top: 15px;
        }
    </style>

</head>

<body>

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

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Administrar estudios</h1>

        <p>
            Agregá, buscá, editá y eliminá
            los estudios del catálogo general de DUCCA.
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
         AGREGAR ESTUDIO
    ===================================== -->

    <section class="tarjeta">

        <h2>Agregar estudio</h2>

        <p>
            Registrá un nuevo tipo de estudio
            para que pueda utilizarse en el sistema.
        </p>

        <form
            action="estudios.php"
            method="POST"
            class="form-agregar"
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
                name="nombre_es"
                maxlength="50"
                placeholder="Nombre del nuevo estudio..."
                required
            >

            <button type="submit" class="btn-agregar">
                Agregar estudio
            </button>

        </form>

    </section>

    <!-- =====================================
         BUSCAR ESTUDIO
    ===================================== -->

    <section class="tarjeta">

        <h2>Buscar estudio</h2>

        <p>
            Escribí el nombre del estudio
            para encontrarlo en el catálogo.
        </p>

        <input
            type="search"
            id="buscar_estudio"
            class="buscador-estudio"
            placeholder="Buscar estudio..."
            autocomplete="off"
        >

        <p class="ayuda-estudio">
            El buscador filtra los estudios registrados
            sin modificar la base de datos.
        </p>

    </section>

    <!-- =====================================
         CATÁLOGO GENERAL
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
                                            onsubmit="return confirm('¿Seguro que querés eliminar este estudio del catálogo? Solo se eliminará si no tiene relaciones ni registros médicos.');"
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

            <p id="sin_resultados" class="sin-resultados">
                No se encontraron estudios con ese nombre.
            </p>

        <?php } ?>

        <p class="ayuda-estudio">
            Para proteger la información clínica,
            los estudios que tengan relaciones o registros
            médicos asociados no podrán eliminarse.
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

<script>

// =========================================
// BUSCADOR EN TIEMPO REAL
// =========================================

const buscador = document.getElementById("buscar_estudio");
const filas = document.querySelectorAll(".fila-estudio");
const contador = document.getElementById("cantidad_encontrados");
const sinResultados = document.getElementById("sin_resultados");

function normalizarBusqueda(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

function filtrarEstudios() {

    const texto = normalizarBusqueda(buscador.value);

    let encontrados = 0;

    filas.forEach(fila => {

        const nombre = normalizarBusqueda(
            fila.dataset.nombre
        );

        const coincide = nombre.includes(texto);

        fila.style.display = coincide ? "" : "none";

        if (coincide) {
            encontrados++;
        }
    });

    if (contador) {
        contador.textContent = encontrados;
    }

    if (sinResultados) {
        sinResultados.style.display =
            encontrados === 0 && filas.length > 0
                ? "block"
                : "none";
    }
}

buscador.addEventListener("input", filtrarEstudios);

</script>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>
