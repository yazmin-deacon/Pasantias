
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set("America/Argentina/Salta");

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

function mostrarError($mensaje) {
    http_response_code(400);

    echo "<h2>No se pudo guardar el turno</h2>";
    echo "<p>" . escapar($mensaje) . "</p>";
    echo '<p><a href="listar.php">Volver a turnos</a></p>';
}

// =========================================
// VALIDAR SOLICITUD
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

if (
    empty($_SESSION["csrf_turnos"]) ||
    !hash_equals(
        $_SESSION["csrf_turnos"],
        (string)($_POST["csrf"] ?? "")
    )
) {
    http_response_code(403);
    exit("Solicitud no válida.");
}

// =========================================
// RECIBIR DATOS
// =========================================

$idTexto = trim((string)($_POST["id_turno"] ?? ""));

$idTurno = $idTexto === ""
    ? null
    : filter_var($idTexto, FILTER_VALIDATE_INT);

$dni = filter_var(
    $_POST["dni_paciente"] ?? null,
    FILTER_VALIDATE_INT
);

$fecha = trim((string)($_POST["fecha"] ?? ""));
$hora = trim((string)($_POST["hora"] ?? ""));

$motivo = limpiar($_POST["motivo"] ?? "");

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

if ($idTexto !== "" && (!$idTurno || $idTurno <= 0)) {
    mostrarError("El ID del turno no es válido.");
    exit;
}

if (!$dni || $dni <= 0) {
    mostrarError("Seleccioná un paciente válido.");
    exit;
}

if (
    $motivo === "" ||
    mb_strlen($motivo, "UTF-8") > 150
) {
    mostrarError(
        "El motivo es obligatorio y no puede superar 150 caracteres."
    );
    exit;
}

if (!in_array($estado, $estadosPermitidos, true)) {
    mostrarError("El estado seleccionado no es válido.");
    exit;
}

// Validar fecha real

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
    mostrarError("La fecha ingresada no es válida.");
    exit;
}

// Validar hora de 00:00 a 23:59

if (
    !preg_match(
        '/^([01]\d|2[0-3]):[0-5]\d$/',
        $hora
    )
) {
    mostrarError("La hora ingresada no es válida.");
    exit;
}

$hora .= ":00";

// =========================================
// GUARDAR EN TRANSACCIÓN
// =========================================

try {
    mysqli_begin_transaction($conexion);

    // Verificar que el paciente exista

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT dni_p
         FROM paciente
         WHERE dni_p = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $paciente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$paciente) {
        throw new Exception(
            "El paciente seleccionado no existe."
        );
    }

    // Si se está editando, verificar el turno

    if ($idTurno !== null) {

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT id_turno
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

        if (!$turnoActual) {
            throw new Exception(
                "El turno que querés editar no existe."
            );
        }
    }

    // Evitar coincidencias exactas de turnos activos

    if (in_array($estado, ["Pendiente", "Confirmado"], true)) {

        $idExcluir = $idTurno ?? 0;

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT id_turno
             FROM turno
             WHERE fecha = ?
               AND hora = ?
               AND estado IN ('Pendiente', 'Confirmado')
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

    // Crear o actualizar

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
            ) VALUES (?, ?, ?, ?, ?, ?)"
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

    mysqli_commit($conexion);

    header(
        "Location: listar.php?dni_p=" .
        urlencode((string)$dni) .
        "&estado=Todos"
    );

    exit;

} catch (Throwable $e) {

    mysqli_rollback($conexion);

    mostrarError($e->getMessage());
}

mysqli_close($conexion);
?>
