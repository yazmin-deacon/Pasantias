
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

    $filas = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt);

    return $filas;
}

// =========================================
// OBTENER PACIENTE
// =========================================

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

$pacientes = consultar(
    $conexion,
    "SELECT dni_p, nombre_p, apellido
     FROM paciente
     WHERE dni_p = ?",
    $dni
);

if (!$pacientes) {
    die("No se encontró el paciente.");
}

$paciente = $pacientes[0];

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

if (empty($_SESSION["csrf_exposiciones_paciente"])) {
    $_SESSION["csrf_exposiciones_paciente"] =
        bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_exposiciones_paciente"];
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
                $_POST["nombre_exposicion"] ?? ""
            );

            if ($nombre === "") {
                throw new Exception(
                    "Ingresá el nombre de una exposición."
                );
            }

            if (mb_strlen($nombre, "UTF-8") > 50) {
                throw new Exception(
                    "El nombre no puede superar 50 caracteres."
                );
            }

            // Buscar si ya existe

            $resultado = mysqli_query(
                $conexion,
                "SELECT id_exposicion, nombre_ex
                 FROM exposicion
                 FOR UPDATE"
            );

            $idExposicion = null;

            while ($fila = mysqli_fetch_assoc($resultado)) {

                if (
                    normalizar($fila["nombre_ex"]) ===
                    normalizar($nombre)
                ) {

                    $idExposicion =
                        (int)$fila["id_exposicion"];

                    break;
                }
            }

            mysqli_free_result($resultado);

            // Crear exposición nueva si hace falta

            if ($idExposicion === null) {

                $stmt = mysqli_prepare(
                    $conexion,
                    "INSERT INTO exposicion (nombre_ex)
                     VALUES (?)"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $nombre
                );

                mysqli_stmt_execute($stmt);

                $idExposicion =
                    mysqli_insert_id($conexion);

                mysqli_stmt_close($stmt);
            }

            // Asignar al paciente

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT IGNORE INTO paciente_exposicion
                    (dni_paciente, id_exposicion)
                 VALUES (?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ii",
                $dni,
                $idExposicion
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

        } elseif ($accion === "quitar") {

            $id = filter_var(
                $_POST["id_exposicion"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {
                throw new Exception(
                    "Exposición no válida."
                );
            }

            $stmt = mysqli_prepare(
                $conexion,
                "DELETE FROM paciente_exposicion
                 WHERE dni_paciente = ?
                   AND id_exposicion = ?"
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
            "Location: administrar_exposiciones.php?dni_p=" .
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
// CONSULTAR CATÁLOGO Y ASIGNACIONES
// =========================================

$catalogo = mysqli_query(
    $conexion,
    "SELECT id_exposicion, nombre_ex
     FROM exposicion
     ORDER BY nombre_ex"
);

$asignadas = consultar(
    $conexion,
    "SELECT
        e.id_exposicion,
        e.nombre_ex
     FROM paciente_exposicion pe
     INNER JOIN exposicion e
        ON pe.id_exposicion = e.id_exposicion
     WHERE pe.dni_paciente = ?
     ORDER BY e.nombre_ex",
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

    <title>Administrar exposiciones</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .campo-exposicion {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            padding: 10px;
        }

        .mensaje-error {
            color: #b12e2e;
        }
    </style>
</head>

<body>

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Administrar exposiciones</h1>

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

        <h2>Agregar exposición</h2>

        <p>
            Buscá una exposición existente
            o escribí una nueva para registrarla.
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
                <label for="nombre_exposicion">
                    Nombre de la exposición
                </label>
            </p>

            <input
                type="text"
                id="nombre_exposicion"
                name="nombre_exposicion"
                list="catalogo_exposiciones"
                class="campo-exposicion"
                maxlength="50"
                autocomplete="off"
                required
                placeholder="Buscar o escribir exposición..."
            >

            <datalist id="catalogo_exposiciones">

                <?php while ($fila = mysqli_fetch_assoc($catalogo)) { ?>

                    <option
                        value="<?php
                            echo escapar($fila["nombre_ex"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p>
                <button type="submit">
                    Agregar exposición
                </button>
            </p>

        </form>

        <p>
            <a
                href="../Exposiciones/listar.php"
                target="_blank"
                rel="noopener noreferrer"
            >
                Abrir catálogo general de exposiciones
            </a>
        </p>

    </section>

    <section class="tarjeta">

        <h2>Exposiciones asignadas</h2>

        <?php if (!$asignadas) { ?>

            <p>
                El paciente todavía no tiene
                exposiciones asignadas.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Exposición</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($asignadas as $fila) { ?>

                        <tr>

                            <td>
                                <?php
                                    echo escapar(
                                        $fila["nombre_ex"]
                                    );
                                ?>
                            </td>

                            <td>

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        '¿Quitar esta exposición del paciente?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="dni_p"
                                        value="<?php
                                            echo escapar($dni);
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf"
                                        value="<?php
                                            echo escapar($token);
                                        ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="quitar"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_exposicion"
                                        value="<?php
                                            echo (int)$fila["id_exposicion"];
                                        ?>"
                                    >

                                    <button type="submit">
                                        Quitar
                                    </button>

                                </form>

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
