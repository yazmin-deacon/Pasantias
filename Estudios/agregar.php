
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

$modoEdicion = $modoEdicion ?? false;

// BLOQUE 2 - Obtener el paciente seleccionado
$dni = isset($_GET["dni"]) ? (int) $_GET["dni"] : 0;

$paciente = null;
$estudiosMarcados = [];
$estudiosHabituales = [];

if ($dni > 0) {

    $sqlPaciente = "SELECT p.dni_p, p.nombre_p, p.apellido,
                           p.id_trabajo, t.nombre_t
                    FROM paciente p
                    LEFT JOIN trabajo t
                        ON p.id_trabajo = t.id_trabajo
                    WHERE p.dni_p = ?";

    $stmt = mysqli_prepare($conexion, $sqlPaciente);
    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultadoPaciente = mysqli_stmt_get_result($stmt);
    $paciente = mysqli_fetch_assoc($resultadoPaciente);

    mysqli_stmt_close($stmt);

    if ($paciente) {

        // BLOQUE 3 - Obtener estudios habituales del trabajo
        if ($paciente["id_trabajo"] !== null) {

            $sqlHabituales = "SELECT id_estudio
                              FROM trabajo_estudio
                              WHERE id_trabajo = ?";

            $stmt = mysqli_prepare($conexion, $sqlHabituales);
            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $paciente["id_trabajo"]
            );

            mysqli_stmt_execute($stmt);
            $resultadoHabituales = mysqli_stmt_get_result($stmt);

            while ($fila = mysqli_fetch_assoc($resultadoHabituales)) {
                $estudiosHabituales[] = (int) $fila["id_estudio"];
            }

            mysqli_stmt_close($stmt);
        }

        // BLOQUE 4 - Obtener estudios que ya tiene asignados
        $sqlAsignados = "SELECT id_estudio
                         FROM paciente_estudio
                         WHERE dni_paciente = ?";

        $stmt = mysqli_prepare($conexion, $sqlAsignados);
        mysqli_stmt_bind_param($stmt, "i", $dni);
        mysqli_stmt_execute($stmt);

        $resultadoAsignados = mysqli_stmt_get_result($stmt);

        while ($fila = mysqli_fetch_assoc($resultadoAsignados)) {
            $estudiosMarcados[] = (int) $fila["id_estudio"];
        }

        mysqli_stmt_close($stmt);

        // Combinar estudios habituales y personalizados
        $estudiosMarcados = array_unique(
            array_merge($estudiosHabituales, $estudiosMarcados)
        );
    }
}

// BLOQUE 5 - Consultar todos los estudios disponibles
$sqlEstudios = "SELECT id_estudio, nombre_es
                FROM estudio
                ORDER BY nombre_es ASC";

$resultadoEstudios = mysqli_query($conexion, $sqlEstudios);

if (!$resultadoEstudios) {
    die("Error al consultar los estudios: " . mysqli_error($conexion));
}

// BLOQUE 6 - Consultar pacientes para el selector
$sqlPacientes = "SELECT dni_p, nombre_p, apellido
                 FROM paciente
                 ORDER BY apellido ASC";

$resultadoPacientes = mysqli_query($conexion, $sqlPacientes);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $modoEdicion ? "Editar estudios" : "Asignar estudios"; ?></title>
</head>
<body>

    <h1>
        <?php echo $modoEdicion ? "Editar estudios del paciente" : "Asignar estudios al paciente"; ?>
    </h1>

    <!-- BLOQUE 7 - Seleccionar paciente -->
    <?php if (!$modoEdicion) { ?>

        <form action="agregar.php" method="GET">

            <label>Seleccionar paciente:</label>

            <select name="dni" required>
                <option value="">Seleccione un paciente</option>

                <?php while ($fila = mysqli_fetch_assoc($resultadoPacientes)) { ?>

                    <option
                        value="<?php echo $fila["dni_p"]; ?>"
                        <?php
                        echo ($dni == $fila["dni_p"]) ? "selected" : "";
                        ?>
                    >
                        <?php
                        echo htmlspecialchars(
                            $fila["apellido"] . ", " . $fila["nombre_p"]
                        );
                        ?>
                        - DNI: <?php echo $fila["dni_p"]; ?>
                    </option>

                <?php } ?>

            </select>

            <button type="submit">Seleccionar</button>

        </form>

        <br>

    <?php } ?>

    <!-- BLOQUE 8 - Mostrar estudios del paciente -->
    <?php if ($dni > 0 && $paciente) { ?>

        <h3>
            Paciente:
            <?php
            echo htmlspecialchars(
                $paciente["nombre_p"] . " " . $paciente["apellido"]
            );
            ?>
        </h3>

        <p>DNI: <?php echo $paciente["dni_p"]; ?></p>

        <p>
            Trabajo:
            <?php echo htmlspecialchars($paciente["nombre_t"] ?? "Sin trabajo"); ?>
        </p>

        <form
            action="<?php echo $modoEdicion ? 'procesar_editar.php' : 'procesar_agregar.php'; ?>"
            method="POST"
        >

            <input type="hidden" name="dni"
                   value="<?php echo $paciente["dni_p"]; ?>">

            <h3>Seleccionar estudios</h3>

            <p>
                Los estudios habituales de su trabajo aparecen
                seleccionados automáticamente.
            </p>

            <?php while ($estudio = mysqli_fetch_assoc($resultadoEstudios)) { ?>

                <div>

                    <label>

                        <input
                            type="checkbox"
                            name="estudios[]"
                            value="<?php echo $estudio["id_estudio"]; ?>"
                            <?php
                            echo in_array(
                                (int) $estudio["id_estudio"],
                                $estudiosMarcados
                            ) ? "checked" : "";
                            ?>
                        >

                        <?php echo htmlspecialchars($estudio["nombre_es"]); ?>

                        <?php
                        if (in_array(
                            (int) $estudio["id_estudio"],
                            $estudiosHabituales
                        )) {
                            echo "(Habitual)";
                        }
                        ?>

                    </label>

                </div>

            <?php } ?>

            <br>

            <button type="submit">
                <?php echo $modoEdicion ? "Guardar cambios" : "Asignar estudios"; ?>
            </button>

        </form>

    <?php } elseif ($dni > 0) { ?>

        <p>El paciente seleccionado no existe.</p>

    <?php } ?>

    <br>

    <a href="index.php">Volver al listado</a>

</body>
</html>

<?php
mysqli_close($conexion);
?>
