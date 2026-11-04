
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar las exposiciones
$sql = "SELECT id_exposicion, nombre_ex FROM exposicion ORDER BY nombre_ex";
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar las exposiciones: " . mysqli_error($conexion));
}

// BLOQUE 3 - Contar las exposiciones
$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exposiciones</title>
</head>
<body>

    <h1>Administración de exposiciones</h1>

    <p>Cantidad de exposiciones cargadas: <?php echo $cantidad; ?></p>

    <a href="agregar.php">Agregar exposición</a>

    <br><br>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Nombre de la exposición</th>
            <th>Acciones</th>
        </tr>

        <?php while ($exposicion = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
                <td><?php echo $exposicion["id_exposicion"]; ?></td>

                <td>
                    <?php echo htmlspecialchars($exposicion["nombre_ex"]); ?>
                </td>

                <td>
                    <a href="editar.php?id=<?php echo $exposicion["id_exposicion"]; ?>">
                        Editar
                    </a>

                    |

                    <a
                        href="eliminar.php?id=<?php echo $exposicion["id_exposicion"]; ?>"
                        onclick="return confirm('¿Seguro que querés eliminar esta exposición?')"
                    >
                        Eliminar
                    </a>
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
