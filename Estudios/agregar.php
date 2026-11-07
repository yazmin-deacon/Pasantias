<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

$modoEdicion = $modoEdicion ?? false;
$mensaje = "";

// BLOQUE 2 - Obtener el paciente seleccionado
$dni = isset($_GET["dni"]) ? (int) $_GET["dni"] : 0;

$paciente = null;
$estudiosMarcados = [];
$estudiosHabituales = [];

if ($dni > 0) {

    $sqlPaciente = "SELECT p.dni_p, p.nombre_p, p.apellido, p.id_trabajo, t.nombre_t
                    FROM paciente p
                    LEFT JOIN trabajo t ON p.id_trabajo = t.id_trabajo
                    WHERE p.dni_p = ?";

    $stmt = mysqli_prepare($conexion, $sqlPaciente);

    mysqli_stmt_bind_param($stmt, "i", $dni);

    mysqli_stmt_execute($stmt);

    $resultadoPaciente = mysqli_stmt_get_result($stmt);

    $paciente = mysqli_fetch_assoc($resultadoPaciente);

    mysqli_stmt_close($stmt);

    if ($paciente) {

        // BLOQUE 3 - Estudios habituales del trabajo
        if ($paciente["id_trabajo"] !== null) {

            $sqlHabituales = "SELECT id_estudio FROM trabajo_estudio WHERE id_trabajo = ?";

            $stmt = mysqli_prepare($conexion, $sqlHabituales);

            mysqli_stmt_bind_param($stmt, "i", $paciente["id_trabajo"]);

            mysqli_stmt_execute($stmt);

            $resultadoHabituales = mysqli_stmt_get_result($stmt);

            while ($fila = mysqli_fetch_assoc($resultadoHabituales)) {
                $estudiosHabituales[] = (int) $fila["id_estudio"];
            }

            mysqli_stmt_close($stmt);
        }

        // BLOQUE 4 - Estudios ya asignados
        $sqlAsignados = "SELECT id_estudio FROM paciente_estudio WHERE dni_paciente = ?";

        $stmt = mysqli_prepare($conexion, $sqlAsignados);

        mysqli_stmt_bind_param($stmt, "i", $dni);

        mysqli_stmt_execute($stmt);

        $resultadoAsignados = mysqli_stmt_get_result($stmt);

        while ($fila = mysqli_fetch_assoc($resultadoAsignados)) {
            $estudiosMarcados[] = (int) $fila["id_estudio"];
        }

        mysqli_stmt_close($stmt);

        $estudiosMarcados = array_unique(array_merge($estudiosHabituales, $estudiosMarcados));
    }
}

// BLOQUE 5 - Consultar estudios disponibles y pacientes
$resultadoEstudios = mysqli_query($conexion, "SELECT id_estudio, nombre_es FROM estudio ORDER BY nombre_es ASC");

if (!$resultadoEstudios) {
    die("Error al consultar los estudios: " . mysqli_error($conexion));
}

$resultadoPacientes = mysqli_query($conexion, "SELECT dni_p, nombre_p, apellido FROM paciente ORDER BY apellido ASC");

if (!$resultadoPacientes) {
    die("Error al consultar los pacientes: " . mysqli_error($conexion));
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo $modoEdicion ? "Editar estudios" : "Asignar estudios"; ?> - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .acciones {
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .acciones form {
            margin:0;
            padding:0;
        }

        .acciones a, .acciones button, .btn-cancelar {
            display:inline-block;
            padding:9px 16px;
            border:0;
            border-radius:8px;
            text-decoration:none;
            font-size:14px;
            font-family:inherit;
            font-weight:600;
            cursor:pointer;
            text-align:center;
            transition:0.3s;
        }

        .acciones a, .btn-secundario {
            background:#e5e5e5;
            color:#333;
        }

        .acciones a:hover, .btn-secundario:hover {
            background:#d0d0d0;
        }

        .btn-eliminar {
            background:#c94b4b;
            color:white;
        }

        .btn-eliminar:hover {
            background:#a93636;
        }

        .btn-cancelar {
            background:transparent;
            color:white;
            margin-left:8px;
            padding:9px 12px;
        }

        .btn-cancelar:hover {
            background:#f3b5c8;
            color:#762b45;
        }

        .lista-estudios {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:12px;
            margin-top:15px;
        }

        .estudio-opcion {
            padding:12px;
            border:1px solid #ddd;
            border-radius:8px;
        }

        .estudio-opcion label {
            cursor:pointer;
        }

        .estudio-opcion input {
            margin-right:8px;
        }

        .mensaje-error {
            color:#b33;
        }

        .mensaje-ok {
            color:#287a45;
        }

        .datos-paciente {
            margin:15px 0;
            padding:15px;
            border-radius:8px;
            background:rgba(128,128,128,.10);
        }
    </style>
</head>

<body>

<header>

    <div class="logo">
        <img id="logo" src="../general/Logo minimalista DUCCA con hojas orgánicas.png" alt="Logo DUCA" height="150" width="175">
    </div>

    <nav>
        <a href="../inicio sesion/index.php">Menú principal</a>
        <a href="../gestionar_exposiciones.html">Exposiciones</a>
        <a href="../Trabajos/Trabajos.php">Trabajos</a>
        <a href="../gestionar_relaciones.html">Relaciones</a>
        <a href="estudios.php">Estudios</a>
    </nav>

    <button type="button" id="btn-tema">Modo oscuro</button>

</header>

<main class="gestion-contenido">

    <section class="bienvenida">
        <h1><?php echo $modoEdicion ? "Editar estudios del paciente" : "Asignar estudios al paciente"; ?></h1>
        <p>Seleccioná un paciente y marcá los estudios que correspondan.</p>
    </section>

    <?php if (!$modoEdicion) { ?>

        <section class="tarjeta">

            <h2>Seleccionar paciente</h2>

            <form class="form-gestion" action="agregar.php" method="GET">

                <div class="campo">

                    <label for="dni">Paciente</label>

                    <select name="dni" id="dni" required>

                        <option value="">Seleccione un paciente</option>

                        <?php while ($fila = mysqli_fetch_assoc($resultadoPacientes)) { ?>

                            <option value="<?php echo $fila["dni_p"]; ?>"
                                <?php echo ($dni == $fila["dni_p"]) ? "selected" : ""; ?>>

                                <?php echo htmlspecialchars($fila["apellido"] . ", " . $fila["nombre_p"] . " - DNI: " . $fila["dni_p"]); ?>

                            </option>

                        <?php } ?>

                    </select>

                </div>

                <button type="submit">Seleccionar paciente</button>

            </form>

        </section>

    <?php } ?>

    <?php if ($dni > 0 && $paciente) { ?>

        <section class="tarjeta">

            <h2>Estudios del paciente</h2>

            <div class="datos-paciente">

                <p>
                    <strong>Paciente:</strong>
                    <?php echo htmlspecialchars($paciente["nombre_p"] . " " . $paciente["apellido"]); ?>
                </p>

                <p>
                    <strong>DNI:</strong>
                    <?php echo htmlspecialchars($paciente["dni_p"]); ?>
                </p>

                <p>
                    <strong>Trabajo:</strong>
                    <?php echo htmlspecialchars($paciente["nombre_t"] ?? "Sin trabajo"); ?>
                </p>

            </div>

            <p>Los estudios habituales correspondientes al trabajo aparecen seleccionados automáticamente. Podés agregar o quitar otros.</p>

            <form class="form-gestion" action="<?php echo $modoEdicion ? 'procesar_editar.php' : 'procesar_agregar.php'; ?>" method="POST">

                <input type="hidden" name="dni" value="<?php echo $paciente["dni_p"]; ?>">

                <div class="lista-estudios">

                    <?php while ($estudio = mysqli_fetch_assoc($resultadoEstudios)) { ?>

                        <div class="estudio-opcion">

                            <label>

                                <input type="checkbox" name="estudios[]" value="<?php echo $estudio["id_estudio"]; ?>"
                                    <?php echo in_array((int) $estudio["id_estudio"], $estudiosMarcados) ? "checked" : ""; ?>>

                                <?php echo htmlspecialchars($estudio["nombre_es"]); ?>

                                <?php if (in_array((int) $estudio["id_estudio"], $estudiosHabituales)) { ?>
                                    <strong>(Habitual)</strong>
                                <?php } ?>

                            </label>

                        </div>

                    <?php } ?>

                </div>

                <br>

                <button type="submit">
                    <?php echo $modoEdicion ? "Guardar cambios" : "Asignar estudios"; ?>
                </button>

                <a href="estudios.php" class="btn-cancelar">Cancelar</a>

            </form>

        </section>

    <?php } elseif ($dni > 0) { ?>

        <section class="tarjeta">
            <p class="mensaje-error">El paciente seleccionado no existe.</p>
        </section>

    <?php } ?>

</main>

<footer>
    <span>Clínica</span>
    <span>Administración de estudios por paciente</span>
</footer>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>

<?php mysqli_close($conexion); ?>