<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
// ===== BLOQUE 2 — Recibir los datos del formulario y el id oculto =====
if (isset($_POST["id"])) {
    $id = intval($_POST["id"]);
} else {
    $id = 0;
}
if ($id <= 0) {
    echo "<h1>Id inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
$nombre = trim($_POST["nombre"]);
$edad = trim($_POST["edad"]);
$email = trim($_POST["email"]);
$idCurso = intval($_POST["idCurso"]);
$volver = "editar.php?id=" . $id;
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
    echo "<h1>No se pudo actualizar</h1>";
    echo "<ul>" . $errores . "</ul>";
    echo "<p><a href='" . $volver . "'>Volver al formulario</a></p>";
    mysqli_close($conexion);
    exit;
}
// ===== BLOQUE 5 — Preparar el UPDATE y atar las variables =====
$edad = intval($edad);
$sql = "UPDATE alumnos SET nombre = ?, edad = ?, email = ?, idCurso = ?
        WHERE id = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "sisii",
    $nombre, $edad, $email, $idCurso, $id);
// ===== BLOQUE 6 — Ejecutar, cerrar y volver al listado =====
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    header("Location: listar.php");
    exit;
} else {
    echo "<h1>Error al actualizar</h1>";
    echo "<p>" . mysqli_stmt_error($stmt) . "</p>";
    echo "<p><a href='" . $volver . "'>Volver al formulario</a></p>";
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
}
?>
