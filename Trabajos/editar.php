
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("ID de trabajo inválido.");
}

$id = (int) $_GET["id"];

// BLOQUE 3 - Buscar el trabajo
$sql = "SELECT id_trabajo, nombre_t FROM trabajo WHERE id_trabajo = ?";
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar trabajo</title>
</head>
<body>

    <h1>Editar trabajo</h1>

    <form action="actualizar.php" method="POST">

        <input
            type="hidden"
            name="id_trabajo"
            value="<?php echo $trabajo["id_trabajo"]; ?>"
        >

        <p>
            Nombre del trabajo:
            <input
                type="text"
                name="nombre_t"
                maxlength="50"
                value="<?php echo htmlspecialchars($trabajo["nombre_t"]); ?>"
                required
            >
        </p>

        <input type="submit" value="Guardar cambios">

    </form>

    <br>

    <a href="listar.php">Volver al listado</a>

</body>
</html>

<?php
mysqli_close($conexion);
?>
