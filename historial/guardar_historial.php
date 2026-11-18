
<?php

// =========================================
// DUCCA - GUARDAR REGISTRO MÉDICO
// =========================================

// 1. VERIFICAR INICIO DE SESIÓN

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
        <link rel="stylesheet"
              href="../general/estilos.css">
    </head>

    <body>

        <main class="gestion-contenido">

            <section class="tarjeta">

                <h1>No se pudo guardar el estudio</h1>

                <p>' . escapar($mensaje) . '</p>

                <p>
                    <a href="../Pacientes/listar.php">
                        Volver a pacientes
                    </a>
                </p>

            </section>

        </main>

    </body>
    </html>';

    exit;
}

// =========================================
// 2. ACEPTAR SOLAMENTE POST
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    mostrarError(
        "Método no permitido.",
        405
    );
}

// =========================================
// 3. COMPROBAR TOKEN CSRF
// =========================================

// Este token se crea en agregar_historial.php
// y se envía en el formulario.

$tokenGuardado = (string)(
    $_SESSION["csrf_guardar_historial"] ?? ""
);

$tokenEnviado = (string)(
    $_POST["csrf_token"] ?? ""
);

if (
    $tokenGuardado === "" ||
    $tokenEnviado === "" ||
    !hash_equals($tokenGuardado, $tokenEnviado)
) {

    mostrarError(
        "La solicitud no es válida. " .
        "Volvé al formulario e intentá nuevamente.",
        403
    );
}

// =========================================
// 4. RECIBIR DATOS DEL FORMULARIO
// =========================================

$dniTexto = (string)(
    $_POST["dni_p"] ?? ""
);

$idEstudioTexto = (string)(
    $_POST["id_estudio"] ?? ""
);

$fecha = (string)(
    $_POST["fecha"] ?? ""
);

$estado = (string)(
    $_POST["estado"] ?? ""
);

$observaciones = trim(
    (string)($_POST["observaciones"] ?? "")
);

// =========================================
// 5. VALIDAR IDENTIFICADORES
// =========================================

if (
    !ctype_digit($dniTexto) ||
    !ctype_digit($idEstudioTexto)
) {

    mostrarError(
        "El paciente o el estudio indicado no es válido."
    );
}

$dni_p = filter_var(
    $dniTexto,
    FILTER_VALIDATE_INT
);

$id_estudio = filter_var(
    $idEstudioTexto,
    FILTER_VALIDATE_INT
);

if (
    $dni_p === false ||
    $id_estudio === false ||
    $dni_p <= 0 ||
    $id_estudio <= 0
) {

    mostrarError(
        "Faltan datos obligatorios o son inválidos."
    );
}

// =========================================
// 6. VALIDAR ESTADO
// =========================================

if (
    !in_array(
        $estado,
        ["Pendiente", "Realizado"],
        true
    )
) {

    mostrarError(
        "El estado del estudio no es válido."
    );
}

// =========================================
// 7. VALIDAR FECHA
// =========================================

$fechaObjeto = DateTime::createFromFormat(
    "!Y-m-d",
    $fecha
);

if (
    !$fechaObjeto ||
    $fechaObjeto->format("Y-m-d") !== $fecha
) {

    mostrarError(
        "La fecha ingresada no es válida."
    );
}

// =========================================
// 8. VALIDAR ARCHIVO ADJUNTO
// =========================================

$hayArchivoNuevo = false;
$archivoTemporal = null;
$extension = null;

$tiposPermitidos = [
    "application/pdf" => "pdf",
    "image/jpeg" => "jpg",
    "image/png" => "png"
];

if (isset($_FILES["archivo"])) {

    $errorSubida = (int)(
        $_FILES["archivo"]["error"]
        ?? UPLOAD_ERR_NO_FILE
    );

    if ($errorSubida !== UPLOAD_ERR_NO_FILE) {

        if ($errorSubida !== UPLOAD_ERR_OK) {

            mostrarError(
                "Ocurrió un error al subir el archivo."
            );
        }

        if (
            !isset(
                $_FILES["archivo"]["tmp_name"],
                $_FILES["archivo"]["size"]
            ) ||
            !is_string(
                $_FILES["archivo"]["tmp_name"]
            )
        ) {

            mostrarError(
                "El archivo enviado no es válido."
            );
        }

        $archivoTemporal =
            $_FILES["archivo"]["tmp_name"];

        $tamanoArchivo = (int)(
            $_FILES["archivo"]["size"]
        );

        if (
            $tamanoArchivo <= 0 ||
            $tamanoArchivo > 5 * 1024 * 1024
        ) {

            mostrarError(
                "El archivo debe tener un tamaño " .
                "máximo de 5 MB."
            );
        }

        if (!is_uploaded_file($archivoTemporal)) {

            mostrarError(
                "No se pudo verificar el archivo enviado."
            );
        }

        // Verificar el tipo real del archivo.

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $tipoArchivo = $finfo->file(
            $archivoTemporal
        );

        if (!isset($tiposPermitidos[$tipoArchivo])) {

            mostrarError(
                "Solo se permiten archivos PDF, JPG y PNG."
            );
        }

        $extension = $tiposPermitidos[$tipoArchivo];

        $hayArchivoNuevo = true;
    }
}

// =========================================
// 9. PREPARAR REGISTRO
// =========================================

$archivo = null;
$rutaArchivo = null;
$transaccionIniciada = false;

try {

    // =====================================
    // 10. INICIAR TRANSACCIÓN
    // =====================================

    mysqli_begin_transaction($conexion);

    $transaccionIniciada = true;

    // =====================================
    // 11. COMPROBAR ESTADO DEL PACIENTE
    // =====================================

    // Se bloquea la fila para evitar que
    // el paciente sea archivado mientras
    // se guarda su estudio.

    $sql = "SELECT
                dni_p,
                estado

            FROM paciente

            WHERE dni_p = ?

            FOR UPDATE";

    $stmt = mysqli_prepare($conexion, $sql);

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

    $paciente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$paciente) {

        throw new Exception(
            "No se encontró el paciente.",
            404
        );
    }

    // =====================================
    // 12. BLOQUEAR PACIENTE ARCHIVADO
    // =====================================

    if ($paciente["estado"] !== "activo") {

        throw new Exception(
            "Este paciente está archivado. " .
            "Su historial médico es de solo lectura. " .
            "Para agregar estudios, primero debés reactivarlo.",
            403
        );
    }

    // =====================================
    // 13. VERIFICAR ESTUDIO INDICADO
    // =====================================

    $sql = "SELECT id_estudio

            FROM paciente_estudio

            WHERE dni_paciente = ?
              AND id_estudio = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "No se pudo consultar el estudio."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $dni_p,
        $id_estudio
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    $estudio = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$estudio) {

        throw new Exception(
            "Este estudio no está indicado " .
            "para el paciente."
        );
    }

    // =====================================
    // 14. GUARDAR ARCHIVO ADJUNTO
    // =====================================

    if ($hayArchivoNuevo) {

        $carpeta = __DIR__ . "/../archivos/";

        if (!is_dir($carpeta)) {

            if (
                !mkdir(
                    $carpeta,
                    0755,
                    true
                ) &&
                !is_dir($carpeta)
            ) {

                throw new Exception(
                    "No se pudo crear la carpeta de archivos."
                );
            }
        }

        $nombreArchivo = bin2hex(
            random_bytes(16)
        ) . "." . $extension;

        $rutaArchivo =
            $carpeta . $nombreArchivo;

        if (
            !move_uploaded_file(
                $archivoTemporal,
                $rutaArchivo
            )
        ) {

            throw new Exception(
                "No se pudo guardar el archivo adjunto."
            );
        }

        $archivo = $nombreArchivo;
    }

    // =====================================
    // 15. INSERTAR REGISTRO MÉDICO
    // =====================================

    $sql = "INSERT INTO historial_medico
            (
                dni_paciente,
                id_estudio,
                fecha,
                estado,
                observaciones,
                archivo
            )

            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare(
        $conexion,
        $sql
    );

    if (!$stmt) {

        throw new Exception(
            "No se pudo preparar el registro."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iissss",
        $dni_p,
        $id_estudio,
        $fecha,
        $estado,
        $observaciones,
        $archivo
    );

    if (!mysqli_stmt_execute($stmt)) {

        $error = mysqli_stmt_error($stmt);

        mysqli_stmt_close($stmt);

        throw new Exception(
            "No se pudo guardar el estudio: " .
            $error
        );
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 16. CONFIRMAR TRANSACCIÓN
    // =====================================

    mysqli_commit($conexion);

    $transaccionIniciada = false;

    // Renovar el token para evitar
    // reutilizar el mismo formulario.

    $_SESSION["csrf_guardar_historial"] =
        bin2hex(random_bytes(32));

    mysqli_close($conexion);

    // =====================================
    // 17. VOLVER AL HISTORIAL
    // =====================================

    header(
        "Location: historial.php?dni_p=" .
        urlencode((string)$dni_p)
    );

    exit;

} catch (Throwable $e) {

    // =====================================
    // 18. DESHACER CAMBIOS SI HAY ERROR
    // =====================================

    if ($transaccionIniciada) {

        try {
            mysqli_rollback($conexion);
        } catch (Throwable $errorRollback) {

            error_log(
                "DUCCA - Error al deshacer cambios: " .
                $errorRollback->getMessage()
            );
        }
    }

    // =====================================
    // 19. LIMPIAR ARCHIVO NUEVO
    // =====================================

    // Si falló el INSERT o cualquier paso
    // anterior, eliminar el archivo nuevo
    // que se hubiera guardado.

    if (
        $rutaArchivo !== null &&
        is_file($rutaArchivo)
    ) {

        if (!unlink($rutaArchivo)) {

            error_log(
                "DUCCA - No se pudo eliminar un " .
                "archivo temporal del historial."
            );
        }
    }

    // Guardar el error técnico en el registro
    // del servidor, no mostrarlo al paciente.

    error_log(
        "DUCCA - Error al guardar historial: " .
        $e->getMessage()
    );

    $codigo = (int)$e->getCode();

    if (!in_array($codigo, [403, 404], true)) {
        $codigo = 500;
    }

    if ($codigo === 403 || $codigo === 404) {

        mostrarError(
            $e->getMessage(),
            $codigo
        );
    }

    mostrarError(
        "No se pudo guardar el estudio. " .
        "No se confirmó el registro.",
        $codigo
    );
}

?>
