
<?php
require_once "../Pacientes/conexion.php";

$id_historial = $_GET["id_historial"] ?? "";

if (empty($id_historial)) {
    die("No se indicó el registro.");
}

// Buscar el registro
$sql = "SELECT historial_medico.*,
               estudio.nombre_es
        FROM historial_medico
        INNER JOIN estudio
        ON historial_medico.id_estudio = estudio.id_estudio
        WHERE id_historial = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_historial);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$registro = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$registro) {
    die("No se encontró el registro.");
}

$dni_p = $registro["dni_paciente"];

// Buscar los estudios indicados al paciente
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar estudio</title>
</head>
<body>

    <h1>Editar registro médico</h1>

    <form action="actualizar_historial.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="id_historial"
               value="<?php echo htmlspecialchars($registro["id_historial"]); ?>">

        <input type="hidden" name="dni_p"
               value="<?php echo htmlspecialchars($dni_p); ?>">

        <p>
            <label>Estudio:</label><br>

            <select name="id_estudio" required>
                <?php while ($fila = mysqli_fetch_assoc($estudios)) { ?>
                    <option value="<?php echo $fila["id_estudio"]; ?>"
                        <?php echo ($fila["id_estudio"] == $registro["id_estudio"]) ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($fila["nombre_es"]); ?>
                    </option>
                <?php } ?>
            </select>
        </p>

        <p>
            <label>Fecha:</label><br>
            <input type="date" name="fecha" required
                   value="<?php echo htmlspecialchars($registro["fecha"]); ?>">
        </p>

        <p>
            <label>Estado:</label><br>

            <select name="estado" required>
                <option value="Pendiente"
                    <?php echo ($registro["estado"] == "Pendiente") ? "selected" : ""; ?>>
                    Pendiente
                </option>

                <option value="Realizado"
                    <?php echo ($registro["estado"] == "Realizado") ? "selected" : ""; ?>>
                    Realizado
                </option>
            </select>
        </p>

        <p>
            <label>Observaciones:</label><br>
            <textarea name="observaciones" rows="4" cols="40"><?php
                echo htmlspecialchars($registro["observaciones"] ?? "");
            ?></textarea>
        </p>

        <p>
            <label>Archivo actual:</label><br>

            <?php if (!empty($registro["archivo"])) { ?>
                <a href="../archivos/<?php echo rawurlencode(basename($registro["archivo"])); ?>"
                   target="_blank">Ver archivo actual</a>
            <?php } else { ?>
                Sin archivo
            <?php } ?>
        </p>

        <p>
            <label>Reemplazar archivo (opcional):</label><br>
            <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
        </p>

        <button type="submit">Guardar cambios</button>

    </form>

    <p>
        <a href="historial.php?dni_p=<?php echo urlencode($dni_p); ?>">
            Volver al historial
        </a>
    </p>

</body>
</html>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
