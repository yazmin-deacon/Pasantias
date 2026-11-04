
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir los datos
if (
    !isset($_POST["id_trabajo"]) ||
    !isset($_POST["nombre_t"]) ||
    !is_numeric($_POST["id_trabajo"])
) {
    die("Datos incompletos o inválidos.");
}

$id = (int) $_POST["id_trabajo"];
$nombre = trim($_POST["nombre_t"]);

if ($nombre === "") {
    die("El nombre del trabajo no puede estar vacío.");
}

// BLOQUE 3 - Actualizar el trabajo
$sql = "UPDATE trabajo SET nombre_t = ? WHERE id_trabajo = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "si", $nombre, $id);

// BLOQUE 4 - Ejecutar y controlar
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: listar.php");
    exit;
}

if (mysqli_stmt_errno($stmt) == 1062) {
    echo "Ya existe un trabajo con ese nombre.";
} else {
    echo "Error al actualizar: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="listar.php">Volver al listado</a>
