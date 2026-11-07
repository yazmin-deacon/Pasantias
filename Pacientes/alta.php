
<?php
require_once "conexion.php";

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

// CATÁLOGOS

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo
     ORDER BY nombre_t"
);

$obras_sociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os
     FROM obra_social
     WHERE activa = 1
     ORDER BY nombre_os"
);

$exposiciones = consultarTodos(
    $conexion,
    "SELECT id_exposicion, nombre_ex
     FROM exposicion
     ORDER BY nombre_ex"
);

$estudios = consultarTodos(
    $conexion,
    "SELECT id_estudio, nombre_es
     FROM estudio
     ORDER BY nombre_es"
);

// RELACIONES POR TRABAJO

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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Agregar paciente</title>

    <!-- Conservá acá el enlace a tu CSS actual -->
</head>
<body>

<h1>Agregar paciente</h1>

<p>
    <a href="listar.php">Volver al listado</a>
</p>

<form action="guardar.php" method="POST" id="formPaciente">

    <h2>Datos personales</h2>

    <p>
        DNI:
        <input type="text"
               name="dni_p"
               maxlength="8"
               inputmode="numeric"
               required>
    </p>

    <p>
        Nombre:
        <input type="text"
               name="nombre_p"
               maxlength="30"
               required>
    </p>

    <p>
        Apellido:
        <input type="text"
               name="apellido"
               maxlength="30"
               required>
    </p>

    <p>
        CUIL:
        <input type="text"
               name="cuil"
               maxlength="11">
    </p>

    <p>
        Teléfono:
        <input type="text"
               name="telefono"
               maxlength="20"
               required>
    </p>

    <p>
        Mail:
        <input type="email"
               name="mail"
               maxlength="100"
               required>
    </p>

    <hr>

    <!-- OBRA SOCIAL -->

    <h2>Obra social</h2>

    <p>
        Buscá una obra social o escribí una nueva.
    </p>

    <div style="position:relative; max-width:420px;">

        <input
            type="text"
            name="obra_social_nombre"
            id="obra_social_nombre"
            placeholder="Ej: OSDE, ISSN, PAMI..."
            maxlength="100"
            autocomplete="off"
            style="width:100%; box-sizing:border-box;"
            required
        >

        <div id="sugerencias_obras"
             style="display:none;
                    border:1px solid #ccc;
                    background:white;
                    max-height:180px;
                    overflow-y:auto;">
        </div>

    </div>

    <p id="mensaje_obra_social"></p>

    <p>
        Si no existe, se registrará automáticamente
        cuando guardes al paciente.
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
            name="trabajo_nombre"
            id="trabajo_nombre"
            list="lista_trabajos"
            maxlength="50"
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

    <p>
        <input
            type="text"
            id="buscar_exposiciones"
            placeholder="Buscar exposición..."
        >
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
                    >
                    <?php
                        echo escapar($exposicion["nombre_ex"]);
                    ?>
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

    <p>
        <input
            type="text"
            id="buscar_estudios"
            placeholder="Buscar estudio..."
        >
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
                    >
                    <?php
                        echo escapar($estudio["nombre_es"]);
                    ?>
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

    <br>

    <input type="submit" value="Guardar paciente">

</form>

<script>

// =========================================
// DATOS DESDE PHP
// =========================================

const trabajos = <?php echo json_encode(
    $trabajos,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const obrasSociales = <?php echo json_encode(
    $obras_sociales,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const exposicionesPorTrabajo =
    <?php echo json_encode($exposiciones_trabajo); ?>;

const estudiosPorTrabajo =
    <?php echo json_encode($estudios_trabajo); ?>;

const SIN_OBRA_SOCIAL = "Particular / Sin obra social";

// =========================================
// NORMALIZAR TEXTO
// =========================================

function normalizar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

// =========================================
// BUSCADOR DE OBRA SOCIAL
// =========================================

const campoObra =
    document.getElementById("obra_social_nombre");

const sugerenciasObras =
    document.getElementById("sugerencias_obras");

const mensajeObra =
    document.getElementById("mensaje_obra_social");

const opcionesObras = [
    SIN_OBRA_SOCIAL,
    ...obrasSociales.map(obra => obra.nombre_os)
].sort((a, b) => a.localeCompare(b, "es"));

function seleccionarObraSocial(nombre) {

    campoObra.value = nombre;
    sugerenciasObras.style.display = "none";

    actualizarMensajeObra();
}

function actualizarMensajeObra() {

    const nombre = normalizar(campoObra.value);

    if (!nombre) {
        mensajeObra.textContent = "";
        return;
    }

    const existente = opcionesObras.find(
        opcion => normalizar(opcion) === nombre
    );

    if (existente) {

        mensajeObra.textContent =
            "Obra social seleccionada: " + existente;

    } else {

        mensajeObra.textContent =
            "Obra social nueva. Se registrará " +
            "al guardar el paciente.";
    }
}

function mostrarSugerenciasObras() {

    const busqueda = normalizar(campoObra.value);

    sugerenciasObras.innerHTML = "";

    const coincidencias = opcionesObras
        .filter(nombre =>
            normalizar(nombre).includes(busqueda)
        )
        .sort((a, b) => {

            const empiezaA =
                normalizar(a).startsWith(busqueda);

            const empiezaB =
                normalizar(b).startsWith(busqueda);

            if (empiezaA !== empiezaB) {
                return empiezaA ? -1 : 1;
            }

            return a.localeCompare(b, "es");
        })
        .slice(0, 10);

    if (coincidencias.length === 0) {
        sugerenciasObras.style.display = "none";
        return;
    }

    coincidencias.forEach(nombre => {

        const boton = document.createElement("button");

        boton.type = "button";
        boton.textContent = nombre;

        boton.style.display = "block";
        boton.style.width = "100%";
        boton.style.textAlign = "left";
        boton.style.padding = "8px";
        boton.style.border = "0";
        boton.style.background = "white";
        boton.style.cursor = "pointer";

        boton.addEventListener("click", () => {
            seleccionarObraSocial(nombre);
        });

        sugerenciasObras.appendChild(boton);
    });

    sugerenciasObras.style.display = "block";
}

campoObra.addEventListener("input", function () {
    mostrarSugerenciasObras();
    actualizarMensajeObra();
});

campoObra.addEventListener(
    "focus",
    mostrarSugerenciasObras
);

document.addEventListener("click", function (evento) {

    if (
        evento.target !== campoObra &&
        !sugerenciasObras.contains(evento.target)
    ) {
        sugerenciasObras.style.display = "none";
    }
});

// =========================================
// TRABAJO
// =========================================

const campoTrabajo =
    document.getElementById("trabajo_nombre");

function actualizarPorTrabajo() {

    const nombre = normalizar(campoTrabajo.value);

    const trabajo = trabajos.find(
        t => normalizar(t.nombre_t) === nombre
    );

    const exp = trabajo
        ? exposicionesPorTrabajo[trabajo.id_trabajo] || []
        : [];

    const est = trabajo
        ? estudiosPorTrabajo[trabajo.id_trabajo] || []
        : [];

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

campoTrabajo.addEventListener(
    "change",
    actualizarPorTrabajo
);

function agregarTrabajo() {

    const nombre = campoTrabajo.value.trim();

    const mensaje =
        document.getElementById("mensaje_trabajo");

    if (!nombre) {
        alert("Escribí o seleccioná un trabajo.");
        return;
    }

    const existente = trabajos.find(
        t => normalizar(t.nombre_t) === normalizar(nombre)
    );

    mensaje.textContent = existente
        ? "Trabajo seleccionado: " + existente.nombre_t
        : "Trabajo nuevo: " + nombre +
          ". Se guardará al registrar el paciente.";

    actualizarPorTrabajo();
}

// =========================================
// BUSCADORES DE EXPOSICIONES Y ESTUDIOS
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
// AGREGAR EXPOSICIONES Y ESTUDIOS
// =========================================

function agregarElemento(
    campoId,
    selectorExistente,
    nombreInput,
    contenedorId
) {

    const campo = document.getElementById(campoId);
    const nombre = campo.value.trim();

    if (!nombre) {
        alert("Escribí un nombre.");
        return;
    }

    const existente = Array.from(
        document.querySelectorAll(selectorExistente)
    ).find(casilla =>
        normalizar(casilla.parentElement.textContent)
        === normalizar(nombre)
    );

    if (existente) {
        existente.checked = true;
        campo.value = "";
        return;
    }

    const contenedor =
        document.getElementById(contenedorId);

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
    const etiqueta = document.createElement("label");

    const casilla = document.createElement("input");
    casilla.type = "checkbox";
    casilla.name = nombreInput;
    casilla.value = nombre;
    casilla.checked = true;

    etiqueta.appendChild(casilla);
    etiqueta.appendChild(
        document.createTextNode(" " + nombre + " (nuevo) ")
    );

    const quitar = document.createElement("button");
    quitar.type = "button";
    quitar.textContent = "Quitar";

    quitar.onclick = () => fila.remove();

    fila.appendChild(etiqueta);
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
