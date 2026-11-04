
<?php

session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = trim($_POST["nombre"]);
    $dni = trim($_POST["dni"]);
    $mail = trim($_POST["correo"]);
    $contrasena = $_POST["contrasena"];

    $sql = "SELECT * FROM usuario WHERE dni_u = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $dni);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 1) {

        $usuario = $resultado->fetch_assoc();

        if (
            $nombre == $usuario["nombre_u"] &&
            $mail == $usuario["mail"] &&
            password_verify($contrasena, $usuario["contrasena"])
        ) {

            session_regenerate_id(true);

            $_SESSION["dni"] = $usuario["dni_u"];
            $_SESSION["nombre"] = $usuario["nombre_u"];

            header("Location: index.php");
            exit();

        }
    }

    header("Location: login.html?error=1");
    exit();

}

header("Location: login.html");
exit();

?>
