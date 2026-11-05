
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar los trabajos
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY id_trabajo ASC";
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}

// BLOQUE 3 - Contar los trabajos
$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trabajos</title>
</head>
<body>

    <h1>Administración de trabajos</h1>

    <p>Cantidad de trabajos cargados: <?php echo $cantidad; ?></p>

    <a href="agregar.php">Agregar trabajo</a>

    <br><br>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Nombre del trabajo</th>
            <th>Acciones</th>
        </tr>

        <?php while ($trabajo = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
                <td><?php echo $trabajo["id_trabajo"]; ?></td>

                <td>
                    <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                </td>

                <td>
                    <a href="editar.php?id=<?php echo $trabajo["id_trabajo"]; ?>">
                        Editar
                    </a>

                    |

                    <a
                        href="eliminar.php?id=<?php echo $trabajo["id_trabajo"]; ?>"
                        onclick="return confirm('¿Seguro que querés eliminar este trabajo?')"
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
