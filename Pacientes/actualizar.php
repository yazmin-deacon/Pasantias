
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

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

function ejecutar($stmt) {
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            mysqli_stmt_error($stmt)
        );
    }
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
        throw new Exception(
            "El nombre del trabajo es demasiado largo."
        );
    }

    $sql = "SELECT id_trabajo, nombre_t
            FROM trabajo
            FOR UPDATE";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        throw new Exception(mysqli_error($conexion));
    }

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
        "INSERT INTO trabajo (nombre_t) VALUES (?)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

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
        throw new Exception(
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
        throw new Exception(
            "El nombre de la obra social es demasiado largo."
        );
    }

    $sql = "SELECT
                id_obra_social,
                nombre_os,
                activa
            FROM obra_social
            FOR UPDATE";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        throw new Exception(mysqli_error($conexion));
    }

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
                throw new Exception(
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

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

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
// COMPROBAR MÉTODO
// =========================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

// =========================================
// DATOS PERSONALES
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
// TELÉFONO INTERNACIONAL
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
    die("El país del teléfono no es válido.");
}

if (
    $codigoTelefono === "54" &&
    strlen($telefonoNacional) !== 10
) {
    die(
        "El celular argentino debe contener " .
        "10 dígitos nacionales."
    );
}

if (
    $codigoTelefono !== "54" &&
    (
        strlen($telefonoNacional) < 6 ||
        strlen($telefonoNacional) > 12 ||
        strlen($codigoTelefono . $telefonoNacional) > 15
    )
) {
    die("La longitud del teléfono no es válida.");
}

$telefono = "+" .
    $codigoTelefono .
    ($codigoTelefono === "54" ? "9" : "") .
    $telefonoNacional;

// =========================================
// DOMICILIO
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
// OTROS DATOS
// =========================================

$obraNombre = limpiarNombre(
    $_POST["obra_social_nombre"] ?? ""
);

$trabajoNombre = limpiarNombre(
    $_POST["nombre_trabajo"] ?? ""
);

// =========================================
// VALIDACIONES
// =========================================

if (
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    (int)$dni <= 0
) {
    die("El DNI no es válido.");
}

if (
    $cuil !== "" &&
    !preg_match('/^[0-9]{11}$/', $cuil)
) {
    die("El CUIL debe contener 11 números.");
}

if (
    $nombre === "" ||
    $apellido === "" ||
    $mail === "" ||
    $obraNombre === ""
) {
    die("Faltan datos obligatorios.");
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    die("El correo electrónico no es válido.");
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
    die("Alguno de los datos supera el tamaño permitido.");
}

// Se permiten direcciones antiguas vacías.
// Si se comienza a cargar un domicilio,
// deben completarse sus campos principales.

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
    die(
        "Si cargás un domicilio, completá calle, " .
        "altura, localidad, provincia, código postal y país."
    );
}

$dniNumero = (int)$dni;

// =========================================
// ACTUALIZAR PACIENTE
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // 1. VERIFICAR PACIENTE

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT id_obra_social
         FROM paciente
         WHERE dni_p = ?
         FOR UPDATE"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

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
        throw new Exception(
            "No se encontró el paciente."
        );
    }

    $idObraAnterior = (int)(
        $pacienteActual["id_obra_social"] ?? 0
    );

    // 2. OBRA SOCIAL

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre,
        $idObraAnterior
    );

    // 3. TRABAJO

    $idTrabajo = obtenerOCrearTrabajo(
        $conexion,
        $trabajoNombre
    );

    // 4. ACTUALIZAR DATOS Y DOMICILIO

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

            WHERE dni_p = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

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

    // No modificar los datos médicos:
    // paciente_estudio
    // paciente_exposicion
    // historial_medico

    // 5. CONFIRMAR CAMBIOS

    mysqli_commit($conexion);

    header("Location: listar.php");
    exit;

} catch (Throwable $e) {

    mysqli_rollback($conexion);

    http_response_code(400);

    echo "<h2>No se pudo actualizar el paciente</h2>";

    echo "<p>" . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    ) . "</p>";

    echo '<p><a href="listar.php">
        Volver al listado de pacientes
    </a></p>';
}

mysqli_close($conexion);
?>
