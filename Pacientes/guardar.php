
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

function obtenerOCrear(
    $conexion,
    $tabla,
    $campoId,
    $campoNombre,
    $nombre
) {
    $permitidos = [
        "trabajo" => ["id_trabajo", "nombre_t"],
        "exposicion" => ["id_exposicion", "nombre_ex"],
        "estudio" => ["id_estudio", "nombre_es"]
    ];

    if (
        !isset($permitidos[$tabla]) ||
        $permitidos[$tabla] !== [$campoId, $campoNombre]
    ) {
        throw new Exception("Catálogo no permitido.");
    }

    $nombre = limpiarNombre($nombre);

    if ($nombre === "") {
        return null;
    }

    if (mb_strlen($nombre, "UTF-8") > 50) {
        throw new Exception(
            "El nombre es demasiado largo."
        );
    }

    $sql = "SELECT $campoId, $campoNombre FROM $tabla";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        throw new Exception(mysqli_error($conexion));
    }

    while ($fila = mysqli_fetch_assoc($resultado)) {
        if (
            normalizarNombre($fila[$campoNombre]) ===
            normalizarNombre($nombre)
        ) {
            $id = (int)$fila[$campoId];
            mysqli_free_result($resultado);
            return $id;
        }
    }

    mysqli_free_result($resultado);

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO $tabla ($campoNombre) VALUES (?)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);
    ejecutar($stmt);

    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// OBRA SOCIAL
// =========================================

function resolverObraSocial($conexion, $nombre) {
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
            ORDER BY id_obra_social
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

            if (!$activa) {
                throw new Exception(
                    "Esa obra social está inactiva. " .
                    "Activala desde Administrar obras sociales."
                );
            }

            return $id;
        }
    }

    mysqli_free_result($resultado);

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO obra_social
            (nombre_os, activa) VALUES (?, 1)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);
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
// OBRA SOCIAL, TRABAJO Y ESTUDIOS
// =========================================

$obraNombre = limpiarNombre(
    $_POST["obra_social_nombre"] ?? ""
);

$trabajoNombre = limpiarNombre(
    $_POST["trabajo_nombre"] ?? ""
);

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];

$nuevasExposiciones =
    $_POST["nueva_exposicion"] ?? [];

$nuevosEstudios =
    $_POST["nuevo_estudio"] ?? [];

// =========================================
// VALIDACIONES
// =========================================

if (
    !preg_match('/^[0-9]{1,8}$/', $dni) ||
    (int)$dni <= 0 ||
    $nombre === "" ||
    $apellido === "" ||
    $mail === "" ||
    $obraNombre === ""
) {
    die(
        "Faltan datos obligatorios " .
        "o el DNI no es válido."
    );
}

if (
    $cuil !== "" &&
    !preg_match('/^[0-9]{11}$/', $cuil)
) {
    die("El CUIL debe contener 11 números.");
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    die("El correo electrónico no es válido.");
}

// Verificar los campos obligatorios
// del domicilio.

if (
    $domicilioCalle === "" ||
    $domicilioAltura === "" ||
    $domicilioLocalidad === "" ||
    $domicilioProvincia === "" ||
    $domicilioCodigoPostal === "" ||
    $domicilioPais === ""
) {
    die(
        "Completá los datos obligatorios " .
        "del domicilio del paciente."
    );
}

// Tamaños según las columnas de MySQL.

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

foreach (
    [
        $exposiciones,
        $estudios,
        $nuevasExposiciones,
        $nuevosEstudios
    ] as $lista
) {
    if (!is_array($lista)) {
        die(
            "Lista de estudios o exposiciones no válida."
        );
    }
}

// =========================================
// GUARDAR TODO
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // 1. OBRA SOCIAL

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre
    );

    // 2. TRABAJO

    $idTrabajo = null;

    if ($trabajoNombre !== "") {
        $idTrabajo = obtenerOCrear(
            $conexion,
            "trabajo",
            "id_trabajo",
            "nombre_t",
            $trabajoNombre
        );
    }

    // 3. PACIENTE Y DOMICILIO

    $dniNumero = (int)$dni;

    $sql = "INSERT INTO paciente (
                dni_p,
                nombre_p,
                apellido,
                cuil,
                telefono,
                mail,
                id_obra_social,
                id_trabajo,
                domicilio_calle,
                domicilio_altura,
                domicilio_piso_depto,
                domicilio_localidad,
                domicilio_provincia,
                domicilio_codigo_postal,
                domicilio_pais
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?
            )";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "isssssiisssssss",
        $dniNumero,
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
        $domicilioPais
    );

    ejecutar($stmt);
    mysqli_stmt_close($stmt);

    // 4. EXPOSICIONES NUEVAS

    foreach ($nuevasExposiciones as $valor) {
        if (!is_string($valor)) {
            throw new Exception("Exposición no válida.");
        }

        if (trim($valor) !== "") {
            $idNuevo = obtenerOCrear(
                $conexion,
                "exposicion",
                "id_exposicion",
                "nombre_ex",
                $valor
            );

            $exposiciones[] = $idNuevo;
        }
    }

    // 5. ESTUDIOS NUEVOS

    foreach ($nuevosEstudios as $valor) {
        if (!is_string($valor)) {
            throw new Exception("Estudio no válido.");
        }

        if (trim($valor) !== "") {
            $idNuevo = obtenerOCrear(
                $conexion,
                "estudio",
                "id_estudio",
                "nombre_es",
                $valor
            );

            $estudios[] = $idNuevo;
        }
    }

    // 6. ASOCIAR EXPOSICIONES

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
         VALUES (?, ?)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach (array_unique($exposiciones) as $id) {
        if (
            !ctype_digit((string)$id) ||
            (int)$id <= 0
        ) {
            throw new Exception("Exposición no válida.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $dniNumero,
            $id
        );

        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

    // 7. ASOCIAR ESTUDIOS

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO paciente_estudio
            (dni_paciente, id_estudio)
         VALUES (?, ?)"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach (array_unique($estudios) as $id) {
        if (
            !ctype_digit((string)$id) ||
            (int)$id <= 0
        ) {
            throw new Exception("Estudio no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $dniNumero,
            $id
        );

        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

    // 8. CONFIRMAR

    mysqli_commit($conexion);

    header("Location: listar.php");
    exit;

} catch (Throwable $e) {

    mysqli_rollback($conexion);

    http_response_code(400);

    echo "<h2>No se pudo guardar el paciente</h2>";

    echo "<p>" . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    ) . "</p>";

    echo '<p><a href="alta.php">
        Volver a agregar paciente
    </a></p>';
}

mysqli_close($conexion);
?>
