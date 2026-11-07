<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

$mensaje = "";

// BLOQUE 2 - Procesar las acciones

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion"])) {

    // AGREGAR TRABAJO
    if ($_POST["accion"] == "agregar") {

        $nombre = trim($_POST["nombre_t"]);

        if ($nombre == "") {
            $mensaje = "El nombre no puede estar vacío.";
        } else {

            $sql = "INSERT INTO trabajo (nombre_t) VALUES (?)";
            $stmt = mysqli_prepare($conexion, $sql);

            mysqli_stmt_bind_param($stmt, "s", $nombre);

            if (mysqli_stmt_execute($stmt)) {
                $mensaje = "Trabajo agregado correctamente.";
            } else {
                $mensaje = "Error al agregar el trabajo. Puede que ya exista.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // ACTUALIZAR TRABAJO
    if ($_POST["accion"] == "actualizar") {

        $id = $_POST["id_trabajo"];
        $nombre = trim($_POST["nombre_t"]);

        if ($nombre == "") {
            $mensaje = "El nombre no puede estar vacío.";
        } else {

            $sql = "UPDATE trabajo SET nombre_t = ? WHERE id_trabajo = ?";
            $stmt = mysqli_prepare($conexion, $sql);

            mysqli_stmt_bind_param($stmt, "si", $nombre, $id);

            if (mysqli_stmt_execute($stmt)) {
                $mensaje = "Trabajo actualizado correctamente.";
            } else {
                $mensaje = "Error al actualizar el trabajo.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // ELIMINAR TRABAJO
    if ($_POST["accion"] == "eliminar") {

        $id = $_POST["id_trabajo"];

        $sql = "DELETE FROM trabajo WHERE id_trabajo = ?";
        $stmt = mysqli_prepare($conexion, $sql);

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $mensaje = "Trabajo eliminado correctamente.";
        } else {
            $mensaje = "No se puede eliminar porque el trabajo está relacionado con otros registros.";
        }

        mysqli_stmt_close($stmt);
    }
}

// BLOQUE 3 - Consultar trabajos

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        ORDER BY nombre_t ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);


// BLOQUE 4 - Buscar trabajo para editar

$trabajo_editar = null;

if (isset($_GET["accion"]) && $_GET["accion"] == "editar") {

    $id = $_GET["id"];

    $sql = "SELECT id_trabajo, nombre_t
            FROM trabajo
            WHERE id_trabajo = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $resultado_editar = mysqli_stmt_get_result($stmt);

    $trabajo_editar = mysqli_fetch_assoc($resultado_editar);

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrar trabajos - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>

        /* BOTONES DE ACCIONES */

        .acciones {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .acciones form {
            margin: 0;
            padding: 0;
        }

        .acciones a,
        .acciones button,
        .btn-cancelar {
            display: inline-block;
            padding: 9px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: 0.3s;
        }

        /* BOTÓN EDITAR */

        .acciones a {
            background-color: #e5e5e5;
            color: #333;
        }

        .acciones a:hover {
            background-color: #d0d0d0;
        }

        /* BOTÓN ELIMINAR */

        .btn-eliminar {
            background-color: #c94b4b;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #a93636;
        }

        /* BOTÓN CANCELAR */

        .btn-cancelar {
            background-color: #e5e5e5;
            color: #333;
            margin-left: 8px;
        }

        .btn-cancelar:hover {
            background-color: #d0d0d0;
        }

    </style>

</head>

<body>

    <header>

        <div class="logo">

            <img id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt="Logo DUCA"
                height="150"
                width="175">

        </div>

        <nav>

            <a href="../inicio sesion/index.php">Menú principal</a>
            <a href="../gestionar_exposiciones.html">Exposiciones</a>
            <a href="Trabajos.php">Trabajos</a>
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


        <!-- MENSAJES -->

        <?php if ($mensaje != "") { ?>

            <section class="tarjeta">

                <p>
                    <?php echo htmlspecialchars($mensaje); ?>
                </p>

            </section>

        <?php } ?>


        <?php if ($trabajo_editar) { ?>

            <!-- FORMULARIO PARA EDITAR -->

            <section class="tarjeta">

                <h2>Editar trabajo</h2>

                <form class="form-gestion" action="Trabajos.php" method="POST">

                    <input type="hidden" name="accion" value="actualizar">

                    <input type="hidden"
                        name="id_trabajo"
                        value="<?php echo $trabajo_editar["id_trabajo"]; ?>">

                    <div class="campo">

                        <label for="nombre_t">Nombre del trabajo</label>

                        <input
                            type="text"
                            id="nombre_t"
                            name="nombre_t"
                            maxlength="50"
                            value="<?php echo htmlspecialchars($trabajo_editar["nombre_t"]); ?>"
                            required>

                    </div>

                    <button type="submit">Guardar cambios</button>

                    <a href="Trabajos.php" class="btn-cancelar">
                        Cancelar
                    </a>

                </form>

            </section>


        <?php } else { ?>


            <!-- FORMULARIO PARA AGREGAR -->

            <section class="tarjeta">

                <h2>Agregar trabajo</h2>

                <form class="form-gestion" action="Trabajos.php" method="POST">

                    <input type="hidden" name="accion" value="agregar">

                    <div class="campo">

                        <label for="nombre_t">Nombre del trabajo</label>

                        <input
                            type="text"
                            id="nombre_t"
                            name="nombre_t"
                            maxlength="50"
                            placeholder="Ej.: Administrativo"
                            required>

                    </div>

                    <button type="submit">Guardar trabajo</button>

                </form>

            </section>


        <?php } ?>


        <!-- TABLA DE TRABAJOS -->

        <section class="tarjeta">

            <h2>Trabajos registrados</h2>

            <p>
                Trabajos registrados: <?php echo $cantidad; ?>
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

                                    <a
                                        href="Trabajos.php?accion=editar&id=<?php echo $trabajo["id_trabajo"]; ?>">

                                        Editar

                                    </a>


                                    <form
                                        action="Trabajos.php"
                                        method="POST"
                                        onsubmit="return confirm('¿Seguro que querés eliminar este trabajo?')">

                                        <input type="hidden"
                                            name="accion"
                                            value="eliminar">

                                        <input type="hidden"
                                            name="id_trabajo"
                                            value="<?php echo $trabajo["id_trabajo"]; ?>">

                                        <button type="submit" class="btn-eliminar">

                                            Eliminar

                                        </button>

                                    </form>

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
// BLOQUE 5 - Cerrar conexión
mysqli_close($conexion);
?>