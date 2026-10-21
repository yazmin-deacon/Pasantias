
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Recibir el DNI por la URL =====
if (isset($_GET["dni_p"])) {
    $dni_p = intval($_GET["dni_p"]);
} else {
    $dni_p = 0;
}

if ($dni_p <= 0) {
    echo "<h1>DNI inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}

// ===== BLOQUE 3 — Traer el paciente y verificar que exista =====
$sql = "SELECT dni_p, nombre_p, apellido, cuil, telefono,
               mail, obra_social, id_trabajo
        FROM paciente
        WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) == 0) {
    echo "<h1>No existe ningún paciente con DNI " . $dni_p . "</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    exit;
}

$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

// ===== BLOQUE 4 — Consultar los trabajos para el select =====
$sqlTrabajos = "SELECT id_trabajo, nombre_t
                FROM trabajo
                ORDER BY nombre_t";

$trabajos = mysqli_query($conexion, $sqlTrabajos);

if (!$trabajos) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar paciente</title>
</head>
<body>

<!-- ===== BLOQUE 5 — Título, link y formulario precargado ===== -->
<h1>Editar paciente</h1>

<p><a href="listar.php">Volver al listado</a></p>

<form action="actualizar.php" method="post">

    <input type="hidden" name="dni_p"
           value="<?php echo $paciente["dni_p"]; ?>">

    <p>Nombre:
        <input type="text" name="nombre_p" maxlength="30" required
               value="<?php echo htmlspecialchars($paciente["nombre_p"]); ?>">
    </p>

    <p>Apellido:
        <input type="text" name="apellido" maxlength="30" required
               value="<?php echo htmlspecialchars($paciente["apellido"]); ?>">
    </p>

    <p>CUIL:
        <input type="text" name="cuil" maxlength="11"
               value="<?php echo htmlspecialchars($paciente["cuil"] ?? ""); ?>">
    </p>

    <p>Teléfono:
        <input type="text" name="telefono" maxlength="20" required
               value="<?php echo htmlspecialchars($paciente["telefono"]); ?>">
    </p>

    <p>Mail:
        <input type="email" name="mail" maxlength="100" required
               value="<?php echo htmlspecialchars($paciente["mail"]); ?>">
    </p>

    <p>Obra social:
        <input type="text" name="obra_social" maxlength="50" required
               value="<?php echo htmlspecialchars($paciente["obra_social"]); ?>">
    </p>

    <p>Trabajo:
        <select name="id_trabajo">
            <option value="">-- Sin trabajo asignado --</option>

            <!-- ===== BLOQUE 6 — Una opción por trabajo ===== -->
            <?php while ($trabajo = mysqli_fetch_assoc($trabajos)) { ?>
                <option value="<?php echo $trabajo["id_trabajo"]; ?>"
                    <?php
                    if ($trabajo["id_trabajo"] == $paciente["id_trabajo"]) {
                        echo "selected";
                    }
                    ?>>
                    <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                </option>
            <?php } ?>
        </select>
    </p>

    <p><input type="submit" value="Guardar cambios"></p>

</form>

<?php
// ===== BLOQUE 7 — Cerrar la conexión =====
mysqli_close($conexion);
?>

</body>
</html>
