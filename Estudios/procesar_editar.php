<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir los datos modificados del formulario
$dni = isset($_POST["dni"]) ? (int) $_POST["dni"] : 0;

$estudios = isset($_POST["estudios"])
    ? array_unique(array_map("intval", $_POST["estudios"]))
    : [];

$estudios = array_values(array_filter($estudios, function ($id) {
    return $id > 0;
}));

// BLOQUE 3 - Verificar que el paciente exista
$sqlPaciente = "SELECT dni_p FROM paciente WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sqlPaciente);

mysqli_stmt_bind_param($stmt, "i", $dni);

mysqli_stmt_execute($stmt);

$resultadoPaciente = mysqli_stmt_get_result($stmt);

$existePaciente = mysqli_num_rows($resultadoPaciente) > 0;

mysqli_stmt_close($stmt);

if ($dni <= 0 || !$existePaciente) {
    die("El paciente seleccionado no existe.");
}

// BLOQUE 4 - Verificar que todos los estudios existan
$sqlVerificar = "SELECT id_estudio FROM estudio WHERE id_estudio = ?";

$stmtVerificar = mysqli_prepare($conexion, $sqlVerificar);

foreach ($estudios as $id) {

    mysqli_stmt_bind_param($stmtVerificar, "i", $id);

    mysqli_stmt_execute($stmtVerificar);

    $resultadoVerificar = mysqli_stmt_get_result($stmtVerificar);

    if (mysqli_num_rows($resultadoVerificar) == 0) {

        mysqli_stmt_close($stmtVerificar);

        die("Uno de los estudios seleccionados no existe.");
    }
}

mysqli_stmt_close($stmtVerificar);

// BLOQUE 5 - Guardar las asignaciones
try {

    mysqli_begin_transaction($conexion);

    $sqlEliminar = "DELETE FROM paciente_estudio WHERE dni_paciente = ?";

    $stmtEliminar = mysqli_prepare($conexion, $sqlEliminar);

    mysqli_stmt_bind_param($stmtEliminar, "i", $dni);

    mysqli_stmt_execute($stmtEliminar);

    mysqli_stmt_close($stmtEliminar);

    $sqlInsertar = "INSERT INTO paciente_estudio (dni_paciente, id_estudio) VALUES (?, ?)";

    $stmtInsertar = mysqli_prepare($conexion, $sqlInsertar);

    foreach ($estudios as $idEstudio) {

        mysqli_stmt_bind_param($stmtInsertar, "ii", $dni, $idEstudio);

        mysqli_stmt_execute($stmtInsertar);
    }

    mysqli_stmt_close($stmtInsertar);

    mysqli_commit($conexion);

    header("Location: estudios.php");
    exit;

} catch (Throwable $e) {

    mysqli_rollback($conexion);

    die("Error al guardar los estudios: " . htmlspecialchars($e->getMessage()));

}
?>