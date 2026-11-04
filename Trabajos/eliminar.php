
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("ID de trabajo inválido.");
}

$id = (int) $_GET["id"];

// BLOQUE 3 - Verificar si el trabajo existe
$sql = "SELECT nombre_t FROM trabajo WHERE id_trabajo = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$trabajo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajo) {
    die("No se encontró el trabajo.");
}

// BLOQUE 4 - Eliminar el trabajo
$sql = "DELETE FROM trabajo WHERE id_trabajo = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: listar.php");
    exit;
}

// BLOQUE 5 - Controlar si tiene relaciones
if (mysqli_stmt_errno($stmt) == 1451) {
    echo "No se puede eliminar el trabajo '" .
         htmlspecialchars($trabajo["nombre_t"]) .
         "' porque está relacionado con pacientes, estudios o exposiciones.";
} else {
    echo "Error al eliminar: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="listar.php">Volver al listado</a>
