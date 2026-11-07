
<?php
require_once "conexion.php";

// =========================================
// 1. RECIBIR DATOS
// =========================================

$dni = trim($_POST["dni_p"] ?? "");
$nombre = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");
$obra = trim($_POST["id_obra_social"] ?? "");
$nombre_trabajo = trim($_POST["nombre_trabajo"] ?? "");

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];
$nuevas_exposiciones = $_POST["nueva_exposicion"] ?? [];
$nuevos_estudios = $_POST["nuevo_estudio"] ?? [];

// =========================================
// 2. VALIDACIONES
// =========================================

if (
    $dni === "" ||
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
    $mail === "" ||
    $obra === ""
) {
    die("Faltan datos obligatorios.");
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
    mb_strlen($nombre_trabajo) > 50
) {
    die("Uno de los campos supera el límite permitido.");
}

foreach ([
    $exposiciones,
    $estudios,
    $nuevas_exposiciones,
    $nuevos_estudios
] as $lista) {

    if (!is_array($lista)) {
        die("La selección de estudios o exposiciones no es válida.");
    }
}

// =========================================
// 3. BUSCAR O CREAR ELEMENTOS
// =========================================

function obtenerOCrear(
    $conexion,
    $tabla,
    $campo_id,
    $campo_nombre,
    $nombre
) {

    $permitidos = [
        "trabajo" => ["id_trabajo", "nombre_t"],
        "exposicion" => ["id_exposicion", "nombre_ex"],
        "estudio" => ["id_estudio", "nombre_es"]
    ];

    if (
        !isset($permitidos[$tabla]) ||
        $permitidos[$tabla] !== [$campo_id, $campo_nombre]
    ) {
        throw new Exception("Catálogo no válido.");
    }

    $nombre = trim($nombre);

    if ($nombre === "") {
        return null;
    }

    if (mb_strlen($nombre) > 50) {
        throw new Exception("El nombre es demasiado largo.");
    }

    $sql = "SELECT $campo_id
            FROM $tabla
            WHERE $campo_nombre = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al buscar elemento.");
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al consultar catálogo.");
    }

    $resultado = mysqli_stmt_get_result($stmt);
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if ($fila) {
        return (int)$fila[$campo_id];
    }

    $sql = "INSERT INTO $tabla ($campo_nombre)
            VALUES (?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar el registro.");
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudo agregar el elemento: " .
            mysqli_stmt_error($stmt)
        );
    }

    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $id;
}

// =========================================
// 4. INICIAR TRANSACCIÓN
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // BUSCAR PACIENTE

    $sql = "SELECT id_obra_social
            FROM paciente
            WHERE dni_p = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al buscar paciente.");
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $paciente_actual = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$paciente_actual) {
        throw new Exception("No se encontró el paciente.");
    }

    // VALIDAR OBRA SOCIAL

    if ($obra === "particular") {

        $id_obra_social = null;

    } else {

        if (!ctype_digit($obra) || (int)$obra <= 0) {
            throw new Exception("Obra social no válida.");
        }

        $id_obra_social = (int)$obra;

        $sql = "SELECT id_obra_social, activa
                FROM obra_social
                WHERE id_obra_social = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {
            throw new Exception("Error al consultar obra social.");
        }

        mysqli_stmt_bind_param(
            $stmt, "i", $id_obra_social
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $obra_encontrada = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$obra_encontrada) {
            throw new Exception("La obra social no existe.");
        }

        if (
            !(int)$obra_encontrada["activa"] &&
            (int)($paciente_actual["id_obra_social"] ?? 0)
                !== $id_obra_social
        ) {
            throw new Exception(
                "La obra social seleccionada está inactiva."
            );
        }
    }

    // BUSCAR O CREAR TRABAJO

    $id_trabajo = null;

    if ($nombre_trabajo !== "") {
        $id_trabajo = obtenerOCrear(
            $conexion,
            "trabajo",
            "id_trabajo",
            "nombre_t",
            $nombre_trabajo
        );
    }

    // ACTUALIZAR PACIENTE

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
        throw new Exception(
            "Error al preparar la actualización."
        );
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssiii",
        $nombre,
        $apellido,
        $cuil,
        $telefono,
        $mail,
        $id_obra_social,
        $id_trabajo,
        $dni
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudo actualizar el paciente: " .
            mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    // CREAR EXPOSICIONES NUEVAS

    foreach ($nuevas_exposiciones as $exposicion) {

        if (!is_string($exposicion)) {
            throw new Exception("Exposición no válida.");
        }

        if (trim($exposicion) !== "") {
            $exposiciones[] = obtenerOCrear(
                $conexion,
                "exposicion",
                "id_exposicion",
                "nombre_ex",
                $exposicion
            );
        }
    }

    // CREAR ESTUDIOS NUEVOS

    foreach ($nuevos_estudios as $estudio) {

        if (!is_string($estudio)) {
            throw new Exception("Estudio no válido.");
        }

        if (trim($estudio) !== "") {
            $estudios[] = obtenerOCrear(
                $conexion,
                "estudio",
                "id_estudio",
                "nombre_es",
                $estudio
            );
        }
    }

    $exposiciones = array_unique($exposiciones);
    $estudios = array_unique($estudios);

    // ACTUALIZAR EXPOSICIONES

    $sql = "DELETE FROM paciente_exposicion
            WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar exposiciones.");
    }

    mysqli_stmt_bind_param($stmt, "i", $dni);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudieron actualizar las exposiciones."
        );
    }

    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar nuevas exposiciones."
        );
    }

    foreach ($exposiciones as $id) {

        if (
            !ctype_digit((string)$id) ||
            (int)$id <= 0
        ) {
            throw new Exception("ID de exposición no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "Error al guardar exposición: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // CONSERVAR ESTUDIOS CON HISTORIAL

    $sql = "DELETE FROM paciente_estudio
            WHERE dni_paciente = ?
            AND id_estudio NOT IN (
                SELECT id_estudio
                FROM historial_medico
                WHERE dni_paciente = ?
            )";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar estudios.");
    }

    mysqli_stmt_bind_param($stmt, "ii", $dni, $dni);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudieron actualizar los estudios."
        );
    }

    mysqli_stmt_close($stmt);

    // GUARDAR ESTUDIOS SELECCIONADOS

    $sql = "INSERT INTO paciente_estudio
            (dni_paciente, id_estudio)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                id_estudio = VALUES(id_estudio)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar los estudios seleccionados."
        );
    }

    foreach ($estudios as $id) {

        if (
            !ctype_digit((string)$id) ||
            (int)$id <= 0
        ) {
            throw new Exception("ID de estudio no válido.");
        }

        $id = (int)$id;

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "Error al guardar estudio: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // CONFIRMAR TODOS LOS CAMBIOS

    mysqli_commit($conexion);

    echo "Paciente actualizado correctamente.<br>";
    echo '<a href="listar.php">Volver al listado</a>';

} catch (Throwable $e) {

    mysqli_rollback($conexion);

    echo "Error: " . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        "UTF-8"
    );

    echo '<br><a href="listar.php">Volver al listado</a>';
}

mysqli_close($conexion);
?>
