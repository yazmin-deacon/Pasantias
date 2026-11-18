
<?php

// =========================================
// DUCCA - ADMINISTRAR EXPOSICIONES
// =========================================

// VERIFICAR INICIO DE SESIÓN

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

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

function limpiar($valor) {
    return trim(
        preg_replace('/\s+/u', ' ', (string)$valor)
    );
}

function normalizar($valor) {
    return mb_strtolower(
        limpiar($valor),
        "UTF-8"
    );
}

function consultar($conexion, $sql, $dni) {
    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $dni
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    $filas = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt);

    return $filas;
}

// =========================================
// VALIDAR MÉTODO Y DNI
// =========================================

$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo !== "GET" && $metodo !== "POST") {
    http_response_code(405);
    header("Allow: GET, POST");
    exit("Método no permitido.");
}

$dniTexto = (string)(
    $metodo === "POST"
        ? ($_POST["dni_p"] ?? "")
        : ($_GET["dni_p"] ?? "")
);

if (
    !ctype_digit($dniTexto) ||
    strlen($dniTexto) > 8 ||
    (int)$dniTexto <= 0
) {
    http_response_code(400);
    exit("DNI no válido.");
}

$dni = (int)$dniTexto;

// =========================================
// BUSCAR PACIENTE
// =========================================

$pacientes = consultar(
    $conexion,
    "SELECT
        dni_p,
        nombre_p,
        apellido,
        estado
     FROM paciente
     WHERE dni_p = ?",
    $dni
);

if (!$pacientes) {
    http_response_code(404);
    exit("No se encontró el paciente.");
}

$paciente = $pacientes[0];

// =========================================
// BLOQUEAR PACIENTES ARCHIVADOS
// =========================================

if ($paciente["estado"] !== "activo") {
    http_response_code(403);

    exit(
        "Este paciente está archivado. " .
        "Sus exposiciones son de solo lectura. " .
        "Para modificarlas, primero debés reactivarlo."
    );
}

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

if (empty($_SESSION["csrf_exposiciones_paciente"])) {
    $_SESSION["csrf_exposiciones_paciente"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_exposiciones_paciente"];

$error = "";

// =========================================
// PROCESAR ACCIONES
// =========================================

if ($metodo === "POST") {

    $tokenEnviado = (string)(
        $_POST["csrf"] ?? ""
    );

    if (!hash_equals($token, $tokenEnviado)) {
        http_response_code(403);
        exit("Solicitud no válida.");
    }

    $accion = (string)(
        $_POST["accion"] ?? ""
    );

    $transaccionIniciada = false;

    try {

        mysqli_begin_transaction($conexion);
        $transaccionIniciada = true;

        // =================================
        // VOLVER A COMPROBAR EL ESTADO
        // =================================

        // Bloquear la fila del paciente
        // durante toda la operación.

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT estado
             FROM paciente
             WHERE dni_p = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $dni
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $pacienteActual = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (
            !$pacienteActual ||
            $pacienteActual["estado"] !== "activo"
        ) {
            throw new RuntimeException(
                "El paciente está archivado o ya no existe.",
                403
            );
        }

        // =================================
        // ACCIÓN: AGREGAR EXPOSICIÓN
        // =================================

        if ($accion === "agregar") {

            $nombre = limpiar(
                $_POST["nombre_exposicion"] ?? ""
            );

            if ($nombre === "") {
                throw new Exception(
                    "Ingresá el nombre de una exposición."
                );
            }

            if (mb_strlen($nombre, "UTF-8") > 50) {
                throw new Exception(
                    "El nombre no puede superar 50 caracteres."
                );
            }

            // Buscar si la exposición
            // ya existe en el catálogo.

            $resultado = mysqli_query(
                $conexion,
                "SELECT
                    id_exposicion,
                    nombre_ex
                 FROM exposicion
                 FOR UPDATE"
            );

            $idExposicion = null;

            while (
                $fila = mysqli_fetch_assoc($resultado)
            ) {

                if (
                    normalizar($fila["nombre_ex"]) ===
                    normalizar($nombre)
                ) {

                    $idExposicion =
                        (int)$fila["id_exposicion"];

                    break;
                }
            }

            mysqli_free_result($resultado);

            // =================================
            // CREAR EXPOSICIÓN SI NO EXISTE
            // =================================

            if ($idExposicion === null) {

                $stmt = mysqli_prepare(
                    $conexion,
                    "INSERT INTO exposicion
                        (nombre_ex)
                     VALUES (?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $nombre
                );

                mysqli_stmt_execute($stmt);

                $idExposicion =
                    mysqli_insert_id($conexion);

                mysqli_stmt_close($stmt);
            }

            // =================================
            // ASIGNAR EXPOSICIÓN AL PACIENTE
            // =================================

            // Si ya está asignada,
            // no se vuelve a duplicar.

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT IGNORE INTO paciente_exposicion
                    (dni_paciente, id_exposicion)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $idExposicion
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        // =================================
        // ACCIÓN: QUITAR EXPOSICIÓN
        // =================================

        } elseif ($accion === "quitar") {

            $id = filter_var(
                $_POST["id_exposicion"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                throw new Exception(
                    "Exposición no válida."
                );
            }

            // Quitar únicamente la relación
            // entre el paciente y la exposición.
            //
            // NO borrar la exposición del
            // catálogo general.

            $stmt = mysqli_prepare(
                $conexion,
                "DELETE FROM paciente_exposicion
                 WHERE dni_paciente = ?
                   AND id_exposicion = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } else {

            throw new Exception(
                "Acción desconocida."
            );
        }

        // =================================
        // CONFIRMAR CAMBIOS
        // =================================

        mysqli_commit($conexion);
        $transaccionIniciada = false;

        header(
            "Location: administrar_exposiciones.php?dni_p=" .
            urlencode((string)$dni) .
            "&guardado=1"
        );

        exit;

    } catch (Throwable $e) {

        if ($transaccionIniciada) {
            try {
                mysqli_rollback($conexion);
            } catch (Throwable $errorRollback) {
                error_log(
                    "DUCCA - Error de rollback: " .
                    $errorRollback->getMessage()
                );
            }
        }

        if ((int)$e->getCode() === 403) {
            http_response_code(403);

            exit(
                "Este paciente está archivado. " .
                "No se permite modificar sus exposiciones."
            );
        }

        error_log(
            "DUCCA - Administrar exposiciones: " .
            $e->getMessage()
        );

        if (
            $e instanceof Exception &&
            !($e instanceof mysqli_sql_exception)
        ) {
            $error = $e->getMessage();
        } else {
            $error =
                "No se pudieron guardar los cambios.";
        }
    }
}

// =========================================
// CONSULTAR CATÁLOGO GENERAL
// =========================================

$catalogo = mysqli_query(
    $conexion,
    "SELECT
        id_exposicion,
        nombre_ex
     FROM exposicion
     ORDER BY nombre_ex"
);

// =========================================
// CONSULTAR EXPOSICIONES ASIGNADAS
// =========================================

$asignadas = consultar(
    $conexion,
    "SELECT
        e.id_exposicion,
        e.nombre_ex

     FROM paciente_exposicion pe

     INNER JOIN exposicion e
        ON pe.id_exposicion = e.id_exposicion

     WHERE pe.dni_paciente = ?

     ORDER BY e.nombre_ex",
    $dni
);

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

    <title>Administrar exposiciones - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .campo-exposicion {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            padding: 10px;
        }

        .mensaje-error {
            color: #b12e2e;
            font-weight: 600;
        }

        .mensaje-exito {
            padding: 12px 15px;
            border-radius: 8px;
            background: rgba(70, 160, 100, 0.12);
        }

        .acciones-exposicion {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .acciones-exposicion form {
            display: inline-block;
        }

    </style>

</head>

<body>

<main class="gestion-contenido">

    <!-- =====================================
         ENCABEZADO
    ===================================== -->

    <section class="bienvenida">

        <h1>Administrar exposiciones</h1>

        <p>
            Paciente:

            <strong>
                <?php
                echo escapar(
                    $paciente["nombre_p"] . " " .
                    $paciente["apellido"]
                );
                ?>
            </strong>
        </p>

        <p>
            DNI:
            <?php echo escapar($dni); ?>
        </p>

        <p>
            <a href="historial.php?dni_p=<?php
                echo urlencode((string)$dni);
            ?>">
                Volver al historial
            </a>
        </p>

    </section>

    <!-- =====================================
         AGREGAR EXPOSICIÓN
    ===================================== -->

    <section class="tarjeta">

        <?php if ($error !== "") { ?>

            <p class="mensaje-error">
                <?php echo escapar($error); ?>
            </p>

        <?php } elseif (
            isset($_GET["guardado"]) &&
            $_GET["guardado"] === "1"
        ) { ?>

            <p class="mensaje-exito">
                Los cambios se guardaron correctamente.
            </p>

        <?php } ?>

        <h2>Agregar exposición</h2>

        <p>
            Buscá una exposición existente
            o escribí una nueva para registrarla.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="dni_p"
                value="<?php echo escapar($dni); ?>"
            >

            <input
                type="hidden"
                name="csrf"
                value="<?php echo escapar($token); ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="agregar"
            >

            <p>
                <label for="nombre_exposicion">
                    Nombre de la exposición
                </label>
            </p>

            <input
                type="text"
                id="nombre_exposicion"
                name="nombre_exposicion"
                list="catalogo_exposiciones"
                class="campo-exposicion"
                maxlength="50"
                autocomplete="off"
                required
                placeholder="Buscar o escribir exposición..."
            >

            <datalist id="catalogo_exposiciones">

                <?php while (
                    $fila = mysqli_fetch_assoc($catalogo)
                ) { ?>

                    <option
                        value="<?php
                            echo escapar($fila["nombre_ex"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p>
                <button type="submit">
                    Agregar exposición
                </button>
            </p>

        </form>

        <p>
            <a
                href="../Exposiciones/listar.php"
                target="_blank"
                rel="noopener noreferrer"
            >
                Abrir catálogo general de exposiciones
            </a>
        </p>

    </section>

    <!-- =====================================
         EXPOSICIONES ASIGNADAS
    ===================================== -->

    <section class="tarjeta">

        <h2>Exposiciones asignadas</h2>

        <?php if (!$asignadas) { ?>

            <p>
                El paciente todavía no tiene
                exposiciones asignadas.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Exposición</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $asignadas as $fila
                        ) { ?>

                            <tr>

                                <td>
                                    <?php
                                    echo escapar(
                                        $fila["nombre_ex"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <div class="acciones-exposicion">

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                '¿Quitar esta exposición del paciente?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="dni_p"
                                                value="<?php
                                                    echo escapar($dni);
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?php
                                                    echo escapar($token);
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="quitar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_exposicion"
                                                value="<?php
                                                    echo (int)$fila["id_exposicion"];
                                                ?>"
                                            >

                                            <button type="submit">
                                                Quitar
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

</main>

</body>
</html>
