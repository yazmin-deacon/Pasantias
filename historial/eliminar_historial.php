
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";
$id_historial = $_GET["id_historial"] ?? "";

if (empty($id_historial)) {
    die("No se indicó el registro.");
}

// Buscar el registro y su archivo
$sql = "SELECT dni_paciente, archivo
        FROM historial_medico
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

// Eliminar el registro
$sql = "DELETE FROM historial_medico
        WHERE id_historial = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_historial);

if (mysqli_stmt_execute($stmt)) {

    // Eliminar el archivo adjunto si existe
    if (!empty($registro["archivo"])) {

        $rutaArchivo = __DIR__ . "/../archivos/" .
                       basename($registro["archivo"]);

        if (is_file($rutaArchivo)) {
            unlink($rutaArchivo);
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: historial.php?dni_p=" . urlencode($dni_p));
    exit;

} else {

    die("No se pudo eliminar el registro: " . mysqli_stmt_error($stmt));
}
?>
