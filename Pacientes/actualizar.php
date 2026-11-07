
<?php
require_once "conexion.php";

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
        throw new Exception(mysqli_stmt_error($stmt));
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
// OBRA SOCIAL
// =========================================

function resolverObraSocial(
    $conexion,
    $nombre,
    $idObraAnterior
) {

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

            $id = (int)$fila["id_obra_social"];

            if (
                !(int)$fila["activa"] &&
                $id !== $idObraAnterior
            ) {
                throw new Exception(
                    "Esa obra social existe, pero está inactiva. " .
                    "Activala desde Administrar obras sociales."
                );
            }

            return $id;
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
    ejecutar($stmt);

    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// RECIBIR DATOS
// =========================================

$dni = trim($_POST["dni_p"] ?? "");
$nombre = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");

$obraNombre = trim($_POST["obra_social_nombre"] ?? "");
$trabajoNombre = trim($_POST["nombre_trabajo"] ?? "");

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
// ACTUALIZAR
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // BLOQUEAR PACIENTE PARA LA EDICIÓN

    $sql = "SELECT id_obra_social
            FROM paciente
            WHERE dni_p = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);
    ejecutar($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $pacienteActual = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$pacienteActual) {
        throw new Exception("No se encontró el paciente.");
    }

    $idObraAnterior =
        (int)($pacienteActual["id_obra_social"] ?? 0);

    // OBRA SOCIAL EXISTENTE O NUEVA

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre,
        $idObraAnterior
    );

    // TRABAJO EXISTENTE O NUEVO

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

    // DATOS PERSONALES

    $sql = "UPDATE paciente
            SET nombre_p = ?,
                apellido = ?,
                cuil = ?,
                telefono = ?,
                mail = ?,
                id_obra_social = ?,
                id_trabajo = ?
            WHERE dni_p = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssiii",
        $nombre,
        $apellido,
        $cuil,
        $telefono,
        $mail,
        $idObraSocial,
        $idTrabajo,
        $dni
    );

    ejecutar($stmt);
    mysqli_stmt_close($stmt);

    // CREAR EXPOSICIONES NUEVAS

    foreach ($nuevasExposiciones as $valor) {

        if (!is_string($valor)) {
            throw new Exception("Exposición no válida.");
        }

        if (trim($valor) !== "") {

            $exposiciones[] = obtenerOCrear(
                $conexion,
                "exposicion",
                "id_exposicion",
                "nombre_ex",
                $valor
            );
        }
    }

    // CREAR ESTUDIOS NUEVOS

    foreach ($nuevosEstudios as $valor) {

        if (!is_string($valor)) {
            throw new Exception("Estudio no válido.");
        }

        if (trim($valor) !== "") {

            $estudios[] = obtenerOCrear(
                $conexion,
                "estudio",
                "id_estudio",
                "nombre_es",
                $valor
            );
        }
    }

    $exposiciones = array_unique($exposiciones);
    $estudios = array_unique($estudios);

    // =====================================
    // ACTUALIZAR EXPOSICIONES
    // =====================================

    $sql = "DELETE FROM paciente_exposicion
            WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);
    ejecutar($stmt);
    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach ($exposiciones as $id) {

        if (!ctype_digit((string)$id) || (int)$id <= 0) {
            throw new Exception("ID de exposición no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);
        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // ESTUDIOS: CONSERVAR EL HISTORIAL
    // =====================================

    // Quitar solamente las asociaciones sin historial.
    // Las que tienen historial se conservan.

    $sql = "DELETE FROM paciente_estudio
            WHERE dni_paciente = ?
              AND id_estudio NOT IN (
                  SELECT id_estudio
                  FROM historial_medico
                  WHERE dni_paciente = ?
              )";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmt, "ii", $dni, $dni);
    ejecutar($stmt);
    mysqli_stmt_close($stmt);

    // Volver a asociar todos los estudios seleccionados

    $sql = "INSERT INTO paciente_estudio
            (dni_paciente, id_estudio)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                id_estudio = VALUES(id_estudio)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conexion));
    }

    foreach ($estudios as $id) {

        if (!ctype_digit((string)$id) || (int)$id <= 0) {
            throw new Exception("ID de estudio no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);
        ejecutar($stmt);
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // CONFIRMAR CAMBIOS
    // =====================================

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

    echo '<p><a href="listar.php">Volver al listado</a></p>';
}

mysqli_close($conexion);
?>
