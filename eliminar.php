<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
 
// ===== BLOQUE 2 — Recibir el id (por POST si viene la confirmación, por GET si es la primera visita) =====
if (isset($_POST["id"])) {
    $id = intval($_POST["id"]);
    $confirmado = true;
} elseif (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
    $confirmado = false;
} else {
    $id = 0;
    $confirmado = false;
}
 
if ($id <= 0) {
    echo "<h1>Id inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
 
// ===== BLOQUE 3 — Si viene la confirmación, eliminar y volver al listado =====
if ($confirmado) {
    $sql = "DELETE FROM alumnos WHERE id = " . $id;
    if (mysqli_query($conexion, $sql)) {
        mysqli_close($conexion);
        header("Location: listar.php");
        exit;
    }
 
    // ===== BLOQUE 4 — Si falló, traducir el error 1451 =====
    if (mysqli_errno($conexion) == 1451) {
        echo "<h1>No se puede eliminar</h1>";
        echo "<p>Este registro tiene otros datos asociados. Primero eliminá o cambiá esos datos.</p>";
    } else {
        echo "<h1>Error al eliminar</h1>";
        echo "<p>" . mysqli_error($conexion) . "</p>";
    }
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
 
// ===== BLOQUE 5 — Primera visita: buscar el alumno para mostrar su nombre =====
$sql = "SELECT nombre FROM alumnos WHERE id = " . $id;
$resultado = mysqli_query($conexion, $sql);
 
if (mysqli_num_rows($resultado) == 0) {
    echo "<h1>No existe ningún alumno con id " . $id . "</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
 
$alumno = mysqli_fetch_assoc($resultado);
mysqli_close($conexion);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Eliminar alumno</title>
</head>
<body>
    <!-- ===== BLOQUE 6 — Pantalla de confirmación ===== -->
    <h1>¿Eliminar alumno?</h1>
    <p>Vas a eliminar a <strong><?php echo $alumno["nombre"]; ?></strong>. Esta acción no se puede deshacer.</p>
    <form action="eliminar.php" method="post">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="submit" value="Sí, eliminar">
    </form>
    <p><a href="listar.php">No, volver al listado</a></p>
</body>
</html>
