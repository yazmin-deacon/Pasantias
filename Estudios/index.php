
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar pacientes y sus estudios
$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t,
            COUNT(pe.id_estudio) AS cantidad,
            GROUP_CONCAT(
                e.nombre_es
                ORDER BY e.nombre_es
                SEPARATOR ', '
            ) AS estudios
        FROM paciente p
        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo
        LEFT JOIN paciente_estudio pe
            ON p.dni_p = pe.dni_paciente
        LEFT JOIN estudio e
            ON pe.id_estudio = e.id_estudio
        GROUP BY
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t
        ORDER BY p.apellido ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los pacientes: " . mysqli_error($conexion));
}

// BLOQUE 3 - Contar pacientes
$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudios por paciente</title>
</head>
<body>

    <h1>Estudios a realizar por paciente</h1>

    <p>Pacientes registrados: <?php echo $cantidad; ?></p>

    <a href="agregar.php">Asignar estudios a un paciente</a>

    <br><br>

    <table border="1" cellpadding="10">
        <tr>
            <th>DNI</th>
            <th>Paciente</th>
            <th>Trabajo</th>
            <th>Cantidad de estudios</th>
            <th>Estudios asignados</th>
            <th>Acciones</th>
        </tr>

        <?php while ($paciente = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
                <td><?php echo $paciente["dni_p"]; ?></td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $paciente["nombre_p"] . " " . $paciente["apellido"]
                    );
                    ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($paciente["nombre_t"] ?? "Sin trabajo"); ?>
                </td>

                <td><?php echo $paciente["cantidad"]; ?></td>

                <td>
                    <?php
                    echo htmlspecialchars(
                        $paciente["estudios"] ?? "Sin estudios asignados"
                    );
                    ?>
                </td>

                <td>
                    <a href="editar.php?dni=<?php echo $paciente["dni_p"]; ?>">
                        Editar
                    </a>

                    |

                    <form action="eliminar.php" method="POST" style="display:inline;"
                          onsubmit="return confirm('¿Seguro que querés eliminar los estudios asignados a este paciente?')">

                        <input type="hidden" name="dni"
                               value="<?php echo $paciente["dni_p"]; ?>">

                        <button type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php } ?>

    </table>

    <br>

    <a href="../general/index.html">Volver al inicio</a>

</body>
</html>

<?php
// BLOQUE 4 - Cerrar conexión
mysqli_close($conexion);
?>
