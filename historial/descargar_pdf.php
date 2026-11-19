
<?php

// =========================================
// DUCCA - DESCARGAR HISTORIAL EN PDF
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/../Pacientes/conexion.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

/** @var mysqli $conexion */

date_default_timezone_set("America/Argentina/Salta");

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

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

function consultar($conexion, $sql, $dni) {
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta.");
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return $filas;
}

function fechaBonita($fecha) {
    if (empty($fecha)) {
        return "-";
    }

    return date("d/m/Y", strtotime($fecha));
}

function textoConSaltos($texto) {
    return nl2br(escapar($texto));
}

// =========================================
// VALIDAR PARÁMETROS
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    header("Allow: GET");
    exit("Método no permitido.");
}

$dni = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

$tipo = (string)($_GET["tipo"] ?? "");

if (!$dni || $dni <= 0) {
    http_response_code(400);
    exit("DNI no válido.");
}

if (!in_array($tipo, ["interno", "paciente"], true)) {
    http_response_code(400);
    exit("Tipo de informe no válido.");
}

$esInterno = ($tipo === "interno");

// =========================================
// CONSULTAR PACIENTE Y ESTADO
// =========================================

$filas = consultar(
    $conexion,
    "SELECT
        dni_p,
        nombre_p,
        apellido,
        cuil,
        telefono,
        mail,
        estado
     FROM paciente
     WHERE dni_p = ?",
    $dni
);

if (!$filas) {
    http_response_code(404);
    exit("No se encontró el paciente.");
}

$paciente = $filas[0];

$esArchivado = (
    $paciente["estado"] === "archivado"
);

// =========================================
// PROTEGER PACIENTES ARCHIVADOS
// =========================================

if ($esArchivado) {

    $dniSesion = (string)(
        $_SESSION["dni"] ?? ""
    );

    $dniAutorizado = (string)(
        $_SESSION["acceso_archivados_dni"] ?? ""
    );

    $accesoHasta = (int)(
        $_SESSION["acceso_archivados_hasta"] ?? 0
    );

    $accesoValido = (
        $dniSesion !== "" &&
        $dniAutorizado === $dniSesion &&
        $accesoHasta > time()
    );

    if (!$accesoValido) {

        mysqli_close($conexion);

        // No generar el PDF sin autorización.

        http_response_code(403);

        exit(
            "El paciente está archivado. " .
            "Primero debés ingresar a Pacientes archivados " .
            "y verificar tu contraseña."
        );
    }
}

// =========================================
// CONFIGURACIÓN DE DUCCA
// =========================================

$consultorio = "DUCCA";

$subtituloConsultorio = "Consultorio profesional";

$rutaLogo = __DIR__ .
    "/../general/Logo minimalista DUCCA con hojas orgánicas.png";

$logoBase64 = "";

if (is_file($rutaLogo) && is_readable($rutaLogo)) {

    $contenidoLogo = file_get_contents($rutaLogo);

    if ($contenidoLogo !== false) {
        $logoBase64 =
            "data:image/png;base64," .
            base64_encode($contenidoLogo);
    }
}

// =========================================
// ESTUDIOS INDICADOS
// =========================================

$estudios = consultar(
    $conexion,
    "SELECT
        e.nombre_es,
        pe.estado
     FROM paciente_estudio pe
     INNER JOIN estudio e
        ON pe.id_estudio = e.id_estudio
     WHERE pe.dni_paciente = ?
     ORDER BY e.nombre_es",
    $dni
);

// =========================================
// REGISTROS DEL HISTORIAL
// =========================================

$historial = consultar(
    $conexion,
    "SELECT
        h.fecha,
        h.estado,
        h.observaciones,
        h.archivo,
        e.nombre_es
     FROM historial_medico h
     INNER JOIN estudio e
        ON h.id_estudio = e.id_estudio
     WHERE h.dni_paciente = ?
     ORDER BY h.fecha DESC, h.id_historial DESC",
    $dni
);

// =========================================
// DATOS SOLO PARA USO INTERNO
// =========================================

$exposiciones = [];
$turnos = [];

if ($esInterno) {

    $exposiciones = consultar(
        $conexion,
        "SELECT e.nombre_ex
         FROM paciente_exposicion pe
         INNER JOIN exposicion e
            ON pe.id_exposicion = e.id_exposicion
         WHERE pe.dni_paciente = ?
         ORDER BY e.nombre_ex",
        $dni
    );

    // Todos los turnos, sin excluir
    // realizados, cancelados o ausentes.

    $turnos = consultar(
        $conexion,
        "SELECT
            fecha,
            hora,
            motivo,
            estado,
            observaciones
         FROM turno
         WHERE dni_paciente = ?
         ORDER BY fecha DESC, hora DESC",
        $dni
    );
}

mysqli_close($conexion);

// =========================================
// DATOS DEL DOCUMENTO
// =========================================

$titulo = $esInterno
    ? "Historial médico completo"
    : "Informe para el paciente";

$subtitulo = $esInterno
    ? "DOCUMENTO CONFIDENCIAL - USO INTERNO"
    : "RESUMEN INFORMATIVO DE ESTUDIOS";

$fechaEmision = date("d/m/Y");

// =========================================
// CREAR HTML DEL PDF
// =========================================

ob_start();

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <style>

        @page {
            margin: 38px 42px 46px;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10px;
            line-height: 1.55;
            color: #304456;
        }

        .encabezado {
            border-bottom: 3px solid #486d88;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }

        .tabla-encabezado {
            width: 100%;
            border: none;
            border-collapse: collapse;
            margin: 0;
            padding: 0;
        }

        .tabla-encabezado td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .celda-logo {
            width: 105px;
        }

        .logo {
            width: 90px;
            height: auto;
        }

        .celda-consultorio {
            padding-left: 15px !important;
        }

        .nombre-consultorio {
            font-size: 24px;
            font-weight: bold;
            color: #25445d;
            margin-bottom: 4px;
        }

        .subtitulo-consultorio {
            font-size: 10px;
            color: #64798b;
        }

        .titulo {
            font-size: 20px;
            color: #25445d;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitulo {
            color: #687c8d;
            font-size: 9px;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .fecha-emision {
            color: #64798b;
            font-size: 9px;
            margin-bottom: 20px;
        }

        .datos-paciente {
            background: #f0f5f9;
            padding: 14px;
            margin-bottom: 20px;
        }

        .datos-paciente p {
            margin: 3px 0;
        }

        h2 {
            color: #25445d;
            font-size: 13px;
            border-bottom: 1px solid #cddbe5;
            padding-bottom: 6px;
            margin-top: 25px;
            margin-bottom: 10px;
        }

        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 18px;
            table-layout: fixed;
        }

        .tabla-datos th {
            text-align: left;
            background: #e8f0f6;
            color: #25445d;
            padding: 9px;
            border: 1px solid #d2dee8;
        }

        .tabla-datos td {
            border: 1px solid #d2dee8;
            padding: 9px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .tabla-datos tr {
            page-break-inside: avoid;
        }

        ul {
            padding-left: 19px;
        }

        li {
            margin-bottom: 7px;
        }

        .sin-datos {
            color: #748494;
            font-style: italic;
        }

        .aviso-final {
            border-top: 1px solid #cddbe5;
            padding-top: 12px;
            margin-top: 30px;
            color: #64798b;
            font-size: 9px;
        }

        .pie {
            color: #8794a1;
            font-size: 8px;
            margin-top: 12px;
        }

    </style>
</head>

<body>

<!-- ENCABEZADO -->

<div class="encabezado">

    <table class="tabla-encabezado">
        <tr>

            <td class="celda-logo">

                <?php if ($logoBase64 !== "") { ?>

                    <img
                        src="<?php echo escapar($logoBase64); ?>"
                        class="logo"
                        alt="Logo DUCCA"
                    >

                <?php } ?>

            </td>

            <td class="celda-consultorio">

                <div class="nombre-consultorio">
                    <?php echo escapar($consultorio); ?>
                </div>

                <div class="subtitulo-consultorio">
                    <?php
                    echo escapar($subtituloConsultorio);
                    ?>
                </div>

            </td>

        </tr>
    </table>

</div>

<!-- TÍTULO -->

<div class="titulo">
    <?php echo escapar($titulo); ?>
</div>

<div class="subtitulo">
    <?php echo escapar($subtitulo); ?>
</div>

<div class="fecha-emision">
    Fecha de emisión:
    <?php echo escapar($fechaEmision); ?>
</div>

<!-- DATOS DEL PACIENTE -->

<div class="datos-paciente">

    <strong>DATOS DEL PACIENTE</strong>

    <p>
        <strong>Nombre y apellido:</strong>
        <?php
        echo escapar(
            $paciente["nombre_p"] . " " .
            $paciente["apellido"]
        );
        ?>
    </p>

    <p>
        <strong>DNI:</strong>
        <?php echo escapar($paciente["dni_p"]); ?>
    </p>

    <?php if ($esInterno) { ?>

        <p>
            <strong>CUIL:</strong>
            <?php echo escapar($paciente["cuil"]); ?>
        </p>

        <p>
            <strong>Teléfono:</strong>
            <?php echo escapar($paciente["telefono"]); ?>
        </p>

        <p>
            <strong>Correo electrónico:</strong>
            <?php echo escapar($paciente["mail"]); ?>
        </p>

    <?php } ?>

</div>

<!-- ESTUDIOS INDICADOS -->

<h2>Estudios indicados</h2>

<?php if (!$estudios) { ?>

    <p class="sin-datos">
        No hay estudios indicados.
    </p>

<?php } else { ?>

    <table class="tabla-datos">

        <thead>
            <tr>
                <th>Estudio</th>
                <th>Estado</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($estudios as $estudio) { ?>

                <tr>

                    <td>
                        <?php
                        echo escapar($estudio["nombre_es"]);
                        ?>
                    </td>

                    <td>
                        <?php
                        echo escapar($estudio["estado"]);
                        ?>
                    </td>

                </tr>

            <?php } ?>

        </tbody>
    </table>

<?php } ?>

<!-- CONTENIDO EXCLUSIVO DEL PDF INTERNO -->

<?php if ($esInterno) { ?>

    <h2>Exposiciones del paciente</h2>

    <?php if (!$exposiciones) { ?>

        <p class="sin-datos">
            No hay exposiciones asignadas.
        </p>

    <?php } else { ?>

        <ul>

            <?php foreach ($exposiciones as $exposicion) { ?>

                <li>
                    <?php
                    echo escapar($exposicion["nombre_ex"]);
                    ?>
                </li>

            <?php } ?>

        </ul>

    <?php } ?>

    <!-- TODOS LOS TURNOS -->

    <h2>Turnos registrados</h2>

    <?php if (!$turnos) { ?>

        <p class="sin-datos">
            No hay turnos registrados.
        </p>

    <?php } else { ?>

        <table class="tabla-datos">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($turnos as $turno) { ?>

                    <tr>

                        <td>
                            <?php
                            echo escapar(
                                fechaBonita($turno["fecha"])
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo escapar(
                                substr($turno["hora"], 0, 5)
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo escapar($turno["motivo"]);
                            ?>
                        </td>

                        <td>
                            <?php
                            echo escapar($turno["estado"]);
                            ?>
                        </td>

                        <td>
                            <?php
                            echo textoConSaltos(
                                $turno["observaciones"]
                            );
                            ?>
                        </td>

                    </tr>

                <?php } ?>

            </tbody>

        </table>

    <?php } ?>

<?php } ?>

<!-- REGISTROS MÉDICOS -->

<h2>Registros de estudios</h2>

<?php if (!$historial) { ?>

    <p class="sin-datos">
        No hay registros de estudios.
    </p>

<?php } else { ?>

    <table class="tabla-datos">

        <thead>

            <tr>
                <th>Estudio</th>
                <th>Fecha</th>
                <th>Estado</th>

                <?php if ($esInterno) { ?>
                    <th>Observaciones</th>
                    <th>Archivo</th>
                <?php } ?>

            </tr>

        </thead>

        <tbody>

            <?php foreach ($historial as $registro) { ?>

                <tr>

                    <td>
                        <?php
                        echo escapar($registro["nombre_es"]);
                        ?>
                    </td>

                    <td>
                        <?php
                        echo escapar(
                            fechaBonita($registro["fecha"])
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo escapar($registro["estado"]);
                        ?>
                    </td>

                    <?php if ($esInterno) { ?>

                        <td>
                            <?php
                            echo textoConSaltos(
                                $registro["observaciones"]
                            );
                            ?>
                        </td>

                        <td>
                            <?php
                            echo !empty($registro["archivo"])
                                ? "Archivo adjunto registrado"
                                : "Sin archivo";
                            ?>
                        </td>

                    <?php } ?>

                </tr>

            <?php } ?>

        </tbody>

    </table>

<?php } ?>

<!-- ACLARACIONES -->

<div class="aviso-final">

    <?php if ($esInterno) { ?>

        <strong>Documento confidencial.</strong>

        <p>
            Este historial contiene información
            de uso interno del consultorio.
            Los archivos adjuntos no están
            incorporados en este PDF.
        </p>

    <?php } else { ?>

        <strong>Informe informativo.</strong>

        <p>
            Este documento resume los estudios
            indicados y los registros disponibles.
            No incluye observaciones internas,
            turnos ni archivos adjuntos.
        </p>

        <p>
            No sustituye un informe profesional
            firmado ni una evaluación clínica.
        </p>

    <?php } ?>

</div>

<div class="pie">
    Documento generado desde el sistema DUCCA.
</div>

</body>
</html>

<?php

// =========================================
// GENERAR PDF
// =========================================

$html = ob_get_clean();

$options = new Options();

$options->set(
    "defaultFont",
    "DejaVu Sans"
);

$options->set(
    "isRemoteEnabled",
    false
);

$options->setChroot(
    realpath(__DIR__ . "/..")
);

$dompdf = new Dompdf($options);

$dompdf->loadHtml(
    $html,
    "UTF-8"
);

$dompdf->setPaper(
    "A4",
    "portrait"
);

$dompdf->render();

// =========================================
// DESCARGAR DOCUMENTO
// =========================================

$nombreArchivo = $esInterno
    ? "DUCCA_historial_interno_" . $dni . ".pdf"
    : "DUCCA_informe_paciente_" . $dni . ".pdf";

$dompdf->stream(
    $nombreArchivo,
    ["Attachment" => true]
);

exit;

?>
