
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Solo permitir solicitudes POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: estudios.php");
    exit;
}

// Verificar token
$token = $_POST["token"] ?? "";

if (
    empty($_SESSION["token_estudios"]) ||
    !is_string($token) ||
    !hash_equals($_SESSION["token_estudios"], $token)
) {
    http_response_code(403);
    die("Solicitud no válida.");
}

// Recibir paciente y estudio
$dni = filter_input(
    INPUT_POST,
    "dni",
    FILTER_VALIDATE_INT
);

$idEstudio = filter_input(
    INPUT_POST,
    "id_estudio",
    FILTER_VALIDATE_INT
);

if (
    $dni === false || $dni === null || $dni <= 0 ||
    $idEstudio === false || $idEstudio === null ||
    $idEstudio <= 0
) {
    header("Location: estudios.php");
    exit;
}

// Cambiar el estado únicamente para
// ese paciente y ese estudio.

$sql = "UPDATE paciente_estudio
        SET estado = CASE
            WHEN estado = 'Pendiente'
                THEN 'Realizado'
            ELSE 'Pendiente'
        END
        WHERE dni_paciente = ?
          AND id_estudio = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar el cambio de estado.");
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $dni,
    $idEstudio
);

if (!mysqli_stmt_execute($stmt)) {
    error_log(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header(
        "Location: estudios.php?mensaje=" .
        urlencode("No se pudo cambiar el estado.")
    );
    exit;
}

$filas = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);
mysqli_close($conexion);

$mensaje = $filas > 0
    ? "Estado del estudio actualizado correctamente."
    : "No se encontró el estudio asignado al paciente.";

header(
    "Location: estudios.php?mensaje=" .
    urlencode($mensaje)
);

exit;
?>
