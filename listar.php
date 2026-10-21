
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Ejecutar la consulta =====
$sql = "SELECT paciente.dni_p, paciente.nombre_p, paciente.apellido,
               paciente.cuil, paciente.telefono, paciente.mail,
               paciente.obra_social, trabajo.nombre_t AS trabajo
        FROM paciente
        LEFT JOIN trabajo ON paciente.id_trabajo = trabajo.id_trabajo
        ORDER BY paciente.dni_p";

$resultado = mysqli_query($conexion, $sql);

// ===== BLOQUE 3 — Contar los registros =====
if (!$resultado) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Listado de pacientes</title>
</head>
<body>
    <h1>Listado de pacientes</h1>

    <!-- BLOQUE 4 — Link al alta -->
    <p><a href="alta.php">Nuevo paciente</a></p>

    <p>Cantidad de pacientes cargados: <?php echo $cantidad; ?></p>

    <!-- BLOQUE 5 — Caso sin registros -->
    <?php if ($cantidad == 0) { ?>
        <p>Todavía no hay pacientes cargados.</p>
    <?php } else { ?>

    <!-- BLOQUE 6 — Tabla de pacientes -->
    <table border="1" cellpadding="5">
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
        </tr>

        <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>
        <tr>
            <td><?php echo htmlspecialchars($fila["dni_p"]); ?></td>
            <td><?php echo htmlspecialchars($fila["nombre_p"]); ?></td>
            <td><?php echo htmlspecialchars($fila["apellido"]); ?></td>
            <td><?php echo htmlspecialchars($fila["cuil"] ?? ""); ?></td>
            <td><?php echo htmlspecialchars($fila["telefono"]); ?></td>
            <td><?php echo htmlspecialchars($fila["mail"]); ?></td>
            <td><?php echo htmlspecialchars($fila["obra_social"]); ?></td>
            <td><?php echo htmlspecialchars($fila["trabajo"] ?? "Sin asignar"); ?></td>
            <td>
                <a href="editar.php?dni_p=<?php echo urlencode($fila["dni_p"]); ?>">Editar</a>
            </td>
            <td>
                <a href="eliminar.php?dni_p=<?php echo urlencode($fila["dni_p"]); ?>">Eliminar</a>
            </td>
        </tr>
        <?php } ?>
    </table>

    <?php } ?>

    <?php
    // ===== BLOQUE 7 — Cerrar la conexión =====
    mysqli_close($conexion);
    ?>
</body>
</html>
