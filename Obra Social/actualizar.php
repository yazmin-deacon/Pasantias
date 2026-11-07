
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
// 2. RECIBIR DATOS
// =========================================

$id = filter_input(
    INPUT_POST,
    "id_obra_social",
    FILTER_VALIDATE_INT
);

$nombre = trim($_POST["nombre_os"] ?? "");

// =========================================
// 3. VALIDAR DATOS
// =========================================

if (!$id || $id <= 0) {
    die("ID de obra social no válido.");
}

if ($nombre === "") {
    die("El nombre de la obra social es obligatorio.");
}

if (mb_strlen($nombre) > 100) {
    die("El nombre no puede superar los 100 caracteres.");
}

// =========================================
// 4. ACTUALIZAR OBRA SOCIAL
// =========================================

try {

    $sql = "UPDATE obra_social
            SET nombre_os = ?
            WHERE id_obra_social = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $nombre,
        $id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_close($conexion);

    header("Location: listar.php");
    exit;

} catch (mysqli_sql_exception $e) {

    if ($e->getCode() == 1062) {
        die(
            "Ya existe una obra social con ese nombre. " .
            '<a href="listar.php">Volver</a>'
        );
    }

    error_log($e->getMessage());
    die("No se pudo actualizar la obra social.");
}
?>
