
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

$id_historial = $_POST["id_historial"] ?? "";
$dni_p = $_POST["dni_p"] ?? "";
$id_estudio = $_POST["id_estudio"] ?? "";
$fecha = $_POST["fecha"] ?? "";
$estado = $_POST["estado"] ?? "";
$observaciones = trim($_POST["observaciones"] ?? "");

if (
    empty($id_historial) ||
    empty($dni_p) ||
    empty($id_estudio) ||
    empty($fecha) ||
    !in_array($estado, ["Pendiente", "Realizado"])
) {
    die("Faltan datos obligatorios o hay datos inválidos.");
}

// Validar fecha
$fechaObjeto = DateTime::createFromFormat("Y-m-d", $fecha);

if (!$fechaObjeto || $fechaObjeto->format("Y-m-d") !== $fecha) {
    die("La fecha no es válida.");
}

// Buscar el registro original
$sql = "SELECT archivo
        FROM historial_medico
        WHERE id_historial = ? AND dni_paciente = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id_historial, $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$registro = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$registro) {
    die("No se encontró el registro.");
}

// Comprobar que el estudio esté indicado al paciente
$sql = "SELECT id_estudio
        FROM paciente_estudio
        WHERE dni_paciente = ? AND id_estudio = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ii", $dni_p, $id_estudio);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$estudio = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$estudio) {
    die("El estudio no está indicado para este paciente.");
}

// Conservar el archivo anterior
$archivo = $registro["archivo"];
$rutaArchivo = null;

// Procesar archivo nuevo
if (isset($_FILES["archivo"]) && $_FILES["archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES["archivo"]["error"] !== UPLOAD_ERR_OK) {
        die("Error al subir el archivo.");
    }

    if ($_FILES["archivo"]["size"] > 5 * 1024 * 1024) {
        die("El archivo supera los 5 MB.");
    }

    $tiposPermitidos = [
        "application/pdf" => "pdf",
        "image/jpeg" => "jpg",
        "image/png" => "png"
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tipoArchivo = finfo_file($finfo, $_FILES["archivo"]["tmp_name"]);
    finfo_close($finfo);

    if (!isset($tiposPermitidos[$tipoArchivo])) {
        die("Solo se permiten archivos PDF, JPG y PNG.");
    }

    $extension = $tiposPermitidos[$tipoArchivo];

    $carpeta = __DIR__ . "/../archivos/";

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    $nuevoNombre = bin2hex(random_bytes(16)) . "." . $extension;
    $rutaArchivo = $carpeta . $nuevoNombre;

    if (!move_uploaded_file($_FILES["archivo"]["tmp_name"], $rutaArchivo)) {
        die("No se pudo guardar el archivo.");
    }

    $archivo = $nuevoNombre;
}

// Actualizar registro
$sql = "UPDATE historial_medico
        SET id_estudio = ?, fecha = ?, estado = ?,
            observaciones = ?, archivo = ?
        WHERE id_historial = ? AND dni_paciente = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "issssii",
    $id_estudio,
    $fecha,
    $estado,
    $observaciones,
    $archivo,
    $id_historial,
    $dni_p
);

if (mysqli_stmt_execute($stmt)) {

    // Eliminar archivo anterior si fue reemplazado
    if ($rutaArchivo && !empty($registro["archivo"])) {
        $archivoAnterior = __DIR__ . "/../archivos/" . basename($registro["archivo"]);

        if (is_file($archivoAnterior)) {
            unlink($archivoAnterior);
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: historial.php?dni_p=" . urlencode($dni_p));
    exit;

} else {

    if ($rutaArchivo && file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }

    die("Error al actualizar: " . mysqli_stmt_error($stmt));
}
?>
