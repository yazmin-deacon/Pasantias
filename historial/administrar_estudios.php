
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function limpiar($valor) {
    return trim(preg_replace('/\s+/u', ' ', (string)$valor));
}

function normalizar($valor) {
    return mb_strtolower(limpiar($valor), "UTF-8");
}

function consultar($conexion, $sql, $dni) {
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return $filas;
}

$dni = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

if (!$dni) {
    $dni = filter_input(
        INPUT_POST,
        "dni_p",
        FILTER_VALIDATE_INT
    );
}

if (!$dni || $dni <= 0) {
    die("DNI no válido.");
}

// Verificar paciente

$pacientes = consultar(
    $conexion,
    "SELECT dni_p, nombre_p, apellido
     FROM paciente WHERE dni_p = ?",
    $dni
);

if (!$pacientes) {
    die("No se encontró el paciente.");
}

$paciente = $pacientes[0];

// Token para proteger las operaciones POST

if (empty($_SESSION["csrf_estudios_paciente"])) {
    $_SESSION["csrf_estudios_paciente"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_estudios_paciente"];
$error = "";

// =========================================
// PROCESAR ACCIONES
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !hash_equals(
            $token,
            (string)($_POST["csrf"] ?? "")
        )
    ) {
        http_response_code(403);
        die("Solicitud no válida.");
    }

    $accion = (string)($_POST["accion"] ?? "");

    try {

        mysqli_begin_transaction($conexion);

        if ($accion === "agregar") {

            $nombre = limpiar(
                $_POST["nombre_estudio"] ?? ""
            );

            if ($nombre === "") {
                throw new Exception(
                    "Ingresá el nombre de un estudio."
                );
            }

            if (mb_strlen($nombre, "UTF-8") > 50) {
                throw new Exception(
                    "El nombre no puede superar 50 caracteres."
                );
            }

            // Buscar si ya existe en el catálogo

            $resultado = mysqli_query(
                $conexion,
                "SELECT id_estudio, nombre_es
                 FROM estudio
                 FOR UPDATE"
            );

            $idEstudio = null;

            while ($fila = mysqli_fetch_assoc($resultado)) {
                if (
                    normalizar($fila["nombre_es"]) ===
                    normalizar($nombre)
                ) {
                    $idEstudio = (int)$fila["id_estudio"];
                    break;
                }
            }

            mysqli_free_result($resultado);

            // Crear en el catálogo si no existe

            if ($idEstudio === null) {

                $stmt = mysqli_prepare(
                    $conexion,
                    "INSERT INTO estudio (nombre_es)
                     VALUES (?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $nombre
                );

                mysqli_stmt_execute($stmt);

                $idEstudio = mysqli_insert_id($conexion);

                mysqli_stmt_close($stmt);
            }

            // Asignar al paciente sin cambiar
            // el estado si ya estaba asignado

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO paciente_estudio
                    (dni_paciente, id_estudio, estado)
                 VALUES (?, ?, 'Pendiente')
                 ON DUPLICATE KEY UPDATE
                    id_estudio = id_estudio"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $idEstudio
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } elseif ($accion === "estado") {

            $id = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            $estado = (string)(
                $_POST["estado"] ?? ""
            );

            if (
                !$id ||
                !in_array(
                    $estado,
                    ["Pendiente", "Realizado"],
                    true
                )
            ) {
                throw new Exception(
                    "Datos del estudio no válidos."
                );
            }

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE paciente_estudio
                 SET estado = ?
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sii",
                $estado,
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) === 0) {
                // Puede ser el mismo estado, en cuyo
                // caso no hay modificaciones.
            }

            mysqli_stmt_close($stmt);

        } elseif ($accion === "quitar") {

            $id = filter_var(
                $_POST["id_estudio"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                throw new Exception(
                    "Estudio no válido."
                );
            }

            // Proteger estudios con registros históricos

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT COUNT(*) AS total
                 FROM historial_medico
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);

            $resultado = mysqli_stmt_get_result($stmt);
            $fila = mysqli_fetch_assoc($resultado);

            mysqli_stmt_close($stmt);

            if ((int)$fila["total"] > 0) {
                throw new Exception(
                    "No se puede quitar este estudio " .
                    "porque tiene registros en el historial médico."
                );
            }

            $stmt = mysqli_prepare(
                $conexion,
                "DELETE FROM paciente_estudio
                 WHERE dni_paciente = ?
                   AND id_estudio = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } else {
            throw new Exception(
                "Acción desconocida."
            );
        }

        mysqli_commit($conexion);

        header(
            "Location: administrar_estudios.php?dni_p=" .
            urlencode($dni) .
            "&guardado=1"
        );
        exit;

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        $error = $e->getMessage();
    }
}

// =========================================
// CONSULTAS PARA MOSTRAR
// =========================================

$catalogo = mysqli_query(
    $conexion,
    "SELECT id_estudio, nombre_es
     FROM estudio
     ORDER BY nombre_es"
);

$asignados = consultar(
    $conexion,
    "SELECT
        e.id_estudio,
        e.nombre_es,
        pe.estado
     FROM paciente_estudio pe
     INNER JOIN estudio e
        ON pe.id_estudio = e.id_estudio
     WHERE pe.dni_paciente = ?
     ORDER BY e.nombre_es",
    $dni
);

mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Administrar estudios del paciente</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .campo-estudio {
            width: 100%;
            max-width: 430px;
            padding: 10px;
            box-sizing: border-box;
        }

        .acciones-estudio {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .acciones-estudio form {
            display: inline-block;
        }

        .mensaje-error {
            color: #b12e2e;
        }
    </style>
</head>

<body>

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Administrar estudios indicados</h1>

        <p>
            Paciente:
            <strong>
                <?php
                    echo escapar(
                        $paciente["nombre_p"] . " " .
                        $paciente["apellido"]
                    );
                ?>
            </strong>
        </p>

        <p>
            DNI: <?php echo escapar($dni); ?>
        </p>

        <p>
            <a href="historial.php?dni_p=<?php
                echo urlencode($dni);
            ?>">
                Volver al historial
            </a>
        </p>

    </section>

    <section class="tarjeta">

        <?php if ($error !== "") { ?>

            <p class="mensaje-error">
                <?php echo escapar($error); ?>
            </p>

        <?php } elseif (isset($_GET["guardado"])) { ?>

            <p>Los cambios se guardaron correctamente.</p>

        <?php } ?>

        <h2>Agregar estudio</h2>

        <p>
            Buscá uno existente o escribí un nombre
            nuevo para registrarlo y asignarlo.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="dni_p"
                value="<?php echo escapar($dni); ?>"
            >

            <input
                type="hidden"
                name="csrf"
                value="<?php echo escapar($token); ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="agregar"
            >

            <p>
                <label for="nombre_estudio">
                    Nombre del estudio
                </label>
            </p>

            <input
                type="text"
                id="nombre_estudio"
                name="nombre_estudio"
                list="catalogo_estudios"
                class="campo-estudio"
                maxlength="50"
                autocomplete="off"
                required
                placeholder="Buscar o escribir estudio..."
            >

            <datalist id="catalogo_estudios">

                <?php while ($estudio = mysqli_fetch_assoc($catalogo)) { ?>

                    <option
                        value="<?php
                            echo escapar($estudio["nombre_es"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p>
                <button type="submit">
                    Agregar estudio
                </button>
            </p>

        </form>

        <p>
            <a
                href="../Estudios/estudios.php"
                target="_blank"
                rel="noopener noreferrer"
            >
                Abrir catálogo general de estudios
            </a>
        </p>

    </section>

    <section class="tarjeta">

        <h2>Estudios asignados</h2>

        <?php if (!$asignados) { ?>

            <p>
                El paciente todavía no tiene
                estudios indicados.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Estudio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($asignados as $estudio) { ?>

                            <tr>

                                <td>
                                    <?php
                                        echo escapar(
                                            $estudio["nombre_es"]
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar(
                                            $estudio["estado"]
                                        );
                                    ?>
                                </td>

                                <td>

                                    <div class="acciones-estudio">

                                        <!-- CAMBIAR ESTADO -->

                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="dni_p"
                                                value="<?php echo escapar($dni); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?php echo escapar($token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="estado"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_estudio"
                                                value="<?php
                                                    echo (int)$estudio["id_estudio"];
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="estado"
                                                value="<?php
                                                    echo $estudio["estado"] === "Realizado"
                                                        ? "Pendiente"
                                                        : "Realizado";
                                                ?>"
                                            >

                                            <button type="submit">

                                                <?php
                                                    echo $estudio["estado"] === "Realizado"
                                                        ? "Marcar pendiente"
                                                        : "Marcar realizado";
                                                ?>

                                            </button>

                                        </form>

                                        <!-- QUITAR ASIGNACIÓN -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                '¿Quitar este estudio del paciente?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="dni_p"
                                                value="<?php echo escapar($dni); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?php echo escapar($token); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="quitar"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_estudio"
                                                value="<?php
                                                    echo (int)$estudio["id_estudio"];
                                                ?>"
                                            >

                                            <button type="submit">
                                                Quitar
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        <?php } ?>

    </section>

</main>

</body>
</html>
