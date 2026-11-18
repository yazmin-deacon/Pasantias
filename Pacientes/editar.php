
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error al consultar los datos.");
    }

    $filas = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_free_result($resultado);

    return $filas;
}

// OBTENER PACIENTE

$dni = preg_replace(
    '/\D/',
    '',
    (string)($_GET["dni_p"] ?? "")
);

if (
    !ctype_digit($dni) ||
    strlen($dni) > 8 ||
    (int)$dni <= 0
) {
    die("DNI no válido.");
}

$dniNumero = (int)$dni;

$stmt = mysqli_prepare(
    $conexion,
    "SELECT * FROM paciente WHERE dni_p = ?"
);

if (!$stmt) {
    die("No se pudo consultar el paciente.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $dniNumero
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
}

// OBRAS SOCIALES Y TRABAJOS

$obrasSociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os
     FROM obra_social ORDER BY nombre_os"
);

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo ORDER BY nombre_t"
);

// DATOS ACTUALES

$nombreObraActual = "Particular / Sin obra social";

foreach ($obrasSociales as $obra) {
    if (
        (int)$obra["id_obra_social"] ===
        (int)($paciente["id_obra_social"] ?? 0)
    ) {
        $nombreObraActual = $obra["nombre_os"];
        break;
    }
}

$nombreTrabajoActual = "";

foreach ($trabajos as $trabajo) {
    if (
        (int)$trabajo["id_trabajo"] ===
        (int)($paciente["id_trabajo"] ?? 0)
    ) {
        $nombreTrabajoActual = $trabajo["nombre_t"];
        break;
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

    <title>Editar paciente - DUCCA</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .campo-busqueda {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            padding: 10px;
            margin: 5px 0;
        }

        .ayuda {
            font-size: 14px;
        }

        .acciones-finales {
            margin-top: 20px;
        }
    </style>
</head>

<body>

<header>

<a class="flecha-volver" href="listar.php"
   aria-label="Volver a pacientes" title="Volver a pacientes">
    <img src="../general/flechita.png" alt="Flecha volver" width="30" height="30">
</a>

<div class="logo">
    <img
        id="logo"
        src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
        alt="Logo DUCA"
        height="150"
        width="175"
    >
</div>
        <nav>

            <a href="../inicio sesion/index.php">
                Menú principal
            </a>

            <a href="../gestionar_exposiciones.html">
                Exposiciones
            </a>

            <a href="listar.php">
                Trabajos
            </a>

            <a href="../gestionar_relaciones.html">
                Relaciones
            </a>

            <a href="../gestionar_estudios.html">
                Estudios
            </a>

        </nav>

        <button type="button" id="btn-tema">
            Modo oscuro
        </button>

    </header>

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Editar paciente</h1>

        <p>
            Modificá los datos personales,
            la obra social y el trabajo del paciente.
        </p>
    </section>

    <section class="tarjeta">

        <form
            action="actualizar.php"
            method="POST"
            id="formPaciente"
            class="form-gestion"
        >

            <!-- DNI ORIGINAL PARA MYSQL -->

            <input
                type="hidden"
                name="dni_p"
                value="<?php
                    echo escapar($paciente["dni_p"]);
                ?>"
            >

            <h2>Datos personales</h2>

            <p>
                <label for="dni_mostrar">DNI</label>

                <input
                    type="text"
                    id="dni_mostrar"
                    value="<?php
                        echo escapar(
                            number_format(
                                (int)$paciente["dni_p"],
                                0,
                                ",",
                                "."
                            )
                        );
                    ?>"
                    disabled
                >
            </p>

            <p>
                <label for="nombre_p">Nombre</label>

                <input
                    type="text"
                    id="nombre_p"
                    name="nombre_p"
                    maxlength="30"
                    required
                    value="<?php
                        echo escapar($paciente["nombre_p"]);
                    ?>"
                >
            </p>

            <p>
                <label for="apellido">Apellido</label>

                <input
                    type="text"
                    id="apellido"
                    name="apellido"
                    maxlength="30"
                    required
                    value="<?php
                        echo escapar($paciente["apellido"]);
                    ?>"
                >
            </p>

            <p>
                <label for="cuil">CUIL</label>

                <input
                    type="text"
                    id="cuil"
                    name="cuil"
                    maxlength="13"
                    inputmode="numeric"
                    placeholder="20-12345678-3"
                    value="<?php
                        echo escapar($paciente["cuil"]);
                    ?>"
                >
            </p>

            <p>
                <label for="telefono">Teléfono</label>

                <input
                    type="text"
                    id="telefono"
                    name="telefono"
                    maxlength="20"
                    required
                    value="<?php
                        echo escapar($paciente["telefono"]);
                    ?>"
                >
            </p>

            <p>
                <label for="mail">Mail</label>

                <input
                    type="email"
                    id="mail"
                    name="mail"
                    maxlength="100"
                    required
                    value="<?php
                        echo escapar($paciente["mail"]);
                    ?>"
                >
            </p>

            <hr>

            <h2>Obra social</h2>

            <p class="ayuda">
                Buscá una obra social existente
                o escribí una nueva.
            </p>

            <input
                type="text"
                name="obra_social_nombre"
                id="obra_social_nombre"
                class="campo-busqueda"
                list="lista_obras_sociales"
                maxlength="100"
                autocomplete="off"
                required
                value="<?php
                    echo escapar($nombreObraActual);
                ?>"
                placeholder="Buscar obra social..."
            >

            <datalist id="lista_obras_sociales">

                <option
                    value="Particular / Sin obra social"
                ></option>

                <?php foreach ($obrasSociales as $obra) { ?>

                    <option
                        value="<?php
                            echo escapar($obra["nombre_os"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p id="mensaje_obra" class="ayuda"></p>

            <p>
                <!-- Administrar obra social -->
<button
    type="button"
    class="btn-administrar"
    onclick="window.location.href='../Obra%20Social/listar.php'">
    Administrar obras sociales
</button>
            </p>

            <hr>

            <h2>Trabajo</h2>

            <p class="ayuda">
                Buscá un trabajo existente
                o escribí uno nuevo.
            </p>

            <input
                type="text"
                name="nombre_trabajo"
                id="nombre_trabajo"
                class="campo-busqueda"
                list="lista_trabajos"
                maxlength="50"
                autocomplete="off"
                value="<?php
                    echo escapar($nombreTrabajoActual);
                ?>"
                placeholder="Buscar trabajo..."
            >

            <datalist id="lista_trabajos">

                <?php foreach ($trabajos as $trabajo) { ?>

                    <option
                        value="<?php
                            echo escapar($trabajo["nombre_t"]);
                        ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p id="mensaje_trabajo" class="ayuda"></p>

            <!-- Administrar trabajos -->
<button
    type="button"
    class="btn-administrar"
    onclick="window.location.href='../Trabajos/listar.php'">
    Administrar trabajos
</button>

            <div class="acciones-finales">

                <button type="submit">
                    Guardar cambios
                </button>

                <!-- Cancelar -->
<button
    type="button"
    class="btn-cancelar-paciente"
    onclick="window.location.href='listar.php'">
    Cancelar
</button>

            </div>

        </form>

    </section>

</main>

<script>
const obras = <?php echo json_encode(
    $obrasSociales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

const trabajos = <?php echo json_encode(
    $trabajos,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

const SIN_OBRA = "Particular / Sin obra social";

function limpiar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ");
}

function normalizar(texto) {
    return limpiar(texto).toLocaleLowerCase("es");
}

// ======================================
// CUIL CON GUIONES AUTOMÁTICOS
// ======================================

function formatoCUIL(texto) {
    const numeros = String(texto)
        .replace(/\D/g, "")
        .slice(0, 11);

    if (numeros.length > 10) {
        return numeros.slice(0, 2) + "-" +
               numeros.slice(2, 10) + "-" +
               numeros.slice(10);
    }

    if (numeros.length > 2) {
        return numeros.slice(0, 2) + "-" +
               numeros.slice(2);
    }

    return numeros;
}

const campoCUIL = document.getElementById("cuil");

// Mostrar con guiones el CUIL guardado.

campoCUIL.value = formatoCUIL(campoCUIL.value);

// Aplicar formato mientras se escribe.

campoCUIL.addEventListener("input", function () {
    const posicion = this.selectionStart ?? this.value.length;

    const digitosAntes = this.value
        .slice(0, posicion)
        .replace(/\D/g, "")
        .length;

    const nuevo = formatoCUIL(this.value);
    this.value = nuevo;

    let indice = 0;
    let digitos = 0;

    while (
        indice < nuevo.length &&
        digitos < digitosAntes
    ) {
        if (/\d/.test(nuevo[indice])) {
            digitos++;
        }

        indice++;
    }

    this.setSelectionRange(indice, indice);
});

// ======================================
// OBRA SOCIAL
// ======================================

const campoObra = document.getElementById(
    "obra_social_nombre"
);

const mensajeObra = document.getElementById(
    "mensaje_obra"
);

function actualizarObra() {
    const nombre = normalizar(campoObra.value);

    const existente = [
        SIN_OBRA,
        ...obras.map(o => o.nombre_os)
    ].find(
        item => normalizar(item) === nombre
    );

    if (!nombre) {
        mensajeObra.textContent = "";
    } else if (existente) {
        mensajeObra.textContent =
            "Obra social seleccionada: " + existente;
    } else {
        mensajeObra.textContent =
            "Obra social nueva. Se registrará al guardar.";
    }
}

campoObra.addEventListener(
    "input",
    actualizarObra
);

actualizarObra();

// ======================================
// TRABAJO
// ======================================

const campoTrabajo = document.getElementById(
    "nombre_trabajo"
);

const mensajeTrabajo = document.getElementById(
    "mensaje_trabajo"
);

function actualizarTrabajo() {
    const nombre = normalizar(campoTrabajo.value);

    const existente = trabajos.find(
        t => normalizar(t.nombre_t) === nombre
    );

    if (!nombre) {
        mensajeTrabajo.textContent =
            "Sin trabajo seleccionado.";
    } else if (existente) {
        mensajeTrabajo.textContent =
            "Trabajo seleccionado: " + existente.nombre_t;
    } else {
        mensajeTrabajo.textContent =
            "Trabajo nuevo. Se registrará al guardar.";
    }
}

campoTrabajo.addEventListener(
    "input",
    actualizarTrabajo
);

actualizarTrabajo();

// ======================================
// VALIDACIÓN Y GUARDADO
// ======================================

document.getElementById("formPaciente")
    .addEventListener("submit", evento => {

        campoObra.value = limpiar(campoObra.value);
        campoTrabajo.value = limpiar(campoTrabajo.value);

        if (!campoObra.value) {
            evento.preventDefault();

            alert(
                "Seleccioná una obra social o indicá Particular."
            );

            campoObra.focus();
            return;
        }

        const cuilNumeros = campoCUIL.value
            .replace(/\D/g, "");

        if (
            cuilNumeros !== "" &&
            !/^[0-9]{11}$/.test(cuilNumeros)
        ) {
            evento.preventDefault();

            alert("El CUIL debe tener 11 números.");

            campoCUIL.focus();
            return;
        }

        // Guardar en MySQL sin guiones.
        campoCUIL.value = cuilNumeros;
    });
</script>

<script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>
</html>
