<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

$busqueda = trim($_GET["busqueda"] ?? "");

if ($busqueda != "") {
    $sql = "SELECT dni_p, nombre_p, apellido, telefono, mail
            FROM paciente
            WHERE CAST(dni_p AS CHAR) LIKE ?
               OR nombre_p LIKE ?
               OR apellido LIKE ?
            ORDER BY apellido, nombre_p";

    $stmt = mysqli_prepare($conexion, $sql);

    $termino = "%" . $busqueda . "%";

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $termino,
        $termino,
        $termino
    );

    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT dni_p, nombre_p, apellido, telefono, mail
            FROM paciente
            ORDER BY apellido, nombre_p";

    $resultado = mysqli_query($conexion, $sql);
}

if (!$resultado) {
    die("Error al buscar pacientes: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Buscar pacientes</title>
</head>
<body>

    <h1>Buscar pacientes</h1>

    <form action="buscar_paciente.php" method="GET">
        <label for="busqueda">
            DNI, nombre o apellido:
        </label>

        <input
            type="text"
            id="busqueda"
            name="busqueda"
            value="<?php echo htmlspecialchars($busqueda); ?>"
        >

        <button type="submit">Buscar</button>

        <a href="buscar_paciente.php">Mostrar todos</a>
    </form>

    <h3>Pacientes encontrados: <?php echo $cantidad; ?></h3>

    <table border="1" cellpadding="8">
        <thead>
            <tr>
                <th>DNI</th>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Teléfono</th>
                <th>Mail</th>
                <th>Historial médico</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($cantidad > 0) { ?>

                <?php while ($paciente = mysqli_fetch_assoc($resultado)) { ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($paciente["dni_p"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($paciente["nombre_p"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($paciente["apellido"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($paciente["telefono"]); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($paciente["mail"]); ?>
                        </td>

                        <td>
                            <a href="historial.php?dni_p=<?php echo urlencode($paciente["dni_p"]); ?>">
                                Ver historial
                            </a>
                        </td>
                    </tr>
                <?php } ?>

            <?php } else { ?>

                <tr>
                    <td colspan="6">No se encontraron pacientes.</td>
                </tr>

            <?php } ?>
        </tbody>
    </table>

    <p>
        <a href="listar.php">Volver al listado de pacientes</a>
    </p>

</body>
</html>