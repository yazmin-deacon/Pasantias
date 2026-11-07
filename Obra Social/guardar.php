
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
// 2. RECIBIR Y VALIDAR NOMBRE
// =========================================

$nombre = trim($_POST["nombre_os"] ?? "");

if ($nombre === "") {
    die("Tenés que ingresar el nombre de la obra social.");
}

if (mb_strlen($nombre) > 100) {
    die("El nombre no puede superar los 100 caracteres.");
}

// =========================================
// 3. GUARDAR EN MYSQL
// =========================================

try {

    $sql = "INSERT INTO obra_social
            (nombre_os, activa)
            VALUES (?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nombre
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_close($conexion);

    header("Location: listar.php");
    exit;

} catch (mysqli_sql_exception $e) {

    if ($e->getCode() == 1062) {
        die(
            "Esa obra social ya existe. " .
            '<a href="listar.php">Volver</a>'
        );
    }

    error_log($e->getMessage());
    die("No se pudo guardar la obra social.");
}
?>
