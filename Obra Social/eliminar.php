
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
// 2. OBTENER ID
// =========================================

$id = filter_input(
    INPUT_POST,
    "id_obra_social",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    die("ID de obra social no válido.");
}

// =========================================
// 3. CAMBIAR ESTADO
// =========================================

try {

    // Si está activa pasa a inactiva.
    // Si está inactiva vuelve a estar activa.

    $sql = "UPDATE obra_social
            SET activa = NOT activa
            WHERE id_obra_social = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_close($conexion);

    header("Location: listar.php");
    exit;

} catch (mysqli_sql_exception $e) {

    error_log($e->getMessage());
    die("No se pudo cambiar el estado de la obra social.");
}
?>
