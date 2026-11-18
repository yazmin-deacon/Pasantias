
<?php

// =========================================
// DUCCA - ADMINISTRAR ESTUDIOS DEL PACIENTE
// =========================================

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
    mysqli_stmt_bind_param($stmt, "i", $dni);
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
// VALIDAR DNI
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
// COMPROBAR PACIENTE
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

// También se comprueba durante el POST,
// dentro de una transacción, para evitar
// modificaciones si el estado cambió.

if ($paciente["estado"] !== "activo") {
    http_response_code(403);

    exit(
        "Este paciente está archivado. " .
        "Sus estudios son de solo lectura. " .
        "Para modificarlos, primero debés reactivarlo."
    );
}

// =========================================
// TOKEN CSRF
// =========================================

if (empty($_SESSION["csrf_estudios_paciente"])) {
    $_SESSION["csrf_estudios_paciente"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_estudios_paciente"];

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
        // ACCIÓN: AGREGAR ESTUDIO
        // =================================

        if ($accion === "agregar") {

            $nombre = limpiar(
                $_POST["nombre_estudio"] ?? ""
            );

            if ($nombre === "") {
                throw new Exception(
                    "Ingresá el nombre de un estudio."
                );
            }

            if (
                mb_strlen($nombre, "UTF-8") > 50
            ) {
                throw new Exception(
                    "El nombre no puede superar 50 caracteres."
                );
            }

            // Buscar en el catálogo.

            $resultado = mysqli_query(
                $conexion,
                "SELECT id_estudio, nombre_es
                 FROM estudio
                 FOR UPDATE"
            );

            $idEstudio = null;

            while (
                $fila = mysqli_fetch_assoc($resultado)
            ) {
                if (
                    normalizar($fila["nombre_es"]) ===
                    normalizar($nombre)
                ) {
                    $idEstudio = (int)$fila["id_estudio"];
                    break;
                }
            }

            mysqli_free_result($resultado);

            // Si no existe, crearlo.

            if ($idEstudio === null) {

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

                $idEstudio = mysqli_insert_id($conexion);

                mysqli_stmt_close($stmt);
            }

            // Asignarlo al paciente.
            // No cambiar el estado si ya existe.

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO paciente_estudio
                    (dni_paciente, id_estudio, estado)
                 VALUES (?, ?, 'Pendiente')
                 ON DUPLICATE KEY UPDATE
                    id_estudio = id_estudio"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $idEstudio
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        // =================================
        // ACCIÓN: CAMBIAR ESTADO
        // =================================

        } elseif ($accion === "estado") {

            $id = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            $estado = (string)(
                $_POST["estado"] ?? ""
            );

            if (
                !$id ||
                !in_array(
                    $estado,
                    ["Pendiente", "Realizado"],
                    true
                )
            ) {
                throw new Exception(
                    "Datos del estudio no válidos."
                );
            }

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE paciente_estudio
                 SET estado = ?
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sii",
                $estado,
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        // =================================
        // ACCIÓN: QUITAR ESTUDIO
        // =================================

        } elseif ($accion === "quitar") {

            $id = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                throw new Exception(
                    "Estudio no válido."
                );
            }

            // Comprobar que no tenga registros
            // asociados en el historial.

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT COUNT(*) AS total
                 FROM historial_medico
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);

            $resultado = mysqli_stmt_get_result($stmt);
            $fila = mysqli_fetch_assoc($resultado);

            mysqli_stmt_close($stmt);

            if ((int)$fila["total"] > 0) {
                throw new Exception(
                    "No se puede quitar este estudio " .
                    "porque tiene registros en el historial médico."
                );
            }

            // Quitar solamente la asignación
            // de este paciente.

            $stmt = mysqli_prepare(
                $conexion,
                "DELETE FROM paciente_estudio
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
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
            "Location: administrar_estudios.php?dni_p=" .
            urlencode((string)$dni) .
            "&guardado=1"
        );

        exit;

    } catch (Throwable $e) {

        if ($transaccionIniciada) {
            mysqli_rollback($conexion);
        }

        if ((int)$e->getCode() === 403) {
            http_response_code(403);
            exit(
                "Este paciente está archivado. " .
                "No se permite modificar sus estudios."
            );
        }

        error_log(
            "DUCCA - Administrar estudios: " .
            $e->getMessage()
        );

        // Errores de validación propios
        // del formulario.

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
    "SELECT id_estudio, nombre_es
     FROM estudio
     ORDER BY nombre_es"
);

// =========================================
// CONSULTAR ESTUDIOS ASIGNADOS
// =========================================

$asignados = consultar(
    $conexion,
    "SELECT
        e.id_estudio,
        e.nombre_es,
        pe.estado
     FROM paciente_estudio pe
     INNER JOIN estudio e
        ON pe.id_estudio = e.id_estudio
     WHERE pe.dni_paciente = ?
     ORDER BY e.nombre_es",
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

    <title>Administrar estudios del paciente - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .campo-estudio {
            width: 100%;
            max-width: 430px;
            padding: 10px;
            box-sizing: border-box;
        }

        .acciones-estudio {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .acciones-estudio form {
            display: inline-block;
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
    </style>

</head>

<body>

<main class="gestion-contenido">

    <!-- =====================================
         ENCABEZADO
    ===================================== -->

    <section class="bienvenida">

        <h1>Administrar estudios indicados</h1>

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
         AGREGAR ESTUDIO
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

        <h2>Agregar estudio</h2>

        <p>
            Buscá uno existente o escribí un nombre
            nuevo para registrarlo y asignarlo.
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
                <label for="nombre_estudio">
                    Nombre del estudio
                </label>
            </p>

            <input
                type="text"
                id="nombre_estudio"
                name="nombre_estudio"
                list="catalogo_estudios"
                class="campo-estudio"
                maxlength="50"
                autocomplete="off"
                required
                placeholder="Buscar o escribir estudio..."
            >

            <datalist id="catalogo_estudios">

                <?php while (
                    $estudio = mysqli_fetch_assoc($catalogo)
                ) { ?>

                    <option
                        value="<?php
                            echo escapar($estudio["nombre_es"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p>
                <button type="submit">
                    Agregar estudio
                </button>
            </p>

        </form>

        <p>
            <a
                href="../Estudios/estudios.php"
                target="_blank"
                rel="noopener noreferrer"
            >
                Abrir catálogo general de estudios
            </a>
        </p>

    </section>

    <!-- =====================================
         ESTUDIOS ASIGNADOS
    ===================================== -->

    <section class="tarjeta">

        <h2>Estudios asignados</h2>

        <?php if (!$asignados) { ?>

            <p>
                El paciente todavía no tiene
                estudios indicados.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Estudio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach (
                            $asignados as $estudio
                        ) { ?>

                            <tr>

                                <td>
                                    <?php
                                    echo escapar(
                                        $estudio["nombre_es"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo escapar(
                                        $estudio["estado"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <div class="acciones-estudio">

                                        <!-- CAMBIAR ESTADO -->

                                        <form method="POST">

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
                                                value="estado"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_estudio"
                                                value="<?php
                                                    echo (int)$estudio["id_estudio"];
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="estado"
                                                value="<?php
                                                    echo $estudio["estado"] === "Realizado"
                                                        ? "Pendiente"
                                                        : "Realizado";
                                                ?>"
                                            >

                                            <button type="submit">
                                                <?php
                                                echo $estudio["estado"] === "Realizado"
                                                    ? "Marcar pendiente"
                                                    : "Marcar realizado";
                                                ?>
                                            </button>

                                        </form>

                                        <!-- QUITAR ASIGNACIÓN -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                '¿Quitar este estudio del paciente?'
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
                                                name="id_estudio"
                                                value="<?php
                                                    echo (int)$estudio["id_estudio"];
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
