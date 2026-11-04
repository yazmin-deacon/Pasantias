
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Recibir los datos del formulario y el DNI oculto =====
if (isset($_POST["dni_p"])) {
    $dni_p = trim($_POST["dni_p"]);
} else {
    $dni_p = "";
}

if ($dni_p == "" || !ctype_digit($dni_p) || strlen($dni_p) > 8 || intval($dni_p) <= 0) {
    echo "<h1>DNI inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}

$nombre_p = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");
$obra_social = trim($_POST["obra_social"] ?? "");
$id_trabajo = trim($_POST["id_trabajo"] ?? "");

$volver = "editar.php?dni_p=" . urlencode($dni_p);

// ===== BLOQUE 3 — Validar los datos =====
$errores = "";

if ($nombre_p == "") {
    $errores .= "<li>El nombre no puede estar vacío.</li>";
} elseif (strlen($nombre_p) > 30) {
    $errores .= "<li>El nombre no puede superar los 30 caracteres.</li>";
}

if ($apellido == "") {
    $errores .= "<li>El apellido no puede estar vacío.</li>";
} elseif (strlen($apellido) > 30) {
    $errores .= "<li>El apellido no puede superar los 30 caracteres.</li>";
}

if ($cuil != "" && (!ctype_digit($cuil) || strlen($cuil) != 11)) {
    $errores .= "<li>El CUIL debe contener 11 dígitos.</li>";
}

if ($telefono == "") {
    $errores .= "<li>El teléfono no puede estar vacío.</li>";
} elseif (strlen($telefono) > 20) {
    $errores .= "<li>El teléfono no puede superar los 20 caracteres.</li>";
}

if ($mail == "") {
    $errores .= "<li>El mail no puede estar vacío.</li>";
} elseif (strlen($mail) > 100) {
    $errores .= "<li>El mail no puede superar los 100 caracteres.</li>";
} elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    $errores .= "<li>El mail no tiene un formato válido.</li>";
}

if ($obra_social == "") {
    $errores .= "<li>La obra social no puede estar vacía.</li>";
} elseif (strlen($obra_social) > 50) {
    $errores .= "<li>La obra social no puede superar los 50 caracteres.</li>";
}

if ($id_trabajo != "" && !ctype_digit($id_trabajo)) {
    $errores .= "<li>El trabajo seleccionado no es válido.</li>";
}

// ===== BLOQUE 4 — Si hay errores, mostrarlos y frenar =====
if ($errores != "") {
    echo "<h1>No se pudo actualizar</h1>";
    echo "<ul>" . $errores . "</ul>";
    echo "<p><a href='" . htmlspecialchars($volver) . "'>Volver al formulario</a></p>";
    mysqli_close($conexion);
    exit;
}

// ===== BLOQUE 5 — Preparar el UPDATE y atar las variables =====
$dni_p = intval($dni_p);
$id_trabajo = ($id_trabajo == "") ? null : intval($id_trabajo);
$cuil = ($cuil == "") ? null : $cuil;

$sql = "UPDATE paciente
        SET nombre_p = ?, apellido = ?, cuil = ?, telefono = ?,
            mail = ?, obra_social = ?, id_trabajo = ?
        WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ssssssii",
    $nombre_p,
    $apellido,
    $cuil,
    $telefono,
    $mail,
    $obra_social,
    $id_trabajo,
    $dni_p
);

// ===== BLOQUE 6 — Ejecutar, cerrar y volver al listado =====
if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    header("Location: listar.php");
    exit;
} else {
    echo "<h1>Error al actualizar</h1>";
    echo "<p>" . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
    echo "<p><a href='" . htmlspecialchars($volver) . "'>Volver al formulario</a></p>";
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
}
?>
