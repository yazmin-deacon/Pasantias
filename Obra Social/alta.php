
<?php
require_once "conexion.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Agregar obra social</title>
</head>
<body>

<h1>Agregar obra social</h1>

<form action="guardar.php" method="POST">

    <input type="hidden"
           name="token"
           value="<?php
               echo escapar(
                   $_SESSION["token_obras_sociales"]
               );
           ?>">

    <p>
        Nombre de la obra social:

        <input type="text"
               name="nombre_os"
               maxlength="100"
               placeholder="Ej: OSDE"
               required>
    </p>

    <p>
        <input type="submit"
               value="Guardar obra social">
    </p>

</form>

<p>
    <a href="listar.php">
        Volver al listado
    </a>
</p>

</body>
</html>
