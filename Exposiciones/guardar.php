
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el nombre
if (!isset($_POST["nombre_ex"])) {
    die("No se recibió el nombre de la exposición.");
}

$nombre = trim($_POST["nombre_ex"]);

if ($nombre === "") {
    die("El nombre de la exposición no puede estar vacío.");
}

// BLOQUE 3 - Preparar la consulta
$sql = "INSERT INTO exposicion (nombre_ex) VALUES (?)";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

// BLOQUE 4 - Ejecutar la consulta
mysqli_stmt_bind_param($stmt, "s", $nombre);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: listar.php");
    exit;
}

// BLOQUE 5 - Controlar errores
if (mysqli_stmt_errno($stmt) == 1062) {
    echo "Esa exposición ya está registrada.";
} else {
    echo "Error al guardar la exposición: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="agregar.php">Volver</a>
