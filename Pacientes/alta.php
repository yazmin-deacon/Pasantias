
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// ======================================
// FUNCIONES
// ======================================

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error en la consulta: " . mysqli_error($conexion));
    }

    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);

    return $filas;
}

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// ======================================
// CATÁLOGOS
// ======================================

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo ORDER BY nombre_t"
);

$obrasSociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os
     FROM obra_social ORDER BY nombre_os"
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

// ======================================
// RELACIONES DE TRABAJOS
// ======================================

$exposicionesTrabajo = [];
$estudiosTrabajo = [];

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_exposicion
     FROM trabajo_exposicion"
);

foreach ($filas as $fila) {
    $exposicionesTrabajo[$fila["id_trabajo"]][] =
        (int)$fila["id_exposicion"];
}

$filas = consultarTodos(
    $conexion,
    "SELECT id_trabajo, id_estudio
     FROM trabajo_estudio"
);

foreach ($filas as $fila) {
    $estudiosTrabajo[$fila["id_trabajo"]][] =
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

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .campo-busqueda {
            width: 100%;
            max-width: 430px;
            box-sizing: border-box;
            padding: 10px;
            margin: 5px 0;
        }

        .sugerencias {
            display: none;
            max-width: 430px;
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: white;
            color: #333;
        }

        .sugerencia {
            display: block;
            width: 100%;
            padding: 9px;
            border: none;
            background: white;
            color: #333;
            text-align: left;
            cursor: pointer;
        }

        .sugerencia:hover {
            background: #f4c6d5;
        }

        .opciones {
            max-height: 230px;
            overflow-y: auto;
            margin: 12px 0;
        }

        .opcion {
            margin: 7px 0;
        }

        .nuevos label {
            display: block;
            margin: 7px 0;
        }

        .ayuda {
            font-size: 14px;
        }
    </style>
</head>

<body>

<header>

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
        <h1>Agregar paciente</h1>
        <p>Registrá un paciente y sus datos correspondientes.</p>
    </section>

    <section class="tarjeta">

        <form
            action="guardar.php"
            method="POST"
            id="formPaciente"
            class="form-gestion"
        >

            <!-- DATOS PERSONALES -->

            <h2>Datos personales</h2>

            <p>
                <label>DNI</label>
                <input
                    type="text"
                    name="dni_p"
                    maxlength="8"
                    inputmode="numeric"
                    pattern="[0-9]{1,8}"
                    required
                >
            </p>

            <p>
                <label>Nombre</label>
                <input
                    type="text"
                    name="nombre_p"
                    maxlength="30"
                    required
                >
            </p>

            <p>
                <label>Apellido</label>
                <input
                    type="text"
                    name="apellido"
                    maxlength="30"
                    required
                >
            </p>

            <p>
                <label>CUIL</label>
                <input
                    type="text"
                    name="cuil"
                    maxlength="11"
                >
            </p>

            <p>
                <label>Teléfono</label>
                <input
                    type="text"
                    name="telefono"
                    maxlength="20"
                    required
                >
            </p>

            <p>
                <label>Mail</label>
                <input
                    type="email"
                    name="mail"
                    maxlength="100"
                    required
                >
            </p>

            <hr>

            <!-- OBRA SOCIAL -->

            <h2>Obra social</h2>

            <p class="ayuda">
                Buscá una obra social existente o escribí una nueva.
            </p>

            <input
                type="text"
                name="obra_social_nombre"
                id="obra_social_nombre"
                class="campo-busqueda"
                maxlength="100"
                autocomplete="off"
                placeholder="Buscar obra social..."
                required
            >

            <div id="sugerencias_obras" class="sugerencias"></div>

            <p id="mensaje_obra" class="ayuda"></p>

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

            <p class="ayuda">
                Buscá un trabajo existente o escribí uno nuevo.
            </p>

            <input
                type="text"
                name="trabajo_nombre"
                id="trabajo_nombre"
                class="campo-busqueda"
                list="lista_trabajos"
                maxlength="50"
                autocomplete="off"
                placeholder="Buscar trabajo..."
            >

            <datalist id="lista_trabajos">

                <?php foreach ($trabajos as $trabajo) { ?>

                    <option value="<?php
                        echo escapar($trabajo["nombre_t"]);
                    ?>"></option>

                <?php } ?>

            </datalist>

            <p id="mensaje_trabajo" class="ayuda"></p>

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

            <p class="ayuda">
                Buscá y seleccioná las exposiciones existentes.
                Para incorporar una nueva, escribí su nombre
                completo y presioná Enter.
            </p>

            <input
                type="text"
                id="buscar_exposiciones"
                class="campo-busqueda"
                maxlength="50"
                autocomplete="off"
                placeholder="Buscar o escribir exposición..."
            >

            <div id="lista_exposiciones" class="opciones">

                <?php foreach ($exposiciones as $exposicion) { ?>

                    <p
                        class="opcion"
                        data-nombre="<?php
                            echo escapar($exposicion["nombre_ex"]);
                        ?>"
                    >
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

            <p id="mensaje_exposiciones" class="ayuda"></p>
            <div id="exposiciones_nuevas" class="nuevos"></div>

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

            <p class="ayuda">
                Buscá y seleccioná los estudios existentes.
                Para incorporar uno nuevo, escribí su nombre
                completo y presioná Enter.
            </p>

            <input
                type="text"
                id="buscar_estudios"
                class="campo-busqueda"
                maxlength="50"
                autocomplete="off"
                placeholder="Buscar o escribir estudio..."
            >

            <div id="lista_estudios" class="opciones">

                <?php foreach ($estudios as $estudio) { ?>

                    <p
                        class="opcion"
                        data-nombre="<?php
                            echo escapar($estudio["nombre_es"]);
                        ?>"
                    >
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

            <p id="mensaje_estudios" class="ayuda"></p>
            <div id="estudios_nuevos" class="nuevos"></div>

            <p>
                <a href="../Estudios/estudios.php"
                   target="_blank"
                   rel="noopener noreferrer">
                    Administrar estudios
                </a>
            </p>

            <hr>

            <!-- GUARDAR -->

            <button type="submit">
                Guardar paciente
            </button>

            <a href="listar.php">
                Cancelar
            </a>

        </form>

    </section>

</main>

<script>

// ======================================
// DATOS
// ======================================

const trabajos = <?php echo json_encode(
    $trabajos,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const obras = <?php echo json_encode(
    $obrasSociales,
    JSON_HEX_TAG | JSON_HEX_APOS |
    JSON_HEX_QUOT | JSON_HEX_AMP
); ?>;

const expTrabajo = <?php echo json_encode(
    $exposicionesTrabajo
); ?>;

const estTrabajo = <?php echo json_encode(
    $estudiosTrabajo
); ?>;

const SIN_OBRA = "Particular / Sin obra social";

function normalizar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

function limpiar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ");
}

// ======================================
// OBRA SOCIAL
// ======================================

const campoObra = document.getElementById(
    "obra_social_nombre"
);

const sugerenciasObras = document.getElementById(
    "sugerencias_obras"
);

const mensajeObra = document.getElementById(
    "mensaje_obra"
);

const nombresObras = [
    SIN_OBRA,
    ...obras.map(o => o.nombre_os)
];

function actualizarObra() {
    const texto = normalizar(campoObra.value);

    if (!texto) {
        mensajeObra.textContent = "";
        return;
    }

    const existente = nombresObras.find(
        nombre => normalizar(nombre) === texto
    );

    mensajeObra.textContent = existente
        ? "Obra social seleccionada: " + existente
        : "Obra social nueva. Se registrará al guardar.";
}

function mostrarObras() {
    const texto = normalizar(campoObra.value);

    sugerenciasObras.replaceChildren();

    const coincidencias = nombresObras
        .filter(nombre => normalizar(nombre).includes(texto))
        .sort((a, b) => {
            const aInicio = normalizar(a).startsWith(texto);
            const bInicio = normalizar(b).startsWith(texto);

            if (aInicio !== bInicio) {
                return aInicio ? -1 : 1;
            }

            return a.localeCompare(b, "es");
        })
        .slice(0, 10);

    for (const nombre of coincidencias) {
        const opcion = document.createElement("button");

        opcion.type = "button";
        opcion.className = "sugerencia";
        opcion.textContent = nombre;

        opcion.addEventListener("click", () => {
            campoObra.value = nombre;
            sugerenciasObras.style.display = "none";
            actualizarObra();
        });

        sugerenciasObras.appendChild(opcion);
    }

    sugerenciasObras.style.display =
        coincidencias.length ? "block" : "none";
}

campoObra.addEventListener("input", () => {
    mostrarObras();
    actualizarObra();
});

campoObra.addEventListener("focus", mostrarObras);

document.addEventListener("click", evento => {
    if (
        evento.target !== campoObra &&
        !sugerenciasObras.contains(evento.target)
    ) {
        sugerenciasObras.style.display = "none";
    }
});

// ======================================
// TRABAJO
// ======================================

const campoTrabajo = document.getElementById(
    "trabajo_nombre"
);

const mensajeTrabajo = document.getElementById(
    "mensaje_trabajo"
);

let trabajoAplicado = "";

function actualizarTrabajo() {
    const nombre = normalizar(campoTrabajo.value);

    const trabajo = trabajos.find(
        t => normalizar(t.nombre_t) === nombre
    );

    mensajeTrabajo.textContent = !nombre
        ? "Sin trabajo seleccionado."
        : trabajo
            ? "Trabajo seleccionado: " + trabajo.nombre_t
            : "Trabajo nuevo. Se registrará al guardar.";

    if (!trabajo || nombre === trabajoAplicado) {
        return;
    }

    trabajoAplicado = nombre;

    const exp = expTrabajo[trabajo.id_trabajo] || [];
    const est = estTrabajo[trabajo.id_trabajo] || [];

    // Sumar sin borrar selecciones manuales.

    document.querySelectorAll("[data-exposicion]")
        .forEach(casilla => {
            if (exp.includes(Number(casilla.dataset.exposicion))) {
                casilla.checked = true;
            }
        });

    document.querySelectorAll("[data-estudio]")
        .forEach(casilla => {
            if (est.includes(Number(casilla.dataset.estudio))) {
                casilla.checked = true;
            }
        });
}

campoTrabajo.addEventListener("input", actualizarTrabajo);
campoTrabajo.addEventListener("change", actualizarTrabajo);

// ======================================
// EXPOSICIONES Y ESTUDIOS
// ======================================

function configurarCatalogo(config) {
    const campo = document.getElementById(config.buscador);
    const lista = document.getElementById(config.lista);
    const nuevos = document.getElementById(config.nuevos);
    const mensaje = document.getElementById(config.mensaje);

    const opciones = Array.from(
        lista.querySelectorAll(".opcion")
    );

    function filtrar() {
        const texto = normalizar(campo.value);
        let coincidencias = 0;

        for (const opcion of opciones) {
            const coincide = normalizar(
                opcion.dataset.nombre
            ).includes(texto);

            opcion.style.display = coincide ? "" : "none";

            if (coincide) {
                coincidencias++;
            }
        }

        const exacta = opciones.find(
            opcion =>
                normalizar(opcion.dataset.nombre) === texto
        );

        if (!texto) {
            mensaje.textContent = "";
        } else if (exacta) {
            mensaje.textContent =
                "Ya existe. Podés marcarlo en la lista o presionar Enter.";
        } else if (coincidencias > 0) {
            mensaje.textContent =
                "Hay elementos similares. Presioná Enter " +
                "para incorporar un nombre nuevo.";
        } else {
            mensaje.textContent =
                "No existe todavía. Presioná Enter para incorporarlo.";
        }
    }

    function incorporar() {
        const nombre = limpiar(campo.value);

        if (!nombre) {
            return;
        }

        const exacta = opciones.find(
            opcion =>
                normalizar(opcion.dataset.nombre) ===
                normalizar(nombre)
        );

        if (exacta) {
            exacta.querySelector("input[type=checkbox]").checked = true;
            campo.value = "";
            filtrar();
            return;
        }

        const repetida = Array.from(
            nuevos.querySelectorAll("input[type=checkbox]")
        ).find(
            input => normalizar(input.value) === normalizar(nombre)
        );

        if (repetida) {
            repetida.checked = true;
            campo.value = "";
            filtrar();
            return;
        }

        const fila = document.createElement("p");
        const etiqueta = document.createElement("label");
        const casilla = document.createElement("input");

        casilla.type = "checkbox";
        casilla.name = config.nombreNuevo;
        casilla.value = nombre;
        casilla.checked = true;

        etiqueta.appendChild(casilla);
        etiqueta.appendChild(
            document.createTextNode(" " + nombre + " (nuevo)")
        );

        fila.appendChild(etiqueta);
        nuevos.appendChild(fila);

        campo.value = "";
        filtrar();
    }

    campo.addEventListener("input", filtrar);

    campo.addEventListener("keydown", evento => {
        if (evento.key === "Enter") {
            evento.preventDefault();
            incorporar();
        }
    });

    return incorporar;
}

const incorporarExposicion = configurarCatalogo({
    buscador: "buscar_exposiciones",
    lista: "lista_exposiciones",
    nuevos: "exposiciones_nuevas",
    mensaje: "mensaje_exposiciones",
    nombreNuevo: "nueva_exposicion[]"
});

const incorporarEstudio = configurarCatalogo({
    buscador: "buscar_estudios",
    lista: "lista_estudios",
    nuevos: "estudios_nuevos",
    mensaje: "mensaje_estudios",
    nombreNuevo: "nuevo_estudio[]"
});

// ======================================
// GUARDADO FINAL
// ======================================

document.getElementById("formPaciente")
    .addEventListener("submit", evento => {

        campoObra.value = limpiar(campoObra.value);
        campoTrabajo.value = limpiar(campoTrabajo.value);

        if (!campoObra.value) {
            evento.preventDefault();
            alert("Seleccioná una obra social o indicá Particular.");
            campoObra.focus();
            return;
        }

        incorporarExposicion();
        incorporarEstudio();
    });

</script>
<script src="../general/script.js"></script>
<script src="../general/animaciones.js"></script>
<script src="../general/modooscuro.js"></script>

</body>
</html>

<?php mysqli_close($conexion); ?>
