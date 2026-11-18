
<?php

// =========================================
// DUCCA - AGREGAR REGISTRO AL HISTORIAL
// =========================================

// VERIFICAR INICIO DE SESIÓN

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

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

// =========================================
// OBTENER Y VALIDAR DNI
// =========================================

$dni_p = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

if (!$dni_p || $dni_p <= 0) {
    http_response_code(400);
    exit("No se indicó un paciente válido.");
}

// =========================================
// BUSCAR PACIENTE Y COMPROBAR ESTADO
// =========================================

$sql = "SELECT
            dni_p,
            nombre_p,
            apellido,
            estado
        FROM paciente
        WHERE dni_p = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    exit("No se pudo consultar al paciente.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dni_p
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    http_response_code(404);
    exit("No se encontró el paciente.");
}

// =========================================
// BLOQUEAR PACIENTES ARCHIVADOS
// =========================================

if ($paciente["estado"] !== "activo") {

    http_response_code(403);

    exit(
        "Este paciente está archivado. " .
        "Su historial médico es de solo lectura. " .
        "Para agregar estudios, primero debés reactivarlo."
    );
}

// =========================================
// BUSCAR ESTUDIOS INDICADOS
// =========================================

$sql = "SELECT
            e.id_estudio,
            e.nombre_es

        FROM paciente_estudio pe

        INNER JOIN estudio e
            ON pe.id_estudio = e.id_estudio

        WHERE pe.dni_paciente = ?

        ORDER BY e.nombre_es";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    exit("No se pudieron consultar los estudios.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dni_p
);

mysqli_stmt_execute($stmt);

$resultadoEstudios = mysqli_stmt_get_result($stmt);

$estudios = mysqli_fetch_all(
    $resultadoEstudios,
    MYSQLI_ASSOC
);

mysqli_stmt_close($stmt);
mysqli_close($conexion);

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

// Se envía con el formulario.
// guardar_historial.php deberá comprobarlo.

if (empty($_SESSION["csrf_guardar_historial"])) {

    $_SESSION["csrf_guardar_historial"] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_guardar_historial"];

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Registrar estudio - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .agregar-historial-contenido {
            width: calc(100% - 30px);
            max-width: 760px;
            margin: 35px auto;
            box-sizing: border-box;
        }

        .agregar-historial-tarjeta {
            padding: 30px;
        }

        .agregar-historial-campo {
            margin-bottom: 20px;
        }

        .agregar-historial-campo label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .agregar-historial-campo input,
        .agregar-historial-campo select,
        .agregar-historial-campo textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
        }

        .agregar-historial-campo textarea {
            resize: vertical;
        }

        .agregar-historial-acciones {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .agregar-historial-acciones button {
            padding: 12px 20px;
            cursor: pointer;
        }

        .agregar-historial-aviso {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 9px;
            background: rgba(130, 140, 150, 0.12);
        }

        @media (max-width: 600px) {

            .agregar-historial-tarjeta {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<main class="agregar-historial-contenido">

    <section class="tarjeta agregar-historial-tarjeta">

        <h1>Registrar estudio</h1>

        <h2>
            <?php
                echo escapar(
                    $paciente["nombre_p"] . " " .
                    $paciente["apellido"]
                );
            ?>
        </h2>

        <div class="agregar-historial-aviso">

            <p>
                Registrá un estudio médico del paciente.
                Podés indicar su estado, fecha,
                observaciones y adjuntar un archivo.
            </p>

        </div>

        <?php if (count($estudios) === 0) { ?>

            <p>
                Este paciente todavía no tiene
                estudios indicados.
            </p>

            <p>
                Primero debés asignarle un estudio
                para poder registrarlo en el historial.
            </p>

            <div class="agregar-historial-acciones">

                <a href="administrar_estudios.php?dni_p=<?php
                    echo urlencode((string)$dni_p);
                ?>">
                    Administrar estudios indicados
                </a>

                <a href="historial.php?dni_p=<?php
                    echo urlencode((string)$dni_p);
                ?>">
                    Volver al historial
                </a>

            </div>

        <?php } else { ?>

            <form
                action="guardar_historial.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <!-- DATOS OCULTOS -->

                <input
                    type="hidden"
                    name="dni_p"
                    value="<?php
                        echo escapar($dni_p);
                    ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php
                        echo escapar($csrfToken);
                    ?>"
                >

                <!-- ESTUDIO -->

                <div class="agregar-historial-campo">

                    <label for="id_estudio">
                        Estudio
                    </label>

                    <select
                        id="id_estudio"
                        name="id_estudio"
                        required
                    >

                        <option value="">
                            Seleccionar estudio
                        </option>

                        <?php foreach ($estudios as $fila) { ?>

                            <option
                                value="<?php
                                    echo (int)$fila["id_estudio"];
                                ?>"
                            >
                                <?php
                                    echo escapar(
                                        $fila["nombre_es"]
                                    );
                                ?>
                            </option>

                        <?php } ?>

                    </select>

                </div>

                <!-- FECHA -->

                <div class="agregar-historial-campo">

                    <label for="fecha">
                        Fecha del estudio
                    </label>

                    <input
                        type="date"
                        id="fecha"
                        name="fecha"
                        required
                        value="<?php
                            echo date("Y-m-d");
                        ?>"
                    >

                </div>

                <!-- ESTADO -->

                <div class="agregar-historial-campo">

                    <label for="estado">
                        Estado
                    </label>

                    <select
                        id="estado"
                        name="estado"
                        required
                    >

                        <option value="Pendiente">
                            Pendiente
                        </option>

                        <option value="Realizado">
                            Realizado
                        </option>

                    </select>

                </div>

                <!-- OBSERVACIONES -->

                <div class="agregar-historial-campo">

                    <label for="observaciones">
                        Observaciones
                    </label>

                    <textarea
                        id="observaciones"
                        name="observaciones"
                        rows="5"
                        placeholder="Ingresá las observaciones del estudio..."
                    ></textarea>

                </div>

                <!-- ARCHIVO ADJUNTO -->

                <div class="agregar-historial-campo">

                    <label for="archivo">
                        Archivo del estudio (opcional)
                    </label>

                    <input
                        type="file"
                        id="archivo"
                        name="archivo"
                        accept=".pdf,.jpg,.jpeg,.png"
                    >

                    <small>
                        Formatos permitidos:
                        PDF, JPG y PNG.
                        Tamaño máximo: 5 MB.
                    </small>

                </div>

                <!-- BOTONES -->

                <div class="agregar-historial-acciones">

                    <button type="submit">
                        Guardar estudio
                    </button>

                    <a href="historial.php?dni_p=<?php
                        echo urlencode((string)$dni_p);
                    ?>">
                        Volver al historial
                    </a>

                </div>

            </form>

        <?php } ?>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
