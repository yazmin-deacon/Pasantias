
<?php
require_once "conexion.php";

// Obtener DNI del paciente
if (!isset($_GET["dni_p"]) || empty($_GET["dni_p"])) {
    die("No se indicó el paciente.");
}

$dni_p = $_GET["dni_p"];

// Buscar paciente
$sql = "SELECT nombre_p, apellido
        FROM paciente
        WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
}

// Buscar estudios indicados al paciente
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
    <title>Registrar estudio</title>
</head>
<body>

    <h1>Registrar estudio</h1>

    <h2>
        <?php echo htmlspecialchars($paciente["nombre_p"] . " " . $paciente["apellido"]); ?>
    </h2>

    <form action="guardar_historial.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="dni_p" value="<?php echo htmlspecialchars($dni_p); ?>">

        <p>
            <label>Estudio:</label><br>

            <select name="id_estudio" required>
                <option value="">Seleccionar estudio</option>

                <?php while ($fila = mysqli_fetch_assoc($estudios)) { ?>
                    <option value="<?php echo $fila["id_estudio"]; ?>">
                        <?php echo htmlspecialchars($fila["nombre_es"]); ?>
                    </option>
                <?php } ?>

            </select>
        </p>

        <p>
            <label>Fecha del estudio:</label><br>
            <input type="date" name="fecha" required>
        </p>

        <p>
            <label>Estado:</label><br>
            <select name="estado" required>
                <option value="Pendiente">Pendiente</option>
                <option value="Realizado">Realizado</option>
            </select>
        </p>

        <p>
            <label>Observaciones:</label><br>
            <textarea name="observaciones" rows="4" cols="40"></textarea>
        </p>

        <p>
            <label>Archivo del estudio (opcional):</label><br>
            <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
        </p>

        <button type="submit">Guardar estudio</button>

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
