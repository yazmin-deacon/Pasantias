
<?php

// =========================================
// DUCCA - ACTUALIZAR PACIENTE
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// =========================================
// FUNCIONES
// =========================================

function limpiarNombre($texto) {
    return trim(
        preg_replace('/\s+/u', ' ', (string)$texto)
    );
}

function normalizarNombre($texto) {
    return mb_strtolower(
        limpiarNombre($texto),
        "UTF-8"
    );
}

function escapar($texto) {
    return htmlspecialchars(
        (string)($texto ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function ejecutar($stmt) {
    mysqli_stmt_execute($stmt);
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
                <h2>No se pudo actualizar el paciente</h2>
                <p>' . escapar($mensaje) . '</p>
                <a href="listar.php">
                    Volver al listado de pacientes
                </a>
            </section>
        </main>
    </body>
    </html>';

    exit;
}

// =========================================
// OBTENER O CREAR TRABAJO
// =========================================

function obtenerOCrearTrabajo($conexion, $nombre) {

    $nombre = limpiarNombre($nombre);

    if ($nombre === "") {
        return null;
    }

    if (mb_strlen($nombre, "UTF-8") > 50) {
        throw new InvalidArgumentException(
            "El nombre del trabajo es demasiado largo."
        );
    }

    $resultado = mysqli_query(
        $conexion,
        "SELECT id_trabajo, nombre_t
         FROM trabajo
         FOR UPDATE"
    );

    while ($fila = mysqli_fetch_assoc($resultado)) {

        if (
            normalizarNombre($fila["nombre_t"]) ===
            normalizarNombre($nombre)
        ) {
            $id = (int)$fila["id_trabajo"];

            mysqli_free_result($resultado);
            return $id;
        }
    }

    mysqli_free_result($resultado);

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO trabajo (nombre_t)
         VALUES (?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nombre
    );

    ejecutar($stmt);

    $id = mysqli_insert_id($conexion);

    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// OBTENER O CREAR OBRA SOCIAL
// =========================================

function resolverObraSocial(
    $conexion,
    $nombre,
    $idObraAnterior
) {

    $nombre = limpiarNombre($nombre);

    if ($nombre === "") {
        throw new InvalidArgumentException(
            "Tenés que ingresar una obra social."
        );
    }

    if (
        normalizarNombre($nombre) ===
        normalizarNombre("Particular / Sin obra social")
    ) {
        return null;
    }

    if (mb_strlen($nombre, "UTF-8") > 100) {
        throw new InvalidArgumentException(
            "El nombre de la obra social es demasiado largo."
        );
    }

    $resultado = mysqli_query(
        $conexion,
        "SELECT
            id_obra_social,
            nombre_os,
            activa
         FROM obra_social
         FOR UPDATE"
    );

    while ($fila = mysqli_fetch_assoc($resultado)) {

        if (
            normalizarNombre($fila["nombre_os"]) ===
            normalizarNombre($nombre)
        ) {

            $id = (int)$fila["id_obra_social"];
            $activa = (int)$fila["activa"];

            mysqli_free_result($resultado);

            if (
                !$activa &&
                $id !== $idObraAnterior
            ) {
                throw new InvalidArgumentException(
                    "Esa obra social está inactiva."
                );
            }

            return $id;
        }
    }

    mysqli_free_result($resultado);

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO obra_social
            (nombre_os, activa)
         VALUES (?, 1)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nombre
    );

    ejecutar($stmt);

    $id = mysqli_insert_id($conexion);

    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// 1. COMPROBAR MÉTODO POST
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Allow: POST");

    mostrarError(
        "Método no permitido.",
        405
    );
}

// =========================================
// 2. VALIDAR TOKEN CSRF
// =========================================

// Este token se genera en editar.php.
// Debe coincidir con el token del formulario.

$tokenGuardado = (string)(
    $_SESSION["csrf_editar_paciente"] ?? ""
);

$tokenEnviado = (string)(
    $_POST["csrf_token"] ?? ""
);



if (
    $tokenGuardado === "" ||
    $tokenEnviado === "" ||
    !hash_equals(
        $tokenGuardado,
        $tokenEnviado
    )
) {

    mostrarError(
        "La solicitud no es válida. " .
        "Volvé a editar el paciente e intentá nuevamente.",
        403
    );
}

// =========================================
// 3. RECIBIR DATOS PERSONALES
// =========================================

$dni = preg_replace(
    '/\D/',
    '',
    (string)($_POST["dni_p"] ?? "")
);

$cuil = preg_replace(
    '/\D/',
    '',
    (string)($_POST["cuil"] ?? "")
);

$nombre = limpiarNombre(
    $_POST["nombre_p"] ?? ""
);

$apellido = limpiarNombre(
    $_POST["apellido"] ?? ""
);

$mail = trim(
    (string)($_POST["mail"] ?? "")
);

// =========================================
// 4. TELÉFONO INTERNACIONAL
// =========================================

$codigosPermitidos = [
    "54", "56", "598", "591", "55",
    "595", "57", "51", "58", "593"
];

$codigoTelefono = (string)(
    $_POST["telefono_codigo"] ?? "54"
);

$telefonoNacional = preg_replace(
    '/\D/',
    '',
    (string)($_POST["telefono"] ?? "")
);

if (
    !in_array(
        $codigoTelefono,
        $codigosPermitidos,
        true
    )
) {
    mostrarError(
        "El país del teléfono no es válido."
    );
}

if (
    $codigoTelefono === "54" &&
    strlen($telefonoNacional) !== 10
) {
    mostrarError(
        "El celular argentino debe contener " .
        "10 dígitos nacionales."
    );
}

if (
    $codigoTelefono !== "54" &&
    (
        strlen($telefonoNacional) < 6 ||
        strlen($telefonoNacional) > 12 ||
        strlen(
            $codigoTelefono . $telefonoNacional
        ) > 15
    )
) {
    mostrarError(
        "La longitud del teléfono no es válida."
    );
}

$telefono = "+" .
    $codigoTelefono .
    ($codigoTelefono === "54" ? "9" : "") .
    $telefonoNacional;

// =========================================
// 5. DOMICILIO
// =========================================

$domicilioCalle = limpiarNombre(
    $_POST["domicilio_calle"] ?? ""
);

$domicilioAltura = limpiarNombre(
    $_POST["domicilio_altura"] ?? ""
);

$domicilioPisoDepto = limpiarNombre(
    $_POST["domicilio_piso_depto"] ?? ""
);

$domicilioLocalidad = limpiarNombre(
    $_POST["domicilio_localidad"] ?? ""
);

$domicilioProvincia = limpiarNombre(
    $_POST["domicilio_provincia"] ?? ""
);

$domicilioCodigoPostal = limpiarNombre(
    $_POST["domicilio_codigo_postal"] ?? ""
);

$domicilioPais = limpiarNombre(
    $_POST["domicilio_pais"] ?? ""
);

// =========================================
// 6. OBRA SOCIAL Y TRABAJO
// =========================================

$obraNombre = limpiarNombre(
    $_POST["obra_social_nombre"] ?? ""
);

$trabajoNombre = limpiarNombre(
    $_POST["nombre_trabajo"] ?? ""
);

// =========================================
// 7. VALIDACIONES GENERALES
// =========================================

if (
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    (int)$dni <= 0
) {
    mostrarError(
        "El DNI no es válido."
    );
}

if (
    $cuil !== "" &&
    !preg_match('/^[0-9]{11}$/', $cuil)
) {
    mostrarError(
        "El CUIL debe contener 11 números."
    );
}

if (
    $nombre === "" ||
    $apellido === "" ||
    $mail === "" ||
    $obraNombre === ""
) {
    mostrarError(
        "Faltan datos obligatorios."
    );
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    mostrarError(
        "El correo electrónico no es válido."
    );
}

if (
    mb_strlen($nombre, "UTF-8") > 30 ||
    mb_strlen($apellido, "UTF-8") > 30 ||
    strlen($telefono) > 20 ||
    strlen($mail) > 100 ||
    mb_strlen($trabajoNombre, "UTF-8") > 50 ||
    mb_strlen($domicilioCalle, "UTF-8") > 100 ||
    mb_strlen($domicilioAltura, "UTF-8") > 15 ||
    mb_strlen($domicilioPisoDepto, "UTF-8") > 30 ||
    mb_strlen($domicilioLocalidad, "UTF-8") > 100 ||
    mb_strlen($domicilioProvincia, "UTF-8") > 100 ||
    mb_strlen($domicilioCodigoPostal, "UTF-8") > 15 ||
    mb_strlen($domicilioPais, "UTF-8") > 100
) {
    mostrarError(
        "Alguno de los datos supera el tamaño permitido."
    );
}

// =========================================
// 8. VALIDAR DOMICILIO
// =========================================

// Los domicilios antiguos pueden estar vacíos.
// Si se comienza a completar uno,
// los campos principales serán obligatorios.

$domicilioIniciado =
    $domicilioCalle !== "" ||
    $domicilioAltura !== "" ||
    $domicilioLocalidad !== "" ||
    $domicilioProvincia !== "" ||
    $domicilioCodigoPostal !== "" ||
    $domicilioPais !== "" ||
    $domicilioPisoDepto !== "";

if (
    $domicilioIniciado &&
    (
        $domicilioCalle === "" ||
        $domicilioAltura === "" ||
        $domicilioLocalidad === "" ||
        $domicilioProvincia === "" ||
        $domicilioCodigoPostal === "" ||
        $domicilioPais === ""
    )
) {
    mostrarError(
        "Si cargás un domicilio, completá calle, " .
        "altura, localidad, provincia, código postal y país."
    );
}

$dniNumero = (int)$dni;

// =========================================
// 9. ACTUALIZAR PACIENTE
// =========================================

$transaccionIniciada = false;

try {

    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // =====================================
    // 10. BLOQUEAR FILA DEL PACIENTE
    // =====================================

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT
            id_obra_social,
            estado
         FROM paciente
         WHERE dni_p = ?
         FOR UPDATE"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $dniNumero
    );

    ejecutar($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $pacienteActual = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$pacienteActual) {
        throw new RuntimeException(
            "No se encontró el paciente.",
            404
        );
    }

    // =====================================
    // 11. PROTEGER PACIENTES ARCHIVADOS
    // =====================================

    if ($pacienteActual["estado"] !== "activo") {
        throw new RuntimeException(
            "Este paciente está archivado. " .
            "No se pueden modificar sus datos. " .
            "Primero debés reactivarlo.",
            403
        );
    }

    $idObraAnterior = (int)(
        $pacienteActual["id_obra_social"] ?? 0
    );

    // =====================================
    // 12. RESOLVER OBRA SOCIAL
    // =====================================

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre,
        $idObraAnterior
    );

    // =====================================
    // 13. RESOLVER TRABAJO
    // =====================================

    $idTrabajo = obtenerOCrearTrabajo(
        $conexion,
        $trabajoNombre
    );

    // =====================================
    // 14. ACTUALIZAR DATOS
    // =====================================

    $sql = "UPDATE paciente

            SET
                nombre_p = ?,
                apellido = ?,
                cuil = ?,
                telefono = ?,
                mail = ?,
                id_obra_social = ?,
                id_trabajo = ?,
                domicilio_calle = ?,
                domicilio_altura = ?,
                domicilio_piso_depto = ?,
                domicilio_localidad = ?,
                domicilio_provincia = ?,
                domicilio_codigo_postal = ?,
                domicilio_pais = ?

            WHERE dni_p = ?
              AND estado = 'activo'";

    $stmt = mysqli_prepare(
        $conexion,
        $sql
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sssssiisssssssi",
        $nombre,
        $apellido,
        $cuil,
        $telefono,
        $mail,
        $idObraSocial,
        $idTrabajo,
        $domicilioCalle,
        $domicilioAltura,
        $domicilioPisoDepto,
        $domicilioLocalidad,
        $domicilioProvincia,
        $domicilioCodigoPostal,
        $domicilioPais,
        $dniNumero
    );

    ejecutar($stmt);

    mysqli_stmt_close($stmt);

    // =====================================
    // 15. CONSERVAR DATOS MÉDICOS
    // =====================================

    // No se modifican:
    //
    // paciente_estudio
    // paciente_exposicion
    // historial_medico
    // turno

    // =====================================
    // 16. CONFIRMAR CAMBIOS
    // =====================================

    mysqli_commit($conexion);
    $transaccionIniciada = false;

    // Renovar el token para una próxima edición.
    $_SESSION["csrf_editar_paciente"] =
        bin2hex(random_bytes(32));

    mysqli_close($conexion);

    header("Location: listar.php");
    exit;

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        try {
            mysqli_rollback($conexion);
        } catch (Throwable $errorRollback) {
            error_log(
                "DUCCA - Error rollback paciente: " .
                $errorRollback->getMessage()
            );
        }
    }

    $codigo = (int)$e->getCode();

    if ($codigo === 403 || $codigo === 404) {
        mostrarError(
            $e->getMessage(),
            $codigo
        );
    }

    if ($e instanceof InvalidArgumentException) {
        mostrarError(
            $e->getMessage(),
            400
        );
    }

    error_log(
        "DUCCA - Error al actualizar paciente: " .
        $e->getMessage()
    );

    mostrarError(
        "No se pudieron guardar los cambios. " .
        "No se confirmó la actualización.",
        500
    );
}

?>
