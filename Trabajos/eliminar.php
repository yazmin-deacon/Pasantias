<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

if (!isset($_POST["id_trabajo"]) || !is_numeric($_POST["id_trabajo"])) {
    header("Location: listar.php?mensaje=ID de trabajo inválido");
    exit;
}

$id = (int) $_POST["id_trabajo"];

$sql = "DELETE FROM trabajo
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {

    $mensaje = "Trabajo eliminado correctamente";

} else {

    if (mysqli_stmt_errno($stmt) == 1451) {

        $mensaje = "No se puede eliminar porque el trabajo está relacionado con otros registros";

    } else {

        $mensaje = "Error al eliminar el trabajo";

    }
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);

header("Location: listar.php?mensaje=" . urlencode($mensaje));
exit;
?>