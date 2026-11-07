<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el DNI del paciente
$dni = isset($_POST["dni"]) ? (int) $_POST["dni"] : 0;

if ($dni <= 0) {
    die("DNI inválido.");
}

// BLOQUE 3 - Quitar las asignaciones del paciente
$sql = "DELETE FROM paciente_estudio WHERE dni_paciente = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "i", $dni);

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    header("Location: estudios.php");
    exit;

} else {

    echo "Error al quitar los estudios: " . htmlspecialchars(mysqli_error($conexion));

}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>