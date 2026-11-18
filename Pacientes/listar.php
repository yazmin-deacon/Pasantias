
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// CONSULTAR SOLO PACIENTES ACTIVOS
// =========================================

$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            p.cuil,
            p.telefono,
            p.mail,
            p.domicilio_localidad,

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

        WHERE p.estado = 'activo'

        ORDER BY p.apellido, p.nombre_p";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);

// =========================================
// FUNCIONES
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// DNI CON PUNTOS

function formatoDNI($dni) {
    $dni = preg_replace('/\D/', '', (string)$dni);

    return preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        '.',
        $dni
    );
}

// CUIL CON GUIONES

function formatoCUIL($cuil) {
    $cuil = preg_replace('/\D/', '', (string)$cuil);

    if (strlen($cuil) !== 11) {
        return $cuil;
    }

    return substr($cuil, 0, 2) . "-" .
           substr($cuil, 2, 8) . "-" .
           substr($cuil, 10, 1);
}

// NÚMERO ARGENTINO

function formatoNumeroArgentino($numero) {
    $numero = preg_replace('/\D/', '', (string)$numero);

    if (strlen($numero) !== 10) {
        return $numero;
    }

    // Buenos Aires: 11 1234-5678

    if (substr($numero, 0, 2) === "11") {
        return substr($numero, 0, 2) . " " .
               substr($numero, 2, 4) . "-" .
               substr($numero, 6, 4);
    }

    // Neuquén: 299 412-3456

    if (substr($numero, 0, 3) === "299") {
        return substr($numero, 0, 3) . " " .
               substr($numero, 3, 3) . "-" .
               substr($numero, 6, 4);
    }

    return $numero;
}

// TELÉFONO INTERNACIONAL

function formatoTelefono($telefono) {
    $telefono = trim((string)($telefono ?? ""));

    if ($telefono === "") {
        return "";
    }

    $numeros = preg_replace('/\D/', '', $telefono);

    if ($numeros === "") {
        return $telefono;
    }

    // Teléfonos antiguos sin prefijo.

    if (substr($telefono, 0, 1) !== "+") {
        if (strlen($numeros) === 10) {
            return formatoNumeroArgentino($numeros);
        }

        return $telefono;
    }

    // Celulares argentinos: +54 9

    if (
        substr($numeros, 0, 3) === "549" &&
        strlen($numeros) === 13
    ) {
        return "+54 9 " .
               formatoNumeroArgentino(
                   substr($numeros, 3)
               );
    }

    // Teléfonos argentinos antiguos: +54

    if (
        substr($numeros, 0, 2) === "54" &&
        strlen($numeros) === 12
    ) {
        return "+54 " .
               formatoNumeroArgentino(
                   substr($numeros, 2)
               );
    }

    // Otros países

    $codigos = [
        "598",
        "591",
        "595",
        "593",
        "54",
        "56",
        "55",
        "57",
        "51",
        "58"
    ];

    foreach ($codigos as $codigo) {
        if (
            substr($numeros, 0, strlen($codigo))
            === $codigo
        ) {
            return "+" . $codigo . " " .
                   substr($numeros, strlen($codigo));
        }
    }

    return $telefono;
}

// MOSTRAR LOCALIDAD

function mostrarLocalidad($localidad) {
    $localidad = trim((string)($localidad ?? ""));

    return $localidad !== ""
        ? $localidad
        : "Sin registrar";
}

// MENSAJES DEL ARCHIVADO Y REACTIVACIÓN

$mensaje = "";

if (isset($_GET["archivado"]) &&
    $_GET["archivado"] === "1") {
    $mensaje = "El paciente fue archivado correctamente.";
}

if (isset($_GET["reactivado"]) &&
    $_GET["reactivado"] === "1") {
    $mensaje = "El paciente fue reactivado correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Listado de pacientes</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .mensaje-operacion {
            padding: 15px 20px;
            border: 1px solid #8fc3a4;
            border-radius: 9px;
            background: rgba(70, 160, 100, 0.12);
            margin-bottom: 20px;
        }

        .accion-archivar {
            background: #f3e2cb;
            color: #68451c;
        }

        .accion-archivar:hover {
            background: #e9d0ae;
        }
    </style>
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

        <a href="../Exposiciones/listar.php">
            Exposiciones
        </a>

        <a href="../Trabajos/listar.php">
            Trabajos
        </a>

        <a href="../Relaciones/listar.php">
            Relaciones
        </a>

        <a href="../Estudios/index.php">
            Estudios
        </a>
    </nav>

    <button
        type="button"
        id="btn-tema"
    >
        Modo oscuro
    </button>

</header>

<main class="gestion-contenido pagina-listado">

    <!-- ENCABEZADO -->

    <section class="bienvenida encabezado-listado">

        <div>

            <h1>Listado de pacientes</h1>

            <p>
                Administración y consulta
                de los pacientes activos.
            </p>

        </div>

        <a
            href="alta.php"
            class="btn-nuevo-paciente"
        >
            + Nuevo paciente
        </a>

    </section>

    <!-- CONTENIDO PRINCIPAL -->

    <section class="tarjeta bloque-listado">

        <?php if ($mensaje !== "") { ?>

            <div class="mensaje-operacion">
                <?php echo escapar($mensaje); ?>
            </div>

        <?php } ?>

        <div class="titulo-listado">

            <div>

                <h2>Pacientes activos</h2>

                <p>
                    Consultá y administrá los pacientes
                    que actualmente reciben atención.
                </p>

            </div>

            <span class="contador-pacientes">

                <?php echo $cantidad; ?>

                <?php
                    echo ($cantidad == 1)
                        ? "paciente"
                        : "pacientes";
                ?>

            </span>

        </div>

        <?php if ($cantidad == 0) { ?>

            <div class="mensaje-listado-vacio">

                <h3>No hay pacientes activos</h3>

                <p>
                    Todavía no se registraron pacientes
                    o todos se encuentran archivados.
                </p>

            </div>

        <?php } else { ?>

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
                            <th>Localidad</th>
                            <th>Obra social</th>
                            <th>Trabajo</th>
                            <th>Editar</th>
                            <th>Archivar</th>
                            <th>Historial médico</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php while ($fila = mysqli_fetch_assoc($resultado)) { ?>

                        <tr>

                            <!-- DNI -->

                            <td>
                                <?php
                                    echo escapar(
                                        formatoDNI($fila["dni_p"])
                                    );
                                ?>
                            </td>

                            <!-- NOMBRE -->

                            <td>
                                <?php
                                    echo escapar($fila["nombre_p"]);
                                ?>
                            </td>

                            <!-- APELLIDO -->

                            <td>
                                <?php
                                    echo escapar($fila["apellido"]);
                                ?>
                            </td>

                            <!-- CUIL -->

                            <td>
                                <?php
                                    echo escapar(
                                        formatoCUIL($fila["cuil"])
                                    );
                                ?>
                            </td>

                            <!-- TELÉFONO -->

                            <td>
                                <?php
                                    echo escapar(
                                        formatoTelefono($fila["telefono"])
                                    );
                                ?>
                            </td>

                            <!-- MAIL -->

                            <td>
                                <?php
                                    echo escapar($fila["mail"]);
                                ?>
                            </td>

                            <!-- LOCALIDAD -->

                            <td>
                                <?php
                                    echo escapar(
                                        mostrarLocalidad(
                                            $fila["domicilio_localidad"]
                                        )
                                    );
                                ?>
                            </td>

                            <!-- OBRA SOCIAL -->

                            <td>
                                <?php
                                    echo escapar($fila["obra_social"]);
                                ?>
                            </td>

                            <!-- TRABAJO -->

                            <td>
                                <?php
                                    echo escapar(
                                        $fila["trabajo"] ?? "Sin asignar"
                                    );
                                ?>
                            </td>

                            <!-- EDITAR -->

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

                            <!-- ARCHIVAR -->

                            <td>
                                <a
                                    class="accion-tabla accion-archivar"
                                    href="eliminar.php?dni_p=<?php
                                        echo urlencode($fila["dni_p"]);
                                    ?>"
                                >
                                    Archivar
                                </a>
                            </td>

                            <!-- HISTORIAL -->

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