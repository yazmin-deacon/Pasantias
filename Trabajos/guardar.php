<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

$nombre = trim($_POST["nombre_t"]);

if ($nombre == "") {
    header("Location: agregar.php?error=1");
    exit;
}

$sql = "INSERT INTO trabajo (nombre_t) VALUES (?)";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "s", $nombre);

if (mysqli_stmt_execute($stmt)) {

    header("Location: listar.php?mensaje=Trabajo agregado correctamente");

} else {

    header("Location: listar.php?mensaje=Error al agregar el trabajo");

}

mysqli_stmt_close($stmt);
mysqli_close($conexion);

exit;
?>