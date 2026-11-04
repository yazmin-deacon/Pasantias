
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

// BLOQUE 4 - Recibir las exposiciones seleccionadas
$exposiciones = [];

if (isset($_POST["exposiciones"]) && is_array($_POST["exposiciones"])) {
    foreach ($_POST["exposiciones"] as $id) {
        if (filter_var($id, FILTER_VALIDATE_INT) !== false && (int)$id > 0) {
            $exposiciones[] = (int)$id;
        }
    }
}

$exposiciones = array_unique($exposiciones);

// BLOQUE 5 - Iniciar una transacción
mysqli_begin_transaction($conexion);

try {

    // Eliminar las relaciones anteriores de este trabajo
    $sql = "DELETE FROM trabajo_exposicion WHERE id_trabajo = ?";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "i", $id_trabajo);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);

    // Preparar la inserción de las nuevas relaciones
    $sql = "INSERT INTO trabajo_exposicion (id_trabajo, id_exposicion)
            SELECT ?, id_exposicion
            FROM exposicion
            WHERE id_exposicion = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach ($exposiciones as $id_exposicion) {

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_trabajo,
            $id_exposicion
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }

        if (mysqli_stmt_affected_rows($stmt) != 1) {
            throw new Exception("Una de las exposiciones seleccionadas no existe.");
        }
    }

    mysqli_stmt_close($stmt);

    // Confirmar los cambios
    mysqli_commit($conexion);

    mysqli_close($conexion);

    header("Location: listar.php?id_trabajo=" . $id_trabajo);
    exit;

} catch (Exception $error) {

    mysqli_rollback($conexion);

    if (isset($stmt) && $stmt) {
        mysqli_stmt_close($stmt);
    }

    mysqli_close($conexion);

    die("Error al guardar las relaciones: " . htmlspecialchars($error->getMessage()));
}
