<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

$id = $_POST["id_trabajo"];
$nombre = trim($_POST["nombre_t"]);

if (!is_numeric($id) || $nombre == "") {
    header("Location: listar.php?mensaje=Datos inválidos");
    exit;
}

$sql = "UPDATE trabajo
        SET nombre_t = ?
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "si", $nombre, $id);

if (mysqli_stmt_execute($stmt)) {

    header("Location: listar.php?mensaje=Trabajo actualizado correctamente");

} else {

    header("Location: listar.php?mensaje=Error al actualizar el trabajo");

}

mysqli_stmt_close($stmt);
mysqli_close($conexion);

exit;
?>