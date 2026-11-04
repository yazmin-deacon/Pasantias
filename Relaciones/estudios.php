
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar los trabajos
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY nombre_t";
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

// BLOQUE 4 - Consultar los estudios
$sql = "SELECT id_estudio, nombre_es
        FROM estudio
        ORDER BY nombre_es";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los estudios: " . mysqli_error($conexion));
}

$estudios = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios[] = $fila;
}

// BLOQUE 5 - Consultar los estudios del trabajo seleccionado
$sql = "SELECT id_estudio
        FROM trabajo_estudio
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id_trabajo);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$seleccionados = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $seleccionados[] = $fila["id_estudio"];
}

mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudios por trabajo</title>
</head>
<body>

    <h1>Estudios por trabajo</h1>

    <!-- Seleccionar trabajo -->
    <form action="estudios.php" method="GET">

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

    <h2>Estudios habituales</h2>

    <!-- Guardar las relaciones -->
    <form action="guardar_estudios.php" method="POST">

        <input
            type="hidden"
            name="id_trabajo"
            value="<?php echo $id_trabajo; ?>"
        >

        <?php if (count($estudios) > 0) { ?>

            <p>Seleccioná los estudios correspondientes a este trabajo:</p>

            <?php foreach ($estudios as $estudio) { ?>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="estudios[]"
                            value="<?php echo $estudio["id_estudio"]; ?>"
                            <?php
                            if (in_array($estudio["id_estudio"], $seleccionados)) {
                                echo "checked";
                            }
                            ?>
                        >

                        <?php echo htmlspecialchars($estudio["nombre_es"]); ?>
                    </label>
                </p>

            <?php } ?>

            <input type="submit" value="Guardar relaciones">

        <?php } else { ?>

            <p>No hay estudios cargados.</p>

        <?php } ?>

    </form>

    <br>

    <a href="listar.php">Administrar exposiciones por trabajo</a>

    <br><br>

    <a href="../Trabajos/listar.php">Administrar trabajos</a>

    <br><br>

    <a href="../general/index.html">Volver al inicio</a>

</body>
</html>

<?php
mysqli_close($conexion);
?>
