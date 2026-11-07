
<?php
require_once "../Pacientes/conexion.php";

// Obtener el DNI del paciente
if (!isset($_GET["dni_p"]) || empty($_GET["dni_p"])) {
    die("No se indicó el paciente.");
}

$dni_p = $_GET["dni_p"];

// Buscar los datos del paciente
$sql = "SELECT dni_p, nombre_p, apellido
        FROM paciente
        WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
}

// Buscar los estudios indicados
$sql = "SELECT estudio.id_estudio, estudio.nombre_es
        FROM paciente_estudio
        INNER JOIN estudio
        ON paciente_estudio.id_estudio = estudio.id_estudio
        WHERE paciente_estudio.dni_paciente = ?
        ORDER BY estudio.nombre_es";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$estudios = mysqli_stmt_get_result($stmt);

// Buscar el historial médico
$sql = "SELECT historial_medico.id_historial,
               historial_medico.fecha,
               historial_medico.estado,
               historial_medico.observaciones,
               historial_medico.archivo,
               estudio.nombre_es
        FROM historial_medico
        INNER JOIN estudio
        ON historial_medico.id_estudio = estudio.id_estudio
        WHERE historial_medico.dni_paciente = ?
        ORDER BY historial_medico.fecha DESC,
                 historial_medico.id_historial DESC";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$historial = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial médico</title>
</head>
<body>

    <h1>Historial médico</h1>

    <h2>
        <?php
        echo htmlspecialchars($paciente["nombre_p"] . " " . $paciente["apellido"]);
        ?>
    </h2>

    <p>
        DNI:
        <?php echo htmlspecialchars($paciente["dni_p"]); ?>
    </p>

    <p>
        <a href="Pacientes/listar.php">Volver al listado de pacientes</a>
    </p>

    <hr>

    <h2>Estudios indicados</h2>

    <?php if (mysqli_num_rows($estudios) == 0) { ?>

        <p>Este paciente no tiene estudios indicados.</p>

    <?php } else { ?>

        <ul>
            <?php while ($fila = mysqli_fetch_assoc($estudios)) { ?>
                <li>
                    <?php echo htmlspecialchars($fila["nombre_es"]); ?>
                </li>
            <?php } ?>
        </ul>

    <?php } ?>

    <hr>

    <h2>Registros del historial</h2>

    <p>
        <a href="agregar_historial.php?dni_p=<?php echo urlencode($dni_p); ?>">
            Registrar nuevo estudio
        </a>
    </p>

    <?php if (mysqli_num_rows($historial) == 0) { ?>

        <p>Todavía no hay estudios registrados en el historial.</p>

    <?php } else { ?>

        <table border="1" cellpadding="5">

            <tr>
                <th>Estudio</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Observaciones</th>
                <th>Archivo</th>
                <th>Acciones</th>
                <th>Eliminar</th>
            </tr>

            <?php while ($fila = mysqli_fetch_assoc($historial)) { ?>

                <tr>
                    <td>
                        <?php echo htmlspecialchars($fila["nombre_es"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($fila["fecha"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($fila["estado"]); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($fila["observaciones"] ?? ""); ?>
                    </td>

                    <td>
                        <a href="editar_historial.php?id_historial=<?php echo urlencode($fila['id_historial']); ?>">
                         Editar
                        </a>
                    </td>

                    <td>
                        <a href="eliminar_historial.php?id_historial=<?php echo urlencode($fila['id_historial']); ?>"
                         onclick="return confirm('¿Estás seguro de eliminar este registro?');">
                         Eliminar
                        </a>
                    </td>

                    <td>
                        <?php if (!empty($fila["archivo"])) { ?>
                            <a href="../archivos/<?php echo rawurlencode(basename($fila["archivo"])); ?>" target="_blank">
                                Ver archivo
                            </a>
                        <?php } else { ?>
                            Sin archivo
                        <?php } ?>
                    </td>
                </tr>

            <?php } ?>

        </table>

    <?php } ?>

    <?php
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    ?>

</body>
</html>
