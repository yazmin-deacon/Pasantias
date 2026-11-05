
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir datos del formulario
$dni = isset($_POST["dni"]) ? (int) $_POST["dni"] : 0;

$estudios = isset($_POST["estudios"])
    ? array_unique(array_map("intval", $_POST["estudios"]))
    : [];

$estudios = array_filter($estudios, function ($id) {
    return $id > 0;
});

// BLOQUE 3 - Verificar que el paciente exista
$sqlPaciente = "SELECT dni_p FROM paciente WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sqlPaciente);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$existePaciente = mysqli_num_rows($resultado) > 0;

mysqli_stmt_close($stmt);

if (!$existePaciente) {
    die("El paciente no existe.");
}

// BLOQUE 4 - Verificar que los estudios existan
$sqlVerificar = "SELECT id_estudio FROM estudio WHERE id_estudio = ?";
$stmtVerificar = mysqli_prepare($conexion, $sqlVerificar);

foreach ($estudios as $id) {

    mysqli_stmt_bind_param($stmtVerificar, "i", $id);
    mysqli_stmt_execute($stmtVerificar);

    $resultado = mysqli_stmt_get_result($stmtVerificar);

    if (mysqli_num_rows($resultado) == 0) {
        die("Uno de los estudios seleccionados no existe.");
    }
}

mysqli_stmt_close($stmtVerificar);

// BLOQUE 5 - Actualizar las asignaciones
try {

    mysqli_begin_transaction($conexion);

    // Eliminar las asignaciones anteriores
    $sqlEliminar = "DELETE FROM paciente_estudio
                    WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sqlEliminar);
    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Guardar las nuevas asignaciones
    $sqlInsertar = "INSERT INTO paciente_estudio
                    (dni_paciente, id_estudio)
                    VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sqlInsertar);

    foreach ($estudios as $id) {

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);
        mysqli_stmt_execute($stmt);

    }

    mysqli_stmt_close($stmt);

    mysqli_commit($conexion);

    header("Location: index.php");
    exit;

} catch (mysqli_sql_exception $e) {

    mysqli_rollback($conexion);
    die("Error al guardar los estudios: " . $e->getMessage());

}
?>
