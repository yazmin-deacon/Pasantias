<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";
 
// ===== BLOQUE 2 — Ejecutar la consulta (con INNER JOIN) =====
$sql = "SELECT alumnos.id, alumnos.nombre, alumnos.edad, alumnos.email, cursos.nombre AS curso
        FROM alumnos
        INNER JOIN cursos ON alumnos.idCurso = cursos.id
        ORDER BY alumnos.id";
$resultado = mysqli_query($conexion, $sql);
 
// ===== BLOQUE 3 — Contar cuántos registros vinieron =====
$cantidad = mysqli_num_rows($resultado);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Listado de alumnos</title>
</head>
<body>
    <h1>Listado de alumnos</h1>
    <!-- ===== BLOQUE 4 — Link al alta ===== -->
    <p><a href="alta.php">Nuevo alumno</a></p>
    <p>Cantidad de alumnos cargados: <?php echo $cantidad; ?></p>
 
    <!-- ===== BLOQUE 5 — Caso sin registros ===== -->
    <?php if ($cantidad == 0) { ?>
        <p>Todavía no hay alumnos cargados.</p>
    <?php } else { ?>
 
    <!-- ===== BLOQUE 6 — Tabla con una fila por cada alumno, ahora con columna Editar (SE MODIFICA) ===== -->
    <table border="1" cellpadding="5">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Edad</th>
            <th>Email</th>
            <th>Curso</th>
            <!-- (NUEVO) -->
            <th>Editar</th>
            <th>Eliminar</th>
        </tr>
        <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>
        <tr>
            <td><?php echo $fila["id"]; ?></td>
            <td><?php echo $fila["nombre"]; ?></td>
            <td><?php echo $fila["edad"]; ?></td>
            <td><?php echo $fila["email"]; ?></td>
            <td><?php echo $fila["curso"]; ?></td>
            <!-- (NUEVO) -->
            <td><a href="editar.php?id=<?php echo $fila["id"]; ?>">Editar</a></td>
            <td><a href="eliminar.php?id=<?php echo $fila["id"]; ?>">Eliminar</a></td>
        </tr>
        <?php } ?>
    </table>
    <?php } ?>
 
    <?php
    // ===== BLOQUE 7 — Cerrar la conexión =====
    mysqli_close($conexion);
    ?>
</body>
</html>
