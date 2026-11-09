
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// CONSULTAR PACIENTES

$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            p.cuil,
            p.telefono,
            p.mail,

            COALESCE(
                os.nombre_os,
                'Particular / Sin obra social'
            ) AS obra_social,

            t.nombre_t AS trabajo

        FROM paciente p

        LEFT JOIN obra_social os
            ON p.id_obra_social = os.id_obra_social

        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo

        ORDER BY p.apellido, p.nombre_p";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Listado de pacientes</title>

    <link rel="stylesheet" href="../general/estilos.css">
</head>

<body>

<header>

        <div class="logo">

            <img
                id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt="Logo DUCA"
                height="150"
                width="175"
            >

        </div>

        <nav>

            <a href="../inicio sesion/index.php">
                Menú principal
            </a>

            <a href="../gestionar_exposiciones.html">
                Exposiciones
            </a>

            <a href="listar.php">
                Trabajos
            </a>

            <a href="../gestionar_relaciones.html">
                Relaciones
            </a>

            <a href="../gestionar_estudios.html">
                Estudios
            </a>

        </nav>

        <button type="button" id="btn-tema">
            Modo oscuro
        </button>

    </header>

<main class="gestion-contenido pagina-listado">

    <!-- ENCABEZADO -->

    <section class="bienvenida encabezado-listado">

        <div>
            <h1>Listado de pacientes</h1>

            <p>
                Administración y consulta de los pacientes registrados
                en el sistema.
            </p>
        </div>

        <a href="alta.php" class="btn-nuevo-paciente">
            + Nuevo paciente
        </a>

    </section>


    <!-- CONTENIDO PRINCIPAL -->

    <section class="tarjeta bloque-listado">

        <div class="titulo-listado">

            <div>
                <h2>Pacientes registrados</h2>

                <p>
                    Consultá los datos y administrá la información
                    de cada paciente.
                </p>
            </div>

            <span class="contador-pacientes">
                <?php echo $cantidad; ?>
                <?php echo ($cantidad == 1) ? "paciente" : "pacientes"; ?>
            </span>

        </div>


        <?php if ($cantidad == 0) { ?>

            <div class="mensaje-listado-vacio">

                <h3>No hay pacientes registrados</h3>

                <p>
                    Todavía no se cargaron pacientes en el sistema.
                </p>

            </div>

        <?php } else { ?>


            <!-- TABLA DE PACIENTES -->

            <div class="tabla-contenedor">

                <table class="tabla-gestion tabla-pacientes">

                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>CUIL</th>
                            <th>Teléfono</th>
                            <th>Mail</th>
                            <th>Obra social</th>
                            <th>Trabajo</th>
                            <th>Editar</th>
                            <th>Eliminar</th>
                            <th>Historial médico</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>

                        <tr>

                            <td>
                                <?php echo escapar($fila["dni_p"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["nombre_p"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["apellido"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["cuil"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["telefono"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["mail"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["obra_social"]); ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    $fila["trabajo"] ?? "Sin asignar"
                                ); ?>
                            </td>

                            <td>
                                <a
                                    class="accion-tabla accion-editar"
                                    href="editar.php?dni_p=<?php
                                        echo urlencode($fila["dni_p"]);
                                    ?>"
                                >
                                    Editar
                                </a>
                            </td>

                            <td>
                                <a
                                    class="accion-tabla accion-eliminar"
                                    href="eliminar.php?dni_p=<?php
                                        echo urlencode($fila["dni_p"]);
                                    ?>"
                                >
                                    Eliminar
                                </a>
                            </td>

                            <td>
                                <a
                                    class="accion-tabla accion-historial"
                                    href="../historial/historial.php?dni_p=<?php
                                        echo urlencode($fila["dni_p"]);
                                    ?>"
                                >
                                    Ver historial
                                </a>
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

</main>


    <script src="../general/animaciones.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/script.js"></script>
</body>
</html>

<?php
mysqli_free_result($resultado);
mysqli_close($conexion);
?>
