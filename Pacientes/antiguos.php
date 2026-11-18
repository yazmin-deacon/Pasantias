
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// =========================================
// CONTROL DE ACCESO
// =========================================

if (empty($_SESSION["dni"])) {
    header("Location: /pasantias/inicio%20sesion/login.html");
    exit;
}

$dniSesion = (string)$_SESSION["dni"];
$accesoDni = (string)($_SESSION["acceso_archivados_dni"] ?? "");
$accesoHasta = (int)($_SESSION["acceso_archivados_hasta"] ?? 0);

if (
    $accesoDni !== $dniSesion ||
    $accesoHasta <= time()
) {
    unset(
        $_SESSION["acceso_archivados_dni"],
        $_SESSION["acceso_archivados_hasta"]
    );

    header("Location: acceso_archivados.php");
    exit;
}

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

function formatoDNI($dni) {
    $dni = preg_replace('/\D/', '', (string)$dni);

    return preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        '.',
        $dni
    );
}

function formatoCUIL($cuil) {
    $cuil = preg_replace('/\D/', '', (string)($cuil ?? ""));

    if (strlen($cuil) !== 11) {
        return $cuil !== "" ? $cuil : "Sin registrar";
    }

    return substr($cuil, 0, 2) . "-" .
           substr($cuil, 2, 8) . "-" .
           substr($cuil, 10, 1);
}

function mostrarDato($dato) {
    $dato = trim((string)($dato ?? ""));

    return $dato !== "" ? $dato : "Sin registrar";
}

function formatoNumeroArgentino($numero) {
    $numero = preg_replace('/\D/', '', (string)$numero);

    if (strlen($numero) !== 10) {
        return $numero;
    }

    if (substr($numero, 0, 2) === "11") {
        return substr($numero, 0, 2) . " " .
               substr($numero, 2, 4) . "-" .
               substr($numero, 6, 4);
    }

    if (substr($numero, 0, 3) === "299") {
        return substr($numero, 0, 3) . " " .
               substr($numero, 3, 3) . "-" .
               substr($numero, 6, 4);
    }

    return $numero;
}

function formatoTelefono($telefono) {
    $telefono = trim((string)($telefono ?? ""));

    if ($telefono === "") {
        return "Sin registrar";
    }

    $numeros = preg_replace('/\D/', '', $telefono);

    if (substr($telefono, 0, 1) !== "+") {
        if (strlen($numeros) === 10) {
            return formatoNumeroArgentino($numeros);
        }

        return $telefono;
    }

    if (
        substr($numeros, 0, 3) === "549" &&
        strlen($numeros) === 13
    ) {
        return "+54 9 " .
               formatoNumeroArgentino(substr($numeros, 3));
    }

    if (
        substr($numeros, 0, 2) === "54" &&
        strlen($numeros) === 12
    ) {
        return "+54 " .
               formatoNumeroArgentino(substr($numeros, 2));
    }

    $codigos = [
        "598", "591", "595", "593",
        "54", "56", "55", "57", "51", "58"
    ];

    foreach ($codigos as $codigo) {
        if (
            substr($numeros, 0, strlen($codigo)) === $codigo
        ) {
            return "+" . $codigo . " " .
                   substr($numeros, strlen($codigo));
        }
    }

    return $telefono;
}

function formatoFecha($fecha) {
    if (empty($fecha)) {
        return "Sin registrar";
    }

    try {
        return (new DateTime($fecha))->format("d/m/Y H:i");
    } catch (Throwable $e) {
        return "Sin registrar";
    }
}

function consultarRelacion($conexion, $sql, $dni) {
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "No se pudo consultar la información relacionada."
        );
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        throw new Exception(
            "No se pudo obtener la información relacionada."
        );
    }

    $resultado = mysqli_stmt_get_result($stmt);
    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);

    return $filas;
}

// =========================================
// BUSCADOR
// =========================================

$busqueda = mb_substr(
    trim((string)($_GET["buscar"] ?? "")),
    0,
    100,
    "UTF-8"
);

$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            p.cuil,
            p.telefono,
            p.mail,
            p.domicilio_localidad,
            p.fecha_archivado,
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
        WHERE p.estado = 'archivado'
        ORDER BY p.fecha_archivado DESC,
                 p.apellido,
                 p.nombre_p";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    http_response_code(500);
    exit("No se pudo cargar el archivo de pacientes.");
}

$pacientes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
mysqli_free_result($resultado);

$pacientesFiltrados = [];

foreach ($pacientes as $fila) {
    if ($busqueda === "") {
        $pacientesFiltrados[] = $fila;
        continue;
    }

    $texto = mb_strtolower($busqueda, "UTF-8");

    $nombre = mb_strtolower(
        $fila["nombre_p"] . " " . $fila["apellido"],
        "UTF-8"
    );

    $apellido = mb_strtolower(
        $fila["apellido"] . " " . $fila["nombre_p"],
        "UTF-8"
    );

    $busquedaDni = preg_replace('/\D/', '', $busqueda);

    if (
        mb_strpos($nombre, $texto) !== false ||
        mb_strpos($apellido, $texto) !== false ||
        (
            $busquedaDni !== "" &&
            strpos((string)$fila["dni_p"], $busquedaDni) !== false
        )
    ) {
        $pacientesFiltrados[] = $fila;
    }
}

$cantidadTotal = count($pacientes);
$cantidadFiltrados = count($pacientesFiltrados);

// =========================================
// FICHA DE PACIENTE ARCHIVADO
// =========================================

$pacienteDetalle = null;
$estudiosPaciente = [];
$exposicionesPaciente = [];
$errorDetalle = "";

if (isset($_GET["dni_p"])) {

    $dniTexto = (string)$_GET["dni_p"];

    if (
        !ctype_digit($dniTexto) ||
        strlen($dniTexto) > 8 ||
        (int)$dniTexto <= 0
    ) {
        $errorDetalle = "El DNI no es válido.";
    } else {
        $dniDetalle = (int)$dniTexto;

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT
                p.*,
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
             WHERE p.dni_p = ?
               AND p.estado = 'archivado'"
        );

        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo consultar la ficha.");
        }

        mysqli_stmt_bind_param($stmt, "i", $dniDetalle);
        mysqli_stmt_execute($stmt);

        $resultadoDetalle = mysqli_stmt_get_result($stmt);
        $pacienteDetalle = mysqli_fetch_assoc($resultadoDetalle);

        mysqli_stmt_close($stmt);

        if (!$pacienteDetalle) {
            $errorDetalle =
                "No se encontró un paciente archivado con ese DNI.";
        } else {
            try {
                $exposicionesPaciente = consultarRelacion(
                    $conexion,
                    "SELECT e.nombre_ex
                     FROM paciente_exposicion pe
                     INNER JOIN exposicion e
                        ON pe.id_exposicion = e.id_exposicion
                     WHERE pe.dni_paciente = ?
                     ORDER BY e.nombre_ex",
                    $dniDetalle
                );

                $estudiosPaciente = consultarRelacion(
                    $conexion,
                    "SELECT e.nombre_es
                     FROM paciente_estudio pe
                     INNER JOIN estudio e
                        ON pe.id_estudio = e.id_estudio
                     WHERE pe.dni_paciente = ?
                     ORDER BY e.nombre_es",
                    $dniDetalle
                );

            } catch (Throwable $e) {
                $errorDetalle =
                    "No se pudieron consultar todas las relaciones.";
            }
        }
    }
}

mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pacientes archivados - DUCCA</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .archivo-contenido {
            width: calc(100% - 24px);
            max-width: none;
            margin: 35px auto;
            box-sizing: border-box;
        }

        .archivo-encabezado {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .archivo-tarjeta {
            width: 100%;
            box-sizing: border-box;
            padding: 30px;
            margin-top: 25px;
        }

        .archivo-buscador {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .archivo-buscador input {
            flex: 1;
            min-width: 200px;
            padding: 12px;
            box-sizing: border-box;
        }

        .archivo-buscador button {
            padding: 12px 20px;
            cursor: pointer;
        }

        .archivo-info,
        .archivo-dato {
            padding: 15px;
            border-radius: 10px;
            background: rgba(110, 130, 150, 0.12);
        }

        .archivo-info {
            margin: 18px 0;
        }

        .archivo-tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }

        .archivo-tabla {
            width: 100%;
            border-collapse: collapse;
        }

        .archivo-tabla th,
        .archivo-tabla td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(130, 140, 150, 0.25);
        }

        .archivo-estado {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: rgba(120, 130, 140, 0.2);
            font-size: 12px;
            font-weight: 600;
        }

        .archivo-boton {
            display: inline-block;
            padding: 10px 15px;
            border: 0;
            border-radius: 8px;
            background: #dcecf1;
            color: #21495c;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
        }

        .archivo-boton:hover {
            background: #c4dfe9;
        }

        .archivo-boton-reactivar {
            background: #d7eddd;
            color: #225a35;
        }

        .archivo-boton-reactivar:hover {
            background: #bce1c6;
        }

        .archivo-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .archivo-dato {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .archivo-dato small {
            display: block;
            opacity: 0.75;
            margin-bottom: 7px;
        }

        .archivo-acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-top: 22px;
        }

        .archivo-advertencia {
            padding: 15px;
            border: 1px solid #d9b878;
            border-radius: 9px;
            background: rgba(220, 170, 80, 0.12);
            margin: 20px 0;
        }

        @media (max-width: 700px) {
            .archivo-grid {
                grid-template-columns: 1fr;
            }

            .archivo-tarjeta {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">
        <img
            id="logo"
            src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
            alt="Logo DUCCA"
            height="150"
            width="175"
        >
    </div>

    <nav>
        <a href="../inicio%20sesion/index.php">
            Menú principal
        </a>

        <a href="listar.php">
            Pacientes activos
        </a>
    </nav>

    <button type="button" id="btn-tema">
        Modo oscuro
    </button>
</header>

<main class="archivo-contenido">

    <section class="bienvenida archivo-encabezado">
        <div>
            <h1>Pacientes archivados</h1>
            <p>
                Consultá los pacientes que ya no se
                encuentran en atención activa.
            </p>
        </div>

        <span class="archivo-estado">
            Área protegida · Solo lectura
        </span>
    </section>

    <section class="tarjeta archivo-tarjeta">

        <div class="archivo-info">
            <strong>Archivo clínico de DUCCA</strong>

            <p>
                Todos los datos del paciente se conservan.
                Mientras esté archivado, su información
                no debe poder modificarse.
            </p>
        </div>

        <h2>Buscar pacientes</h2>

        <form
            action="antiguos.php"
            method="GET"
            class="archivo-buscador"
        >
            <input
                type="search"
                name="buscar"
                maxlength="100"
                placeholder="Buscar por nombre, apellido o DNI..."
                value="<?php echo escapar($busqueda); ?>"
            >

            <button type="submit">Buscar</button>

            <a href="antiguos.php" class="archivo-boton">
                Limpiar
            </a>
        </form>

        <p>
            <strong>
                <?php echo $cantidadFiltrados; ?>
            </strong>
            de
            <strong>
                <?php echo $cantidadTotal; ?>
            </strong>
            pacientes archivados
        </p>

        <?php if ($cantidadTotal === 0) { ?>

            <div class="archivo-info">
                <h3>No hay pacientes archivados</h3>
                <p>
                    Los pacientes que archives aparecerán acá.
                </p>
            </div>

        <?php } elseif ($cantidadFiltrados === 0) { ?>

            <div class="archivo-info">
                <p>No se encontraron resultados.</p>
            </div>

        <?php } else { ?>

            <div class="archivo-tabla-contenedor">

                <table class="archivo-tabla">

                    <thead>
                        <tr>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Teléfono</th>
                            <th>Localidad</th>
                            <th>Fecha de archivado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($pacientesFiltrados as $fila) { ?>

                        <tr>
                            <td>
                                <?php echo escapar(
                                    formatoDNI($fila["dni_p"])
                                ); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["nombre_p"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($fila["apellido"]); ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    formatoTelefono($fila["telefono"])
                                ); ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    mostrarDato($fila["domicilio_localidad"])
                                ); ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    formatoFecha($fila["fecha_archivado"])
                                ); ?>
                            </td>

                            <td>
                                <span class="archivo-estado">
                                    Archivado
                                </span>
                            </td>

                            <td>
                                <div class="archivo-acciones">

                                    <a
                                        class="archivo-boton"
                                        href="antiguos.php?dni_p=<?php
                                            echo urlencode($fila["dni_p"]);
                                        ?>"
                                    >
                                        Ver ficha
                                    </a>

                                    <a
                                        class="archivo-boton archivo-boton-reactivar"
                                        href="reactivar.php?dni_p=<?php
                                            echo urlencode($fila["dni_p"]);
                                        ?>"
                                    >
                                        Reactivar
                                    </a>

                                </div>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

    <?php if ($errorDetalle !== "") { ?>

        <section class="tarjeta archivo-tarjeta">
            <p><?php echo escapar($errorDetalle); ?></p>

            <a href="antiguos.php" class="archivo-boton">
                Volver al archivo
            </a>
        </section>

    <?php } ?>

    <?php if ($pacienteDetalle) { ?>

        <section class="tarjeta archivo-tarjeta">

            <h2>
                Ficha archivada:
                <?php echo escapar(
                    $pacienteDetalle["nombre_p"] . " " .
                    $pacienteDetalle["apellido"]
                ); ?>
            </h2>

            <div class="archivo-info">
                <strong>Ficha de solo lectura</strong>

                <p>
                    Archivado el:
                    <?php echo escapar(
                        formatoFecha(
                            $pacienteDetalle["fecha_archivado"]
                        )
                    ); ?>
                </p>
            </div>

            <h2>Datos personales</h2>

            <?php
            $datosPersonales = [
                "DNI" => formatoDNI($pacienteDetalle["dni_p"]),
                "Nombre" => $pacienteDetalle["nombre_p"],
                "Apellido" => $pacienteDetalle["apellido"],
                "CUIL" => formatoCUIL($pacienteDetalle["cuil"]),
                "Celular" => formatoTelefono($pacienteDetalle["telefono"]),
                "Mail" => $pacienteDetalle["mail"],
                "Obra social" => $pacienteDetalle["obra_social"],
                "Trabajo" => $pacienteDetalle["trabajo"]
            ];
            ?>

            <div class="archivo-grid">

                <?php foreach ($datosPersonales as $titulo => $valor) { ?>

                    <div class="archivo-dato">
                        <small><?php echo escapar($titulo); ?></small>
                        <strong>
                            <?php echo escapar(mostrarDato($valor)); ?>
                        </strong>
                    </div>

                <?php } ?>

            </div>

            <hr>

            <h2>Domicilio</h2>

            <?php
            $datosDomicilio = [
                "Calle" => $pacienteDetalle["domicilio_calle"] ?? "",
                "Altura" => $pacienteDetalle["domicilio_altura"] ?? "",
                "Piso / departamento" =>
                    $pacienteDetalle["domicilio_piso_depto"] ?? "",
                "Localidad" =>
                    $pacienteDetalle["domicilio_localidad"] ?? "",
                "Provincia / región" =>
                    $pacienteDetalle["domicilio_provincia"] ?? "",
                "Código postal" =>
                    $pacienteDetalle["domicilio_codigo_postal"] ?? "",
                "País" =>
                    $pacienteDetalle["domicilio_pais"] ?? ""
            ];
            ?>

            <div class="archivo-grid">

                <?php foreach ($datosDomicilio as $titulo => $valor) { ?>

                    <div class="archivo-dato">
                        <small><?php echo escapar($titulo); ?></small>
                        <strong>
                            <?php echo escapar(mostrarDato($valor)); ?>
                        </strong>
                    </div>

                <?php } ?>

            </div>

            <hr>

            <h2>Exposiciones registradas</h2>

            <?php if (count($exposicionesPaciente) === 0) { ?>

                <p>No hay exposiciones registradas.</p>

            <?php } else { ?>

                <ul>
                    <?php foreach ($exposicionesPaciente as $expo) { ?>

                        <li>
                            <?php echo escapar($expo["nombre_ex"]); ?>
                        </li>

                    <?php } ?>
                </ul>

            <?php } ?>

            <hr>

            <h2>Estudios registrados</h2>

            <?php if (count($estudiosPaciente) === 0) { ?>

                <p>No hay estudios registrados.</p>

            <?php } else { ?>

                <ul>
                    <?php foreach ($estudiosPaciente as $estudio) { ?>

                        <li>
                            <?php echo escapar($estudio["nombre_es"]); ?>
                        </li>

                    <?php } ?>
                </ul>

            <?php } ?>

            <hr>

            <h2>Opciones del paciente archivado</h2>

            <div class="archivo-advertencia">
                <strong>Historial médico completo</strong>

                <p>
                    El historial se conserva, pero falta
                    conectar su pantalla en modo lectura.
                    Por seguridad, todavía no se habilita
                    ese acceso.
                </p>
            </div>

            <div class="archivo-acciones">

                <a
                    class="archivo-boton archivo-boton-reactivar"
                    href="reactivar.php?dni_p=<?php
                        echo urlencode($pacienteDetalle["dni_p"]);
                    ?>"
                >
                    Reactivar paciente
                </a>

                <a
                    class="archivo-boton"
                    href="antiguos.php"
                >
                    Volver al archivo
                </a>

            </div>

        </section>

    <?php } ?>

</main>

<script src="../general/animaciones.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/script.js"></script>

</body>
</html>
