
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

date_default_timezone_set("America/Argentina/Salta");

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

function avisoTurno($turno) {

    if (
        !in_array(
            $turno["estado"],
            ["Pendiente", "Confirmado"],
            true
        )
    ) {
        return "Turno " . strtolower($turno["estado"]);
    }

    $hoy = new DateTimeImmutable("today");

    $fecha = new DateTimeImmutable($turno["fecha"]);

    $dias = (int)$hoy->diff(
        $fecha
    )->format("%r%a");

    if ($dias < 0) {
        return "Vencido hace " . abs($dias) . " día(s)";
    }

    if ($dias === 0) {
        return "¡Es hoy!";
    }

    if ($dias === 1) {
        return "¡Es mañana!";
    }

    return "Faltan " . $dias . " días";
}

// =========================================
// TOKEN DE SEGURIDAD
// =========================================

if (empty($_SESSION["csrf_turnos"])) {
    $_SESSION["csrf_turnos"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_turnos"];

// =========================================
// FILTROS
// =========================================

$estados = [
    "Todos",
    "Activos",
    "Pendiente",
    "Confirmado",
    "Realizado",
    "Cancelado",
    "Ausente"
];

$filtroEstado = trim(
    (string)($_GET["estado"] ?? "Activos")
);

if (!in_array($filtroEstado, $estados, true)) {
    die("El estado seleccionado no es válido.");
}

$filtroDni = trim(
    (string)($_GET["dni_p"] ?? "")
);

if (
    $filtroDni !== "" &&
    (
        !ctype_digit($filtroDni) ||
        strlen($filtroDni) > 8 ||
        (int)$filtroDni <= 0
    )
) {
    die("El DNI ingresado no es válido.");
}

// =========================================
// ARMAR CONSULTA SQL
// =========================================

$sql = "SELECT
            t.id_turno,
            t.dni_paciente,
            t.fecha,
            t.hora,
            t.motivo,
            t.estado,
            t.observaciones,
            p.nombre_p,
            p.apellido

        FROM turno t

        INNER JOIN paciente p
            ON p.dni_p = t.dni_paciente

        WHERE 1 = 1";

// Filtrar por DNI, si se ingresó uno.

$usarDni = ($filtroDni !== "");

if ($usarDni) {
    $sql .= " AND t.dni_paciente = ?";
}

// Filtrar por estado.

$usarEstado = false;

if ($filtroEstado === "Activos") {

    $sql .= " AND t.estado IN (
        'Pendiente',
        'Confirmado'
    )";

} elseif ($filtroEstado !== "Todos") {

    $sql .= " AND t.estado = ?";

    $usarEstado = true;
}

// Ordenar por fecha y hora.

$sql .= " ORDER BY
            t.fecha ASC,
            t.hora ASC,
            t.id_turno ASC";

// =========================================
// EJECUTAR CONSULTA
// =========================================

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("No se pudieron consultar los turnos.");
}

// Vincular solamente los filtros utilizados.

$dniNumero = (int)$filtroDni;

if ($usarDni && $usarEstado) {

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $dniNumero,
        $filtroEstado
    );

} elseif ($usarDni) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $dniNumero
    );

} elseif ($usarEstado) {

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $filtroEstado
    );
}

if (!mysqli_stmt_execute($stmt)) {
    die("Error al buscar los turnos.");
}

$resultado = mysqli_stmt_get_result($stmt);

$turnos = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_stmt_close($stmt);
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

    <title>Turnos de pacientes</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .filtros-turnos {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
            margin: 20px 0;
        }

        .filtros-turnos label {
            display: block;
            margin-bottom: 5px;
        }

        .filtros-turnos input,
        .filtros-turnos select {
            padding: 8px;
        }

        .acciones-turnos {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .acciones-turnos form {
            margin: 0;
        }

        .tabla-contenedor {
            overflow-x: auto;
        }

        .tabla-turnos {
            width: 100%;
            border-collapse: collapse;
        }

        .tabla-turnos th,
        .tabla-turnos td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        .aviso-importante {
            font-weight: bold;
        }
    </style>
</head>

<body>

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Turnos de pacientes</h1>

        <p>
            Consultá los turnos pendientes,
            confirmados y finalizados.
        </p>

        <p>
            <a href="formulario.php">
                Registrar nuevo turno
            </a>
        </p>

        <p>
            <a href="../Pacientes/listar.php">
                Volver a pacientes
            </a>
        </p>

    </section>

    <!-- BUSCAR TURNOS -->

    <section class="tarjeta">

        <h2>Buscar turnos</h2>

        <form
            method="GET"
            action="listar.php"
            class="filtros-turnos"
        >

            <div>
                <label for="dni_p">
                    DNI del paciente
                </label>

                <input
                    type="text"
                    id="dni_p"
                    name="dni_p"
                    inputmode="numeric"
                    maxlength="8"
                    value="<?php
                        echo escapar($filtroDni);
                    ?>"
                    placeholder="Todos los pacientes"
                >
            </div>

            <div>
                <label for="estado">
                    Estado
                </label>

                <select
                    id="estado"
                    name="estado"
                >

                    <?php foreach ($estados as $estado) { ?>

                        <option
                            value="<?php
                                echo escapar($estado);
                            ?>"
                            <?php
                                echo $estado === $filtroEstado
                                    ? "selected"
                                    : "";
                            ?>
                        >
                            <?php echo escapar($estado); ?>
                        </option>

                    <?php } ?>

                </select>
            </div>

            <button type="submit">
                Buscar
            </button>

            <a href="listar.php">
                Limpiar filtros
            </a>

        </form>

        <p>
            Turnos encontrados:
            <strong>
                <?php echo count($turnos); ?>
            </strong>
        </p>

        <!-- RESULTADOS -->

        <?php if (!$turnos) { ?>

            <p>
                No se encontraron turnos
                con los filtros seleccionados.
            </p>

        <?php } else { ?>

            <div class="tabla-contenedor">

                <table class="tabla-turnos">

                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>DNI</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Motivo</th>
                            <th>Estado</th>
                            <th>Recordatorio</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($turnos as $turno) { ?>

                            <tr>

                                <td>
                                    <?php
                                        echo escapar(
                                            $turno["nombre_p"] . " " .
                                            $turno["apellido"]
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar(
                                            $turno["dni_paciente"]
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar(
                                            date(
                                                "d/m/Y",
                                                strtotime($turno["fecha"])
                                            )
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar(
                                            substr($turno["hora"], 0, 5)
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar($turno["motivo"]);
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo escapar($turno["estado"]);
                                    ?>
                                </td>

                                <td class="aviso-importante">
                                    <?php
                                        echo escapar(
                                            avisoTurno($turno)
                                        );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        echo nl2br(
                                            escapar(
                                                $turno["observaciones"]
                                            )
                                        );
                                    ?>
                                </td>

                                <td>

                                    <div class="acciones-turnos">

                                        <a href="formulario.php?id_turno=<?php
                                            echo (int)$turno["id_turno"];
                                        ?>">
                                            Editar
                                        </a>

                                        <a href="../historial/historial.php?dni_p=<?php
                                            echo (int)$turno["dni_paciente"];
                                        ?>">
                                            Historial
                                        </a>

                                        <form
                                            method="POST"
                                            action="eliminar.php"
                                            onsubmit="return confirm(
                                                '¿Eliminar definitivamente este turno?'
                                            );"
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
                                                name="id_turno"
                                                value="<?php
                                                    echo (int)$turno["id_turno"];
                                                ?>"
                                            >

                                            <button type="submit">
                                                Eliminar
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
