<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
 
// ===== BLOQUE 2 — Consultar los cursos para el select =====
$sql = "SELECT id, nombre FROM cursos ORDER BY nombre";
$resultado = mysqli_query($conexion, $sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nuevo alumno</title>
</head>
<body>
    <!-- ===== BLOQUE 3 — Título y link de vuelta ===== -->
    <h1>Nuevo alumno</h1>
    <p><a href="listar.php">Volver al listado</a></p>
 
    <!-- ===== BLOQUE 4 — El formulario ===== -->
    <form action="guardar.php" method="post">
        <p>Nombre: <input type="text" name="nombre"></p>
        <p>Edad: <input type="text" name="edad"></p>
        <p>Email: <input type="text" name="email"></p>
        <p>Curso:
            <select name="idCurso">
                <option value="">-- Elegí un curso --</option>
                <!-- ===== BLOQUE 5 — Una option por cada curso ===== -->
                <?php while ($curso = mysqli_fetch_assoc($resultado)) { ?>
                    <option value="<?php echo $curso["id"]; ?>"><?php echo $curso["nombre"]; ?></option>
                <?php } ?>
            </select>
        </p>
        <p><input type="submit" value="Guardar"></p>
    </form>
    <?php
    // ===== BLOQUE 6 — Cerrar la conexión =====
    mysqli_close($conexion);
    ?>
</body>
</html>
