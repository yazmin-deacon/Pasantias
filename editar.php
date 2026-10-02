<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
// ===== BLOQUE 2 — Recibir el id por la URL y asegurarlo =====
if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);
} else {
    $id = 0;
}
if ($id <= 0) {
    echo "<h1>Id inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
// ===== BLOQUE 3 — Traer el alumno y verificar que exista =====
$sql = "SELECT id, nombre, edad, email, idCurso
        FROM alumnos WHERE id = " . $id;
$resultado = mysqli_query($conexion, $sql);
if (mysqli_num_rows($resultado) == 0) {
    echo "<h1>No existe ningún alumno con id " . $id . "</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}
$alumno = mysqli_fetch_assoc($resultado);
// ===== BLOQUE 4 — Consultar los cursos para el select =====
$sqlCursos = "SELECT id, nombre FROM cursos ORDER BY nombre";
$cursos = mysqli_query($conexion, $sqlCursos);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Editar alumno</title>
</head>
<body>
<!-- ===== BLOQUE 5 — Título, link y formulario precargado ===== -->
<h1>Editar alumno</h1>
<p><a href="listar.php">Volver al listado</a></p>
<form action="actualizar.php" method="post">
  <input type="hidden" name="id" value="<?php echo $alumno["id"]; ?>">
  <p>Nombre:
  <input type="text" name="nombre" value="<?php echo $alumno["nombre"]; ?>">
  </p>
  <p>Edad:
  <input type="text" name="edad" value="<?php echo $alumno["edad"]; ?>">
  </p>
  <p>Email:
  <input type="text" name="email" value="<?php echo $alumno["email"]; ?>">
  </p>
  <p>Curso:
  <select name="idCurso">
    <option value="">-- Elegí un curso --</option>
    <!-- ===== BLOQUE 6 — Una option por curso, con selected ===== -->
    <?php while ($curso = mysqli_fetch_assoc($cursos)) { ?>
    <option value="<?php echo $curso["id"]; ?>"
      <?php if ($curso["id"] == $alumno["idCurso"]) { echo "selected"; } ?>>
      <?php echo $curso["nombre"]; ?>
    </option>
    <?php } ?>
  </select>
  </p>
  <p><input type="submit" value="Guardar cambios"></p>
</form>
<?php
// ===== BLOQUE 7 — Cerrar la conexión =====
mysqli_close($conexion);
?>
</body>
</html>
