
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// COMPROBAR INICIO DE SESIÓN
// =========================================

if (!isset($_SESSION["dni"])) {
    header(
        "Location: /pasantias/inicio%20sesion/login.html"
    );
    exit;
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

// =========================================
// CONFIGURAR SEGURIDAD
// =========================================

// El acceso al archivo durará 10 minutos.
// Después habrá que verificar otra vez
// la contraseña habitual.

$duracionAcceso = 600;

$dniSesion = (string)$_SESSION["dni"];

// Protección CSRF.

if (empty($_SESSION["csrf_acceso_archivados"])) {
    $_SESSION["csrf_acceso_archivados"] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION["csrf_acceso_archivados"];

// Control de intentos incorrectos.

if (!isset($_SESSION["intentos_acceso_archivados"])) {
    $_SESSION["intentos_acceso_archivados"] = 0;
}

if (!isset($_SESSION["bloqueo_acceso_archivados"])) {
    $_SESSION["bloqueo_acceso_archivados"] = 0;
}

if (
    $_SESSION["bloqueo_acceso_archivados"] > 0 &&
    time() >= $_SESSION["bloqueo_acceso_archivados"]
) {
    $_SESSION["bloqueo_acceso_archivados"] = 0;
    $_SESSION["intentos_acceso_archivados"] = 0;
}

$bloqueado =
    $_SESSION["bloqueo_acceso_archivados"] > time();

$error = "";

// =========================================
// VERIFICAR CONTRASEÑA
// =========================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenEnviado = (string)(
        $_POST["csrf_token"] ?? ""
    );

    if (!hash_equals($csrfToken, $tokenEnviado)) {

        $error = "La solicitud no es válida.";

    } elseif ($bloqueado) {

        $error = "Demasiados intentos. Intentá nuevamente más tarde.";

    } else {

        $contrasena = (string)(
            $_POST["contrasena"] ?? ""
        );

        // Consultar la contraseña del usuario
        // que tiene la sesión iniciada.

        $sql = "SELECT contrasena
                FROM usuario
                WHERE dni_u = ?";

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {
            http_response_code(500);
            exit("No se pudo verificar el acceso.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $dniSesion
        );

        mysqli_stmt_execute($stmt);

        $resultado = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($resultado);

        mysqli_stmt_close($stmt);

        if (
            $usuario &&
            password_verify(
                $contrasena,
                $usuario["contrasena"]
            )
        ) {

            // CONTRASEÑA CORRECTA

            session_regenerate_id(true);

            $_SESSION["acceso_archivados_dni"] =
                $dniSesion;

            $_SESSION["acceso_archivados_hasta"] =
                time() + $duracionAcceso;

            $_SESSION["intentos_acceso_archivados"] = 0;
            $_SESSION["bloqueo_acceso_archivados"] = 0;

            $_SESSION["csrf_acceso_archivados"] =
                bin2hex(random_bytes(32));

            mysqli_close($conexion);

            header("Location: antiguos.php");
            exit;

        } else {

            // CONTRASEÑA INCORRECTA

            $_SESSION["intentos_acceso_archivados"]++;

            $error = "La contraseña es incorrecta.";

            if (
                $_SESSION["intentos_acceso_archivados"] >= 5
            ) {

                $_SESSION["bloqueo_acceso_archivados"] =
                    time() + 900;

                $bloqueado = true;

                $error =
                    "Se alcanzó el límite de intentos. " .
                    "El acceso se bloqueó durante 15 minutos.";
            }
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

    <title>Acceso a pacientes archivados - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .acceso-archivo {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .acceso-tarjeta {
            width: 100%;
            max-width: 480px;
            box-sizing: border-box;
            padding: 35px;
            border-radius: 14px;
        }

        .acceso-tarjeta h1 {
            margin-top: 0;
        }

        .acceso-tarjeta p {
            line-height: 1.6;
        }

        .acceso-campo {
            margin: 25px 0;
        }

        .acceso-campo label {
            display: block;
            margin-bottom: 8px;
        }

        .acceso-campo input {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
        }

        .acceso-botones {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .acceso-botones button {
            padding: 12px 18px;
            cursor: pointer;
        }

        .acceso-error {
            color: #b02a37;
            font-weight: 600;
        }

        .acceso-info {
            padding: 15px;
            margin: 20px 0;
            border-radius: 9px;
            background: rgba(130, 140, 150, 0.12);
        }

        @media (max-width: 600px) {
            .acceso-tarjeta {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<main class="acceso-archivo">

    <section class="tarjeta acceso-tarjeta">

        <h1>Pacientes archivados</h1>

        <p>
            Esta sección contiene los pacientes
            que ya no se encuentran en atención activa.
        </p>

        <div class="acceso-info">
            <strong>Área protegida</strong>

            <p>
                Para consultar los pacientes archivados,
                ingresá tu contraseña habitual
                de inicio de sesión.
            </p>

            <p>
                Podrás consultar sus fichas
                e historiales médicos en modo lectura.
            </p>
        </div>

        <?php if ($error !== "") { ?>

            <p class="acceso-error">
                <?php echo escapar($error); ?>
            </p>

        <?php } ?>

        <form
            action="acceso_archivados.php"
            method="POST"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo escapar($csrfToken); ?>"
            >

            <div class="acceso-campo">

                <label for="contrasena">
                    Contraseña de inicio de sesión
                </label>

                <input
                    type="password"
                    id="contrasena"
                    name="contrasena"
                    autocomplete="current-password"
                    required
                    <?php
                        echo $bloqueado ? "disabled" : "";
                    ?>
                >

            </div>

            <div class="acceso-botones">

                <button
                    type="submit"
                    <?php
                        echo $bloqueado ? "disabled" : "";
                    ?>
                >
                    Acceder al archivo
                </button>

                <a href="../inicio%20sesion/index.php">
                    Volver al menú
                </a>

            </div>

        </form>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
