
<?php
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

// =========================================
// FUNCIONES
// =========================================

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        die("Error al consultar los datos.");
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

// =========================================
// CATÁLOGOS
// =========================================

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

// =========================================
// RELACIONES DE TRABAJOS
// =========================================

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

    <title>Agregar paciente - DUCCA</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .campo-busqueda {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin: 5px 0;
        }

        .sugerencias {
            display: none;
            width: 100%;
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

        header,
        main.gestion-contenido {
            width: calc(100% - 24px) !important;
            max-width: none !important;
            margin-left: auto !important;
            margin-right: auto !important;
            box-sizing: border-box !important;
        }

        main.gestion-contenido {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 30px !important;
            padding: 0 !important;
            margin-top: 35px !important;
        }

        main.gestion-contenido > .bienvenida,
        main.gestion-contenido > .tarjeta {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        main.gestion-contenido > .tarjeta {
            padding: 35px !important;
        }

        main.gestion-contenido .form-gestion {
            width: 100% !important;
            max-width: none !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        main.gestion-contenido .form-gestion > p,
        main.gestion-contenido .campo-busqueda,
        main.gestion-contenido .sugerencias,
        main.gestion-contenido .opciones,
        main.gestion-contenido .nuevos {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        main.gestion-contenido input:not([type="checkbox"]),
        main.gestion-contenido select,
        main.gestion-contenido textarea {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }

        @media (max-width: 600px) {
            main.gestion-contenido > .tarjeta {
                padding: 20px !important;
            }
        }

        /* =====================================
           TELÉFONO INTERNACIONAL
           ===================================== */

        .telefono-ducca {
            display: flex;
            align-items: stretch;
            width: 100%;
            gap: 0;
            position: relative;
            margin-top: 5px;
        }

        .telefono-ducca .telefono-pais {
            flex: none;
            width: 94px !important;
            max-width: 94px !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: 1px solid #a5b8c4;
            border-radius: 8px 0 0 8px;
            background: #e8f2f6;
            color: #17475c;
            cursor: pointer;
            padding: 8px 5px;
        }

        .telefono-ducca .telefono-pais strong {
            font-size: 14px;
        }

        .telefono-ducca .telefono-pais small {
            display: block;
            font-size: 10px;
        }

        .telefono-ducca #telefono {
            flex: 1;
            min-width: 0;
            width: 100% !important;
            margin: 0 !important;
            border-radius: 0 8px 8px 0 !important;
        }

        .telefono-ducca .telefono-lista {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 50;
            width: 260px;
            max-height: 235px;
            overflow: auto;
            background: white;
            border: 1px solid #a5b8c4;
            border-radius: 8px;
            box-shadow: 0 6px 18px #0002;
        }

        .telefono-ducca .telefono-lista[hidden] {
            display: none !important;
        }

        .telefono-ducca .telefono-opcion {
            display: block;
            width: 100%;
            border: 0;
            padding: 10px;
            text-align: left;
            background: transparent;
            color: #243744;
            cursor: pointer;
        }

        .telefono-ducca .telefono-opcion:hover {
            background: #dcecf3;
        }

        body.modo-oscuro .telefono-ducca .telefono-pais,
        body.modo-oscuro .telefono-ducca .telefono-lista {
            background: #243c47;
            color: white;
        }

        body.modo-oscuro .telefono-ducca .telefono-opcion {
            color: white;
        }

        body.modo-oscuro .telefono-ducca .telefono-opcion:hover {
            background: #345b69;
        }

        /* =====================================
           DOMICILIO DEL PACIENTE
           ===================================== */

        .domicilio-ducca {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 20px;
            margin: 18px 0 25px;
        }

        .domicilio-ducca .campo-domicilio {
            min-width: 0;
        }

        .domicilio-ducca .campo-domicilio label {
            display: block;
            margin-bottom: 7px;
        }

        .domicilio-ducca .campo-domicilio input {
            width: 100%;
            box-sizing: border-box;
        }

        .domicilio-ducca .campo-ancho {
            grid-column: 1 / -1;
        }

        @media (max-width: 600px) {
            .domicilio-ducca {
                grid-template-columns: 1fr;
            }

            .domicilio-ducca .campo-ancho {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">
        <img
            id="logo"
            src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
            alt="Logo DUCCA"
            height="150"
            width="175"
        >
    </div>

    <nav>
        <a href="../inicio sesion/index.php">Menú principal</a>
        <a href="../Exposiciones/listar.php">Exposiciones</a>
        <a href="../Trabajos/listar.php">Trabajos</a>
        <a href="../Relaciones/listar.php">Relaciones</a>
        <a href="../Estudios/index.php">Estudios</a>
    </nav>

    <button type="button" id="btn-tema">
        Modo oscuro
    </button>
</header>

<main class="gestion-contenido">

    <section class="bienvenida">
        <h1>Agregar paciente</h1>
        <p>
            Registrá un paciente y sus datos correspondientes.
        </p>
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
                <label for="dni_p">DNI</label>

                <input
                    type="text"
                    id="dni_p"
                    name="dni_p"
                    maxlength="10"
                    inputmode="numeric"
                    placeholder="12.345.678"
                    required
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
                >
            </p>

            <!-- CELULAR INTERNACIONAL -->

            <div>
                <label for="telefono">Teléfono celular</label>

                <div class="telefono-ducca">

                    <input
                        type="hidden"
                        id="telefono_codigo"
                        name="telefono_codigo"
                        value="54"
                    >

                    <button
                        type="button"
                        id="telefono_pais"
                        class="telefono-pais"
                        aria-expanded="false"
                        aria-controls="telefono_lista"
                    >
                        <span>
                            <strong id="telefono_prefijo">+54</strong>
                            <small id="telefono_sigla">ARG</small>
                        </span>

                        <span aria-hidden="true">⌄</span>
                    </button>

                    <div
                        id="telefono_lista"
                        class="telefono-lista"
                        hidden
                    ></div>

                    <input
                        type="tel"
                        id="telefono"
                        name="telefono"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        maxlength="20"
                        placeholder="299 412-3456"
                        required
                    >

                </div>

                <p class="ayuda">
                    Para Argentina, el +54 9 se agrega automáticamente.
                    Ingresá solo los 10 dígitos nacionales.
                </p>
            </div>

            <p>
                <label for="mail">Mail</label>

                <input
                    type="email"
                    id="mail"
                    name="mail"
                    maxlength="100"
                    required
                >
            </p>

            <hr>

            <!-- =================================
                 DOMICILIO DEL PACIENTE
                 ================================= -->

            <h2>Domicilio del paciente</h2>

            <p class="ayuda">
                Completá los datos del domicilio.
                El listado de pacientes mostrará solamente la localidad.
            </p>

            <div class="domicilio-ducca">

                <div class="campo-domicilio campo-ancho">
                    <label for="domicilio_calle">
                        Calle
                    </label>

                    <input
                        type="text"
                        id="domicilio_calle"
                        name="domicilio_calle"
                        maxlength="100"
                        placeholder="Ej. Avenida Argentina"
                        required
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_altura">
                        Altura
                    </label>

                    <input
                        type="text"
                        id="domicilio_altura"
                        name="domicilio_altura"
                        maxlength="15"
                        placeholder="Ej. 1234"
                        required
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_piso_depto">
                        Piso / departamento (opcional)
                    </label>

                    <input
                        type="text"
                        id="domicilio_piso_depto"
                        name="domicilio_piso_depto"
                        maxlength="30"
                        placeholder="Ej. 2° B"
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_localidad">
                        Ciudad / localidad
                    </label>

                    <input
                        type="text"
                        id="domicilio_localidad"
                        name="domicilio_localidad"
                        maxlength="100"
                        placeholder="Ej. Neuquén"
                        required
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_provincia">
                        Provincia / región
                    </label>

                    <input
                        type="text"
                        id="domicilio_provincia"
                        name="domicilio_provincia"
                        maxlength="100"
                        placeholder="Ej. Neuquén"
                        required
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_codigo_postal">
                        Código postal
                    </label>

                    <input
                        type="text"
                        id="domicilio_codigo_postal"
                        name="domicilio_codigo_postal"
                        maxlength="15"
                        placeholder="Ej. 8300"
                        required
                    >
                </div>

                <div class="campo-domicilio">
                    <label for="domicilio_pais">
                        País
                    </label>

                    <input
                        type="text"
                        id="domicilio_pais"
                        name="domicilio_pais"
                        maxlength="100"
                        value="Argentina"
                        required
                    >
                </div>

            </div>

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

            <div
                id="sugerencias_obras"
                class="sugerencias"
            ></div>

            <p id="mensaje_obra" class="ayuda"></p>

            <p>
                <a
                    href="../Obra%20Social/listar.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-administrar"
                >
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
                    <option
                        value="<?php
                            echo escapar($trabajo["nombre_t"]);
                        ?>"
                    ></option>
                <?php } ?>
            </datalist>

            <p id="mensaje_trabajo" class="ayuda"></p>

            <p>
                <a
                    href="../Trabajos/listar.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-administrar"
                >
                    Administrar trabajos
                </a>
            </p>

            <hr>

            <!-- EXPOSICIONES -->

            <h2>Exposiciones del paciente</h2>

            <p class="ayuda">
                Buscá y seleccioná las exposiciones existentes.
                Para agregar una nueva, escribí su nombre
                y presioná Enter.
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

            <p
                id="mensaje_exposiciones"
                class="ayuda"
            ></p>

            <div
                id="exposiciones_nuevas"
                class="nuevos"
            ></div>

            <p>
                <a
                    href="../Exposiciones/listar.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-administrar"
                >
                    Administrar exposiciones
                </a>
            </p>

            <hr>

            <!-- ESTUDIOS -->

            <h2>Estudios del paciente</h2>

            <p class="ayuda">
                Buscá y seleccioná los estudios existentes.
                Para agregar uno nuevo, escribí su nombre
                y presioná Enter.
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

            <div
                id="estudios_nuevos"
                class="nuevos"
            ></div>

            <p>
                <a
                    href="../Estudios/estudios.php"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-administrar"
                >
                    Administrar estudios
                </a>
            </p>

            <hr>

            <!-- BOTONES -->

            <div class="acciones-paciente">

                <button
                    type="submit"
                    class="btn-guardar-paciente"
                >
                    Guardar paciente
                </button>

                <a
                    href="listar.php"
                    class="btn-cancelar-paciente"
                >
                    Cancelar
                </a>

            </div>

        </form>

    </section>
</main>

<script>

// ======================================
// DATOS DE LOS CATÁLOGOS
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

function limpiar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ");
}

function normalizar(texto) {
    return limpiar(texto).toLocaleLowerCase("es");
}

// ======================================
// DNI Y CUIL
// ======================================

function soloNumeros(texto, maximo) {
    return String(texto)
        .replace(/\D/g, "")
        .slice(0, maximo);
}

function formatoDNI(texto) {
    return soloNumeros(texto, 8).replace(
        /\B(?=(\d{3})+(?!\d))/g,
        "."
    );
}

function formatoCUIL(texto) {
    const n = soloNumeros(texto, 11);

    if (n.length > 10) {
        return n.slice(0, 2) + "-" +
               n.slice(2, 10) + "-" +
               n.slice(10);
    }

    if (n.length > 2) {
        return n.slice(0, 2) + "-" + n.slice(2);
    }

    return n;
}

function activarFormato(campo, formatear) {
    campo.addEventListener("input", function () {
        const posicion = this.selectionStart ?? this.value.length;

        const cantidadNumeros = this.value
            .slice(0, posicion)
            .replace(/\D/g, "")
            .length;

        const nuevo = formatear(this.value);
        this.value = nuevo;

        let indice = 0;
        let digitos = 0;

        while (
            indice < nuevo.length &&
            digitos < cantidadNumeros
        ) {
            if (/\d/.test(nuevo[indice])) {
                digitos++;
            }

            indice++;
        }

        this.setSelectionRange(indice, indice);
    });
}

const campoDNI = document.getElementById("dni_p");
const campoCUIL = document.getElementById("cuil");

activarFormato(campoDNI, formatoDNI);
activarFormato(campoCUIL, formatoCUIL);

// ======================================
// TELÉFONO INTERNACIONAL
// ======================================

const telefonosPaises = [
    ["54", "ARG", "Argentina"],
    ["56", "CHL", "Chile"],
    ["598", "URY", "Uruguay"],
    ["591", "BOL", "Bolivia"],
    ["55", "BRA", "Brasil"],
    ["595", "PRY", "Paraguay"],
    ["57", "COL", "Colombia"],
    ["51", "PER", "Perú"],
    ["58", "VEN", "Venezuela"],
    ["593", "ECU", "Ecuador"]
];

const telefonoCodigo = document.getElementById(
    "telefono_codigo"
);

const telefonoCampo = document.getElementById(
    "telefono"
);

const telefonoBoton = document.getElementById(
    "telefono_pais"
);

const telefonoLista = document.getElementById(
    "telefono_lista"
);

function telefonoFormateado(valor) {
    const n = String(valor).replace(/\D/g, "");

    if (telefonoCodigo.value !== "54") {
        return n;
    }

    // Buenos Aires: 11 1234-5678

    if (n.startsWith("11")) {
        if (n.length <= 2) return n;

        if (n.length <= 6) {
            return n.slice(0, 2) + " " + n.slice(2);
        }

        return n.slice(0, 2) + " " +
               n.slice(2, 6) + "-" +
               n.slice(6);
    }

    // Neuquén: 299 412-3456

    if (n.startsWith("299")) {
        if (n.length <= 3) return n;

        if (n.length <= 6) {
            return n.slice(0, 3) + " " + n.slice(3);
        }

        return n.slice(0, 3) + " " +
               n.slice(3, 6) + "-" +
               n.slice(6);
    }

    // No inventar agrupaciones para otros códigos.

    return n;
}

function configurarPaisTelefono(codigo) {
    const pais = telefonosPaises.find(
        p => p[0] === codigo
    ) || telefonosPaises[0];

    telefonoCodigo.value = pais[0];

    document.getElementById(
        "telefono_prefijo"
    ).textContent = "+" + pais[0];

    document.getElementById(
        "telefono_sigla"
    ).textContent = pais[1];

    telefonoCampo.placeholder =
        pais[0] === "54"
            ? "299 412-3456"
            : "Número nacional";

    telefonoCampo.value = telefonoFormateado(
        telefonoCampo.value
    );

    telefonoLista.hidden = true;

    telefonoBoton.setAttribute(
        "aria-expanded",
        "false"
    );
}

for (const pais of telefonosPaises) {
    const opcion = document.createElement("button");

    opcion.type = "button";
    opcion.className = "telefono-opcion";

    opcion.textContent =
        pais[2] + " (+" + pais[0] + ")";

    opcion.addEventListener("click", () => {
        telefonoCampo.value = "";

        configurarPaisTelefono(pais[0]);

        telefonoCampo.focus();
    });

    telefonoLista.appendChild(opcion);
}

telefonoBoton.addEventListener("click", () => {
    telefonoLista.hidden = !telefonoLista.hidden;

    telefonoBoton.setAttribute(
        "aria-expanded",
        String(!telefonoLista.hidden)
    );
});

document.addEventListener("click", evento => {
    if (!evento.target.closest(".telefono-ducca")) {
        telefonoLista.hidden = true;

        telefonoBoton.setAttribute(
            "aria-expanded",
            "false"
        );
    }
});

telefonoCampo.addEventListener("input", () => {
    const maximo =
        telefonoCodigo.value === "54" ? 10 : 12;

    const numeros = telefonoCampo.value
        .replace(/\D/g, "")
        .slice(0, maximo);

    telefonoCampo.value = telefonoFormateado(numeros);
});

function validarTelefono() {
    const numeros = telefonoCampo.value.replace(/\D/g, "");

    if (
        telefonoCodigo.value === "54" &&
        numeros.length !== 10
    ) {
        alert(
            "El celular argentino debe contener " +
            "10 dígitos nacionales."
        );

        telefonoCampo.focus();
        return false;
    }

    if (
        telefonoCodigo.value !== "54" &&
        (
            numeros.length < 6 ||
            numeros.length > 12 ||
            telefonoCodigo.value.length +
                numeros.length > 15
        )
    ) {
        alert(
            "Revisá la longitud del número " +
            "del país seleccionado."
        );

        telefonoCampo.focus();
        return false;
    }

    telefonoCampo.value = numeros;

    return true;
}

configurarPaisTelefono("54");

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
        .filter(
            nombre => normalizar(nombre).includes(texto)
        )
        .sort((a, b) => {
            const aInicio =
                normalizar(a).startsWith(texto);

            const bInicio =
                normalizar(b).startsWith(texto);

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

    document.querySelectorAll("[data-exposicion]")
        .forEach(casilla => {
            if (
                exp.includes(
                    Number(casilla.dataset.exposicion)
                )
            ) {
                casilla.checked = true;
            }
        });

    document.querySelectorAll("[data-estudio]")
        .forEach(casilla => {
            if (
                est.includes(
                    Number(casilla.dataset.estudio)
                )
            ) {
                casilla.checked = true;
            }
        });
}

campoTrabajo.addEventListener(
    "input",
    actualizarTrabajo
);

campoTrabajo.addEventListener(
    "change",
    actualizarTrabajo
);

// ======================================
// EXPOSICIONES Y ESTUDIOS
// ======================================

function configurarCatalogo(config) {
    const campo = document.getElementById(
        config.buscador
    );

    const lista = document.getElementById(
        config.lista
    );

    const nuevos = document.getElementById(
        config.nuevos
    );

    const mensaje = document.getElementById(
        config.mensaje
    );

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

            opcion.style.display =
                coincide ? "" : "none";

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
                "Ya existe. Podés marcarlo o presionar Enter.";
        } else if (coincidencias > 0) {
            mensaje.textContent =
                "Hay elementos similares. Presioná Enter " +
                "para agregar uno nuevo.";
        } else {
            mensaje.textContent =
                "No existe todavía. Presioná Enter para agregarlo.";
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
            exacta.querySelector(
                "input[type=checkbox]"
            ).checked = true;

            campo.value = "";
            filtrar();
            return;
        }

        const repetida = Array.from(
            nuevos.querySelectorAll(
                "input[type=checkbox]"
            )
        ).find(
            input =>
                normalizar(input.value) ===
                normalizar(nombre)
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
            document.createTextNode(
                " " + nombre + " (nuevo)"
            )
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

            alert(
                "Seleccioná una obra social o indicá Particular."
            );

            campoObra.focus();
            return;
        }

        const dniNumeros = soloNumeros(
            campoDNI.value,
            8
        );

        const cuilNumeros = soloNumeros(
            campoCUIL.value,
            11
        );

        if (
            !/^[0-9]{1,8}$/.test(dniNumeros) ||
            Number(dniNumeros) <= 0
        ) {
            evento.preventDefault();
            alert("El DNI no es válido.");
            campoDNI.focus();
            return;
        }

        if (
            cuilNumeros !== "" &&
            !/^[0-9]{11}$/.test(cuilNumeros)
        ) {
            evento.preventDefault();
            alert("El CUIL debe tener 11 números.");
            campoCUIL.focus();
            return;
        }

        if (!validarTelefono()) {
            evento.preventDefault();
            return;
        }

        incorporarExposicion();
        incorporarEstudio();

        // Separadores solo visuales.
        campoDNI.value = dniNumeros;
        campoCUIL.value = cuilNumeros;
    });

</script>

<script src="../general/script.js"></script>
<script src="../general/animaciones.js"></script>
<script src="../general/modooscuro.js"></script>

</body>
</html>
