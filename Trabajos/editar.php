
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Recibir el ID
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    die("ID de trabajo inválido.");
}

$id = (int) $_GET["id"];

// BLOQUE 3 - Buscar el trabajo
$sql = "SELECT id_trabajo, nombre_t FROM trabajo WHERE id_trabajo = ?";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$trabajo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajo) {
    die("No se encontró el trabajo.");
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar trabajo - Clínica</title>

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
            <h1>Editar trabajo</h1>
            <p>Modificá los datos del trabajo seleccionado.</p>
        </section>

        <section class="tarjeta">

            <h2>Actualizar trabajo</h2>

            <form class="form-gestion" action="actualizar.php" method="POST">

                <input type="hidden" name="id_trabajo" value="<?php echo $trabajo[" id_trabajo"]; ?>"
                >

                <div class="campo">

                    <label for="nombre_t">Nombre del trabajo</label>

                    <input type="text" id="nombre_t" name="nombre_t" maxlength="50"
                        value="<?php echo htmlspecialchars($trabajo[" nombre_t"]); ?>"
                    required
                    >

                </div>

                <button type="submit">Guardar cambios</button>

                <a href="listar.php" class="btn-secundario">
                    Cancelar
                </a>

            </form>

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
mysqli_close($conexion);
?>
