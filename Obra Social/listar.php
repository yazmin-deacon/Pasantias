
<?php
require_once "conexion.php";

// =========================================
// CONSULTAR OBRAS SOCIALES
// =========================================

$sql = "SELECT id_obra_social, nombre_os, activa
        FROM obra_social
        ORDER BY nombre_os ASC";

$resultado = mysqli_query($conexion, $sql);

$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Obras sociales</title>
</head>
<body>

<h1>Administrar obras sociales</h1>

<p>
    <a href="alta.php">Agregar obra social</a>
</p>

<p>
    Obras sociales registradas:
    <?php echo $cantidad; ?>
</p>

<?php if ($cantidad == 0) { ?>

    <p>No hay obras sociales registradas.</p>

<?php } else { ?>

    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Estado</th>
            <th>Editar</th>
            <th>Activar / Desactivar</th>
        </tr>

        <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>

            <tr>
                <td>
                    <?php echo (int)$fila["id_obra_social"]; ?>
                </td>

                <td>
                    <?php echo escapar($fila["nombre_os"]); ?>
                </td>

                <td>
                    <?php
                    echo $fila["activa"]
                        ? "Activa"
                        : "Inactiva";
                    ?>
                </td>

                <td>
                    <a href="editar.php?id_obra_social=<?php
                        echo (int)$fila["id_obra_social"];
                    ?>">
                        Editar
                    </a>
                </td>

                <td>
                    <form action="eliminar.php" method="POST">

                        <input type="hidden"
                               name="token"
                               value="<?php
                                   echo escapar(
                                       $_SESSION["token_obras_sociales"]
                                   );
                               ?>">

                        <input type="hidden"
                               name="id_obra_social"
                               value="<?php
                                   echo (int)$fila["id_obra_social"];
                               ?>">

                        <button type="submit"
                            onclick="return confirm('¿Confirmar cambio de estado?');">
                            <?php
                            echo $fila["activa"]
                                ? "Desactivar"
                                : "Activar";
                            ?>
                        </button>
                    </form>
                </td>
            </tr>

        <?php } ?>

    </table>

<?php } ?>

<?php
mysqli_free_result($resultado);
mysqli_close($conexion);
?>

</body>
</html>
