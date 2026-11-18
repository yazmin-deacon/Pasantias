
<?php

// =========================================
// DUCCA - REACTIVAR PACIENTE
// =========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// EVITAR CACHÉ DE INFORMACIÓN CLÍNICA
// =========================================

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// =========================================
// COMPROBAR SESIÓN HABITUAL
// =========================================

if (empty($_SESSION["dni"])) {
    header(
        "Location: /pasantias/inicio%20sesion/login.html"
    );
    exit;
}

// =========================================
// COMPROBAR ACCESO A ARCHIVADOS
// =========================================

$dniSesion = (string)$_SESSION["dni"];

$accesoDni = (string)(
    $_SESSION["acceso_archivados_dni"] ?? ""
);

$accesoHasta = (int)(
    $_SESSION["acceso_archivados_hasta"] ?? 0
);

if (
    $accesoDni !== $dniSesion ||
    $accesoHasta <= time()
) {
    unset(
        $_SESSION["acceso_archivados_dni"],
        $_SESSION["acceso_archivados_hasta"]
    );

    header("Location: acceso_archivados.php");
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

function mostrarError($mensaje) {
    http_response_code(400);

    echo '<!DOCTYPE html>';
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Error - DUCCA</title>';
    echo '<link rel="stylesheet" href="../general/estilos.css">';
    echo '</head>';
    echo '<body>';

    echo '<main class="gestion-contenido">';
    echo '<section class="tarjeta">';

    echo '<h1>No se pudo reactivar al paciente</h1>';

    echo '<p>' . escapar($mensaje) . '</p>';

    echo '<p>';
    echo '<a href="antiguos.php">';
    echo 'Volver a pacientes archivados';
    echo '</a>';
    echo '</p>';

    echo '</section>';
    echo '</main>';

    echo '</body>';
    echo '</html>';

    exit;
}

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

if (empty($_SESSION["csrf_reactivar"])) {
    $_SESSION["csrf_reactivar"] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION["csrf_reactivar"];

// =========================================
// COMPROBAR MÉTODO
// =========================================

$metodo = $_SERVER["REQUEST_METHOD"];

if (
    $metodo !== "GET" &&
    $metodo !== "POST"
) {
    http_response_code(405);
    exit("Método no permitido.");
}

// =========================================
// RECIBIR DNI
// =========================================

if ($metodo === "POST") {
    $dniTexto = (string)(
        $_POST["dni_p"] ?? ""
    );
} else {
    $dniTexto = (string)(
        $_GET["dni_p"] ?? ""
    );
}

if (
    !ctype_digit($dniTexto) ||
    strlen($dniTexto) > 8 ||
    (int)$dniTexto <= 0
) {
    mostrarError("El DNI no es válido.");
}

$dniPaciente = (int)$dniTexto;

// =========================================
// COMPROBAR TOKEN SI ES POST
// =========================================

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
            "Volvé a intentarlo."
        );
    }

    if (
        ($_POST["accion"] ?? "") !== "reactivar"
    ) {
        mostrarError("Acción no permitida.");
    }
}

// =========================================
// CONSULTAR PACIENTE ARCHIVADO
// =========================================

$sql = "SELECT
            dni_p,
            nombre_p,
            apellido,
            estado,
            fecha_archivado
        FROM paciente
        WHERE dni_p = ?";

$stmt = mysqli_prepare(
    $conexion,
    $sql
);

if (!$stmt) {
    mostrarError(
        "No se pudo consultar al paciente."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dniPaciente
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);

    mostrarError(
        "No se pudo consultar al paciente."
    );
}

$resultado = mysqli_stmt_get_result($stmt);

$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    mostrarError(
        "No se encontró el paciente."
    );
}

if ($paciente["estado"] !== "archivado") {
    mostrarError(
        "Este paciente no está archivado."
    );
}

$nombreCompleto = trim(
    $paciente["nombre_p"] . " " .
    $paciente["apellido"]
);

// =========================================
// REACTIVAR DESPUÉS DE CONFIRMAR
// =========================================

if ($metodo === "POST") {

    try {

        mysqli_begin_transaction($conexion);

        // =================================
        // 1. BLOQUEAR FILA DEL PACIENTE
        // =================================

        $stmt = mysqli_prepare(
            $conexion,
            "SELECT estado
             FROM paciente
             WHERE dni_p = ?
             FOR UPDATE"
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo verificar al paciente."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $dniPaciente
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
            $pacienteActual["estado"] !== "archivado"
        ) {
            throw new Exception(
                "El paciente ya no está archivado."
            );
        }

        // =================================
        // 2. CAMBIAR ESTADO A ACTIVO
        // =================================

        $sql = "UPDATE paciente
                SET
                    estado = 'activo',
                    fecha_archivado = NULL
                WHERE
                    dni_p = ?
                    AND estado = 'archivado'";

        $stmt = mysqli_prepare(
            $conexion,
            $sql
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo preparar la reactivación."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $dniPaciente
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
        // 3. REGISTRAR AUDITORÍA
        // =================================

        $usuarioResponsable = substr(
            $dniSesion,
            0,
            100
        );

        $sqlAuditoria =
            "INSERT INTO auditoria_pacientes
                (
                    dni_paciente,
                    accion,
                    usuario_responsable
                )
             VALUES (?, 'reactivar', ?)";

        $stmt = mysqli_prepare(
            $conexion,
            $sqlAuditoria
        );

        if (!$stmt) {
            throw new Exception(
                "No se pudo registrar la auditoría."
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "is",
            $dniPaciente,
            $usuarioResponsable
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(
                mysqli_stmt_error($stmt)
            );
        }

        mysqli_stmt_close($stmt);

        // =================================
        // NO MODIFICAR INFORMACIÓN CLÍNICA
        // =================================

        // No borrar ni modificar:
        //
        // - Datos personales
        // - DNI y CUIL
        // - Teléfono
        // - Domicilio
        // - Obra social
        // - Trabajo
        // - paciente_estudio
        // - paciente_exposicion
        // - historial_medico

        // =================================
        // 4. CONFIRMAR TRANSACCIÓN
        // =================================

        mysqli_commit($conexion);

        // Invalidar el token utilizado.

        $_SESSION["csrf_reactivar"] = bin2hex(
            random_bytes(32)
        );

        mysqli_close($conexion);

        // =================================
        // 5. VOLVER AL LISTADO HABITUAL
        // =================================

        header(
            "Location: listar.php?reactivado=1"
        );

        exit;

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        error_log(
            "Error al reactivar paciente: " .
            $e->getMessage()
        );

        mostrarError(
            "No fue posible reactivar al paciente. " .
            "La operación no se completó."
        );
    }
}

mysqli_close($conexion);

// =========================================
// FORMATO DNI
// =========================================

function formatoDNI($dni) {
    $dni = preg_replace(
        '/\D/',
        '',
        (string)$dni
    );

    return preg_replace(
        '/\B(?=(\d{3})+(?!\d))/',
        '.',
        $dni
    );
}

// =========================================
// FORMATO FECHA
// =========================================

function formatoFecha($fecha) {
    if (empty($fecha)) {
        return "Sin registrar";
    }

    try {
        return (new DateTime($fecha))
            ->format("d/m/Y H:i");
    } catch (Throwable $e) {
        return "Sin registrar";
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Reactivar paciente - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        .reactivar-pagina {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px;
            box-sizing: border-box;
        }

        .reactivar-tarjeta {
            width: 100%;
            max-width: 560px;
            padding: 35px;
            box-sizing: border-box;
            border-radius: 14px;
        }

        .reactivar-tarjeta h1 {
            margin-top: 0;
        }

        .reactivar-datos {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            background: rgba(130, 140, 150, 0.12);
        }

        .reactivar-datos p {
            margin: 9px 0;
        }

        .reactivar-info {
            padding: 18px;
            margin: 20px 0;
            border: 1px solid #9ab8ab;
            border-radius: 10px;
            background: rgba(80, 155, 110, 0.10);
        }

        .reactivar-info p {
            line-height: 1.6;
        }

        .reactivar-acciones {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .reactivar-acciones button {
            cursor: pointer;
            padding: 12px 20px;
        }

        .reactivar-cancelar {
            display: inline-block;
            padding: 12px 15px;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .reactivar-tarjeta {
                padding: 22px;
            }
        }

    </style>

</head>

<body>

<main class="reactivar-pagina">

    <section class="tarjeta reactivar-tarjeta">

        <h1>Reactivar paciente</h1>

        <p>
            Estás por devolver al siguiente paciente
            al listado habitual de DUCCA.
        </p>

        <!-- DATOS DEL PACIENTE -->

        <div class="reactivar-datos">

            <h2>
                <?php
                    echo escapar($nombreCompleto);
                ?>
            </h2>

            <p>
                <strong>DNI:</strong>

                <?php
                    echo escapar(
                        formatoDNI($dniPaciente)
                    );
                ?>
            </p>

            <p>
                <strong>Estado actual:</strong>
                Archivado
            </p>

            <p>
                <strong>Fecha de archivado:</strong>

                <?php
                    echo escapar(
                        formatoFecha(
                            $paciente["fecha_archivado"]
                        )
                    );
                ?>
            </p>

        </div>

        <!-- ADVERTENCIA -->

        <div class="reactivar-info">

            <strong>
                ¿Querés reactivar a este paciente?
            </strong>

            <p>
                El paciente volverá a aparecer
                en el listado habitual.
            </p>

            <p>
                Se conservarán sus datos personales,
                domicilio, obra social, trabajo,
                estudios, exposiciones
                e historial médico completo.
            </p>

            <p>
                Una vez reactivado, sus datos
                podrán volver a modificarse
                desde las funciones habituales
                de DUCCA.
            </p>

        </div>

        <!-- CONFIRMACIÓN -->

        <form
            method="POST"
            action="reactivar.php"
        >

            <input
                type="hidden"
                name="dni_p"
                value="<?php
                    echo escapar($dniPaciente);
                ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php
                    echo escapar($csrfToken);
                ?>"
            >

            <input
                type="hidden"
                name="accion"
                value="reactivar"
            >

            <div class="reactivar-acciones">

                <button type="submit">
                    Sí, reactivar paciente
                </button>

                <a
                    href="antiguos.php"
                    class="reactivar-cancelar"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

<script src="../general/modooscuro.js"></script>

</body>
</html>
