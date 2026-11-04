
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el nombre
if (!isset($_POST["nombre_t"])) {
    die("No se recibió el nombre del trabajo.");
}

$nombre = trim($_POST["nombre_t"]);

if ($nombre === "") {
    die("El nombre del trabajo no puede estar vacío.");
}

// BLOQUE 3 - Preparar la consulta
$sql = "INSERT INTO trabajo (nombre_t) VALUES (?)";
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
    echo "Ese trabajo ya está registrado.";
} else {
    echo "Error al guardar el trabajo: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<br>
<a href="agregar.php">Volver</a>
