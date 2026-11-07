
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. VALIDAR MÉTODO DE SOLICITUD
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: listar.php");
    exit;
}

// =========================================
// 2. FUNCIONES
// =========================================

function limpiarNombre($nombre) {
    $nombre = trim((string)$nombre);

    return preg_replace(
        '/\s+/u',
        ' ',
        $nombre
    );
}

function normalizarNombre($nombre) {
    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

function volverAlListado($mensaje) {
    header(
        "Location: listar.php?mensaje=" .
        urlencode($mensaje)
    );
    exit;
}

// =========================================
// 3. RECIBIR DATOS
// =========================================

$id = filter_input(
    INPUT_POST,
    "id_trabajo",
    FILTER_VALIDATE_INT
);

$nombre = limpiarNombre(
    $_POST["nombre_t"] ?? ""
);

// =========================================
// 4. VALIDAR DATOS
// =========================================

if (!$id || $id <= 0) {
    volverAlListado("ID de trabajo no válido.");
}

if ($nombre === "") {
    volverAlListado("El nombre del trabajo es obligatorio.");
}

if (mb_strlen($nombre, "UTF-8") > 50) {
    volverAlListado(
        "El nombre del trabajo no puede superar los 50 caracteres."
    );
}

// =========================================
// 5. COMPROBAR QUE EL TRABAJO EXISTA
// =========================================

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    volverAlListado("Error al consultar el trabajo.");
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (!mysqli_stmt_execute($stmt)) {
    error_log(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    volverAlListado("No se pudo consultar el trabajo.");
}

$resultado = mysqli_stmt_get_result($stmt);
$trabajoActual = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajoActual) {
    mysqli_close($conexion);

    volverAlListado("El trabajo que querés editar no existe.");
}

// =========================================
// 6. BUSCAR NOMBRES DUPLICADOS
// =========================================

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        WHERE id_trabajo <> ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    volverAlListado("Error al verificar trabajos duplicados.");
}

mysqli_stmt_bind_param($stmt, "i", $id);

if (!mysqli_stmt_execute($stmt)) {
    error_log(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    volverAlListado("No se pudieron comprobar los nombres.");
}

$resultado = mysqli_stmt_get_result($stmt);

$duplicado = null;

while ($fila = mysqli_fetch_assoc($resultado)) {

    if (
        normalizarNombre($fila["nombre_t"]) ===
        normalizarNombre($nombre)
    ) {
        $duplicado = $fila;
        break;
    }
}

mysqli_stmt_close($stmt);

// =========================================
// 7. IMPEDIR DUPLICADOS
// =========================================

if ($duplicado !== null) {

    mysqli_close($conexion);

    volverAlListado(
        "No se pudo actualizar: ya existe otro trabajo llamado " .
        $duplicado["nombre_t"] . "."
    );
}

// =========================================
// 8. ACTUALIZAR EL TRABAJO
// =========================================

$sql = "UPDATE trabajo
        SET nombre_t = ?
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    volverAlListado("Error al preparar la actualización.");
}

mysqli_stmt_bind_param(
    $stmt,
    "si",
    $nombre,
    $id
);

$actualizado = mysqli_stmt_execute($stmt);

if (!$actualizado) {

    $codigoError = mysqli_stmt_errno($stmt);
    $mensajeError = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    if ($codigoError == 1062) {
        volverAlListado(
            "Ya existe un trabajo registrado con ese nombre."
        );
    }

    error_log($mensajeError);

    volverAlListado(
        "Ocurrió un error al actualizar el trabajo."
    );
}

// =========================================
// 9. FINALIZAR
// =========================================

mysqli_stmt_close($stmt);
mysqli_close($conexion);

volverAlListado(
    "Trabajo actualizado correctamente: " . $nombre
);
?>
