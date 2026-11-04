
<?php
require_once "conexion.php";

// BLOQUE 1 - Recibir datos del formulario
$dni = trim($_POST["dni_p"] ?? "");
$nombre = trim($_POST["nombre_p"] ?? "");
$apellido = trim($_POST["apellido"] ?? "");
$cuil = trim($_POST["cuil"] ?? "");
$telefono = trim($_POST["telefono"] ?? "");
$mail = trim($_POST["mail"] ?? "");
$obra_social = trim($_POST["obra_social"] ?? "");

$nombre_trabajo = trim($_POST["nombre_trabajo"] ?? "");

$exposiciones = $_POST["exposiciones"] ?? [];
$estudios = $_POST["estudios"] ?? [];

$nuevas_exposiciones = $_POST["nueva_exposicion"] ?? [];
$nuevos_estudios = $_POST["nuevo_estudio"] ?? [];

// BLOQUE 2 - Validar datos
if (
    !ctype_digit($dni) ||
    $dni === "" ||
    $nombre === "" ||
    $apellido === "" ||
    $telefono === "" ||
    $mail === "" ||
    $obra_social === ""
) {
    die("Faltan completar datos obligatorios o el DNI no es válido.");
}

if (strlen($nombre_trabajo) > 50) {
    die("El nombre del trabajo no puede superar los 50 caracteres.");
}

// BLOQUE 3 - Función para buscar o crear elementos
function obtenerOCrear($conexion, $tabla, $campo_id, $campo_nombre, $nombre) {

    $nombre = trim($nombre);

    if ($nombre === "") {
        return null;
    }

    $sql = "SELECT $campo_id FROM $tabla WHERE $campo_nombre = ?";
    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "s", $nombre);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);

    if ($fila = mysqli_fetch_assoc($resultado)) {
        mysqli_stmt_close($stmt);
        return (int)$fila[$campo_id];
    }

    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO $tabla ($campo_nombre) VALUES (?)";
    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param($stmt, "s", $nombre);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudo agregar $nombre: " . mysqli_stmt_error($stmt)
        );
    }

    $id = mysqli_insert_id($conexion);

    mysqli_stmt_close($stmt);

    return $id;
}

try {

    // BLOQUE 4 - Iniciar transacción
    mysqli_begin_transaction($conexion);

    // BLOQUE 5 - Buscar o crear el trabajo
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

    // BLOQUE 6 - Actualizar datos del paciente
    $sql = "UPDATE paciente
            SET nombre_p = ?,
                apellido = ?,
                cuil = ?,
                telefono = ?,
                mail = ?,
                obra_social = ?,
                id_trabajo = ?
            WHERE dni_p = ?";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssii",
        $nombre,
        $apellido,
        $cuil,
        $telefono,
        $mail,
        $obra_social,
        $id_trabajo,
        $dni
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(
            "No se pudieron actualizar los datos: " . mysqli_stmt_error($stmt)
        );
    }

    mysqli_stmt_close($stmt);

    // BLOQUE 7 - Crear o recuperar exposiciones nuevas
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

    // BLOQUE 8 - Crear o recuperar estudios nuevos
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

    // BLOQUE 9 - Evitar IDs repetidos
    $exposiciones = array_unique($exposiciones);
    $estudios = array_unique($estudios);

    // BLOQUE 10 - Actualizar exposiciones del paciente
    $sql = "DELETE FROM paciente_exposicion
            WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $dni);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("No se pudieron actualizar las exposiciones.");
    }

    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO paciente_exposicion
            (dni_paciente, id_exposicion)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    foreach ($exposiciones as $id_exposicion) {

        $id_exposicion = (int)$id_exposicion;

        if ($id_exposicion <= 0) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id_exposicion);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "No se pudo guardar una exposición: " . mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // BLOQUE 11 - Eliminar estudios que ya no fueron seleccionados,
    // pero conservar los que tengan historial médico
    $sql = "DELETE FROM paciente_estudio
            WHERE dni_paciente = ?
            AND id_estudio NOT IN (
                SELECT id_estudio
                FROM historial_medico
                WHERE dni_paciente = ?
            )";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $dni, $dni);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("No se pudieron actualizar los estudios.");
    }

    mysqli_stmt_close($stmt);

    // BLOQUE 12 - Guardar los estudios seleccionados
    // INSERT IGNORE evita duplicar asociaciones existentes
    $sql = "INSERT IGNORE INTO paciente_estudio
            (dni_paciente, id_estudio)
            VALUES (?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    foreach ($estudios as $id_estudio) {

        $id_estudio = (int)$id_estudio;

        if ($id_estudio <= 0) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, "ii", $dni, $id_estudio);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                "No se pudo guardar un estudio: " . mysqli_stmt_error($stmt)
            );
        }
    }

    mysqli_stmt_close($stmt);

    // BLOQUE 13 - Confirmar cambios
    mysqli_commit($conexion);

    echo "Paciente actualizado correctamente.<br>";
    echo '<a href="listar.php">Volver al listado de pacientes</a>';

} catch (Exception $e) {

    mysqli_rollback($conexion);

    echo "Error: " . htmlspecialchars($e->getMessage());
    echo '<br><a href="listar.php">Volver al listado de pacientes</a>';
}

mysqli_close($conexion);
?>
