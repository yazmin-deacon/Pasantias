
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("ID de exposición inválido.");
}

$id = (int) $_GET["id"];

// BLOQUE 3 - Buscar la exposición
$sql = "SELECT id_exposicion, nombre_ex FROM exposicion WHERE id_exposicion = ?";
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar exposición</title>
</head>
<body>

    <h1>Editar exposición</h1>

    <form action="actualizar.php" method="POST">

        <input
            type="hidden"
            name="id_exposicion"
            value="<?php echo $exposicion["id_exposicion"]; ?>"
        >

        <p>
            Nombre de la exposición:
            <input
                type="text"
                name="nombre_ex"
                maxlength="50"
                value="<?php echo htmlspecialchars($exposicion["nombre_ex"]); ?>"
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
