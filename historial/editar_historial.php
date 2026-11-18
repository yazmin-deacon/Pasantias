
<?php

// =========================================
// DUCCA - EDITAR REGISTRO MÉDICO
// =========================================

// VERIFICAR INICIO DE SESIÓN

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

// Evitar que el navegador guarde datos médicos.

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
// VALIDAR ID DEL REGISTRO
// =========================================

$id_historial = filter_input(
    INPUT_GET,
    "id_historial",
    FILTER_VALIDATE_INT
);

if (!$id_historial || $id_historial <= 0) {
    http_response_code(400);
    exit("No se indicó un registro válido.");
}

// =========================================
// BUSCAR REGISTRO Y ESTADO DEL PACIENTE
// =========================================

$sql = "SELECT
            h.*,
            e.nombre_es,
            p.estado AS estado_paciente

        FROM historial_medico h

        INNER JOIN estudio e
            ON h.id_estudio = e.id_estudio

        INNER JOIN paciente p
            ON h.dni_paciente = p.dni_p

        WHERE h.id_historial = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    exit("No se pudo consultar el registro.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id_historial
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$registro = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$registro) {
    http_response_code(404);
    exit("No se encontró el registro.");
}

// =========================================
// BLOQUEAR PACIENTES ARCHIVADOS
// =========================================

if ($registro["estado_paciente"] !== "activo") {

    http_response_code(403);

    exit(
        "Este paciente está archivado. " .
        "Su historial médico es de solo lectura. " .
        "Para modificarlo, primero debés reactivarlo."
    );
}

$dni_p = (int)$registro["dni_paciente"];

// =========================================
// BUSCAR ESTUDIOS INDICADOS
// =========================================

$sql = "SELECT
            estudio.id_estudio,
            estudio.nombre_es

        FROM paciente_estudio

        INNER JOIN estudio
            ON paciente_estudio.id_estudio = estudio.id_estudio

        WHERE paciente_estudio.dni_paciente = ?

        ORDER BY estudio.nombre_es";

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

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Editar registro médico - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .editar-historial-contenido {
            width: calc(100% - 30px);
            max-width: 760px;
            margin: 35px auto;
        }

        .editar-historial-tarjeta {
            padding: 30px;
        }

        .editar-historial-campo {
            margin-bottom: 20px;
        }

        .editar-historial-campo label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .editar-historial-campo input,
        .editar-historial-campo select,
        .editar-historial-campo textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
        }

        .editar-historial-campo textarea {
            resize: vertical;
        }

        .editar-historial-acciones {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .editar-historial-acciones button {
            padding: 12px 20px;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .editar-historial-tarjeta {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<main class="editar-historial-contenido">

    <section class="tarjeta editar-historial-tarjeta">

        <h1>Editar registro médico</h1>

        <p>
            Modificá la información del estudio
            registrado en el historial del paciente.
        </p>

        <form
            action="actualizar_historial.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <input
                type="hidden"
                name="id_historial"
                value="<?php
                    echo escapar($registro["id_historial"]);
                ?>"
            >

            <input
                type="hidden"
                name="dni_p"
                value="<?php echo escapar($dni_p); ?>"
            >

            <!-- ESTUDIO -->

            <div class="editar-historial-campo">

                <label for="id_estudio">
                    Estudio
                </label>

                <select
                    id="id_estudio"
                    name="id_estudio"
                    required
                >

                    <?php foreach ($estudios as $fila) { ?>

                        <option
                            value="<?php
                                echo (int)$fila["id_estudio"];
                            ?>"
                            <?php
                                echo (
                                    $fila["id_estudio"] ==
                                    $registro["id_estudio"]
                                )
                                    ? "selected"
                                    : "";
                            ?>
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

            <div class="editar-historial-campo">

                <label for="fecha">
                    Fecha
                </label>

                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    required
                    value="<?php
                        echo escapar($registro["fecha"]);
                    ?>"
                >

            </div>

            <!-- ESTADO -->

            <div class="editar-historial-campo">

                <label for="estado">
                    Estado
                </label>

                <select
                    id="estado"
                    name="estado"
                    required
                >

                    <option
                        value="Pendiente"
                        <?php
                            echo $registro["estado"] === "Pendiente"
                                ? "selected"
                                : "";
                        ?>
                    >
                        Pendiente
                    </option>

                    <option
                        value="Realizado"
                        <?php
                            echo $registro["estado"] === "Realizado"
                                ? "selected"
                                : "";
                        ?>
                    >
                        Realizado
                    </option>

                </select>

            </div>

            <!-- OBSERVACIONES -->

            <div class="editar-historial-campo">

                <label for="observaciones">
                    Observaciones
                </label>

                <textarea
                    id="observaciones"
                    name="observaciones"
                    rows="5"
                ><?php
                    echo escapar(
                        $registro["observaciones"] ?? ""
                    );
                ?></textarea>

            </div>

            <!-- ARCHIVO ACTUAL -->

            <div class="editar-historial-campo">

                <label>Archivo actual</label>

                <?php if (!empty($registro["archivo"])) { ?>

                    <a
                        href="../archivos/<?php
                            echo rawurlencode(
                                basename($registro["archivo"])
                            );
                        ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Ver archivo actual
                    </a>

                <?php } else { ?>

                    <p>Sin archivo</p>

                <?php } ?>

            </div>

            <!-- REEMPLAZAR ARCHIVO -->

            <div class="editar-historial-campo">

                <label for="archivo">
                    Reemplazar archivo (opcional)
                </label>

                <input
                    type="file"
                    id="archivo"
                    name="archivo"
                    accept=".pdf,.jpg,.jpeg,.png"
                >

            </div>

            <!-- BOTONES -->

            <div class="editar-historial-acciones">

                <button type="submit">
                    Guardar cambios
                </button>

                <a href="historial.php?dni_p=<?php
                    echo urlencode($dni_p);
                ?>">
                    Volver al historial
                </a>

            </div>

        </form>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
