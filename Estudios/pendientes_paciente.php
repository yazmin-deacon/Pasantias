
<?php
// =============================================
// DUCCA - ESTUDIOS PENDIENTES POR PACIENTE
// =============================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// =============================================
// FUNCIONES
// =============================================

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

// =============================================
// CONSULTAR ESTUDIOS PENDIENTES
// =============================================

// Mostrar únicamente pacientes activos
// que tengan estudios pendientes.

$sql = "
    SELECT
        p.dni_p,
        p.nombre_p,
        p.apellido,
        t.nombre_t,
        e.id_estudio,
        e.nombre_es

    FROM paciente p

    INNER JOIN paciente_estudio pe
        ON pe.dni_paciente = p.dni_p

    INNER JOIN estudio e
        ON e.id_estudio = pe.id_estudio

    LEFT JOIN trabajo t
        ON t.id_trabajo = p.id_trabajo

    WHERE p.estado = 'activo'
      AND pe.estado = 'Pendiente'

    ORDER BY
        p.apellido ASC,
        p.nombre_p ASC,
        p.dni_p ASC,
        e.nombre_es ASC
";

try {

    $resultado = mysqli_query($conexion, $sql);

    $pacientes = [];

    while ($fila = mysqli_fetch_assoc($resultado)) {

        $dni = (string)$fila["dni_p"];

        if (!isset($pacientes[$dni])) {

            $pacientes[$dni] = [
                "dni_p" => $fila["dni_p"],
                "nombre_p" => $fila["nombre_p"],
                "apellido" => $fila["apellido"],
                "nombre_t" => $fila["nombre_t"],
                "estudios" => []
            ];
        }

        $pacientes[$dni]["estudios"][] = [
            "id_estudio" => $fila["id_estudio"],
            "nombre_es" => $fila["nombre_es"],
            "estado" => "Pendiente"
        ];
    }

    mysqli_free_result($resultado);
    mysqli_close($conexion);

} catch (Throwable $e) {

    error_log(
        "DUCCA - Error estudios pendientes: " .
        $e->getMessage()
    );

    http_response_code(500);

    exit("No se pudieron consultar los estudios pendientes.");
}

// =============================================
// RESUMEN
// =============================================

$totalPacientes = count($pacientes);
$totalEstudios = 0;

foreach ($pacientes as $paciente) {
    $totalEstudios += count($paciente["estudios"]);
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

    <title>Estudios pendientes por paciente - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .buscador-pendientes {
            width: 100%;
            max-width: 500px;
            padding: 12px;
            border: 1px solid #b9c7ce;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .resumen-pendientes {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .dato-resumen {
            flex: 1;
            min-width: 150px;
            padding: 17px;
            border: 1px solid #c5d7df;
            border-radius: 10px;
            background: rgba(80, 150, 180, 0.08);
        }

        .dato-resumen strong {
            display: block;
            font-size: 25px;
            margin-bottom: 6px;
        }

        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }

        .tabla-gestion {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-gestion th,
        .tabla-gestion td {
            padding: 13px;
            border: 1px solid #ccd6db;
            text-align: left;
            vertical-align: top;
        }

        .lista-estudios {
            padding-left: 20px;
            margin: 0;
            list-style: disc;
        }

        .lista-estudios li {
            margin-bottom: 10px;
            min-height: 27px;
            line-height: 27px;
        }

        .lista-estudios li:last-child {
            margin-bottom: 0;
        }

        /* COLUMNA DE ESTADOS */

        .lista-estados {
            list-style: none;
            padding-left: 0;
        }

        .etiqueta-pendiente {
            display: inline-block;
            padding: 4px 10px;
            background: #fff0cf;
            color: #8b5900;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            line-height: 19px;
        }

        .btn-administrar-estudios {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 8px;
            background: #287b99;
            color: white;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-administrar-estudios:hover {
            background: #195d77;
            color: white;
        }

        .contador-estudios {
            display: inline-block;
            padding: 6px 10px;
            background: #e8f2f6;
            color: #17475c;
            border-radius: 20px;
            font-weight: 600;
        }

        .sin-resultados {
            display: none;
            margin-top: 15px;
        }

        .texto-ayuda {
            font-size: 14px;
            opacity: 0.85;
            margin-top: 12px;
        }

        @media (max-width: 600px) {

            .resumen-pendientes {
                flex-direction: column;
            }

            .tabla-gestion {
                min-width: 750px;
            }
        }

    </style>

</head>

<body>

<header>

    <a
        class="flecha-volver"
        href="../inicio%20sesion/index.php"
        title="Volver al menú"
    >
        <img
            src="../general/flechita.png"
            alt="Volver"
            width="30"
            height="30"
        >
    </a>

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

        <a href="../Pacientes/listar.php">
            Pacientes
        </a>

        <a href="estudios.php">
            Estudios
        </a>

        <a href="../Relaciones/listar.php">
            Relaciones
        </a>

    </nav>

    <button type="button" id="btn-tema">
        Modo oscuro
    </button>

</header>

<main class="gestion-contenido">

    <!-- =====================================
         ENCABEZADO
    ===================================== -->

    <section class="bienvenida">

        <h1>Estudios pendientes por paciente</h1>

        <p>
            Consultá los estudios médicos pendientes
            de cada paciente y accedé a su administración
            individual.
        </p>

    </section>

    <!-- =====================================
         RESUMEN GENERAL
    ===================================== -->

    <section class="tarjeta">

        <h2>Resumen de pendientes</h2>

        <div class="resumen-pendientes">

            <div class="dato-resumen">

                <strong>
                    <?php echo $totalPacientes; ?>
                </strong>

                Pacientes con estudios pendientes

            </div>

            <div class="dato-resumen">

                <strong>
                    <?php echo $totalEstudios; ?>
                </strong>

                Estudios pendientes en total

            </div>

        </div>

        <p class="texto-ayuda">
            Solo se muestran pacientes activos.
            Los estudios realizados no aparecen
            en este listado.
        </p>

    </section>

    <!-- =====================================
         BUSCADOR
    ===================================== -->

    <section class="tarjeta">

        <h2>Buscar paciente o estudio</h2>

        <input
            type="search"
            id="buscar_pendientes"
            class="buscador-pendientes"
            placeholder="Nombre, apellido, DNI, trabajo o estudio..."
            autocomplete="off"
        >

        <p>
            Pacientes encontrados:
            <strong id="cantidad_encontrados">
                <?php echo $totalPacientes; ?>
            </strong>
        </p>

    </section>

    <!-- =====================================
         LISTADO
    ===================================== -->

    <section class="tarjeta">

        <h2>Pacientes con estudios pendientes</h2>

        <?php if (!$pacientes) { ?>

            <p>
                No hay estudios pendientes asignados
                a pacientes activos.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>

                        <tr>
                            <th>DNI</th>
                            <th>Paciente</th>
                            <th>Trabajo</th>
                            <th>Cantidad</th>
                            <th>Estudios pendientes</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($pacientes as $paciente) { ?>

                            <?php

                            $dni = (int)$paciente["dni_p"];

                            $nombreCompleto =
                                $paciente["nombre_p"] . " " .
                                $paciente["apellido"];

                            $trabajo =
                                $paciente["nombre_t"] ?: "Sin trabajo";

                            $nombresEstudios = array_column(
                                $paciente["estudios"],
                                "nombre_es"
                            );

                            $textoBusqueda = implode(
                                " ",
                                [
                                    $dni,
                                    formatoDNI($dni),
                                    $nombreCompleto,
                                    $trabajo,
                                    implode(" ", $nombresEstudios)
                                ]
                            );

                            ?>

                            <tr
                                class="fila-paciente"
                                data-busqueda="<?php
                                    echo escapar($textoBusqueda);
                                ?>"
                            >

                                <!-- DNI -->

                                <td>
                                    <?php
                                    echo escapar(formatoDNI($dni));
                                    ?>
                                </td>

                                <!-- PACIENTE -->

                                <td>
                                    <?php
                                    echo escapar($nombreCompleto);
                                    ?>
                                </td>

                                <!-- TRABAJO -->

                                <td>
                                    <?php
                                    echo escapar($trabajo);
                                    ?>
                                </td>

                                <!-- CANTIDAD -->

                                <td>
                                    <span class="contador-estudios">
                                        <?php
                                        echo count(
                                            $paciente["estudios"]
                                        );
                                        ?>
                                    </span>
                                </td>

                                <!-- ESTUDIOS -->

                                <td>

                                    <ul class="lista-estudios">

                                        <?php foreach (
                                            $paciente["estudios"] as $estudio
                                        ) { ?>

                                            <li>
                                                <?php
                                                echo escapar(
                                                    $estudio["nombre_es"]
                                                );
                                                ?>
                                            </li>

                                        <?php } ?>

                                    </ul>

                                </td>

                                <!-- ESTADO SEPARADO -->

                                <td>

                                    <ul class="lista-estudios lista-estados">

                                        <?php foreach (
                                            $paciente["estudios"] as $estudio
                                        ) { ?>

                                            <li>
                                                <span class="etiqueta-pendiente">
                                                    Pendiente
                                                </span>
                                            </li>

                                        <?php } ?>

                                    </ul>

                                </td>

                                <!-- ADMINISTRAR -->

                                <td>

                                    <a
                                        class="btn-administrar-estudios"
                                        href="../historial/administrar_estudios.php?dni_p=<?php
                                            echo urlencode(
                                                (string)$dni
                                            );
                                        ?>"
                                    >
                                        Administrar
                                    </a>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <p id="sin_resultados" class="sin-resultados">
                No se encontraron pacientes
                ni estudios con esa búsqueda.
            </p>

        <?php } ?>

        <p class="texto-ayuda">
            Para cambiar estados, asignar o quitar estudios,
            utilizá el botón Administrar.
            Esta página es únicamente de consulta.
        </p>

    </section>

    <!-- =====================================
         VOLVER
    ===================================== -->

    <section class="tarjeta">

        <a
            href="../inicio%20sesion/index.php"
            class="btn-volver-pacientes"
        >
            Volver al menú principal
        </a>

    </section>

</main>

<footer>

    <span>DUCCA</span>
    <span>Seguimiento de estudios pendientes</span>

</footer>

<script>

// =============================================
// BUSCADOR EN TIEMPO REAL
// =============================================

const buscador = document.getElementById(
    "buscar_pendientes"
);

const filas = document.querySelectorAll(
    ".fila-paciente"
);

const contador = document.getElementById(
    "cantidad_encontrados"
);

const sinResultados = document.getElementById(
    "sin_resultados"
);

function normalizarBusqueda(texto) {

    return String(texto)
        .trim()
        .toLocaleLowerCase("es")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ");
}

function filtrarPacientes() {

    const texto = normalizarBusqueda(
        buscador.value
    );

    const textoSinPuntos = texto.replace(/\./g, "");

    let encontrados = 0;

    filas.forEach(fila => {

        const datos = normalizarBusqueda(
            fila.dataset.busqueda
        );

        const coincide =
            datos.includes(texto) ||
            datos.replace(/\./g, "").includes(textoSinPuntos);

        fila.style.display = coincide ? "" : "none";

        if (coincide) {
            encontrados++;
        }
    });

    if (contador) {
        contador.textContent = encontrados;
    }

    if (sinResultados) {

        sinResultados.style.display =
            encontrados === 0 && filas.length > 0
                ? "block"
                : "none";
    }
}

buscador.addEventListener(
    "input",
    filtrarPacientes
);

</script>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>
