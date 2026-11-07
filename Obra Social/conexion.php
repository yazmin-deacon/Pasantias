
<?php
require_once "../Pacientes/conexion.php";

// Mostrar errores de MySQL como excepciones.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Iniciar sesión para proteger los formularios.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["token_obras_sociales"])) {
    $_SESSION["token_obras_sociales"] =
        bin2hex(random_bytes(32));
}

// Mostrar textos de forma segura.
function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Comprobar que el formulario sea válido.
function validarToken() {
    $token = $_POST["token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["token_obras_sociales"] ?? "",
            $token
        )
    ) {
        http_response_code(403);
        die("Solicitud no autorizada.");
    }
}
?>
