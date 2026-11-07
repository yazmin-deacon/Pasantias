```php
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar trabajos
$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        ORDER BY nombre_t ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar trabajos - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">
</head>

<body>

    <header>
        <div class="logo">Clínica</div>

        <nav>
            <a href="../menu_principal.html">Menú principal</a>
            <a href="../gestionar_exposiciones.html">Exposiciones</a>
            <a href="listar.php">Trabajos</a>
            <a href="../gestionar_relaciones.html">Relaciones</a>
            <a href="../gestionar_estudios.html">Estudios</a>
        </nav>

        <button type="button" id="btn-tema">Modo oscuro</button>
    </header>

    <main class="gestion-contenido">

        <section class="bienvenida">
            <h1>Administrar trabajos</h1>
            <p>Registrá y mantené actualizado el catálogo de trabajos.</p>
        </section>

        <section class="tarjeta">

            <h2>Agregar trabajo</h2>

            <form class="form-gestion" action="agregar.php" method="POST">

                <div class="campo">
                    <label for="nombre_t">Nombre del trabajo</label>

                    <input type="text" id="nombre_t" name="nombre_t" maxlength="50" placeholder="Ej.: Administrativo"
                        required>
                </div>

                <button type="submit">Guardar trabajo</button>

            </form>

        </section>

        <section class="tarjeta">

            <h2>Trabajos registrados</h2>

            <p>Trabajos registrados:
                <?php echo $cantidad; ?>
            </p>

            <br>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($trabajo = mysqli_fetch_assoc($resultado)) { ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                            </td>

                            <td class="acciones">

                                <a href="editar.php?id=<?php echo $trabajo[" id_trabajo"]; ?>"
                                    class="btn-secundario"
                                    >
                                    Editar
                                </a>

                            </td>

                        </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <footer>
        <span>Clínica</span>
        <span>Administración de trabajos</span>
    </footer>

    <script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>

</html>

<?php
// BLOQUE 3 - Cerrar conexión
mysqli_close($conexion);
?>
```