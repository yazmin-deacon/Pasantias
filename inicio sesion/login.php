
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit();
}

$nombre = trim($_POST["nombre"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

$sql = "SELECT dni_u, nombre_u, contrasena
        FROM usuario
        WHERE nombre_u = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $nombre);
$stmt->execute();

$usuario = $stmt->get_result()->fetch_assoc();

if (
    $usuario &&
    password_verify($contrasena, $usuario["contrasena"])
) {
    session_regenerate_id(true);

    $_SESSION["dni"] = $usuario["dni_u"];
    $_SESSION["nombre"] = $usuario["nombre_u"];

    header("Location: index.php");
    exit();
}

header("Location: login.html?error=1");
exit();
?>
