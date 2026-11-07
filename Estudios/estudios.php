
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

$mensaje = "";

// BLOQUE 2 - Procesar las acciones

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["accion"])) {

    // AGREGAR ESTUDIO
    if ($_POST["accion"] == "agregar") {

        $nombre = trim($_POST["nombre_es"]);

        if ($nombre == "") {
            $mensaje = "El nombre del estudio no puede estar vacío.";
        } else {

            $sql = "INSERT INTO estudio (nombre_es) VALUES (?)";
            $stmt = mysqli_prepare($conexion, $sql);

            mysqli_stmt_bind_param($stmt, "s", $nombre);

            if (mysqli_stmt_execute($stmt)) {
                $mensaje = "Estudio agregado correctamente.";
            } else {
                $mensaje = "Error al agregar el estudio. Puede que ya exista.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // ACTUALIZAR ESTUDIO
    if ($_POST["accion"] == "actualizar") {

        $id = $_POST["id_estudio"];
        $nombre = trim($_POST["nombre_es"]);

        if ($nombre == "") {
            $mensaje = "El nombre del estudio no puede estar vacío.";
        } else {

            $sql = "UPDATE estudio SET nombre_es = ? WHERE id_estudio = ?";
            $stmt = mysqli_prepare($conexion, $sql);

            mysqli_stmt_bind_param($stmt, "si", $nombre, $id);

            if (mysqli_stmt_execute($stmt)) {
                $mensaje = "Estudio actualizado correctamente.";
            } else {
                $mensaje = "Error al actualizar el estudio.";
            }

            mysqli_stmt_close($stmt);
        }
    }

    // ELIMINAR ESTUDIO
    if ($_POST["accion"] == "eliminar") {

        $id = $_POST["id_estudio"];

        $sql = "DELETE FROM estudio WHERE id_estudio = ?";
        $stmt = mysqli_prepare($conexion, $sql);

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $mensaje = "Estudio eliminado correctamente.";
        } else {
            $mensaje = "No se puede eliminar porque el estudio está asignado a uno o más pacientes.";
        }

        mysqli_stmt_close($stmt);
    }
}

// BLOQUE 3 - Consultar estudios

$sql = "SELECT id_estudio, nombre_es
        FROM estudio
        ORDER BY nombre_es ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los estudios: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);


// BLOQUE 4 - Buscar estudio para editar

$estudio_editar = null;

if (isset($_GET["accion"]) && $_GET["accion"] == "editar") {

    $id = $_GET["id"];

    $sql = "SELECT id_estudio, nombre_es
            FROM estudio
            WHERE id_estudio = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $resultado_editar = mysqli_stmt_get_result($stmt);

    $estudio_editar = mysqli_fetch_assoc($resultado_editar);

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrar estudios - Clínica</title>

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
            <a href="../Trabajos/Trabajos.php">Trabajos</a>
            <a href="../gestionar_relaciones.html">Relaciones</a>
            <a href="estudios.php">Estudios por paciente</a>

        </nav>

        <button type="button" id="btn-tema">Modo oscuro</button>

    </header>


    <main class="gestion-contenido">

        <section class="bienvenida">

            <h1>Administrar estudios</h1>

            <p>Registrá y mantené actualizado el catálogo de estudios médicos.</p>

        </section>


        <!-- MENSAJES -->

        <?php if ($mensaje != "") { ?>

            <section class="tarjeta">

                <p>
                    <?php echo htmlspecialchars($mensaje); ?>
                </p>

            </section>

        <?php } ?>


        <?php if ($estudio_editar) { ?>

            <!-- FORMULARIO PARA EDITAR -->

            <section class="tarjeta">

                <h2>Editar estudio</h2>

                <form class="form-gestion" action="Administrar_estudios.php" method="POST">

                    <input type="hidden" name="accion" value="actualizar">

                    <input type="hidden"
                        name="id_estudio"
                        value="<?php echo $estudio_editar["id_estudio"]; ?>">

                    <div class="campo">

                        <label for="nombre_es">Nombre del estudio</label>

                        <input
                            type="text"
                            id="nombre_es"
                            name="nombre_es"
                            maxlength="50"
                            value="<?php echo htmlspecialchars($estudio_editar["nombre_es"]); ?>"
                            required>

                    </div>

                    <button type="submit">Guardar cambios</button>

                    <a href="Administrar_estudios.php" class="btn-cancelar">
                        Cancelar
                    </a>

                </form>

            </section>


        <?php } else { ?>


            <!-- FORMULARIO PARA AGREGAR -->

            <section class="tarjeta">

                <h2>Agregar estudio</h2>

                <form class="form-gestion" action="Administrar_estudios.php" method="POST">

                    <input type="hidden" name="accion" value="agregar">

                    <div class="campo">

                        <label for="nombre_es">Nombre del estudio</label>

                        <input
                            type="text"
                            id="nombre_es"
                            name="nombre_es"
                            maxlength="50"
                            placeholder="Ej.: Audiometría"
                            required>

                    </div>

                    <button type="submit">Guardar estudio</button>

                </form>

            </section>


        <?php } ?>


        <!-- TABLA DE ESTUDIOS -->

        <section class="tarjeta">

            <h2>Estudios registrados</h2>

            <p>
                Estudios registrados: <?php echo $cantidad; ?>
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

                        <?php while ($estudio = mysqli_fetch_assoc($resultado)) { ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($estudio["nombre_es"]); ?>
                                </td>

                                <td class="acciones">

                                    <a
                                        href="Administrar_estudios.php?accion=editar&id=<?php echo $estudio["id_estudio"]; ?>">

                                        Editar

                                    </a>


                                    <form
                                        action="Administrar_estudios.php"
                                        method="POST"
                                        onsubmit="return confirm('¿Seguro que querés eliminar este estudio?')">

                                        <input type="hidden"
                                            name="accion"
                                            value="eliminar">

                                        <input type="hidden"
                                            name="id_estudio"
                                            value="<?php echo $estudio["id_estudio"]; ?>">

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

        <span>Administración de estudios</span>

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
