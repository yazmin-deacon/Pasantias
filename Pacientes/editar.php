
<?php
require_once "conexion.php";

// =========================================
// 1. OBTENER PACIENTE
// =========================================

$dni = trim($_GET["dni_p"] ?? "");

if (!ctype_digit($dni) || strlen($dni) > 8) {
    die("DNI no válido.");
}

$sql = "SELECT * FROM paciente WHERE dni_p = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
}

// =========================================
// 2. CONSULTAR CATÁLOGOS
// =========================================

function consultarTodos($conexion, $sql) {

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error en la consulta: " . mysqli_error($conexion));
    }

    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo ORDER BY nombre_t"
);

$id_os_actual = (int)($paciente["id_obra_social"] ?? 0);

$obras_sociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os, activa
     FROM obra_social
     WHERE activa = 1
        OR id_obra_social = $id_os_actual
     ORDER BY nombre_os"
);

$exposiciones = consultarTodos(
    $conexion,
    "SELECT id_exposicion, nombre_ex
     FROM exposicion ORDER BY nombre_ex"
);

$estudios = consultarTodos(
    $conexion,
    "SELECT id_estudio, nombre_es
     FROM estudio ORDER BY nombre_es"
);

// =========================================
// 3. OBTENER SELECCIONES ACTUALES
// =========================================

function obtenerIds($conexion, $tabla, $campo, $dni) {

    $permitidos = [
        "paciente_estudio" => "id_estudio",
        "paciente_exposicion" => "id_exposicion"
    ];

    if (
        !isset($permitidos[$tabla]) ||
        $permitidos[$tabla] !== $campo
    ) {
        die("Consulta no válida.");
    }

    $sql = "SELECT $campo
            FROM $tabla
            WHERE dni_paciente = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $dni);
    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $ids = [];

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $ids[] = (int)$fila[$campo];
    }

    mysqli_stmt_close($stmt);

    return $ids;
}

$exposiciones_paciente = obtenerIds(
    $conexion,
    "paciente_exposicion",
    "id_exposicion",
    $dni
);

$estudios_paciente = obtenerIds(
    $conexion,
    "paciente_estudio",
    "id_estudio",
    $dni
);

// =========================================
// 4. RELACIONES DE LOS TRABAJOS
// =========================================

$exposiciones_trabajo = [];
$estudios_trabajo = [];

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_exposicion
     FROM trabajo_exposicion"
);

foreach ($filas as $fila) {
    $exposiciones_trabajo[$fila["id_trabajo"]][] =
        (int)$fila["id_exposicion"];
}

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_estudio
     FROM trabajo_estudio"
);

foreach ($filas as $fila) {
    $estudios_trabajo[$fila["id_trabajo"]][] =
        (int)$fila["id_estudio"];
}

// =========================================
// 5. TRABAJO ACTUAL
// =========================================

$nombre_trabajo_actual = "";

foreach ($trabajos as $trabajo) {

    if (
        (int)$trabajo["id_trabajo"] ===
        (int)($paciente["id_trabajo"] ?? 0)
    ) {
        $nombre_trabajo_actual = $trabajo["nombre_t"];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Editar paciente</title>
     <link rel="stylesheet" href="../general/estilos.css">
</head>
<body>

<h1>Editar paciente</h1>

<form action="actualizar.php" method="POST">

    <input type="hidden"
           name="dni_p"
           value="<?php echo escapar($paciente["dni_p"]); ?>">

    <!-- DATOS PERSONALES -->

    <h2>Datos personales</h2>

    <p>
        DNI:
        <input type="text"
               value="<?php echo escapar($paciente["dni_p"]); ?>"
               disabled>
    </p>

    <p>
        Nombre:
        <input type="text"
               name="nombre_p"
               maxlength="30"
               required
               value="<?php echo escapar($paciente["nombre_p"]); ?>">
    </p>

    <p>
        Apellido:
        <input type="text"
               name="apellido"
               maxlength="30"
               required
               value="<?php echo escapar($paciente["apellido"]); ?>">
    </p>

    <p>
        CUIL:
        <input type="text"
               name="cuil"
               maxlength="11"
               value="<?php echo escapar($paciente["cuil"]); ?>">
    </p>

    <p>
        Teléfono:
        <input type="text"
               name="telefono"
               maxlength="20"
               required
               value="<?php echo escapar($paciente["telefono"]); ?>">
    </p>

    <p>
        Mail:
        <input type="email"
               name="mail"
               maxlength="100"
               required
               value="<?php echo escapar($paciente["mail"]); ?>">
    </p>

    <hr>

    <!-- OBRA SOCIAL -->

    <h2>Obra social</h2>

    <p>
        <select name="id_obra_social" required>

            <option value="particular"
                <?php echo
                    $paciente["id_obra_social"] === null
                    ? "selected" : "";
                ?>>
                Particular / Sin obra social
            </option>

            <?php foreach ($obras_sociales as $obra) { ?>

                <option
                    value="<?php
                        echo (int)$obra["id_obra_social"];
                    ?>"
                    <?php echo (
                        $paciente["id_obra_social"] !== null &&
                        (int)$paciente["id_obra_social"] ===
                        (int)$obra["id_obra_social"]
                    ) ? "selected" : ""; ?>
                >
                    <?php echo escapar($obra["nombre_os"]); ?>

                    <?php if (!(int)$obra["activa"]) { ?>
                        (Inactiva)
                    <?php } ?>
                </option>

            <?php } ?>

        </select>
    </p>

    <p>
        <a href="../Obra%20Social/listar.php"
           target="_blank"
           rel="noopener noreferrer">
            Administrar obras sociales
        </a>
    </p>

    <hr>

    <!-- TRABAJO -->

    <h2>Trabajo</h2>

    <p>Buscar o escribir trabajo:</p>

    <p>
        <input
            type="text"
            name="nombre_trabajo"
            id="nombre_trabajo"
            list="lista_trabajos"
            maxlength="50"
            value="<?php echo escapar($nombre_trabajo_actual); ?>"
            placeholder="Buscar o escribir trabajo"
            autocomplete="off"
        >

        <datalist id="lista_trabajos">

            <?php foreach ($trabajos as $trabajo) { ?>
                <option value="<?php
                    echo escapar($trabajo["nombre_t"]);
                ?>">
            <?php } ?>

        </datalist>
    </p>

    <p>
        <button type="button"
                onclick="agregarTrabajo()">
            Agregar trabajo
        </button>
    </p>

    <p id="mensaje_trabajo"></p>

    <p>
        <a href="../Trabajos/listar.php"
           target="_blank"
           rel="noopener noreferrer">
            Administrar trabajos
        </a>
    </p>

    <hr>

    <!-- EXPOSICIONES -->

    <h2>Exposiciones del paciente</h2>

    <p>Podés buscar, agregar o quitar exposiciones.</p>

    <p>
        <input type="text"
               id="buscar_exposiciones"
               placeholder="Buscar exposición...">
    </p>

    <div id="lista_exposiciones">

        <?php foreach ($exposiciones as $exposicion) { ?>

            <p class="opcion-exposicion">
                <label>
                    <input
                        type="checkbox"
                        name="exposiciones[]"
                        value="<?php
                            echo (int)$exposicion["id_exposicion"];
                        ?>"
                        data-exposicion="<?php
                            echo (int)$exposicion["id_exposicion"];
                        ?>"
                        <?php echo in_array(
                            (int)$exposicion["id_exposicion"],
                            $exposiciones_paciente,
                            true
                        ) ? "checked" : ""; ?>
                    >

                    <?php echo escapar($exposicion["nombre_ex"]); ?>
                </label>
            </p>

        <?php } ?>

    </div>

    <p>
        <input
            type="text"
            id="nueva_exposicion"
            maxlength="50"
            placeholder="Nueva exposición"
        >

        <button type="button"
                onclick="agregarExposicion()">
            Agregar exposición
        </button>
    </p>

    <div id="exposiciones_nuevas"></div>

    <p>
        <a href="../Exposiciones/listar.php"
           target="_blank"
           rel="noopener noreferrer">
            Administrar exposiciones
        </a>
    </p>

    <hr>

    <!-- ESTUDIOS -->

    <h2>Estudios del paciente</h2>

    <p>Podés buscar, agregar o quitar estudios.</p>

    <p>
        <input type="text"
               id="buscar_estudios"
               placeholder="Buscar estudio...">
    </p>

    <div id="lista_estudios">

        <?php foreach ($estudios as $estudio) { ?>

            <p class="opcion-estudio">
                <label>
                    <input
                        type="checkbox"
                        name="estudios[]"
                        value="<?php
                            echo (int)$estudio["id_estudio"];
                        ?>"
                        data-estudio="<?php
                            echo (int)$estudio["id_estudio"];
                        ?>"
                        <?php echo in_array(
                            (int)$estudio["id_estudio"],
                            $estudios_paciente,
                            true
                        ) ? "checked" : ""; ?>
                    >

                    <?php echo escapar($estudio["nombre_es"]); ?>
                </label>
            </p>

        <?php } ?>

    </div>

    <p>
        <input
            type="text"
            id="nuevo_estudio"
            maxlength="50"
            placeholder="Nuevo estudio"
        >

        <button type="button"
                onclick="agregarEstudio()">
            Agregar estudio
        </button>
    </p>

    <div id="estudios_nuevos"></div>

    <p>
        <a href="../Estudios/estudios.php"
           target="_blank"
           rel="noopener noreferrer">
            Administrar estudios
        </a>
    </p>

    <br><br>

    <input type="submit" value="Guardar cambios">

</form>

<p>
    <a href="listar.php">Volver al listado</a>
</p>

<script>

// =========================================
// DATOS DESDE PHP
// =========================================

const trabajos = <?php echo json_encode(
    $trabajos,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const exposicionesPorTrabajo =
    <?php echo json_encode($exposiciones_trabajo); ?>;

const estudiosPorTrabajo =
    <?php echo json_encode($estudios_trabajo); ?>;

const exposicionesOriginales =
    <?php echo json_encode($exposiciones_paciente); ?>;

const estudiosOriginales =
    <?php echo json_encode($estudios_paciente); ?>;

const trabajoOriginal = <?php echo json_encode(
    $nombre_trabajo_actual,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const selectorTrabajo =
    document.getElementById("nombre_trabajo");

function normalizar(texto) {
    return texto.trim().toLocaleLowerCase("es");
}

// =========================================
// SELECCIÓN AUTOMÁTICA SEGÚN TRABAJO
// =========================================

function actualizarSelecciones() {

    const nombre = normalizar(selectorTrabajo.value);

    const trabajo = trabajos.find(t =>
        normalizar(t.nombre_t) === nombre
    );

    let exp = [];
    let est = [];

    if (nombre === normalizar(trabajoOriginal)) {

        exp = exposicionesOriginales;
        est = estudiosOriginales;

    } else if (trabajo) {

        exp = exposicionesPorTrabajo[trabajo.id_trabajo] || [];
        est = estudiosPorTrabajo[trabajo.id_trabajo] || [];
    }

    document.querySelectorAll("[data-exposicion]")
        .forEach(casilla => {

            casilla.checked = exp.includes(
                Number(casilla.dataset.exposicion)
            );
        });

    document.querySelectorAll("[data-estudio]")
        .forEach(casilla => {

            casilla.checked = est.includes(
                Number(casilla.dataset.estudio)
            );
        });
}

selectorTrabajo.addEventListener(
    "change",
    actualizarSelecciones
);

// =========================================
// AGREGAR O SELECCIONAR TRABAJO
// =========================================

function agregarTrabajo() {

    const nombre = selectorTrabajo.value.trim();

    const mensaje =
        document.getElementById("mensaje_trabajo");

    if (nombre === "") {
        alert("Escribí o seleccioná un trabajo.");
        return;
    }

    const existente = trabajos.find(t =>
        normalizar(t.nombre_t) === normalizar(nombre)
    );

    if (existente) {

        mensaje.textContent =
            "Trabajo seleccionado: " + existente.nombre_t;

    } else {

        mensaje.textContent =
            "Trabajo nuevo: " + nombre +
            ". Se guardará al guardar los cambios.";
    }

    actualizarSelecciones();
}

// =========================================
// BUSCADORES
// =========================================

function configurarBuscador(id, clase) {

    document.getElementById(id)
        .addEventListener("input", function () {

            const texto = normalizar(this.value);

            document.querySelectorAll(clase)
                .forEach(elemento => {

                    elemento.style.display =
                        normalizar(elemento.textContent)
                            .includes(texto)
                        ? ""
                        : "none";
                });
        });
}

configurarBuscador(
    "buscar_exposiciones",
    ".opcion-exposicion"
);

configurarBuscador(
    "buscar_estudios",
    ".opcion-estudio"
);

// =========================================
// AGREGAR EXPOSICIONES Y ESTUDIOS
// =========================================

function agregarElemento(
    campoId,
    selectorExistente,
    nombreInput,
    contendorId
) {

    const campo = document.getElementById(campoId);
    const nombre = campo.value.trim();

    if (nombre === "") {
        alert("Escribí un nombre.");
        return;
    }

    const existente = Array.from(
        document.querySelectorAll(selectorExistente)
    ).find(casilla =>
        normalizar(
            casilla.parentElement.textContent
        ) === normalizar(nombre)
    );

    if (existente) {

        existente.checked = true;
        campo.value = "";
        return;
    }

    const contenedor =
        document.getElementById(contendorId);

    const repetido = Array.from(
        contenedor.querySelectorAll(
            'input[name="' + nombreInput + '"]'
        )
    ).some(input =>
        normalizar(input.value) === normalizar(nombre)
    );

    if (repetido) {
        alert("Ese elemento ya fue agregado.");
        return;
    }

    const fila = document.createElement("p");
    const texto = document.createElement("span");

    texto.textContent = "Se agregará: " + nombre + " ";

    const oculto = document.createElement("input");

    oculto.type = "hidden";
    oculto.name = nombreInput;
    oculto.value = nombre;

    const quitar = document.createElement("button");

    quitar.type = "button";
    quitar.textContent = "Quitar";

    quitar.onclick = () => fila.remove();

    fila.appendChild(texto);
    fila.appendChild(oculto);
    fila.appendChild(quitar);

    contenedor.appendChild(fila);
    campo.value = "";
}

function agregarExposicion() {
    agregarElemento(
        "nueva_exposicion",
        "[data-exposicion]",
        "nueva_exposicion[]",
        "exposiciones_nuevas"
    );
}

function agregarEstudio() {
    agregarElemento(
        "nuevo_estudio",
        "[data-estudio]",
        "nuevo_estudio[]",
        "estudios_nuevos"
    );
}

</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
