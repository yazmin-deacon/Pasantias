
<?php
require_once "conexion.php";

// =========================================
// 1. RECIBIR DATOS DEL FORMULARIO
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
    $_POST["nombre_trabajo"] ?? ""
);

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];

$nuevas_exposiciones =
    $_POST["nueva_exposicion"] ?? [];

$nuevos_estudios =
    $_POST["nuevo_estudio"] ?? [];

// =========================================
// 2. VALIDAR DATOS
// =========================================

if (
    $dni === "" ||
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
    $mail === "" ||
    $id_obra_social === ""
) {
    die("Faltan datos obligatorios o el DNI no es válido.");
}

if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    die("El correo electrónico no es válido.");
}

if (strlen($nombre_trabajo) > 50) {
    die("El nombre del trabajo supera los 50 caracteres.");
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
// 3. BUSCAR O CREAR ELEMENTOS
// =========================================

function obtenerOCrear(
    $conexion,
    $tabla,
    $campo_id,
    $campo_nombre,
    $nombre
) {
    // Los nombres de tabla y columnas se utilizan
    // solamente con valores fijos del código.
    $nombre = trim($nombre);

    if ($nombre === "") {
        return null;
    }

    $sql = "SELECT $campo_id
            FROM $tabla
            WHERE $campo_nombre = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la búsqueda.");
    }

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al buscar el elemento.");
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
        throw new Exception("Error al preparar la inserción.");
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

    // =====================================
    // 5. VALIDAR LA OBRA SOCIAL
    // =====================================

    if ($id_obra_social === "particular") {

        $id_obra_social = null;

    } else {

        if (
            !ctype_digit($id_obra_social) ||
            (int)$id_obra_social <= 0
        ) {
            throw new Exception(
                "La obra social seleccionada no es válida."
            );
        }

        $id_obra_social = (int)$id_obra_social;

        // Permitimos mantener una obra social inactiva
        // si ya estaba asociada a este paciente.
        $sql = "SELECT os.id_obra_social
                FROM obra_social os
                JOIN paciente p ON p.dni_p = ?
                WHERE os.id_obra_social = ?
                  AND (
                    os.activa = 1
                    OR p.id_obra_social = os.id_obra_social
                  )";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {
            throw new Exception(
                "No se pudo consultar la obra social."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $dni,
            $id_obra_social
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "No se pudo validar la obra social."
            );
        }

        $resultado = mysqli_stmt_get_result($stmt);
        $obra = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (!$obra) {
            throw new Exception(
                "La obra social no está disponible."
            );
        }
    }

    // =====================================
    // 6. BUSCAR O CREAR EL TRABAJO
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
    // 7. ACTUALIZAR PACIENTE
    // =====================================

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
            "No se pudo preparar la actualización."
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

    // =====================================
    // 8. AGREGAR EXPOSICIONES NUEVAS
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
    // 9. AGREGAR ESTUDIOS NUEVOS
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

    // =====================================
    // 10. EVITAR IDs REPETIDOS
    // =====================================

    $exposiciones = array_unique($exposiciones);
    $estudios = array_unique($estudios);

    // =====================================
    // 11. ACTUALIZAR EXPOSICIONES
    // =====================================

    $sql = "DELETE FROM paciente_exposicion
            WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar las exposiciones."
        );
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
                "Error al guardar exposición: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 12. CONSERVAR ESTUDIOS CON HISTORIAL
    // =====================================

    $sql = "DELETE FROM paciente_estudio
            WHERE dni_paciente = ?
            AND id_estudio NOT IN (
                SELECT id_estudio
                FROM historial_medico
                WHERE dni_paciente = ?
            )";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        throw new Exception(
            "Error al preparar los estudios."
        );
    }

    mysqli_stmt_bind_param($stmt, "ii", $dni, $dni);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudieron actualizar los estudios."
        );
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 13. GUARDAR ESTUDIOS SELECCIONADOS
    // =====================================

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
                "Error al guardar estudio: " .
                mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // =====================================
    // 14. CONFIRMAR CAMBIOS
    // =====================================

    mysqli_commit($conexion);

    echo "Paciente actualizado correctamente.<br>";
    echo '<a href="listar.php">
            Volver al listado de pacientes
          </a>';

} catch (Exception $e) {

    mysqli_rollback($conexion);

    echo "Error: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        );

    echo '<br><a href="listar.php">
            Volver al listado
          </a>';
}

mysqli_close($conexion);
?>
