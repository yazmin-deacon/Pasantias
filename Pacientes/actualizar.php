
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

    $sql = "INSERT INTO trabajo (nombre_t)
            VALUES (?)";

    $stmt = mysqli_prepare($conexion, $sql);

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

            mysqli_free_result($resultado);

            if (
                !(int)$fila["activa"] &&
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

    $sql = "INSERT INTO obra_social
                (nombre_os, activa)
            VALUES (?, 1)";

    $stmt = mysqli_prepare($conexion, $sql);

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
    die("Método no permitido.");
}

// =========================================
// RECIBIR DATOS
// =========================================

$dni = trim((string)($_POST["dni_p"] ?? ""));

$nombre = limpiarNombre(
    $_POST["nombre_p"] ?? ""
);

$apellido = limpiarNombre(
    $_POST["apellido"] ?? ""
);

$cuil = trim((string)($_POST["cuil"] ?? ""));

$telefono = trim(
    (string)($_POST["telefono"] ?? "")
);

$mail = trim(
    (string)($_POST["mail"] ?? "")
);

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
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
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
    strlen($cuil) > 11 ||
    strlen($telefono) > 20 ||
    strlen($mail) > 100 ||
    mb_strlen($trabajoNombre, "UTF-8") > 50
) {
    die("Alguno de los datos supera el tamaño permitido.");
}

$dniNumero = (int)$dni;

// =========================================
// ACTUALIZAR PACIENTE
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // =====================================
    // 1. VERIFICAR PACIENTE
    // =====================================

    $sql = "SELECT id_obra_social
            FROM paciente
            WHERE dni_p = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conexion, $sql);

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

    $pacienteActual = mysqli_fetch_assoc(
        $resultado
    );

    mysqli_stmt_close($stmt);

    if (!$pacienteActual) {
        throw new Exception(
            "No se encontró el paciente."
        );
    }

    $idObraAnterior = (int)(
        $pacienteActual["id_obra_social"] ?? 0
    );

    // =====================================
    // 2. OBRA SOCIAL
    // =====================================

    $idObraSocial = resolverObraSocial(
        $conexion,
        $obraNombre,
        $idObraAnterior
    );

    // =====================================
    // 3. TRABAJO
    // =====================================

    $idTrabajo = obtenerOCrearTrabajo(
        $conexion,
        $trabajoNombre
    );

    // =====================================
    // 4. ACTUALIZAR DATOS PERSONALES
    // =====================================

    $sql = "UPDATE paciente

            SET
                nombre_p = ?,
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
        $dniNumero
    );

    ejecutar($stmt);

    mysqli_stmt_close($stmt);

    // =====================================
    // IMPORTANTE
    // =====================================

    // NO modificar paciente_estudio.
    // NO modificar paciente_exposicion.
    // NO modificar historial_medico.
    //
    // Esas asignaciones se administrarán
    // desde el historial del paciente.

    // =====================================
    // 5. CONFIRMAR CAMBIOS
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

    echo '<p><a href="listar.php">
            Volver al listado de pacientes
          </a></p>';
}

mysqli_close($conexion);

?>
