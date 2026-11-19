
<?php

// =========================================
// DUCCA - GUARDAR Y ACTUALIZAR TURNOS
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set("America/Argentina/Salta");

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

function mostrarError($mensaje, $codigo = 400) {

    http_response_code($codigo);

    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error al guardar turno - DUCCA</title>
        <link rel="stylesheet" href="../general/estilos.css">
    </head>
    <body>
        <main class="gestion-contenido">
            <section class="tarjeta">
                <h2>No se pudo guardar el turno</h2>
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
// VALIDAR MÉTODO POST
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    mostrarError(
        "Método no permitido.",
        405
    );
}

// =========================================
// VALIDAR TOKEN CSRF
// =========================================

$tokenGuardado = (string)(
    $_SESSION["csrf_turnos"] ?? ""
);

$tokenEnviado = (string)(
    $_POST["csrf"] ?? ""
);

if (
    $tokenGuardado === "" ||
    $tokenEnviado === "" ||
    !hash_equals(
        $tokenGuardado,
        $tokenEnviado
    )
) {

    mostrarError(
        "La solicitud no es válida. " .
        "Volvé al formulario e intentá nuevamente.",
        403
    );
}

// =========================================
// RECIBIR DATOS
// =========================================

$idTexto = trim(
    (string)($_POST["id_turno"] ?? "")
);

$idTurno = $idTexto === ""
    ? null
    : filter_var(
        $idTexto,
        FILTER_VALIDATE_INT
    );

$dni = filter_var(
    $_POST["dni_paciente"] ?? null,
    FILTER_VALIDATE_INT
);

$fecha = trim(
    (string)($_POST["fecha"] ?? "")
);

$hora = trim(
    (string)($_POST["hora"] ?? "")
);

$motivo = limpiar(
    $_POST["motivo"] ?? ""
);

$estado = trim(
    (string)($_POST["estado"] ?? "Pendiente")
);

$observaciones = trim(
    (string)($_POST["observaciones"] ?? "")
);

$estadosPermitidos = [
    "Pendiente",
    "Confirmado",
    "Realizado",
    "Cancelado",
    "Ausente"
];

// =========================================
// VALIDACIONES
// =========================================

if (
    $idTexto !== "" &&
    (
        !$idTurno ||
        $idTurno <= 0
    )
) {

    mostrarError(
        "El ID del turno no es válido."
    );
}

if (!$dni || $dni <= 0) {

    mostrarError(
        "Seleccioná un paciente válido."
    );
}

if (
    $motivo === "" ||
    mb_strlen($motivo, "UTF-8") > 150
) {

    mostrarError(
        "El motivo es obligatorio y no puede superar 150 caracteres."
    );
}

if (
    !in_array(
        $estado,
        $estadosPermitidos,
        true
    )
) {

    mostrarError(
        "El estado seleccionado no es válido."
    );
}

// =========================================
// VALIDAR FECHA REAL
// =========================================

$fechaValida = false;

if (
    preg_match(
        '/^(\d{4})-(\d{2})-(\d{2})$/',
        $fecha,
        $partesFecha
    )
) {

    $fechaValida = checkdate(
        (int)$partesFecha[2],
        (int)$partesFecha[3],
        (int)$partesFecha[1]
    );
}

if (!$fechaValida) {

    mostrarError(
        "La fecha ingresada no es válida."
    );
}

// =========================================
// VALIDAR HORA
// =========================================

// Se acepta HH:MM entre 00:00 y 23:59.

if (
    !preg_match(
        '/^([01]\d|2[0-3]):[0-5]\d$/',
        $hora
    )
) {

    mostrarError(
        "La hora ingresada no es válida."
    );
}

$hora .= ":00";

// =========================================
// INICIAR TRANSACCIÓN
// =========================================

$transaccionIniciada = false;

try {

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // =====================================
    // 1. BUSCAR TURNO ORIGINAL
    // =====================================

    // Si estamos editando, buscamos a qué
    // paciente pertenecía originalmente.

    $dniOriginal = null;

    if ($idTurno !== null) {

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT
                id_turno,
                dni_paciente

             FROM turno

             WHERE id_turno = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idTurno
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        $turnoActual = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$turnoActual) {

            throw new Exception(
                "El turno que querés editar no existe.",
                404
            );
        }

        $dniOriginal = (int)(
            $turnoActual["dni_paciente"]
        );
    }

    // =====================================
    // 2. COMPROBAR PACIENTES ACTIVOS
    // =====================================

    // En una edición, verificar tanto el
    // paciente original como el seleccionado.
    //
    // Bloquear las filas en orden de DNI
    // para reducir problemas de concurrencia.

    $dnisComprobar = [$dni];

    if ($dniOriginal !== null) {
        $dnisComprobar[] = $dniOriginal;
    }

    $dnisComprobar = array_values(
        array_unique($dnisComprobar)
    );

    sort($dnisComprobar, SORT_NUMERIC);

    foreach ($dnisComprobar as $dniVerificar) {

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT
                dni_p,
                estado

             FROM paciente

             WHERE dni_p = ?

             FOR UPDATE"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $dniVerificar
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        $paciente = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$paciente) {

            throw new Exception(
                "Uno de los pacientes seleccionados no existe.",
                404
            );
        }

        // =================================
        // BLOQUEAR PACIENTE ARCHIVADO
        // =================================

        if ($paciente["estado"] !== "activo") {

            throw new Exception(
                "No se pueden registrar ni modificar " .
                "turnos de pacientes archivados. " .
                "Primero debés reactivar al paciente.",
                403
            );
        }
    }

    // =====================================
    // 3. BLOQUEAR TURNO AL EDITAR
    // =====================================

    if ($idTurno !== null) {

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT
                id_turno,
                dni_paciente

             FROM turno

             WHERE id_turno = ?

             FOR UPDATE"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $idTurno
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        $turnoBloqueado = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$turnoBloqueado) {

            throw new Exception(
                "El turno ya no existe.",
                404
            );
        }

        // Evitar que se cambie el paciente
        // original mientras se procesa
        // la actualización.

        if (
            (int)$turnoBloqueado["dni_paciente"] !==
            $dniOriginal
        ) {

            throw new Exception(
                "El turno cambió de paciente. " .
                "Volvé a abrir el formulario."
            );
        }
    }

    // =====================================
    // 4. EVITAR HORARIOS OCUPADOS
    // =====================================

    // No permitir dos turnos activos
    // en la misma fecha y hora.

    if (
        in_array(
            $estado,
            ["Pendiente", "Confirmado"],
            true
        )
    ) {

        $idExcluir = $idTurno ?? 0;

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT id_turno

             FROM turno

             WHERE fecha = ?
               AND hora = ?
               AND estado IN (
                   'Pendiente',
                   'Confirmado'
               )
               AND id_turno <> ?

             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssi",
            $fecha,
            $hora,
            $idExcluir
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);

        $ocupado = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if ($ocupado) {

            throw new Exception(
                "Ya existe otro turno pendiente o confirmado " .
                "en esa fecha y hora."
            );
        }
    }

    // =====================================
    // 5. CREAR TURNO NUEVO
    // =====================================

    if ($idTurno === null) {

        $stmt = mysqli_prepare(
            $conexion,
            "INSERT INTO turno (
                dni_paciente,
                fecha,
                hora,
                motivo,
                estado,
                observaciones
             )

             VALUES (?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "isssss",
            $dni,
            $fecha,
            $hora,
            $motivo,
            $estado,
            $observaciones
        );

    // =====================================
    // 6. ACTUALIZAR TURNO EXISTENTE
    // =====================================

    } else {

        $stmt = mysqli_prepare(
            $conexion,
            "UPDATE turno

             SET
                dni_paciente = ?,
                fecha = ?,
                hora = ?,
                motivo = ?,
                estado = ?,
                observaciones = ?

             WHERE id_turno = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "isssssi",
            $dni,
            $fecha,
            $hora,
            $motivo,
            $estado,
            $observaciones,
            $idTurno
        );
    }

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    // =====================================
    // 7. CONFIRMAR TRANSACCIÓN
    // =====================================

    mysqli_commit($conexion);
    $transaccionIniciada = false;

    mysqli_close($conexion);

    // =====================================
    // 8. VOLVER AL LISTADO
    // =====================================

    header(
        "Location: listar.php?dni_p=" .
        urlencode((string)$dni) .
        "&estado=Todos"
    );

    exit;

} catch (Throwable $e) {

    // =====================================
    // DESHACER CAMBIOS
    // =====================================

    if ($transaccionIniciada) {

        try {
            mysqli_rollback($conexion);

        } catch (Throwable $errorRollback) {

            error_log(
                "DUCCA - Error en rollback de turno: " .
                $errorRollback->getMessage()
            );
        }
    }

    $codigo = (int)$e->getCode();

    if ($codigo === 403 || $codigo === 404) {

        mostrarError(
            $e->getMessage(),
            $codigo
        );
    }

    // Mantener visibles los errores
    // habituales de validación.

    if (
        $e instanceof Exception &&
        !($e instanceof mysqli_sql_exception)
    ) {

        mostrarError(
            $e->getMessage()
        );
    }

    error_log(
        "DUCCA - Error al guardar turno: " .
        $e->getMessage()
    );

    mostrarError(
        "Ocurrió un error al guardar el turno. " .
        "No se confirmaron los cambios.",
        500
    );
}

?>
