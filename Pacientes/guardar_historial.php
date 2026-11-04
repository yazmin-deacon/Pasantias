
<?php
require_once "conexion.php";

// Obtener los datos del formulario
$dni_p = $_POST["dni_p"] ?? "";
$id_estudio = $_POST["id_estudio"] ?? "";
$fecha = $_POST["fecha"] ?? "";
$estado = $_POST["estado"] ?? "";
$observaciones = trim($_POST["observaciones"] ?? "");

$archivo = null;
$rutaArchivo = null;

// Validar campos obligatorios
if (
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
    die("La fecha ingresada no es válida.");
}

// Comprobar que el paciente exista
$sql = "SELECT dni_p FROM paciente WHERE dni_p = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
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
    die("Este estudio no está indicado para el paciente.");
}

// Procesar archivo si se adjuntó uno
if (isset($_FILES["archivo"]) && $_FILES["archivo"]["error"] !== UPLOAD_ERR_NO_FILE) {

    if ($_FILES["archivo"]["error"] !== UPLOAD_ERR_OK) {
        die("Ocurrió un error al subir el archivo.");
    }

    if ($_FILES["archivo"]["size"] > 5 * 1024 * 1024) {
        die("El archivo supera el límite de 5 MB.");
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

    $nombreArchivo = bin2hex(random_bytes(16)) . "." . $extension;
    $rutaArchivo = $carpeta . $nombreArchivo;

    if (!move_uploaded_file($_FILES["archivo"]["tmp_name"], $rutaArchivo)) {
        die("No se pudo guardar el archivo.");
    }

    $archivo = $nombreArchivo;
}

// Guardar el registro en el historial
$sql = "INSERT INTO historial_medico
        (dni_paciente, id_estudio, fecha, estado, observaciones, archivo)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    if ($rutaArchivo && file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }

    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param(
    $stmt,
    "iissss",
    $dni_p,
    $id_estudio,
    $fecha,
    $estado,
    $observaciones,
    $archivo
);

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: historial.php?dni_p=" . urlencode($dni_p));
    exit;

} else {

    if ($rutaArchivo && file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }

    $error = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    die("No se pudo guardar el estudio: " . $error);
}
?>
