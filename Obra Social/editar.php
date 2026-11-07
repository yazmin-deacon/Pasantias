
<?php
require_once "conexion.php";

// =========================================
// 1. OBTENER ID
// =========================================

$id = filter_input(
    INPUT_GET,
    "id_obra_social",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    die("ID de obra social no válido.");
}

// =========================================
// 2. CONSULTAR OBRA SOCIAL
// =========================================

$sql = "SELECT id_obra_social, nombre_os, activa
        FROM obra_social
        WHERE id_obra_social = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$obra = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$obra) {
    die("No se encontró la obra social.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Editar obra social</title>
</head>
<body>

<h1>Editar obra social</h1>

<form action="actualizar.php" method="POST">

    <input type="hidden"
           name="token"
           value="<?php
               echo escapar(
                   $_SESSION["token_obras_sociales"]
               );
           ?>">

    <input type="hidden"
           name="id_obra_social"
           value="<?php echo (int)$obra["id_obra_social"]; ?>">

    <p>
        Nombre:

        <input type="text"
               name="nombre_os"
               maxlength="100"
               required
               value="<?php echo escapar($obra["nombre_os"]); ?>">
    </p>

    <p>
        Estado actual:
        <?php
        echo $obra["activa"] ? "Activa" : "Inactiva";
        ?>
    </p>

    <p>
        <input type="submit"
               value="Guardar cambios">
    </p>

</form>

<p>
    <a href="listar.php">
        Volver al listado
    </a>
</p>

</body>
</html>

<?php mysqli_close($conexion); ?>
