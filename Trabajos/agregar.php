
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar trabajo</title>
</head>
<body>

    <h1>Agregar trabajo</h1>

    <form action="guardar.php" method="POST">

        <p>
            Nombre del trabajo:
            <input
                type="text"
                name="nombre_t"
                maxlength="50"
                required
            >
        </p>

        <input type="submit" value="Guardar">

    </form>

    <br>

    <a href="listar.php">Volver al listado</a>

</body>
</html>
