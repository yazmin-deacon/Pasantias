
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir los datos
if (
    !isset($_POST["id_exposicion"]) ||
    !isset($_POST["nombre_ex"]) ||
    !is_numeric($_POST["id_exposicion"])
) {
    die("Datos incompletos o inválidos.");
}

$id = (int) $_POST["id_exposicion"];
$nombre = trim($_POST["nombre_ex"]);

if ($nombre === "") {
    die("El nombre de la exposición no puede estar vacío.");
}

// BLOQUE 3 - Actualizar la exposición
$sql = "UPDATE exposicion SET nombre_ex = ? WHERE id_exposicion = ?";
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

// BLOQUE 5 - Controlar errores
if (mysqli_stmt_errno($stmt) == 1062) {
    echo "Ya existe una exposición con ese nombre.";
} else {
    echo "Error al actualizar: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="listar.php">Volver al listado</a>
