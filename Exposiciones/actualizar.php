
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

// Quitar espacios al principio, al final
// y espacios repetidos entre palabras.

function limpiarNombre($nombre) {

    $nombre = trim((string)$nombre);

    return preg_replace(
        '/\s+/u',
        ' ',
        $nombre
    );
}

// Normalizar para comparar sin importar
// mayúsculas ni minúsculas.

function normalizarNombre($nombre) {

    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

// Volver al listado con un mensaje.

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
    "id_exposicion",
    FILTER_VALIDATE_INT
);

$nombre = limpiarNombre(
    $_POST["nombre_ex"] ?? ""
);

// =========================================
// 4. VALIDAR DATOS
// =========================================

if (!$id || $id <= 0) {

    volverAlListado(
        "ID de exposición no válido."
    );
}

if ($nombre === "") {

    volverAlListado(
        "El nombre de la exposición es obligatorio."
    );
}

if (mb_strlen($nombre, "UTF-8") > 50) {

    volverAlListado(
        "El nombre no puede superar los 50 caracteres."
    );
}

// =========================================
// 5. COMPROBAR QUE LA EXPOSICIÓN EXISTA
// =========================================

$sql = "SELECT id_exposicion, nombre_ex
        FROM exposicion
        WHERE id_exposicion = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    volverAlListado(
        "Error al consultar la exposición."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

if (!mysqli_stmt_execute($stmt)) {

    error_log(mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    volverAlListado(
        "No se pudo consultar la exposición."
    );
}

$resultado = mysqli_stmt_get_result($stmt);

$exposicionActual = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$exposicionActual) {

    mysqli_close($conexion);

    volverAlListado(
        "La exposición que querés editar no existe."
    );
}

// =========================================
// 6. COMPROBAR NOMBRES DUPLICADOS
// =========================================

// Buscar las otras exposiciones.
// No incluimos la que estamos editando.

$sql = "SELECT id_exposicion, nombre_ex
        FROM exposicion
        WHERE id_exposicion <> ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    volverAlListado(
        "Error al verificar exposiciones duplicadas."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

if (!mysqli_stmt_execute($stmt)) {

    error_log(mysqli_stmt_error($stmt));

    mysqli_stmt_close($stmt);

    volverAlListado(
        "No se pudieron comprobar los nombres."
    );
}

$resultado = mysqli_stmt_get_result($stmt);

$duplicada = null;

while ($fila = mysqli_fetch_assoc($resultado)) {

    if (
        normalizarNombre($fila["nombre_ex"]) ===
        normalizarNombre($nombre)
    ) {

        $duplicada = $fila;

        break;
    }
}

mysqli_stmt_close($stmt);

// =========================================
// 7. IMPEDIR DUPLICADOS
// =========================================

if ($duplicada !== null) {

    mysqli_close($conexion);

    volverAlListado(
        "No se pudo actualizar: ya existe otra exposición llamada " .
        $duplicada["nombre_ex"] . "."
    );
}

// =========================================
// 8. ACTUALIZAR EXPOSICIÓN
// =========================================

$sql = "UPDATE exposicion
        SET nombre_ex = ?
        WHERE id_exposicion = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    volverAlListado(
        "Error al preparar la actualización."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "si",
    $nombre,
    $id
);

$actualizado = mysqli_stmt_execute($stmt);

// =========================================
// 9. CONTROLAR ERRORES
// =========================================

if (!$actualizado) {

    $codigoError = mysqli_stmt_errno($stmt);

    $mensajeError = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    mysqli_close($conexion);

    // Error de clave duplicada en MySQL

    if ($codigoError == 1062) {

        volverAlListado(
            "Ya existe una exposición registrada con ese nombre."
        );
    }

    error_log($mensajeError);

    volverAlListado(
        "Ocurrió un error al actualizar la exposición."
    );
}

// =========================================
// 10. FINALIZAR
// =========================================

mysqli_stmt_close($stmt);

mysqli_close($conexion);

volverAlListado(
    "Exposición actualizada correctamente: " .
    $nombre
);
?>
