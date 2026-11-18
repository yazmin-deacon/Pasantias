
<?php

// =========================================
// DUCCA - HISTORIAL MÉDICO
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

date_default_timezone_set("America/Argentina/Salta");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

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

function consultar($conexion, $sql, $dni) {

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        error_log(mysqli_error($conexion));
        http_response_code(500);
        exit("No se pudo preparar la consulta.");
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);

    if (!mysqli_stmt_execute($stmt)) {
        error_log(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        http_response_code(500);
        exit("No se pudo realizar la consulta.");
    }

    $resultado = mysqli_stmt_get_result($stmt);

    $filas = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt);

    return $filas;
}

function avisoTurno($fecha, $estado) {

    if (
        !in_array(
            $estado,
            ["Pendiente", "Confirmado"],
            true
        )
    ) {
        return $estado;
    }

    $hoy = new DateTimeImmutable("today");
    $diaTurno = new DateTimeImmutable($fecha);

    $dias = (int)$hoy->diff(
        $diaTurno
    )->format("%r%a");

    if ($dias < 0) {
        return "Vencido hace " . abs($dias) . " día(s)";
    }

    if ($dias === 0) {
        return "¡El turno es hoy!";
    }

    if ($dias === 1) {
        return "¡El turno es mañana!";
    }

    return "Faltan " . $dias . " días";
}

// =========================================
// RECIBIR DNI
// =========================================

$dni_p = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

if (!$dni_p || $dni_p <= 0) {
    http_response_code(400);
    exit("No se indicó un DNI válido.");
}

// =========================================
// BUSCAR PACIENTE Y ESTADO
// =========================================

$filasPaciente = consultar(
    $conexion,
    "SELECT
        dni_p,
        nombre_p,
        apellido,
        estado
     FROM paciente
     WHERE dni_p = ?",
    $dni_p
);

if (!$filasPaciente) {
    http_response_code(404);
    exit("No se encontró el paciente.");
}

$paciente = $filasPaciente[0];

$esArchivado = (
    $paciente["estado"] === "archivado"
);

// =========================================
// PROTEGER HISTORIALES ARCHIVADOS
// =========================================

if ($esArchivado) {

    $dniSesion = (string)(
        $_SESSION["dni"] ?? ""
    );

    $accesoDni = (string)(
        $_SESSION["acceso_archivados_dni"] ?? ""
    );

    $accesoHasta = (int)(
        $_SESSION["acceso_archivados_hasta"] ?? 0
    );

    $tieneAcceso = (
        $dniSesion !== "" &&
        $accesoDni === $dniSesion &&
        $accesoHasta > time()
    );

    if (!$tieneAcceso) {

        mysqli_close($conexion);

        header(
            "Location: ../Pacientes/acceso_archivados.php"
        );

        exit;
    }
}

// =========================================
// ESTUDIOS INDICADOS
// =========================================

$estudios = consultar(
    $conexion,
    "SELECT
        e.id_estudio,
        e.nombre_es,
        pe.estado

     FROM paciente_estudio pe

     INNER JOIN estudio e
        ON pe.id_estudio = e.id_estudio

     WHERE pe.dni_paciente = ?

     ORDER BY e.nombre_es",
    $dni_p
);

// =========================================
// EXPOSICIONES
// =========================================

$exposiciones = consultar(
    $conexion,
    "SELECT
        e.id_exposicion,
        e.nombre_ex

     FROM paciente_exposicion pe

     INNER JOIN exposicion e
        ON pe.id_exposicion = e.id_exposicion

     WHERE pe.dni_paciente = ?

     ORDER BY e.nombre_ex",
    $dni_p
);

// =========================================
// TURNOS PENDIENTES O CONFIRMADOS
// =========================================

$turnos = consultar(
    $conexion,
    "SELECT
        id_turno,
        fecha,
        hora,
        motivo,
        estado,
        observaciones

     FROM turno

     WHERE dni_paciente = ?
       AND estado IN ('Pendiente', 'Confirmado')

     ORDER BY fecha ASC, hora ASC",
    $dni_p
);

// =========================================
// REGISTROS DEL HISTORIAL
// =========================================

$historial = consultar(
    $conexion,
    "SELECT
        h.id_historial,
        h.fecha,
        h.estado,
        h.observaciones,
        h.archivo,
        e.nombre_es

     FROM historial_medico h

     INNER JOIN estudio e
        ON h.id_estudio = e.id_estudio

     WHERE h.dni_paciente = ?

     ORDER BY
        h.fecha DESC,
        h.id_historial DESC",
    $dni_p
);

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

    <title>Historial médico - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .lista-datos {
            padding-left: 20px;
        }

        .lista-datos li {
            margin-bottom: 10px;
        }

        .estado {
            display: inline-block;
            padding: 5px 10px;
            margin-left: 8px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
        }

        .estado-pendiente {
            background: #fff0cf;
            color: #8b5900;
        }

        .estado-realizado {
            background: #d7f3e2;
            color: #17663c;
        }

        .estado-confirmado {
            background: #dce9ff;
            color: #174a9c;
        }

        .enlace-administrar {
            display: inline-block;
            margin: 10px 0 15px;
        }

        .acciones-historial a {
            display: inline-block;
            margin: 3px;
        }

        .btn-eliminar {
            color: #b12e2e;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .tabla-gestion {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-gestion th,
        .tabla-gestion td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
            vertical-align: top;
        }

        .aviso-turno {
            font-weight: 600;
        }

        .acciones-turnos a {
            display: inline-block;
            margin: 3px;
        }

        .aviso-archivado {
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #d9b878;
            border-radius: 10px;
            background: rgba(220, 170, 80, 0.12);
        }

        .aviso-archivado p {
            margin-bottom: 0;
            line-height: 1.6;
        }

        .etiqueta-archivado {
            display: inline-block;
            padding: 7px 12px;
            margin: 10px 0;
            border-radius: 18px;
            background: rgba(130, 140, 150, 0.2);
            font-weight: 600;
        }

        .mensaje-proteccion {
            margin-top: 10px;
            font-style: italic;
            opacity: 0.8;
        }

        .enlaces-descarga a {
            display: inline-block;
            margin: 6px 0;
        }

    </style>

</head>

<body>

<main class="gestion-contenido">

    <!-- =====================================
         DATOS DEL PACIENTE
    ===================================== -->

    <section class="bienvenida">

        <h1>Historial médico</h1>

        <h2>
            <?php
            echo escapar(
                $paciente["nombre_p"] . " " .
                $paciente["apellido"]
            );
            ?>
        </h2>

        <p>
            DNI:
            <?php
            echo escapar(
                formatoDNI($paciente["dni_p"])
            );
            ?>
        </p>

        <?php if ($esArchivado) { ?>

            <span class="etiqueta-archivado">
                Paciente archivado
            </span>

            <div class="aviso-archivado">

                <strong>
                    Historial médico de solo lectura
                </strong>

                <p>
                    Este paciente se encuentra archivado.
                    Podés consultar sus estudios,
                    exposiciones, turnos y registros médicos.
                </p>

                <p>
                    Para volver a modificar su información,
                    primero deberás reactivarlo desde
                    Pacientes archivados.
                </p>

            </div>

            <p>
                <a
                    href="../Pacientes/antiguos.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>"
                >
                    Volver a la ficha archivada
                </a>
            </p>

        <?php } else { ?>

            <p>
                <a href="../Pacientes/listar.php">
                    Volver al listado de pacientes
                </a>
            </p>

        <?php } ?>

    </section>

    <!-- =====================================
         DESCARGAS DEL HISTORIAL
    ===================================== -->

    <section class="tarjeta">

        <h2>Documentación del paciente</h2>

        <?php if (!$esArchivado) { ?>

            <div class="enlaces-descarga">

                <p>
                    <a
                        href="descargar_pdf.php?dni_p=<?php
                            echo urlencode($dni_p);
                        ?>&tipo=interno"
                    >
                        Descargar historial completo (PDF)
                    </a>
                </p>

                <p>
                    <a
                        href="descargar_pdf.php?dni_p=<?php
                            echo urlencode($dni_p);
                        ?>&tipo=paciente"
                    >
                        Descargar informe para el paciente (PDF)
                    </a>
                </p>

            </div>

        <?php } else { ?>

            <p class="mensaje-proteccion">
                La descarga de PDF para pacientes archivados
                estará disponible cuando protejamos
                también descargar_pdf.php.
            </p>

        <?php } ?>

    </section>

    <!-- =====================================
         ESTUDIOS INDICADOS
    ===================================== -->

    <section class="tarjeta">

        <h2>Estudios indicados</h2>

        <?php if (!$esArchivado) { ?>

            <p>
                <a
                    class="enlace-administrar"
                    href="administrar_estudios.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>"
                >
                    Administrar estudios indicados
                </a>
            </p>

        <?php } ?>

        <?php if (!$estudios) { ?>

            <p>
                Este paciente no tiene estudios indicados.
            </p>

        <?php } else { ?>

            <ul class="lista-datos">

                <?php foreach ($estudios as $estudio) { ?>

                    <li>

                        <?php
                        echo escapar(
                            $estudio["nombre_es"]
                        );
                        ?>

                        <?php
                        $realizado =
                            $estudio["estado"] === "Realizado";
                        ?>

                        <span class="estado <?php
                            echo $realizado
                                ? "estado-realizado"
                                : "estado-pendiente";
                        ?>">

                            <?php
                            echo escapar(
                                $estudio["estado"]
                            );
                            ?>

                        </span>

                    </li>

                <?php } ?>

            </ul>

        <?php } ?>

    </section>

    <!-- =====================================
         EXPOSICIONES
    ===================================== -->

    <section class="tarjeta">

        <h2>Exposiciones del paciente</h2>

        <?php if (!$esArchivado) { ?>

            <p>
                <a
                    class="enlace-administrar"
                    href="administrar_exposiciones.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>"
                >
                    Administrar exposiciones
                </a>
            </p>

        <?php } ?>

        <?php if (!$exposiciones) { ?>

            <p>
                Este paciente no tiene exposiciones asignadas.
            </p>

        <?php } else { ?>

            <ul class="lista-datos">

                <?php foreach ($exposiciones as $exposicion) { ?>

                    <li>
                        <?php
                        echo escapar(
                            $exposicion["nombre_ex"]
                        );
                        ?>
                    </li>

                <?php } ?>

            </ul>

        <?php } ?>

    </section>

    <!-- =====================================
         TURNOS PENDIENTES
    ===================================== -->

    <section class="tarjeta">

        <h2>Turnos pendientes</h2>

        <?php if (!$esArchivado) { ?>

            <p>
                <a
                    class="enlace-administrar"
                    href="../turnos/formulario.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>"
                >
                    Registrar nuevo turno
                </a>
            </p>

            <p>
                <a
                    href="../turnos/listar.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>&estado=Todos"
                >
                    Ver todos los turnos de este paciente
                </a>
            </p>

        <?php } ?>

        <?php if (!$turnos) { ?>

            <p>
                Este paciente no tiene turnos
                pendientes ni confirmados.
            </p>

        <?php } else { ?>

            <p>
                Turnos activos:
                <strong>
                    <?php echo count($turnos); ?>
                </strong>
            </p>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>

                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Recordatorio</th>
                            <th>Observaciones</th>

                            <?php if (!$esArchivado) { ?>
                                <th>Acciones</th>
                            <?php } ?>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($turnos as $turno) { ?>

                            <tr>

                                <td>
                                    <?php
                                    echo escapar(
                                        date(
                                            "d/m/Y",
                                            strtotime(
                                                $turno["fecha"]
                                            )
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo escapar(
                                        substr(
                                            $turno["hora"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo escapar(
                                        $turno["motivo"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <span class="estado <?php
                                        echo $turno["estado"] === "Confirmado"
                                            ? "estado-confirmado"
                                            : "estado-pendiente";
                                    ?>">

                                        <?php
                                        echo escapar(
                                            $turno["estado"]
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td class="aviso-turno">
                                    <?php
                                    echo escapar(
                                        avisoTurno(
                                            $turno["fecha"],
                                            $turno["estado"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo nl2br(
                                        escapar(
                                            $turno["observaciones"]
                                        )
                                    );
                                    ?>
                                </td>

                                <?php if (!$esArchivado) { ?>

                                    <td class="acciones-turnos">

                                        <a
                                            href="../turnos/formulario.php?id_turno=<?php
                                                echo (int)$turno["id_turno"];
                                            ?>"
                                        >
                                            Editar turno
                                        </a>

                                    </td>

                                <?php } ?>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

    <!-- =====================================
         REGISTROS DEL HISTORIAL
    ===================================== -->

    <section class="tarjeta">

        <h2>Registros del historial</h2>

        <?php if (!$esArchivado) { ?>

            <p>
                <a
                    href="agregar_historial.php?dni_p=<?php
                        echo urlencode($dni_p);
                    ?>"
                >
                    Registrar nuevo estudio
                </a>
            </p>

        <?php } ?>

        <?php if (!$historial) { ?>

            <p>
                Todavía no hay estudios registrados
                en el historial.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>

                        <tr>
                            <th>Estudio</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Archivo</th>

                            <?php if (!$esArchivado) { ?>
                                <th>Acciones</th>
                            <?php } ?>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($historial as $fila) { ?>

                            <tr>

                                <td>
                                    <?php
                                    echo escapar(
                                        $fila["nombre_es"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo escapar(
                                        $fila["fecha"]
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php
                                    $realizado =
                                        $fila["estado"] === "Realizado";
                                    ?>

                                    <span class="estado <?php
                                        echo $realizado
                                            ? "estado-realizado"
                                            : "estado-pendiente";
                                    ?>">

                                        <?php
                                        echo escapar(
                                            $fila["estado"]
                                        );
                                        ?>

                                    </span>

                                </td>

                                <td>
                                    <?php
                                    echo nl2br(
                                        escapar(
                                            $fila["observaciones"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php if (!empty($fila["archivo"])) { ?>

                                        <?php if (!$esArchivado) { ?>

                                            <a
                                                href="../archivos/<?php
                                                    echo rawurlencode(
                                                        basename(
                                                            $fila["archivo"]
                                                        )
                                                    );
                                                ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Ver archivo
                                            </a>

                                        <?php } else { ?>

                                            <span class="mensaje-proteccion">
                                                Archivo conservado.
                                                Acceso protegido pendiente.
                                            </span>

                                        <?php } ?>

                                    <?php } else { ?>

                                        Sin archivo

                                    <?php } ?>

                                </td>

                                <?php if (!$esArchivado) { ?>

                                    <td class="acciones-historial">

                                        <a
                                            href="editar_historial.php?id_historial=<?php
                                                echo (int)$fila["id_historial"];
                                            ?>"
                                        >
                                            Editar
                                        </a>

                                        <a
                                            href="eliminar_historial.php?id_historial=<?php
                                                echo (int)$fila["id_historial"];
                                            ?>"
                                            class="btn-eliminar"
                                            onclick="return confirm('¿Estás seguro de eliminar este registro?');"
                                        >
                                            Eliminar
                                        </a>

                                    </td>

                                <?php } ?>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

</main>

</body>
</html>
