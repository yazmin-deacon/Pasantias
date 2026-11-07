
<?php
require_once "conexion.php";

// =========================================
// 1. RECIBIR DNI
// =========================================

$dni = trim(
    $_GET["dni_p"] ?? $_GET["dni"] ?? ""
);

if (!ctype_digit($dni) || strlen($dni) > 8) {
    die("DNI no válido.");
}

// =========================================
// 2. CONSULTAR PACIENTE
// =========================================

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
// 3. CONSULTAR CATÁLOGOS
// =========================================

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error en la consulta: " .
            mysqli_error($conexion));
    }

    return mysqli_fetch_all($resultado, MYSQLI_ASSOC);
}

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo ORDER BY nombre_t"
);

// Obras activas y la que tenga el paciente,
// aunque se encuentre inactiva.
$obras_sociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os, activa
     FROM obra_social
     WHERE activa = 1
        OR id_obra_social = " .
        (int)($paciente["id_obra_social"] ?? 0) .
    " ORDER BY nombre_os"
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
// 4. SELECCIONES ACTUALES DEL PACIENTE
// =========================================

function obtenerIds($conexion, $tabla, $campo, $dni) {

    $sql = "SELECT $campo FROM $tabla
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
// 5. RELACIONES DE LOS TRABAJOS
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
// 6. TRABAJO ACTUAL
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

// Función para mostrar texto de forma segura
function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Editar paciente</title>
</head>
<body>

<h1>Editar paciente</h1>

<form action="actualizar.php" method="POST">

    <input type="hidden" name="dni_p"
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
        <input type="text" name="nombre_p"
               maxlength="30" required
               value="<?php echo escapar($paciente["nombre_p"]); ?>">
    </p>

    <p>
        Apellido:
        <input type="text" name="apellido"
               maxlength="30" required
               value="<?php echo escapar($paciente["apellido"]); ?>">
    </p>

    <p>
        CUIL:
        <input type="text" name="cuil"
               maxlength="11"
               value="<?php echo escapar($paciente["cuil"]); ?>">
    </p>

    <p>
        Teléfono:
        <input type="text" name="telefono"
               maxlength="20" required
               value="<?php echo escapar($paciente["telefono"]); ?>">
    </p>

    <p>
        Mail:
        <input type="email" name="mail"
               maxlength="100" required
               value="<?php echo escapar($paciente["mail"]); ?>">
    </p>

    <!-- OBRA SOCIAL -->

    <p>
        Obra social:

        <select name="id_obra_social" required>

            <option value="" disabled>
                Seleccionar obra social
            </option>

            <option value="particular"
                <?php echo $paciente["id_obra_social"] === null
                    ? "selected" : ""; ?>>
                Particular / Sin obra social
            </option>

            <?php foreach ($obras_sociales as $obra) { ?>

                <option
                    value="<?php echo (int)$obra["id_obra_social"]; ?>"
                    <?php
                    echo (int)$obra["id_obra_social"] ===
                         (int)$paciente["id_obra_social"]
                         && $paciente["id_obra_social"] !== null
                         ? "selected" : "";
                    ?>
                >
                    <?php echo escapar($obra["nombre_os"]); ?>
                    <?php
                    if (!(int)$obra["activa"]) {
                        echo " (Inactiva)";
                    }
                    ?>
                </option>

            <?php } ?>
        </select>
    </p>

    <hr>

    <!-- TRABAJO -->

    <h2>Trabajo</h2>

    <p>Buscar o escribir trabajo:</p>

    <input type="text"
           name="nombre_trabajo"
           id="nombre_trabajo"
           list="trabajos_disponibles"
           maxlength="50"
           autocomplete="off"
           value="<?php echo escapar($nombre_trabajo_actual); ?>"
           placeholder="Escribí para buscar un trabajo">

    <datalist id="trabajos_disponibles">
        <?php foreach ($trabajos as $trabajo) { ?>
            <option value="<?php echo escapar($trabajo["nombre_t"]); ?>">
        <?php } ?>
    </datalist>

    <hr>

    <!-- EXPOSICIONES -->

    <h2>Exposiciones del paciente</h2>

    <p>Podés buscar, agregar o quitar exposiciones.</p>

    <p>
        <input type="text" id="buscar_exposiciones"
               placeholder="Buscar exposición...">
    </p>

    <div id="lista_exposiciones">

        <?php foreach ($exposiciones as $exposicion) { ?>

            <p class="opcion-exposicion">
                <label>
                    <input
                        type="checkbox"
                        name="exposiciones[]"
                        value="<?php echo (int)$exposicion["id_exposicion"]; ?>"
                        data-exposicion="<?php echo (int)$exposicion["id_exposicion"]; ?>"
                        <?php
                        echo in_array(
                            (int)$exposicion["id_exposicion"],
                            $exposiciones_paciente,
                            true
                        ) ? "checked" : "";
                        ?>
                    >
                    <?php echo escapar($exposicion["nombre_ex"]); ?>
                </label>
            </p>

        <?php } ?>
    </div>

    <p>
        <input type="text" id="nueva_exposicion"
               maxlength="50"
               placeholder="Nueva exposición">

        <button type="button"
                onclick="agregarExposicion()">
            Agregar exposición
        </button>
    </p>

    <div id="exposiciones_nuevas"></div>

    <hr>

    <!-- ESTUDIOS -->

    <h2>Estudios del paciente</h2>

    <p>Podés buscar, agregar o quitar estudios.</p>

    <p>
        <input type="text" id="buscar_estudios"
               placeholder="Buscar estudio...">
    </p>

    <div id="lista_estudios">

        <?php foreach ($estudios as $estudio) { ?>

            <p class="opcion-estudio">
                <label>
                    <input
                        type="checkbox"
                        name="estudios[]"
                        value="<?php echo (int)$estudio["id_estudio"]; ?>"
                        data-estudio="<?php echo (int)$estudio["id_estudio"]; ?>"
                        <?php
                        echo in_array(
                            (int)$estudio["id_estudio"],
                            $estudios_paciente,
                            true
                        ) ? "checked" : "";
                        ?>
                    >
                    <?php echo escapar($estudio["nombre_es"]); ?>
                </label>
            </p>

        <?php } ?>
    </div>

    <p>
        <input type="text" id="nuevo_estudio"
               maxlength="50"
               placeholder="Nuevo estudio">

        <button type="button"
                onclick="agregarEstudio()">
            Agregar estudio
        </button>
    </p>

    <div id="estudios_nuevos"></div>

    <br><br>

    <input type="submit" value="Guardar cambios">

</form>

<p>
    <a href="listar.php">Volver al listado</a>
</p>

<!-- JAVASCRIPT -->

<script>

// Datos de PHP
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

const nombreTrabajoOriginal =
    <?php echo json_encode($nombre_trabajo_actual); ?>;

const selectorTrabajo =
    document.getElementById("nombre_trabajo");

function normalizar(texto) {
    return texto.trim().toLocaleLowerCase("es");
}

// =========================================
// SELECCIÓN AUTOMÁTICA POR TRABAJO
// =========================================

function actualizarSelecciones() {

    const nombre = normalizar(selectorTrabajo.value);

    const trabajo = trabajos.find(t =>
        normalizar(t.nombre_t) === nombre
    );

    let exp = [];
    let est = [];

    if (nombre === normalizar(nombreTrabajoOriginal)) {

        exp = exposicionesOriginales;
        est = estudiosOriginales;

    } else if (trabajo) {

        exp = exposicionesPorTrabajo[trabajo.id_trabajo] || [];
        est = estudiosPorTrabajo[trabajo.id_trabajo] || [];
    }

    document.querySelectorAll(
        "[data-exposicion]"
    ).forEach(casilla => {
        casilla.checked = exp.includes(
            Number(casilla.dataset.exposicion)
        );
    });

    document.querySelectorAll(
        "[data-estudio]"
    ).forEach(casilla => {
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
// BUSCADORES
// =========================================

function configurarBuscador(id, clase) {

    document.getElementById(id)
        .addEventListener("input", function () {

            const busqueda = normalizar(this.value);

            document.querySelectorAll(clase)
                .forEach(elemento => {

                    elemento.style.display =
                        normalizar(elemento.textContent)
                            .includes(busqueda)
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
// AGREGAR NUEVOS ELEMENTOS
// =========================================

function agregarElemento(
    campoId,
    selectorExistente,
    nombreInput,
    contenedorId,
    tipo
) {

    const campo = document.getElementById(campoId);
    const nombre = campo.value.trim();

    if (!nombre) {
        alert("Escribí el nombre del elemento.");
        return;
    }

    // Buscar un elemento que ya existe
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
        document.getElementById(contenedorId);

    // Evitar duplicados
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

    quitar.addEventListener("click", () => {
        fila.remove();
    });

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
        "exposiciones_nuevas",
        "exposición"
    );
}

function agregarEstudio() {
    agregarElemento(
        "nuevo_estudio",
        "[data-estudio]",
        "nuevo_estudio[]",
        "estudios_nuevos",
        "estudio"
    );
}

</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
