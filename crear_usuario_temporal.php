
<?php
// Ejecutar solamente desde la terminal.
// No acceder desde el navegador.

if (PHP_SAPI !== "cli") {
    http_response_code(403);
    exit("Acceso no permitido.");
}

require_once __DIR__ . "/Pacientes/conexion.php";

echo "DNI: ";
$dni = trim(fgets(STDIN));

echo "Nombre de usuario: ";
$nombre = trim(fgets(STDIN));

echo "Apellido: ";
$apellido = trim(fgets(STDIN));

echo "Correo electronico: ";
$mail = trim(fgets(STDIN));

echo "Contrasena nueva: ";
$contrasena = rtrim(fgets(STDIN), "\r\n");

if (
    !preg_match('/^[0-9]{7,8}$/', $dni) ||
    $nombre === "" ||
    $apellido === "" ||
    !filter_var($mail, FILTER_VALIDATE_EMAIL) ||
    strlen($contrasena) < 12
) {
    exit("Datos invalidos. Revisalos e intenta nuevamente.\n");
}

$hash = password_hash($contrasena, PASSWORD_DEFAULT);

$sql = "INSERT INTO usuario
        (dni_u, nombre_u, apellido, mail, contrasena)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conexion->prepare($sql);
$stmt->bind_param(
    "sssss",
    $dni,
    $nombre,
    $apellido,
    $mail,
    $hash
);

try {
    $stmt->execute();
    echo "\nUsuario creado correctamente.\n";
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo "\nYa existe un usuario con ese DNI.\n";
    } else {
        echo "\nNo se pudo crear el usuario.\n";
    }
}
?>
