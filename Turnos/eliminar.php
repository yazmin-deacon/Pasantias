
<?php

// =========================================
// DUCCA - ELIMINAR TURNO
// =========================================

// Verificar que haya una sesión iniciada.

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

function mostrarError($mensaje, $codigo = 400) {

    http_response_code($codigo);

    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error al eliminar turno - DUCCA</title>
        <link rel="stylesheet" href="../general/estilos.css">
    </head>
    <body>
        <main class="gestion-contenido">
            <section class="tarjeta">
                <h2>No se pudo eliminar el turno</h2>
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
// 1. COMPROBAR MÉTODO
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    mostrarError(
        "Método no permitido.",
        405
    );
}

// =========================================
// 2. VALIDAR TOKEN CSRF
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
    !hash_equals($tokenGuardado, $tokenEnviado)
) {

    mostrarError(
        "La solicitud no es válida. " .
        "Volvé a turnos e intentá nuevamente.",
        403
    );
}

// =========================================
// 3. VALIDAR ID DEL TURNO
// =========================================

$idTurno = filter_var(
    $_POST["id_turno"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idTurno || $idTurno <= 0) {

    mostrarError(
        "El ID del turno no es válido."
    );
}

// =========================================
// 4. INICIAR TRANSACCIÓN
// =========================================

$transaccionIniciada = false;

try {

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // =====================================
    // 5. BUSCAR PACIENTE ORIGINAL
    // =====================================

    // Averiguar a quién pertenece el turno.

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT dni_paciente
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
    $turno = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$turno) {

        throw new Exception(
            "No se encontró el turno.",
            404
        );
    }

    $dniPaciente = (int)$turno["dni_paciente"];

    // =====================================
    // 6. BLOQUEAR FILA DEL PACIENTE
    // =====================================

    // Evitar que el paciente cambie
    // de estado mientras se elimina
    // el turno.

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
        $dniPaciente
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $paciente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$paciente) {

        throw new Exception(
            "No se encontró el paciente del turno.",
            404
        );
    }

    // =====================================
    // 7. BLOQUEAR PACIENTES ARCHIVADOS
    // =====================================

    if ($paciente["estado"] !== "activo") {

        throw new Exception(
            "Este turno pertenece a un paciente archivado. " .
            "No se puede eliminar. " .
            "Para modificar sus turnos, " .
            "primero debés reactivarlo.",
            403
        );
    }

    // =====================================
    // 8. BLOQUEAR Y VERIFICAR EL TURNO
    // =====================================

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT dni_paciente
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
    $turnoActual = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (
        !$turnoActual ||
        (int)$turnoActual["dni_paciente"] !== $dniPaciente
    ) {

        throw new Exception(
            "El turno cambió o ya no está disponible. " .
            "Volvé al listado e intentá nuevamente."
        );
    }

    // =====================================
    // 9. ELIMINAR TURNO
    // =====================================

    $stmt = mysqli_prepare(
        $conexion,
        "DELETE FROM turno
         WHERE id_turno = ?
           AND dni_paciente = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $idTurno,
        $dniPaciente
    );

    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) !== 1) {

        mysqli_stmt_close($stmt);

        throw new Exception(
            "No se pudo eliminar el turno."
        );
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 10. CONFIRMAR ELIMINACIÓN
    // =====================================

    mysqli_commit($conexion);
    $transaccionIniciada = false;

    mysqli_close($conexion);

    // =====================================
    // 11. VOLVER AL LISTADO
    // =====================================

    header(
        "Location: listar.php?estado=Todos"
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

    error_log(
        "DUCCA - Error al eliminar turno: " .
        $e->getMessage()
    );

    mostrarError(
        "No se pudo eliminar el turno. " .
        "No se confirmó la operación.",
        500
    );
}

?>
