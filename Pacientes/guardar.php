
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

    if (mb_strlen($nombre) > 50) {
        throw new Exception("El nombre es demasiado largo.");
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
            mysqli_free_result($resultado);
            return (int)$fila[$campoId];
        }
    }

    mysqli_free_result($resultado);

    $sql = "INSERT INTO $tabla ($campoNombre) VALUES (?)";
    $stmt = mysqli_prepare($conexion, $sql);

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
// OBTENER O CREAR OBRA SOCIAL
// =========================================

function resolverObraSocial($conexion, $nombre) {

    $nombre = limpiarNombre($nombre);

    if ($nombre === "") {
        throw new Exception("Tenés que ingresar una obra social.");
    }

    if (
        normalizarNombre($nombre) ===
        normalizarNombre("Particular / Sin obra social")
    ) {
        return null;
    }

    if (mb_strlen($nombre) > 100) {
        throw new Exception(
            "El nombre de la obra social es demasiado largo."
        );
    }

    $sql = "SELECT id_obra_social, nombre_os, activa
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

            mysqli_free_result($resultado);

            if (!(int)$fila["activa"]) {
                throw new Exception(
                    "Esa obra social existe, pero está inactiva. " .
                    "Activala desde Administrar obras sociales."
                );
            }

            return (int)$fila["id_obra_social"];
        }
    }

    mysqli_free_result($resultado);

    $sql = "INSERT INTO obra_social (nombre_os, activa)
            VALUES (?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        throw new Exception(
            "No se pudo registrar la obra social: " . $error
        );
    }

    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// RECIBIR FORMULARIO
// =========================================

$dni = trim($_POST["dni_p"] ?? "");
$nombre = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");

$obraNombre = trim($_POST["obra_social_nombre"] ?? "");
$trabajoNombre = trim($_POST["trabajo_nombre"] ?? "");

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];

$nuevasExposiciones =
    $_POST["nueva_exposicion"] ?? [];

$nuevosEstudios =
    $_POST["nuevo_estudio"] ?? [];

if (
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
    $mail === "" ||
    $obraNombre === ""
) {
    die("Faltan datos obligatorios o el DNI no es válido.");
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    die("El correo electrónico no es válido.");
}

if (
    mb_strlen($nombre) > 30 ||
    mb_strlen($apellido) > 30 ||
    strlen($cuil) > 11 ||
    strlen($telefono) > 20 ||
    strlen($mail) > 100 ||
    mb_strlen($trabajoNombre) > 50
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
        die("Lista de estudios o exposiciones no válida.");
    }
}

// =========================================
// GUARDAR TODO
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // OBRA SOCIAL

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre
    );

    // TRABAJO

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

    // PACIENTE

    $sql = "INSERT INTO paciente (
                dni_p,
                nombre_p,
                apellido,
                cuil,
                telefono,
                mail,
                id_obra_social,
                id_trabajo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "isssssii",
        $dni,
        $nombre,
        $apellido,
        $cuil,
        $telefono,
        $mail,
        $idObraSocial,
        $idTrabajo
    );

    ejecutar($stmt);
    mysqli_stmt_close($stmt);

    // EXPOSICIONES NUEVAS

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

    // ESTUDIOS NUEVOS

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

    // ASOCIAR EXPOSICIONES

    $sql = "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach (array_unique($exposiciones) as $id) {

        if (!ctype_digit((string)$id) || (int)$id <= 0) {
            throw new Exception("Exposición no válida.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);
        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

    // ASOCIAR ESTUDIOS

    $sql = "INSERT INTO paciente_estudio
            (dni_paciente, id_estudio)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach (array_unique($estudios) as $id) {

        if (!ctype_digit((string)$id) || (int)$id <= 0) {
            throw new Exception("Estudio no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);
        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

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

    echo '<p><a href="alta.php">Volver a agregar paciente</a></p>';
}

mysqli_close($conexion);
?>
