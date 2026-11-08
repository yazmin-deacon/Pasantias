
<?php
session_start();

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

if (empty($_SESSION["csrf_turnos"])) {
    $_SESSION["csrf_turnos"] = bin2hex(random_bytes(32));
}

$token = $_SESSION["csrf_turnos"];

$idTurno = filter_input(
    INPUT_GET,
    "id_turno",
    FILTER_VALIDATE_INT
);

$dniSolicitado = filter_input(
    INPUT_GET,
    "dni_p",
    FILTER_VALIDATE_INT
);

$turno = [
    "id_turno" => "",
    "dni_paciente" => $dniSolicitado ?: "",
    "fecha" => "",
    "hora" => "",
    "motivo" => "",
    "estado" => "Pendiente",
    "observaciones" => ""
];

$editando = false;

if ($idTurno && $idTurno > 0) {

    $stmt = mysqli_prepare(
        $conexion,
        "SELECT *
         FROM turno
         WHERE id_turno = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $idTurno);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $existente = mysqli_fetch_assoc($resultado);

    mysqli_stmt_close($stmt);

    if (!$existente) {
        die("No se encontró el turno.");
    }

    $turno = $existente;
    $editando = true;
}

$resultado = mysqli_query(
    $conexion,
    "SELECT dni_p, nombre_p, apellido
     FROM paciente
     ORDER BY apellido, nombre_p"
);

if (!$resultado) {
    die("No se pudieron consultar los pacientes.");
}

$pacientes = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);
mysqli_close($conexion);

$estados = [
    "Pendiente",
    "Confirmado",
    "Realizado",
    "Cancelado",
    "Ausente"
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo $editando ? "Editar turno" : "Nuevo turno"; ?>
    </title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .form-turno {
            max-width: 600px;
        }

        .form-turno label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-turno input,
        .form-turno select,
        .form-turno textarea {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        .form-turno textarea {
            min-height: 120px;
            resize: vertical;
        }

        .acciones-formulario {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<main class="gestion-contenido">

    <section class="bienvenida">
        <h1>
            <?php
                echo $editando
                    ? "Editar turno"
                    : "Registrar nuevo turno";
            ?>
        </h1>

        <p>
            Completá los datos del turno del paciente.
        </p>
    </section>

    <section class="tarjeta">

        <form
            action="guardar.php"
            method="POST"
            class="form-turno"
        >

            <input
                type="hidden"
                name="csrf"
                value="<?php echo escapar($token); ?>"
            >

            <input
                type="hidden"
                name="id_turno"
                value="<?php
                    echo escapar($turno["id_turno"]);
                ?>"
            >

            <p>
                <label for="dni_paciente">
                    Paciente
                </label>

                <select
                    id="dni_paciente"
                    name="dni_paciente"
                    required
                >
                    <option value="">
                        Seleccionar paciente
                    </option>

                    <?php foreach ($pacientes as $paciente) { ?>

                        <option
                            value="<?php
                                echo (int)$paciente["dni_p"];
                            ?>"
                            <?php
                                echo (int)$turno["dni_paciente"] ===
                                     (int)$paciente["dni_p"]
                                    ? "selected"
                                    : "";
                            ?>
                        >
                            <?php
                                echo escapar(
                                    $paciente["apellido"] . ", " .
                                    $paciente["nombre_p"] . " - DNI " .
                                    $paciente["dni_p"]
                                );
                            ?>
                        </option>

                    <?php } ?>

                </select>
            </p>

            <p>
                <label for="fecha">Fecha del turno</label>

                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    required
                    value="<?php
                        echo escapar($turno["fecha"]);
                    ?>"
                >
            </p>

            <p>
                <label for="hora">Hora del turno</label>

                <input
                    type="time"
                    id="hora"
                    name="hora"
                    required
                    value="<?php
                        echo escapar(
                            substr($turno["hora"], 0, 5)
                        );
                    ?>"
                >
            </p>

            <p>
                <label for="motivo">Motivo del turno</label>

                <input
                    type="text"
                    id="motivo"
                    name="motivo"
                    maxlength="150"
                    required
                    value="<?php
                        echo escapar($turno["motivo"]);
                    ?>"
                    placeholder="Ej.: Control de estudios"
                >
            </p>

            <p>
                <label for="estado">Estado</label>

                <select id="estado" name="estado">

                    <?php foreach ($estados as $estado) { ?>

                        <option
                            value="<?php echo escapar($estado); ?>"
                            <?php
                                echo $turno["estado"] === $estado
                                    ? "selected"
                                    : "";
                            ?>
                        >
                            <?php echo escapar($estado); ?>
                        </option>

                    <?php } ?>

                </select>
            </p>

            <p>
                <label for="observaciones">
                    Observaciones
                </label>

                <textarea
                    id="observaciones"
                    name="observaciones"
                    placeholder="Información adicional sobre el turno..."
                ><?php echo escapar(
                    $turno["observaciones"]
                ); ?></textarea>
            </p>

            <div class="acciones-formulario">

                <button type="submit">
                    <?php
                        echo $editando
                            ? "Guardar cambios"
                            : "Registrar turno";
                    ?>
                </button>

                <a href="listar.php">
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>
