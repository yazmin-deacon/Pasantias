
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el trabajo
if (!isset($_POST["id_trabajo"]) || !is_numeric($_POST["id_trabajo"])) {
    die("Trabajo inválido.");
}

$id_trabajo = (int) $_POST["id_trabajo"];

// BLOQUE 3 - Verificar que el trabajo exista
$sql = "SELECT id_trabajo FROM trabajo WHERE id_trabajo = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id_trabajo);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$trabajo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajo) {
    die("No se encontró el trabajo.");
}

// BLOQUE 4 - Recibir los estudios seleccionados
$estudios = [];

if (isset($_POST["estudios"]) && is_array($_POST["estudios"])) {
    foreach ($_POST["estudios"] as $id) {
        if (filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0) {
            $estudios[] = (int)$id;
        }
    }
}

$estudios = array_unique($estudios);

// BLOQUE 5 - Iniciar una transacción
mysqli_begin_transaction($conexion);

try {

    // Eliminar las relaciones anteriores de este trabajo
    $sql = "DELETE FROM trabajo_estudio WHERE id_trabajo = ?";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "i", $id_trabajo);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    // Insertar las nuevas relaciones
    $sql = "INSERT INTO trabajo_estudio (id_trabajo, id_estudio)
            SELECT ?, id_estudio
            FROM estudio
            WHERE id_estudio = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach ($estudios as $id_estudio) {

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_trabajo,
            $id_estudio
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }

        if (mysqli_stmt_affected_rows($stmt) != 1) {
            throw new Exception("Uno de los estudios seleccionados no existe.");
        }
    }

    mysqli_stmt_close($stmt);

    // Confirmar los cambios
    mysqli_commit($conexion);

    mysqli_close($conexion);

    header("Location: estudios.php?id_trabajo=" . $id_trabajo);
    exit;

} catch (Exception $error) {

    mysqli_rollback($conexion);

    if (isset($stmt) && $stmt) {
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conexion);

    die("Error al guardar los estudios: " . htmlspecialchars($error->getMessage()));
}
