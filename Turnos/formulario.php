
<?php

// =========================================
// DUCCA - FORMULARIO DE TURNOS
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

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

function mostrarError($mensaje, $codigo = 400) {

    http_response_code($codigo);

    echo '<!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <title>Turnos - DUCCA</title>
        <link rel="stylesheet"
              href="../general/estilos.css">
    </head>

    <body>

        <main class="gestion-contenido">

            <section class="tarjeta">

                <h2>No se puede abrir el formulario</h2>

                <p>' . escapar($mensaje) . '</p>

                <p>
                    <a href="listar.php">
                        Volver a turnos
                    </a>
                </p>

            </section>

        </main>

    </body>
    </html>';

    exit;
}

// =========================================
// TOKEN CSRF
// =========================================

if (empty($_SESSION["csrf_turnos"])) {

    $_SESSION["csrf_turnos"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_turnos"];

// =========================================
// RECIBIR IDENTIFICADORES
// =========================================

$idTurno = filter_input(
    INPUT_GET,
    "id_turno",
    FILTER_VALIDATE_INT
);

$dniSolicitado = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

if (
    isset($_GET["id_turno"]) &&
    (!$idTurno || $idTurno <= 0)
) {
    mostrarError(
        "El identificador del turno no es válido."
    );
}

if (
    isset($_GET["dni_p"]) &&
    (!$dniSolicitado || $dniSolicitado <= 0)
) {
    mostrarError(
        "El DNI del paciente no es válido."
    );
}

// =========================================
// DATOS INICIALES DEL TURNO
// =========================================

$turno = [
    "id_turno" => "",
    "dni_paciente" => $dniSolicitado ?: "",
    "fecha" => "",
    "hora" => "",
    "motivo" => "",
    "estado" => "Pendiente",
    "observaciones" => ""
];

$editando = false;

// =========================================
// BUSCAR TURNO SI SE ESTÁ EDITANDO
// =========================================

if ($idTurno && $idTurno > 0) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT
            t.*,
            p.estado AS estado_paciente

         FROM turno t

         INNER JOIN paciente p
            ON t.dni_paciente = p.dni_p

         WHERE t.id_turno = ?"
    );

    if (!$stmt) {
        mostrarError(
            "No se pudo consultar el turno.",
            500
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $idTurno
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $existente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$existente) {
        mostrarError(
            "No se encontró el turno.",
            404
        );
    }

    // =====================================
    // BLOQUEAR TURNOS DE ARCHIVADOS
    // =====================================

    if ($existente["estado_paciente"] !== "activo") {

        mostrarError(
            "Este turno pertenece a un paciente " .
            "archivado. No se puede modificar. " .
            "Primero debés reactivar al paciente.",
            403
        );
    }

    $turno = $existente;
    $editando = true;
}

// =========================================
// COMPROBAR PACIENTE PRESELECCIONADO
// =========================================

if (!$editando && $dniSolicitado) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT estado
         FROM paciente
         WHERE dni_p = ?"
    );

    if (!$stmt) {
        mostrarError(
            "No se pudo consultar al paciente.",
            500
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $dniSolicitado
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $pacienteSolicitado = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$pacienteSolicitado) {
        mostrarError(
            "No se encontró el paciente.",
            404
        );
    }

    if ($pacienteSolicitado["estado"] !== "activo") {

        mostrarError(
            "Este paciente está archivado. " .
            "No se pueden registrar nuevos turnos " .
            "hasta que sea reactivado.",
            403
        );
    }
}

// =========================================
// CONSULTAR SOLO PACIENTES ACTIVOS
// =========================================

$resultado = mysqli_query(
    $conexion,
    "SELECT
        dni_p,
        nombre_p,
        apellido

     FROM paciente

     WHERE estado = 'activo'

     ORDER BY apellido, nombre_p"
);

if (!$resultado) {
    mostrarError(
        "No se pudieron consultar los pacientes.",
        500
    );
}

$pacientes = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);
mysqli_close($conexion);

// =========================================
// ESTADOS PERMITIDOS
// =========================================

$estados = [
    "Pendiente",
    "Confirmado",
    "Realizado",
    "Cancelado",
    "Ausente"
];

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php
        echo $editando
            ? "Editar turno - DUCCA"
            : "Nuevo turno - DUCCA";
        ?>
    </title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .form-turno {
            max-width: 600px;
        }

        .form-turno label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-turno input,
        .form-turno select,
        .form-turno textarea {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        .form-turno textarea {
            min-height: 120px;
            resize: vertical;
        }

        .acciones-formulario {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .aviso-turno {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: rgba(130, 140, 150, 0.12);
        }

    </style>

</head>

<body>

<main class="gestion-contenido">

    <!-- =====================================
         ENCABEZADO
    ===================================== -->

    <section class="bienvenida">

        <h1>
            <?php
            echo $editando
                ? "Editar turno"
                : "Registrar nuevo turno";
            ?>
        </h1>

        <p>
            Completá los datos del turno
            del paciente.
        </p>

    </section>

    <!-- =====================================
         FORMULARIO
    ===================================== -->

    <section class="tarjeta">

        <?php if (!$pacientes) { ?>

            <div class="aviso-turno">

                <p>
                    No hay pacientes activos disponibles.
                </p>

                <p>
                    Primero debés registrar o reactivar
                    un paciente para asignarle un turno.
                </p>

                <a href="../Pacientes/listar.php">
                    Volver a pacientes
                </a>

            </div>

        <?php } else { ?>

            <form
                action="guardar.php"
                method="POST"
                class="form-turno"
            >

                <!-- TOKEN CSRF -->

                <input
                    type="hidden"
                    name="csrf"
                    value="<?php
                        echo escapar($token);
                    ?>"
                >

                <!-- ID DEL TURNO -->

                <input
                    type="hidden"
                    name="id_turno"
                    value="<?php
                        echo escapar($turno["id_turno"]);
                    ?>"
                >

                <!-- PACIENTE -->

                <p>

                    <label for="dni_paciente">
                        Paciente
                    </label>

                    <select
                        id="dni_paciente"
                        name="dni_paciente"
                        required
                    >

                        <option value="">
                            Seleccionar paciente
                        </option>

                        <?php foreach ($pacientes as $paciente) { ?>

                            <option
                                value="<?php
                                    echo (int)$paciente["dni_p"];
                                ?>"
                                <?php
                                    echo (
                                        (int)$turno["dni_paciente"] ===
                                        (int)$paciente["dni_p"]
                                    )
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php
                                echo escapar(
                                    $paciente["apellido"] . ", " .
                                    $paciente["nombre_p"] . " - DNI " .
                                    $paciente["dni_p"]
                                );
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </p>

                <!-- FECHA -->

                <p>

                    <label for="fecha">
                        Fecha del turno
                    </label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        required
                        value="<?php
                            echo escapar($turno["fecha"]);
                        ?>"
                    >

                </p>

                <!-- HORA -->

                <p>

                    <label for="hora">
                        Hora del turno
                    </label>

                    <input
                        type="time"
                        id="hora"
                        name="hora"
                        required
                        value="<?php
                            echo escapar(
                                substr(
                                    (string)$turno["hora"],
                                    0,
                                    5
                                )
                            );
                        ?>"
                    >

                </p>

                <!-- MOTIVO -->

                <p>

                    <label for="motivo">
                        Motivo del turno
                    </label>

                    <input
                        type="text"
                        id="motivo"
                        name="motivo"
                        maxlength="150"
                        required
                        value="<?php
                            echo escapar($turno["motivo"]);
                        ?>"
                        placeholder="Ej.: Control de estudios"
                    >

                </p>

                <!-- ESTADO -->

                <p>

                    <label for="estado">
                        Estado
                    </label>

                    <select
                        id="estado"
                        name="estado"
                    >

                        <?php foreach ($estados as $estado) { ?>

                            <option
                                value="<?php
                                    echo escapar($estado);
                                ?>"
                                <?php
                                    echo $turno["estado"] === $estado
                                        ? "selected"
                                        : "";
                                ?>
                            >

                                <?php
                                echo escapar($estado);
                                ?>

                            </option>

                        <?php } ?>

                    </select>

                </p>

                <!-- OBSERVACIONES -->

                <p>

                    <label for="observaciones">
                        Observaciones
                    </label>

                    <textarea
                        id="observaciones"
                        name="observaciones"
                        placeholder="Información adicional sobre el turno..."
                    ><?php
                        echo escapar(
                            $turno["observaciones"]
                        );
                    ?></textarea>

                </p>

                <!-- BOTONES -->

                <div class="acciones-formulario">

                    <button type="submit">
                        <?php
                        echo $editando
                            ? "Guardar cambios"
                            : "Registrar turno";
                        ?>
                    </button>

                    <a href="listar.php">
                        Cancelar
                    </a>

                </div>

            </form>

        <?php } ?>

    </section>

</main>

</body>
</html>
