
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar los trabajos
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY id_trabajo ASC";
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}

$trabajos = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $trabajos[] = $fila;
}

if (count($trabajos) == 0) {
    die("Primero tenés que cargar algún trabajo.");
}

// BLOQUE 3 - Seleccionar el trabajo
$id_trabajo = isset($_GET["id_trabajo"])
    ? (int) $_GET["id_trabajo"]
    : (int) $trabajos[0]["id_trabajo"];

$trabajo_valido = false;

foreach ($trabajos as $trabajo) {
    if ($trabajo["id_trabajo"] == $id_trabajo) {
        $trabajo_valido = true;
        break;
    }
}

if (!$trabajo_valido) {
    $id_trabajo = (int) $trabajos[0]["id_trabajo"];
}

// BLOQUE 4 - Consultar las exposiciones
$sql = "SELECT id_exposicion, nombre_ex
        FROM exposicion
        ORDER BY nombre_ex";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar las exposiciones: " . mysqli_error($conexion));
}

$exposiciones = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones[] = $fila;
}

// BLOQUE 5 - Consultar las exposiciones del trabajo seleccionado
$sql = "SELECT id_exposicion
        FROM trabajo_exposicion
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id_trabajo);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$seleccionadas = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $seleccionadas[] = $fila["id_exposicion"];
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relaciones entre trabajos y exposiciones</title>
</head>
<body>

    <h1>Exposiciones por trabajo</h1>

    <!-- Seleccionar trabajo -->
    <form action="listar.php" method="GET">

        <p>
            Seleccionar trabajo:

            <select name="id_trabajo" onchange="this.form.submit()">

                <?php foreach ($trabajos as $trabajo) { ?>

                    <option
                        value="<?php echo $trabajo["id_trabajo"]; ?>"
                        <?php
                        if ($trabajo["id_trabajo"] == $id_trabajo) {
                            echo "selected";
                        }
                        ?>
                    >
                        <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                    </option>

                <?php } ?>

            </select>
        </p>

    </form>

    <hr>

    <h2>Exposiciones habituales</h2>

    <!-- Guardar las relaciones -->
    <form action="guardar.php" method="POST">

        <input
            type="hidden"
            name="id_trabajo"
            value="<?php echo $id_trabajo; ?>"
        >

        <?php if (count($exposiciones) > 0) { ?>

            <p>Seleccioná las exposiciones correspondientes a este trabajo:</p>

            <?php foreach ($exposiciones as $exposicion) { ?>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="exposiciones[]"
                            value="<?php echo $exposicion["id_exposicion"]; ?>"
                            <?php
                            if (in_array($exposicion["id_exposicion"], $seleccionadas)) {
                                echo "checked";
                            }
                            ?>
                        >

                        <?php echo htmlspecialchars($exposicion["nombre_ex"]); ?>
                    </label>
                </p>

            <?php } ?>

            <input type="submit" value="Guardar relaciones">

        <?php } else { ?>

            <p>No hay exposiciones cargadas.</p>

            <a href="../Exposiciones/agregar.php">Agregar exposición</a>

        <?php } ?>

    </form>

    <br>

    <a href="../Trabajos/listar.php">Administrar trabajos</a>

    <br><br>

    <a href="../Exposiciones/listar.php">Administrar exposiciones</a>

    <br><br>
    
    <a href="estudios.php">Administrar estudios por trabajo</a>

    <br><br>
    
    <a href="../general/index.html">Volver al inicio</a>

</body>
</html>

<?php
mysqli_close($conexion);
?>
