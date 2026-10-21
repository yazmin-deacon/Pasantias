
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Consultar los trabajos para el select =====
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY nombre_t";
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nuevo paciente</title>
</head>
<body>

    <!-- ===== BLOQUE 3 — Título y link de vuelta ===== -->
    <h1>Nuevo paciente</h1>
    <p><a href="listar.php">Volver al listado</a></p>

    <!-- ===== BLOQUE 4 — El formulario ===== -->
    <form action="guardar.php" method="post">

        <p>DNI:
            <input type="text" name="dni_p" maxlength="8" required>
        </p>

        <p>Nombre:
            <input type="text" name="nombre_p" maxlength="30" required>
        </p>

        <p>Apellido:
            <input type="text" name="apellido" maxlength="30" required>
        </p>

        <p>CUIL:
            <input type="text" name="cuil" maxlength="11">
        </p>

        <p>Teléfono:
            <input type="text" name="telefono" maxlength="20" required>
        </p>

        <p>Mail:
            <input type="email" name="mail" maxlength="100" required>
        </p>

        <p>Obra social:
            <input type="text" name="obra_social" maxlength="50" required>
        </p>

        <p>Trabajo:
            <select name="id_trabajo">
                <option value="">-- Sin trabajo asignado --</option>

                <!-- ===== BLOQUE 5 — Una opción por cada trabajo ===== -->
                <?php while ($trabajo = mysqli_fetch_assoc($resultado)) { ?>
                    <option value="<?php echo $trabajo["id_trabajo"]; ?>">
                        <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                    </option>
                <?php } ?>
            </select>
        </p>

        <p><input type="submit" value="Guardar"></p>

    </form>

    <?php
    // ===== BLOQUE 6 — Cerrar la conexión =====
    mysqli_close($conexion);
    ?>

</body>
</html>
