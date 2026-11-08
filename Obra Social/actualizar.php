
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. VALIDAR SOLICITUD
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: listar.php");
    exit;
}

validarToken();

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

// =========================================
// 3. RECIBIR DATOS
// =========================================

$id = filter_input(
    INPUT_POST,
    "id_obra_social",
    FILTER_VALIDATE_INT
);

$nombre = limpiarNombre(
    $_POST["nombre_os"] ?? ""
);

// =========================================
// 4. VALIDAR DATOS
// =========================================

if (!$id || $id <= 0) {

    die(
        "ID de obra social no válido. " .
        '<a href="listar.php">Volver</a>'
    );
}

if ($nombre === "") {

    die(
        "El nombre de la obra social es obligatorio. " .
        '<a href="listar.php">Volver</a>'
    );
}

if (mb_strlen($nombre, "UTF-8") > 100) {

    die(
        "El nombre no puede superar los 100 caracteres. " .
        '<a href="listar.php">Volver</a>'
    );
}

if (
    normalizarNombre($nombre) ===
    normalizarNombre("Particular / Sin obra social")
) {

    die(
        "Ese nombre está reservado para pacientes " .
        "sin obra social. " .
        '<a href="listar.php">Volver</a>'
    );
}

// =========================================
// 5. VERIFICAR QUE EXISTA
// =========================================

$sql = "SELECT id_obra_social
        FROM obra_social
        WHERE id_obra_social = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    die("Error al consultar la obra social.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

if (!mysqli_stmt_execute($stmt)) {

    error_log(mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    die("No se pudo consultar la obra social.");
}

$resultado = mysqli_stmt_get_result($stmt);

$obraActual = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$obraActual) {

    mysqli_close($conexion);

    die(
        "La obra social que querés editar no existe. " .
        '<a href="listar.php">Volver</a>'
    );
}

// =========================================
// 6. COMPROBAR NOMBRES DUPLICADOS
// =========================================

$sql = "SELECT id_obra_social, nombre_os
        FROM obra_social
        WHERE id_obra_social <> ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    die("No se pudo comprobar el nombre.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

if (!mysqli_stmt_execute($stmt)) {

    error_log(mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    die("Error al verificar nombres duplicados.");
}

$resultado = mysqli_stmt_get_result($stmt);

$duplicada = false;

while ($fila = mysqli_fetch_assoc($resultado)) {

    if (
        normalizarNombre($fila["nombre_os"]) ===
        normalizarNombre($nombre)
    ) {

        $duplicada = true;
        break;
    }
}

mysqli_stmt_close($stmt);

if ($duplicada) {

    mysqli_close($conexion);

    die(
        "<h2>Nombre duplicado</h2>" .
        "<p>Ya existe otra obra social " .
        "registrada con ese nombre.</p>" .
        '<p><a href="listar.php">Volver al listado</a></p>'
    );
}

// =========================================
// 7. ACTUALIZAR OBRA SOCIAL
// =========================================

$sql = "UPDATE obra_social
        SET nombre_os = ?
        WHERE id_obra_social = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    die("No se pudo preparar la actualización.");
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

        die(
            "<h2>Nombre duplicado</h2>" .
            "<p>Ya existe una obra social " .
            "con ese nombre.</p>" .
            '<p><a href="listar.php">Volver</a></p>'
        );
    }

    error_log($mensajeError);

    die(
        "No se pudo actualizar la obra social. " .
        '<a href="listar.php">Volver</a>'
    );
}

// =========================================
// 8. FINALIZAR
// =========================================

mysqli_stmt_close($stmt);
mysqli_close($conexion);

header("Location: listar.php");
exit;
?>
