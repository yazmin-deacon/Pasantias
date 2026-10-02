<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
 
// ===== BLOQUE 2 — Recibir los datos del formulario =====
$nombre = trim($_POST["nombre"]);
$edad = trim($_POST["edad"]);
$email = trim($_POST["email"]);
$idCurso = intval($_POST["idCurso"]);
 
// ===== BLOQUE 3 — Validar: vacío -> tipo -> condición =====
$errores = "";
 
if (empty($nombre)) {
    $errores .= "<li>El nombre no puede estar vacío.</li>";
} elseif (strlen($nombre) > 50) {
    $errores .= "<li>El nombre no puede superar los 50 caracteres.</li>";
}
 
if (empty($edad)) {
    $errores .= "<li>La edad no puede estar vacía.</li>";
} elseif (!is_numeric($edad)) {
    $errores .= "<li>La edad tiene que ser un número.</li>";
} elseif ($edad < 12 || $edad > 99) {
    $errores .= "<li>La edad tiene que estar entre 12 y 99.</li>";
}
 
if (empty($email)) {
    $errores .= "<li>El email no puede estar vacío.</li>";
} elseif (strlen($email) > 100) {
    $errores .= "<li>El email no puede superar los 100 caracteres.</li>";
}
 
if (empty($idCurso)) {
    $errores .= "<li>Tenés que elegir un curso.</li>";
}
 
// ===== BLOQUE 4 — Si hay errores, mostrarlos y frenar =====
if ($errores != "") {
    echo "<h1>No se pudo guardar</h1>";
    echo "<ul>" . $errores . "</ul>";
    echo "<p><a href='alta.php'>Volver al formulario</a></p>";
    mysqli_close($conexion);
    exit;
}
 
// ===== BLOQUE 5 — Preparar la consulta y atar las variables =====
$edad = intval($edad);
$sql = "INSERT INTO alumnos (nombre, edad, email, idCurso) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "sisi", $nombre, $edad, $email, $idCurso);
 
// ===== BLOQUE 6 — Ejecutar, cerrar y volver al listado =====
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    header("Location: listar.php");
    exit;
} else {
    echo "<h1>Error al guardar</h1>";
    echo "<p>" . mysqli_stmt_error($stmt) . "</p>";
    echo "<p><a href='alta.php'>Volver al formulario</a></p>";
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
}
?>
