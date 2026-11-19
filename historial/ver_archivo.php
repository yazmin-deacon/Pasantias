
<?php

session_start();

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =========================================
// VALIDAR SESIÓN
// =========================================

if (empty($_SESSION["dni"])) {
    http_response_code(403);
    exit("No tenés autorización para acceder.");
}

// =========================================
// VALIDAR IDENTIFICADOR
// =========================================

$idHistorial = filter_input(
    INPUT_GET,
    "id_historial",
    FILTER_VALIDATE_INT
);

if (!$idHistorial || $idHistorial <= 0) {
    http_response_code(400);
    exit("Registro inválido.");
}

// =========================================
// BUSCAR ARCHIVO
// =========================================

$stmt = mysqli_prepare(
    $conexion,
    "SELECT archivo
     FROM historial_medico
     WHERE id_historial = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $idHistorial
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$registro = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);
mysqli_close($conexion);

if (!$registro || empty($registro["archivo"])) {
    http_response_code(404);
    exit("No se encontró el archivo.");
}

// =========================================
// VALIDAR NOMBRE Y UBICACIÓN
// =========================================

$nombreArchivo = basename($registro["archivo"]);

$rutaArchivo = __DIR__ . "/../archivos/" . $nombreArchivo;

if (!is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
    http_response_code(404);
    exit("El archivo no está disponible.");
}

// =========================================
// VALIDAR TIPO DE ARCHIVO
// =========================================

$finfo = finfo_open(FILEINFO_MIME_TYPE);

$tipoArchivo = finfo_file(
    $finfo,
    $rutaArchivo
);

finfo_close($finfo);

$tiposPermitidos = [
    "application/pdf" => "pdf",
    "image/jpeg" => "jpg",
    "image/png" => "png"
];

if (!isset($tiposPermitidos[$tipoArchivo])) {
    http_response_code(415);
    exit("Tipo de archivo no permitido.");
}

// =========================================
// MOSTRAR ARCHIVO
// =========================================

header("Content-Type: " . $tipoArchivo);

header(
    "Content-Length: " . filesize($rutaArchivo)
);

header(
    'Content-Disposition: inline; filename="estudio.' .
    $tiposPermitidos[$tipoArchivo] . '"'
);

header("X-Content-Type-Options: nosniff");

header("Cache-Control: private, no-store, no-cache, must-revalidate");

readfile($rutaArchivo);

exit;

?>
