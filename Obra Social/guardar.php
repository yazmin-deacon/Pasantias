
<?php
require_once "conexion.php";

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

// Quitar espacios sobrantes y repetidos
function limpiarNombre($nombre) {

    $nombre = trim((string)$nombre);

    return preg_replace(
        '/\s+/u',
        ' ',
        $nombre
    );
}

// Normalizar para comparar nombres
function normalizarNombre($nombre) {

    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

// =========================================
// 3. RECIBIR DATOS
// =========================================

$nombre = limpiarNombre(
    $_POST["nombre_os"] ?? ""
);

// =========================================
// 4. VALIDAR NOMBRE
// =========================================

if ($nombre === "") {
    die(
        "Tenés que ingresar el nombre de la obra social. " .
        '<a href="alta.php">Volver</a>'
    );
}

if (mb_strlen($nombre, "UTF-8") > 100) {
    die(
        "El nombre no puede superar los 100 caracteres. " .
        '<a href="alta.php">Volver</a>'
    );
}

if (
    normalizarNombre($nombre) ===
    normalizarNombre("Particular / Sin obra social")
) {
    die(
        "Particular / Sin obra social es una opción especial " .
        "y no debe registrarse como obra social. " .
        '<a href="alta.php">Volver</a>'
    );
}

// =========================================
// 5. COMPROBAR SI YA EXISTE
// =========================================

$sql = "SELECT id_obra_social, nombre_os
        FROM obra_social";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {

    error_log(mysqli_error($conexion));

    die("No se pudieron consultar las obras sociales.");
}

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

mysqli_free_result($resultado);

if ($duplicada) {

    mysqli_close($conexion);

    die(
        "<h2>Obra social duplicada</h2>" .
        "<p>Ya existe una obra social registrada " .
        "con ese nombre.</p>" .
        '<p><a href="listar.php">Volver al listado</a></p>'
    );
}

// =========================================
// 6. GUARDAR NUEVA OBRA SOCIAL
// =========================================

$sql = "INSERT INTO obra_social
        (nombre_os, activa)
        VALUES (?, 1)";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {

    error_log(mysqli_error($conexion));

    die("No se pudo preparar el registro.");
}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $nombre
);

$guardado = mysqli_stmt_execute($stmt);

if (!$guardado) {

    $codigoError = mysqli_stmt_errno($stmt);
    $mensajeError = mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    if ($codigoError == 1062) {

        die(
            "<h2>Obra social duplicada</h2>" .
            "<p>Ya existe una obra social " .
            "con ese nombre.</p>" .
            '<p><a href="listar.php">Volver</a></p>'
        );
    }

    error_log($mensajeError);

    die(
        "No se pudo guardar la obra social. " .
        '<a href="alta.php">Volver</a>'
    );
}

// =========================================
// 7. FINALIZAR
// =========================================

mysqli_stmt_close($stmt);
mysqli_close($conexion);

header("Location: listar.php");
exit;
?>
