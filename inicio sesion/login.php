
<?php

session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// SOLO PERMITIR POST
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit;
}

// =========================================
// RECIBIR DATOS
// =========================================

$nombre = trim(
    (string)($_POST["nombre"] ?? "")
);

$dni = preg_replace(
    '/\D/',
    '',
    (string)($_POST["dni"] ?? "")
);

$mail = trim(
    (string)($_POST["correo"] ?? "")
);

$contrasena = (string)(
    $_POST["contrasena"] ?? ""
);

// =========================================
// FUNCIÓN PARA VOLVER AL LOGIN
// =========================================

function rechazarLogin() {
    header("Location: login.html?error=1");
    exit;
}

// =========================================
// VALIDACIONES INICIALES
// =========================================

if (
    $nombre === "" ||
    !preg_match('/^[0-9]{7,8}$/', $dni) ||
    !filter_var($mail, FILTER_VALIDATE_EMAIL) ||
    $contrasena === ""
) {
    rechazarLogin();
}

// =========================================
// BUSCAR USUARIO POR DNI
// =========================================

$sql = "SELECT
            dni_u,
            nombre_u,
            mail,
            contrasena

        FROM usuario

        WHERE dni_u = ?

        LIMIT 1";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    exit("No se pudo procesar el inicio de sesión.");
}

$dniNumero = (int)$dni;

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dniNumero
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    http_response_code(500);
    exit("No se pudo procesar el inicio de sesión.");
}

$resultado = mysqli_stmt_get_result($stmt);

$usuario = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);
mysqli_close($conexion);

// =========================================
// COMPROBAR LOS CUATRO CAMPOS
// =========================================

if (!$usuario) {
    rechazarLogin();
}

// Comparación del nombre sin distinguir
// mayúsculas y minúsculas.

$nombreCorrecto = (
    mb_strtolower($nombre, "UTF-8") ===
    mb_strtolower($usuario["nombre_u"], "UTF-8")
);

$mailCorrecto = (
    strcasecmp($mail, $usuario["mail"]) === 0
);

$contrasenaCorrecta = password_verify(
    $contrasena,
    $usuario["contrasena"]
);

if (
    !$nombreCorrecto ||
    !$mailCorrecto ||
    !$contrasenaCorrecta
) {
    rechazarLogin();
}

// =========================================
// INICIAR SESIÓN
// =========================================

session_regenerate_id(true);

$_SESSION["dni"] = $usuario["dni_u"];

$_SESSION["nombre"] = $usuario["nombre_u"];

// =========================================
// REDIRIGIR AL MENÚ PRINCIPAL
// =========================================

header("Location: index.php");
exit;

?>
