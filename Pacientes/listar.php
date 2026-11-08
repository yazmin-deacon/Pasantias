
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// CONSULTAR PACIENTES
// =========================================

$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            p.cuil,
            p.telefono,
            p.mail,

            COALESCE(
                os.nombre_os,
                'Particular / Sin obra social'
            ) AS obra_social,

            t.nombre_t AS trabajo

        FROM paciente p

        LEFT JOIN obra_social os
            ON p.id_obra_social = os.id_obra_social

        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo

        ORDER BY p.apellido, p.nombre_p";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Listado de pacientes</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >
</head>

<body>

<h1>Listado de pacientes</h1>

<p>
    <a href="alta.php">Nuevo paciente</a>
</p>

<p>
    Cantidad de pacientes cargados:
    <?php echo $cantidad; ?>
</p>

<?php if ($cantidad == 0) { ?>

    <p>Todavía no hay pacientes cargados.</p>

<?php } else { ?>

    <table border="1" cellpadding="5">

        <thead>
            <tr>
                <th>DNI</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>CUIL</th>
                <th>Teléfono</th>
                <th>Mail</th>
                <th>Obra social</th>
                <th>Trabajo</th>
                <th>Editar</th>
                <th>Eliminar</th>
                <th>Historial médico</th>
            </tr>
        </thead>

        <tbody>

        <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>

            <tr>

                <td>
                    <?php echo escapar($fila["dni_p"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["nombre_p"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["apellido"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["cuil"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["telefono"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["mail"]); ?>
                </td>

                <td>
                    <?php echo escapar($fila["obra_social"]); ?>
                </td>

                <td>
                    <?php echo escapar(
                        $fila["trabajo"] ?? "Sin asignar"
                    ); ?>
                </td>

                <td>
                    <a href="editar.php?dni_p=<?php
                        echo urlencode($fila["dni_p"]);
                    ?>">
                        Editar
                    </a>
                </td>

                <td>
                    <a href="eliminar.php?dni_p=<?php
                        echo urlencode($fila["dni_p"]);
                    ?>">
                        Eliminar
                    </a>
                </td>

                <td>
                    <a href="../historial/historial.php?dni_p=<?php
                        echo urlencode($fila["dni_p"]);
                    ?>">
                        Ver historial
                    </a>
                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

<?php } ?>

</body>
</html>

<?php
mysqli_free_result($resultado);
mysqli_close($conexion);
?>
