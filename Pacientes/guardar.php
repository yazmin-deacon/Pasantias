
<?php
require_once "conexion.php";

// =========================================
// 1. OBTENER DATOS PERSONALES
// =========================================

$dni = trim($_POST["dni_p"] ?? "");
$nombre = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");

$id_obra_social = trim(
    $_POST["id_obra_social"] ?? ""
);

$nombre_trabajo = trim(
    $_POST["trabajo_nombre"] ?? ""
);

// =========================================
// 2. OBTENER ESTUDIOS Y EXPOSICIONES
// =========================================

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];

$nuevas_exposiciones =
    $_POST["nueva_exposicion"] ?? [];

$nuevos_estudios =
    $_POST["nuevo_estudio"] ?? [];

// =========================================
// 3. VALIDAR DATOS
// =========================================

if (
    $dni === "" ||
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
    $mail === "" ||
    $id_obra_social === ""
) {
    die("Faltan completar datos obligatorios.");
}

if (!ctype_digit($dni) || strlen($dni) > 8) {
    die("El DNI no es válido.");
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    die("El correo electrónico no es válido.");
}

if (strlen($nombre_trabajo) > 50) {
    die("El nombre del trabajo es demasiado largo.");
}

if (
    !is_array($exposiciones) ||
    !is_array($estudios) ||
    !is_array($nuevas_exposiciones) ||
    !is_array($nuevos_estudios)
) {
    die("Los estudios o exposiciones no son válidos.");
}

// =========================================
// 4. CREAR O RECUPERAR ELEMENTOS
// =========================================

function obtenerOCrear(
    $conexion,
    $tabla,
    $campo_id,
    $campo_nombre,
    $nombre
) {

    $nombre = trim($nombre);

    if ($nombre === "") {
        return null;
    }

    // Buscar elemento existente
    $sql = "SELECT $campo_id
            FROM $tabla
            WHERE $campo_nombre = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar búsqueda.");
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al buscar elemento.");
    }

    $resultado = mysqli_stmt_get_result($stmt);

    if ($fila = mysqli_fetch_assoc($resultado)) {
        mysqli_stmt_close($stmt);
        return (int)$fila[$campo_id];
    }

    mysqli_stmt_close($stmt);

    // Crear elemento nuevo
    $sql = "INSERT INTO $tabla ($campo_nombre)
            VALUES (?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar inserción.");
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
// 5. INICIAR TRANSACCIÓN
// =========================================

try {

    mysqli_begin_transaction($conexion);

    // =====================================
    // 6. VALIDAR OBRA SOCIAL
    // =====================================

    if ($id_obra_social === "particular") {

        // Paciente sin obra social
        $id_obra_social = null;

    } else {

        if (
            !ctype_digit($id_obra_social) ||
            (int)$id_obra_social <= 0
        ) {
            throw new Exception(
                "La obra social no es válida."
            );
        }

        $id_obra_social = (int)$id_obra_social;

        $sql = "SELECT id_obra_social
                FROM obra_social
                WHERE id_obra_social = ?
                AND activa = 1";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {
            throw new Exception(
                "Error al consultar la obra social."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id_obra_social
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "Error al validar la obra social."
            );
        }

        $resultado = mysqli_stmt_get_result($stmt);

        $obra = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$obra) {
            throw new Exception(
                "La obra social no existe o está inactiva."
            );
        }
    }

    // =====================================
    // 7. BUSCAR O CREAR TRABAJO
    // =====================================

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

    // =====================================
    // 8. GUARDAR PACIENTE
    // =====================================

    $sql = "INSERT INTO paciente (
                dni_p,
                nombre_p,
                apellido,
                cuil,
                telefono,
                mail,
                id_obra_social,
                id_trabajo
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "No se pudo preparar el registro del paciente."
        );
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
        $id_obra_social,
        $id_trabajo
    );

    if (!mysqli_stmt_execute($stmt)) {

        throw new Exception(
            "No se pudo registrar el paciente: " .
            mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 9. CREAR EXPOSICIONES NUEVAS
    // =====================================

    foreach ($nuevas_exposiciones as $exposicion) {

        $exposicion = trim($exposicion);

        if ($exposicion !== "") {

            $id = obtenerOCrear(
                $conexion,
                "exposicion",
                "id_exposicion",
                "nombre_ex",
                $exposicion
            );

            $exposiciones[] = $id;
        }
    }

    // =====================================
    // 10. CREAR ESTUDIOS NUEVOS
    // =====================================

    foreach ($nuevos_estudios as $estudio) {

        $estudio = trim($estudio);

        if ($estudio !== "") {

            $id = obtenerOCrear(
                $conexion,
                "estudio",
                "id_estudio",
                "nombre_es",
                $estudio
            );

            $estudios[] = $id;
        }
    }

    // Evitar IDs duplicados
    $exposiciones = array_unique($exposiciones);
    $estudios = array_unique($estudios);

    // =====================================
    // 11. GUARDAR EXPOSICIONES
    // =====================================

    $sql = "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar exposiciones."
        );
    }

    foreach ($exposiciones as $id_exposicion) {

        if (
            !ctype_digit((string)$id_exposicion) ||
            (int)$id_exposicion <= 0
        ) {
            continue;
        }

        $id_exposicion = (int)$id_exposicion;

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $dni,
            $id_exposicion
        );

        if (!mysqli_stmt_execute($stmt)) {

            throw new Exception(
                "Error al guardar una exposición: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 12. GUARDAR ESTUDIOS
    // =====================================

    $sql = "INSERT INTO paciente_estudio
            (dni_paciente, id_estudio)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar estudios."
        );
    }

    foreach ($estudios as $id_estudio) {

        if (
            !ctype_digit((string)$id_estudio) ||
            (int)$id_estudio <= 0
        ) {
            continue;
        }

        $id_estudio = (int)$id_estudio;

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $dni,
            $id_estudio
        );

        if (!mysqli_stmt_execute($stmt)) {

            throw new Exception(
                "Error al guardar un estudio: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 13. CONFIRMAR LOS CAMBIOS
    // =====================================

    mysqli_commit($conexion);

    echo "Paciente registrado correctamente.<br>";

    echo '<a href="listar.php">
            Volver al listado de pacientes
          </a>';

} catch (Exception $e) {

    // Si falla algo, deshacer la transacción
    mysqli_rollback($conexion);

    echo "Error: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        );

    echo '<br><a href="alta.php">
            Volver al formulario
          </a>';
}

mysqli_close($conexion);
?>
