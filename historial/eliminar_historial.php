
<?php

// =========================================
// DUCCA - ELIMINAR REGISTRO DEL HISTORIAL
// =========================================

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

function mostrarError($mensaje, $codigo = 400) {
    http_response_code($codigo);

    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error - DUCCA</title>
        <link rel="stylesheet" href="../general/estilos.css">
    </head>
    <body>
        <main class="gestion-contenido">
            <section class="tarjeta">
                <h1>No se pudo eliminar el registro</h1>
                <p>' . escapar($mensaje) . '</p>
                <a href="../Pacientes/listar.php">
                    Volver a pacientes
                </a>
            </section>
        </main>
    </body>
    </html>';

    exit;
}

// =========================================
// COMPROBAR MÉTODO
// =========================================

$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo !== "GET" && $metodo !== "POST") {
    header("Allow: GET, POST");
    mostrarError("Método no permitido.", 405);
}

// =========================================
// VALIDAR ID
// =========================================

$idTexto = (string)(
    $metodo === "POST"
        ? ($_POST["id_historial"] ?? "")
        : ($_GET["id_historial"] ?? "")
);

if (
    !ctype_digit($idTexto) ||
    strlen($idTexto) > 10 ||
    (int)$idTexto <= 0
) {
    mostrarError("El registro indicado no es válido.");
}

$id_historial = (int)$idTexto;

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

if (empty($_SESSION["csrf_eliminar_historial"])) {
    $_SESSION["csrf_eliminar_historial"] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_eliminar_historial"];

// =========================================
// COMPROBAR REGISTRO Y ESTADO DEL PACIENTE
// =========================================

$sql = "SELECT
            h.id_historial,
            h.dni_paciente,
            h.id_estudio,
            h.fecha,
            h.estado,
            h.archivo,
            p.nombre_p,
            p.apellido,
            p.estado AS estado_paciente,
            e.nombre_es

        FROM historial_medico h

        INNER JOIN paciente p
            ON h.dni_paciente = p.dni_p

        INNER JOIN estudio e
            ON h.id_estudio = e.id_estudio

        WHERE h.id_historial = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    mostrarError(
        "No se pudo consultar el registro.",
        500
    );
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
    mostrarError(
        "No se encontró el registro médico.",
        404
    );
}

// =========================================
// BLOQUEAR PACIENTES ARCHIVADOS
// =========================================

if ($registro["estado_paciente"] !== "activo") {

    mostrarError(
        "Este paciente está archivado. " .
        "Su historial médico es de solo lectura. " .
        "Para modificarlo, primero debés reactivarlo.",
        403
    );
}

$dni_p = (int)$registro["dni_paciente"];

// =========================================
// PROCESAR ELIMINACIÓN CONFIRMADA
// =========================================

if ($metodo === "POST") {

    $tokenEnviado = (string)(
        $_POST["csrf_token"] ?? ""
    );

    if (!hash_equals($csrfToken, $tokenEnviado)) {
        mostrarError(
            "La solicitud no es válida. Volvé a intentarlo.",
            403
        );
    }

    if (
        ($_POST["accion"] ?? "") !== "eliminar"
    ) {
        mostrarError("Acción no permitida.");
    }

    $transaccionIniciada = false;

    try {

        mysqli_begin_transaction($conexion);
        $transaccionIniciada = true;

        // =================================
        // 1. BLOQUEAR PACIENTE
        // =================================

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT estado
             FROM paciente
             WHERE dni_p = ?
             FOR UPDATE"
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo consultar al paciente."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $dni_p
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $pacienteActual = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (
            !$pacienteActual ||
            $pacienteActual["estado"] !== "activo"
        ) {
            throw new Exception(
                "El paciente ya no está activo."
            );
        }

        // =================================
        // 2. BLOQUEAR REGISTRO
        // =================================

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT
                dni_paciente,
                archivo
             FROM historial_medico
             WHERE id_historial = ?
             FOR UPDATE"
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo consultar el historial."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_historial
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $registroActual = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (
            !$registroActual ||
            (int)$registroActual["dni_paciente"] !== $dni_p
        ) {
            throw new Exception(
                "El registro ya no está disponible."
            );
        }

        $archivoAnterior = $registroActual["archivo"];

        // =================================
        // 3. ELIMINAR REGISTRO
        // =================================

        $stmt = mysqli_prepare(
            $conexion,
            "DELETE FROM historial_medico
             WHERE id_historial = ?
               AND dni_paciente = ?"
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo preparar la eliminación."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $id_historial,
            $dni_p
        );

        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception(
                "No se pudo eliminar el registro."
            );
        }

        mysqli_stmt_close($stmt);

        // =================================
        // 4. COMPROBAR SI EL ARCHIVO
        //    LO USAN OTROS REGISTROS
        // =================================

        $borrarArchivo = false;

        if (!empty($archivoAnterior)) {

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT COUNT(*) AS cantidad
                 FROM historial_medico
                 WHERE archivo = ?"
            );

            if (!$stmt) {
                throw new Exception(
                    "No se pudo verificar el archivo."
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $archivoAnterior
            );

            mysqli_stmt_execute($stmt);

            $resultado = mysqli_stmt_get_result($stmt);
            $fila = mysqli_fetch_assoc($resultado);

            mysqli_stmt_close($stmt);

            $borrarArchivo =
                ((int)$fila["cantidad"] === 0);
        }

        // =================================
        // 5. CONFIRMAR EN MYSQL
        // =================================

        mysqli_commit($conexion);
        $transaccionIniciada = false;

        // =================================
        // 6. BORRAR ADJUNTO SI CORRESPONDE
        // =================================

        if ($borrarArchivo) {

            $nombreSeguro = basename($archivoAnterior);

            if (
                $nombreSeguro === $archivoAnterior &&
                !in_array(
                    $nombreSeguro,
                    [".", ".."],
                    true
                )
            ) {

                $rutaArchivo =
                    __DIR__ . "/../archivos/" .
                    $nombreSeguro;

                if (
                    is_file($rutaArchivo) &&
                    !unlink($rutaArchivo)
                ) {
                    error_log(
                        "DUCCA: no se pudo borrar un adjunto."
                    );
                }
            }
        }

        // Renovar token.

        $_SESSION["csrf_eliminar_historial"] =
            bin2hex(random_bytes(32));

        mysqli_close($conexion);

        header(
            "Location: historial.php?dni_p=" .
            urlencode((string)$dni_p)
        );

        exit;

    } catch (Throwable $e) {

        if ($transaccionIniciada) {
            try {
                mysqli_rollback($conexion);
            } catch (Throwable $errorRollback) {
                error_log(
                    "DUCCA - Error de rollback: " .
                    $errorRollback->getMessage()
                );
            }
        }

        error_log(
            "DUCCA - Error eliminando historial: " .
            $e->getMessage()
        );

        mostrarError(
            "No se pudo eliminar el registro. " .
            "No se confirmó la operación.",
            500
        );
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

    <title>Eliminar registro médico - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .eliminar-pagina {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .eliminar-tarjeta {
            width: 100%;
            max-width: 580px;
            padding: 30px;
            box-sizing: border-box;
        }

        .eliminar-datos {
            padding: 18px;
            margin: 20px 0;
            border-radius: 9px;
            background: rgba(130, 140, 150, 0.12);
        }

        .eliminar-advertencia {
            padding: 18px;
            margin: 20px 0;
            border: 1px solid #d9b878;
            border-radius: 9px;
            background: rgba(220, 170, 80, 0.12);
        }

        .eliminar-acciones {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 22px;
        }

        .eliminar-acciones button {
            padding: 12px 18px;
            cursor: pointer;
        }

    </style>

</head>

<body>

<main class="eliminar-pagina">

    <section class="tarjeta eliminar-tarjeta">

        <h1>Eliminar registro médico</h1>

        <p>
            Estás por eliminar un registro
            del historial de este paciente.
        </p>

        <div class="eliminar-datos">

            <h2>
                <?php
                echo escapar(
                    $registro["nombre_p"] . " " .
                    $registro["apellido"]
                );
                ?>
            </h2>

            <p>
                <strong>Estudio:</strong>
                <?php
                echo escapar($registro["nombre_es"]);
                ?>
            </p>

            <p>
                <strong>Fecha:</strong>
                <?php
                echo escapar($registro["fecha"]);
                ?>
            </p>

            <p>
                <strong>Estado:</strong>
                <?php
                echo escapar($registro["estado"]);
                ?>
            </p>

        </div>

        <div class="eliminar-advertencia">

            <strong>¿Confirmás la eliminación?</strong>

            <p>
                Se eliminará el registro médico
                y, si corresponde, su archivo adjunto.
            </p>

            <p>
                Esta acción no se puede deshacer
                desde DUCCA.
            </p>

        </div>

        <form
            action="eliminar_historial.php"
            method="POST"
        >

            <input
                type="hidden"
                name="id_historial"
                value="<?php
                    echo (int)$id_historial;
                ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo escapar($csrfToken);
                ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="eliminar"
            >

            <div class="eliminar-acciones">

                <button type="submit">
                    Sí, eliminar registro
                </button>

                <a
                    href="historial.php?dni_p=<?php
                        echo urlencode((string)$dni_p);
                    ?>"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
