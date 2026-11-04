
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("ID de exposición inválido.");
}

$id = (int) $_GET["id"];

// BLOQUE 3 - Buscar la exposición
$sql = "SELECT nombre_ex FROM exposicion WHERE id_exposicion = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$exposicion = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$exposicion) {
    die("No se encontró la exposición.");
}

// BLOQUE 4 - Eliminar la exposición
$sql = "DELETE FROM exposicion WHERE id_exposicion = ?";
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

// BLOQUE 5 - Controlar relaciones
if (mysqli_stmt_errno($stmt) == 1451) {
    echo "No se puede eliminar la exposición '" .
         htmlspecialchars($exposicion["nombre_ex"]) .
         "' porque está relacionada con trabajos o pacientes.";
} else {
    echo "Error al eliminar: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="listar.php">Volver al listado</a>
