
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Método no permitido.");
}

if (
    empty($_SESSION["csrf_turnos"]) ||
    !hash_equals(
        $_SESSION["csrf_turnos"],
        (string)($_POST["csrf"] ?? "")
    )
) {
    http_response_code(403);
    die("Solicitud no válida.");
}

$idTurno = filter_var(
    $_POST["id_turno"] ?? null,
    FILTER_VALIDATE_INT
);

if (!$idTurno || $idTurno <= 0) {
    die("ID de turno no válido.");
}

try {

    $stmt = mysqli_prepare(
        $conexion,
        "DELETE FROM turno
         WHERE id_turno = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $idTurno
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    header("Location: listar.php?estado=Todos");
    exit;

} catch (Throwable $e) {

    http_response_code(400);

    echo "<h2>No se pudo eliminar el turno</h2>";

    echo "<p>" . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    ) . "</p>";

    echo '<a href="listar.php">Volver a turnos</a>';
}

mysqli_close($conexion);
?>
