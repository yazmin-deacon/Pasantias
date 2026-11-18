
<?php

// =========================================
// DUCCA - ACTUALIZAR HISTORIAL MÉDICO
// =========================================

// VERIFICAR SESIÓN

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

header("Cache-Control: no-store, no-cache, must-revalidate");

// =========================================
// FUNCIONES
// =========================================

function mostrarError($mensaje, $codigo = 400) {

    http_response_code($codigo);

    echo "<!DOCTYPE html>";
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Error - DUCCA</title>';
    echo '<link rel="stylesheet" href="../general/estilos.css">';
    echo '</head>';
    echo '<body>';

    echo '<main class="gestion-contenido">';
    echo '<section class="tarjeta">';

    echo '<h1>No se pudieron guardar los cambios</h1>';

    echo '<p>' . htmlspecialchars(
        $mensaje,
        ENT_QUOTES,
        "UTF-8"
    ) . '</p>';

    echo '<p><a href="../Pacientes/listar.php">';
    echo 'Volver a pacientes';
    echo '</a></p>';

    echo '</section>';
    echo '</main>';

    echo '</body>';
    echo '</html>';

    exit;
}

// =========================================
// ACEPTAR SOLO POST
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    mostrarError(
        "Método no permitido.",
        405
    );
}

// =========================================
// RECIBIR Y VALIDAR DATOS
// =========================================

$idHistorialTexto = (string)(
    $_POST["id_historial"] ?? ""
);

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

// Validar identificadores.

if (
    !ctype_digit($idHistorialTexto) ||
    !ctype_digit($dniTexto) ||
    !ctype_digit($idEstudioTexto)
) {
    mostrarError(
        "Los identificadores enviados no son válidos."
    );
}

$id_historial = filter_var(
    $idHistorialTexto,
    FILTER_VALIDATE_INT
);

$dni_p = filter_var(
    $dniTexto,
    FILTER_VALIDATE_INT
);

$id_estudio = filter_var(
    $idEstudioTexto,
    FILTER_VALIDATE_INT
);

if (
    $id_historial === false ||
    $dni_p === false ||
    $id_estudio === false ||
    $id_historial <= 0 ||
    $dni_p <= 0 ||
    $id_estudio <= 0
) {
    mostrarError(
        "Faltan datos obligatorios o son inválidos."
    );
}

// Validar estado.

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

// Validar fecha.

$fechaObjeto = DateTime::createFromFormat(
    "!Y-m-d",
    $fecha
);

if (
    !$fechaObjeto ||
    $fechaObjeto->format("Y-m-d") !== $fecha
) {
    mostrarError("La fecha no es válida.");
}

// =========================================
// VALIDAR ARCHIVO NUEVO
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

        // Comprobar el tipo real del archivo.

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
// PREPARAR TRANSACCIÓN
// =========================================

$rutaArchivoNuevo = null;
$archivoAnterior = null;
$transaccionIniciada = false;

try {

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // =====================================
    // 1. BLOQUEAR Y COMPROBAR PACIENTE
    // =====================================

    // FOR UPDATE evita que el paciente
    // cambie de estado mientras se
    // procesa esta modificación.

    $sqlPaciente = "SELECT estado
                    FROM paciente
                    WHERE dni_p = ?
                    FOR UPDATE";

    $stmt = mysqli_prepare(
        $conexion,
        $sqlPaciente
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

    $paciente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$paciente) {
        throw new Exception(
            "No se encontró el paciente.",
            404
        );
    }

    // =====================================
    // BLOQUEAR PACIENTES ARCHIVADOS
    // =====================================

    if ($paciente["estado"] !== "activo") {

        throw new Exception(
            "Este paciente está archivado. " .
            "Su historial médico es de solo lectura. " .
            "Para modificarlo, primero debés reactivarlo.",
            403
        );
    }

    // =====================================
    // 2. BUSCAR REGISTRO ORIGINAL
    // =====================================

    $sqlRegistro = "SELECT
                        archivo
                    FROM historial_medico
                    WHERE id_historial = ?
                      AND dni_paciente = ?
                    FOR UPDATE";

    $stmt = mysqli_prepare(
        $conexion,
        $sqlRegistro
    );

    if (!$stmt) {
        throw new Exception(
            "No se pudo consultar el registro."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $id_historial,
        $dni_p
    );

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    $registro = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$registro) {
        throw new Exception(
            "No se encontró el registro médico.",
            404
        );
    }

    // =====================================
    // 3. COMPROBAR ESTUDIO INDICADO
    // =====================================

    $sqlEstudio = "SELECT id_estudio
                   FROM paciente_estudio
                   WHERE dni_paciente = ?
                     AND id_estudio = ?";

    $stmt = mysqli_prepare(
        $conexion,
        $sqlEstudio
    );

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
            "El estudio no está indicado " .
            "para este paciente."
        );
    }

    // =====================================
    // 4. CONSERVAR ARCHIVO ANTERIOR
    // =====================================

    $archivoAnterior = $registro["archivo"];

    $archivoGuardar = $archivoAnterior;

    // =====================================
    // 5. GUARDAR ARCHIVO NUEVO
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

        $nuevoNombre = bin2hex(
            random_bytes(16)
        ) . "." . $extension;

        $rutaArchivoNuevo =
            $carpeta . $nuevoNombre;

        if (
            !move_uploaded_file(
                $archivoTemporal,
                $rutaArchivoNuevo
            )
        ) {
            throw new Exception(
                "No se pudo guardar el archivo nuevo."
            );
        }

        $archivoGuardar = $nuevoNombre;
    }

    // =====================================
    // 6. ACTUALIZAR REGISTRO
    // =====================================

    // Comprobar que el paciente
    // siga activo al momento
    // de guardar los cambios.

    $sqlActualizar = "UPDATE historial_medico
                      SET
                          id_estudio = ?,
                          fecha = ?,
                          estado = ?,
                          observaciones = ?,
                          archivo = ?
                      WHERE id_historial = ?
                        AND dni_paciente = ?
                        AND EXISTS (
                            SELECT 1
                            FROM paciente
                            WHERE dni_p = ?
                              AND estado = 'activo'
                        )";

    $stmt = mysqli_prepare(
        $conexion,
        $sqlActualizar
    );

    if (!$stmt) {
        throw new Exception(
            "No se pudo preparar la actualización."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "issssiii",
        $id_estudio,
        $fecha,
        $estado,
        $observaciones,
        $archivoGuardar,
        $id_historial,
        $dni_p,
        $dni_p
    );

    if (!mysqli_stmt_execute($stmt)) {

        $detalle = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new Exception(
            "No se pudo actualizar el registro: " .
            $detalle
        );
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 7. CONFIRMAR TRANSACCIÓN
    // =====================================

    mysqli_commit($conexion);
    $transaccionIniciada = false;

    // =====================================
    // 8. BORRAR ARCHIVO ANTERIOR
    // =====================================

    // Solo después de haber confirmado
    // los cambios en la base de datos.

    if (
        $rutaArchivoNuevo !== null &&
        !empty($archivoAnterior)
    ) {

        $rutaAnterior =
            __DIR__ . "/../archivos/" .
            basename($archivoAnterior);

        if (is_file($rutaAnterior)) {

            if (!unlink($rutaAnterior)) {
                error_log(
                    "DUCCA: no se pudo eliminar un " .
                    "archivo anterior del historial."
                );
            }
        }
    }

    mysqli_close($conexion);

    // =====================================
    // 9. VOLVER AL HISTORIAL
    // =====================================

    header(
        "Location: historial.php?dni_p=" .
        urlencode((string)$dni_p)
    );

    exit;

} catch (Throwable $e) {

    // =====================================
    // ERROR: DESHACER CAMBIOS
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

    // Si se había subido un archivo nuevo
    // y la operación falló, eliminarlo.

    if (
        $rutaArchivoNuevo !== null &&
        is_file($rutaArchivoNuevo)
    ) {
        @unlink($rutaArchivoNuevo);
    }

    error_log(
        "DUCCA - Error al actualizar historial: " .
        $e->getMessage()
    );

    $codigo = (int)$e->getCode();

    if (
        !in_array($codigo, [400, 403, 404], true)
    ) {
        $codigo = 500;
    }

    if ($codigo === 403 || $codigo === 404) {
        mostrarError(
            $e->getMessage(),
            $codigo
        );
    }

    mostrarError(
        "No se pudieron guardar los cambios. " .
        "No se modificó el registro médico.",
        $codigo
    );
}

?>
