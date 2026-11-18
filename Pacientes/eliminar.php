
<?php
session_start();

require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// CONFIGURACIÓN DE SEGURIDAD
// =========================================

$config = require __DIR__ . "/seguridad_archivado.php";

$claveSesion = (string)($config["sesion_clave"] ?? "");
$hashArchivado = (string)($config["password_hash"] ?? "");

// Bloquear el acceso hasta conectar
// correctamente el inicio de sesión.

if (
    $claveSesion === "" ||
    empty($_SESSION[$claveSesion])
) {
    http_response_code(403);
    exit("Acceso no autorizado.");
}

// No permitir el archivado si todavía
// no se configuró la contraseña especial.

if ($hashArchivado === "") {
    http_response_code(503);
    exit(
        "La contraseña de archivado todavía " .
        "no está configurada."
    );
}

// =========================================
// FUNCIONES
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function mostrarError($mensaje) {
    http_response_code(400);

    echo "<h2>No se pudo archivar al paciente</h2>";
    echo "<p>" . escapar($mensaje) . "</p>";
    echo '<p><a href="listar.php">Volver al listado</a></p>';

    exit;
}

// =========================================
// PROTECCIÓN CONTRA SOLICITUDES FALSAS
// =========================================

if (empty($_SESSION["csrf_archivado"])) {
    $_SESSION["csrf_archivado"] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION["csrf_archivado"];

// =========================================
// RECIBIR DNI
// =========================================

$metodo = $_SERVER["REQUEST_METHOD"];

if ($metodo === "POST") {
    $dniTexto = (string)($_POST["dni_p"] ?? "");
} else {
    $dniTexto = (string)($_GET["dni_p"] ?? "");
}

if (
    !ctype_digit($dniTexto) ||
    strlen($dniTexto) > 8 ||
    (int)$dniTexto <= 0
) {
    mostrarError("El DNI no es válido.");
}

$dni_p = (int)$dniTexto;

// =========================================
// COMPROBAR SOLICITUD POST
// =========================================

$accion = "";

if ($metodo === "POST") {

    $tokenEnviado = (string)(
        $_POST["csrf_token"] ?? ""
    );

    if (
        !hash_equals(
            $csrfToken,
            $tokenEnviado
        )
    ) {
        mostrarError(
            "La solicitud no es válida. " .
            "Volvé al listado e intentá nuevamente."
        );
    }

    $accion = (string)(
        $_POST["accion"] ?? ""
    );

    if (
        !in_array(
            $accion,
            ["continuar", "archivar"],
            true
        )
    ) {
        mostrarError("Acción no permitida.");
    }
}

// =========================================
// BUSCAR PACIENTE ACTIVO
// =========================================

$stmt = mysqli_prepare(
    $conexion,
    "SELECT
        dni_p,
        nombre_p,
        apellido,
        estado
     FROM paciente
     WHERE dni_p = ?"
);

if (!$stmt) {
    mostrarError(
        "No se pudo consultar al paciente."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dni_p
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    mostrarError(
        "No se encontró el paciente."
    );
}

if ($paciente["estado"] !== "activo") {
    mostrarError(
        "Este paciente ya se encuentra archivado."
    );
}

$nombreCompleto = trim(
    $paciente["nombre_p"] . " " .
    $paciente["apellido"]
);

// =========================================
// CONTROL DE INTENTOS DE CONTRASEÑA
// =========================================

if (!isset($_SESSION["intentos_archivado"])) {
    $_SESSION["intentos_archivado"] = 0;
}

if (!isset($_SESSION["bloqueo_archivado"])) {
    $_SESSION["bloqueo_archivado"] = 0;
}

if (
    $_SESSION["bloqueo_archivado"] > 0 &&
    time() >= $_SESSION["bloqueo_archivado"]
) {
    $_SESSION["bloqueo_archivado"] = 0;
    $_SESSION["intentos_archivado"] = 0;
}

$bloqueado =
    $_SESSION["bloqueo_archivado"] > time();

$errorClave = "";

// =========================================
// ARCHIVAR PACIENTE
// =========================================

if ($accion === "archivar") {

    if ($bloqueado) {
        mostrarError(
            "Se alcanzó el límite de intentos. " .
            "Intentá nuevamente más tarde."
        );
    }

    $claveIngresada = (string)(
        $_POST["clave_archivado"] ?? ""
    );

    if (
        !password_verify(
            $claveIngresada,
            $hashArchivado
        )
    ) {
        $_SESSION["intentos_archivado"]++;

        if (
            $_SESSION["intentos_archivado"] >= 5
        ) {
            $_SESSION["bloqueo_archivado"] =
                time() + 900;

            $errorClave =
                "Demasiados intentos. " .
                "El archivado se bloqueó " .
                "durante 15 minutos.";
        } else {
            $errorClave =
                "La contraseña especial es incorrecta.";
        }

    } else {

        // Contraseña correcta.

        try {

            mysqli_begin_transaction($conexion);

            // Volver a verificar el estado
            // dentro de la transacción.

            $stmt = mysqli_prepare(
                $conexion,
                "SELECT estado
                 FROM paciente
                 WHERE dni_p = ?
                 FOR UPDATE"
            );

            if (!$stmt) {
                throw new Exception(
                    mysqli_error($conexion)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $dni_p
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(
                    mysqli_stmt_error($stmt)
                );
            }

            $resultadoActual =
                mysqli_stmt_get_result($stmt);

            $pacienteActual =
                mysqli_fetch_assoc($resultadoActual);

            mysqli_stmt_close($stmt);

            if (
                !$pacienteActual ||
                $pacienteActual["estado"] !== "activo"
            ) {
                throw new Exception(
                    "El paciente ya no está activo."
                );
            }

            // =================================
            // CAMBIAR ESTADO
            // =================================

            $stmt = mysqli_prepare(
                $conexion,
                "UPDATE paciente
                 SET
                    estado = 'archivado',
                    fecha_archivado = NOW()
                 WHERE
                    dni_p = ?
                    AND estado = 'activo'"
            );

            if (!$stmt) {
                throw new Exception(
                    mysqli_error($conexion)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $dni_p
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(
                    mysqli_stmt_error($stmt)
                );
            }

            if (
                mysqli_stmt_affected_rows($stmt) !== 1
            ) {
                throw new Exception(
                    "No se pudo cambiar el estado."
                );
            }

            mysqli_stmt_close($stmt);

            // =================================
            // REGISTRAR AUDITORÍA
            // =================================

            $usuarioResponsable = substr(
                (string)$_SESSION[$claveSesion],
                0,
                100
            );

            $stmt = mysqli_prepare(
                $conexion,
                "INSERT INTO auditoria_pacientes
                    (
                        dni_paciente,
                        accion,
                        usuario_responsable
                    )
                 VALUES (?, 'archivar', ?)"
            );

            if (!$stmt) {
                throw new Exception(
                    mysqli_error($conexion)
                );
            }

            mysqli_stmt_bind_param(
                $stmt,
                "is",
                $dni_p,
                $usuarioResponsable
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(
                    mysqli_stmt_error($stmt)
                );
            }

            mysqli_stmt_close($stmt);

            // No borrar:
            // - paciente
            // - paciente_estudio
            // - paciente_exposicion
            // - historial_medico
            // - datos personales
            // - domicilio

            mysqli_commit($conexion);

            // Restablecer protección.

            $_SESSION["intentos_archivado"] = 0;
            $_SESSION["bloqueo_archivado"] = 0;

            $_SESSION["csrf_archivado"] = bin2hex(
                random_bytes(32)
            );

            mysqli_close($conexion);

            header(
                "Location: listar.php?archivado=1"
            );

            exit;

        } catch (Throwable $e) {

            mysqli_rollback($conexion);

            mostrarError(
                "No se pudo archivar al paciente: " .
                $e->getMessage()
            );
        }
    }
}

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

    <title>Archivar paciente - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .archivo-pagina {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .archivo-tarjeta {
            width: 100%;
            max-width: 550px;
            box-sizing: border-box;
            padding: 35px;
            border-radius: 14px;
        }

        .archivo-tarjeta h1 {
            margin-top: 0;
        }

        .archivo-advertencia {
            padding: 18px;
            border-radius: 10px;
            border: 1px solid #d9a0a0;
            background: #fff0f0;
            color: #7c2828;
            margin: 20px 0;
        }

        .archivo-datos {
            padding: 15px;
            margin: 15px 0;
            border-radius: 9px;
            background: rgba(130, 140, 150, 0.12);
        }

        .archivo-campo {
            margin: 20px 0;
        }

        .archivo-campo label {
            display: block;
            margin-bottom: 8px;
        }

        .archivo-campo input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
        }

        .archivo-error {
            color: #b02a37;
            font-weight: 600;
        }

        .archivo-acciones {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 22px;
        }

        .archivo-acciones button {
            padding: 12px 18px;
            cursor: pointer;
        }
    </style>

</head>

<body>

<main class="archivo-pagina">

    <section class="tarjeta archivo-tarjeta">

        <?php if ($accion === "") { ?>

            <!-- PRIMERA CONFIRMACIÓN -->

            <h1>Archivar paciente</h1>

            <div class="archivo-datos">
                <strong>
                    <?php echo escapar($nombreCompleto); ?>
                </strong>

                <p>
                    DNI:
                    <?php echo escapar($dni_p); ?>
                </p>
            </div>

            <div class="archivo-advertencia">

                <strong>¿Querés archivar a este paciente?</strong>

                <p>
                    Dejará de aparecer en el listado
                    habitual de pacientes.
                </p>

                <p>
                    Todos sus datos, estudios,
                    exposiciones e historial médico
                    se conservarán.
                </p>

                <p>
                    Podrás consultarlo desde
                    Pacientes archivados y
                    reactivarlo cuando sea necesario.
                </p>

            </div>

            <form method="POST" action="eliminar.php">

                <input
                    type="hidden"
                    name="dni_p"
                    value="<?php echo $dni_p; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escapar($csrfToken); ?>"
                >

                <input
                    type="hidden"
                    name="accion"
                    value="continuar"
                >

                <div class="archivo-acciones">

                    <button type="submit">
                        Sí, continuar
                    </button>

                    <a href="listar.php">
                        Cancelar
                    </a>

                </div>

            </form>

        <?php } else { ?>

            <!-- CONTRASEÑA ESPECIAL -->

            <h1>Confirmar archivado</h1>

            <p>
                Estás por archivar a
                <strong>
                    <?php echo escapar($nombreCompleto); ?>
                </strong>.
            </p>

            <p>
                Para completar esta acción,
                ingresá la contraseña especial
                de archivado.
            </p>

            <?php if ($errorClave !== "") { ?>

                <p class="archivo-error">
                    <?php echo escapar($errorClave); ?>
                </p>

            <?php } ?>

            <form method="POST" action="eliminar.php">

                <input
                    type="hidden"
                    name="dni_p"
                    value="<?php echo $dni_p; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo escapar($csrfToken); ?>"
                >

                <input
                    type="hidden"
                    name="accion"
                    value="archivar"
                >

                <div class="archivo-campo">

                    <label for="clave_archivado">
                        Contraseña especial
                    </label>

                    <input
                        type="password"
                        id="clave_archivado"
                        name="clave_archivado"
                        autocomplete="off"
                        required
                        <?php
                            echo $bloqueado ? "disabled" : "";
                        ?>
                    >

                </div>

                <div class="archivo-acciones">

                    <button
                        type="submit"
                        <?php
                            echo $bloqueado ? "disabled" : "";
                        ?>
                    >
                        Archivar paciente
                    </button>

                    <a href="listar.php">
                        Cancelar
                    </a>

                </div>

            </form>

        <?php } ?>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
