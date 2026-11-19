
<?php

// =========================================
// DUCCA - EDITAR PACIENTE
// =========================================

require_once __DIR__ . "/../inicio sesion/verificar_sesion.php";
require_once __DIR__ . "/conexion.php";

/** @var mysqli $conexion */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

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

function consultarTodos($conexion, $sql) {
    $resultado = mysqli_query($conexion, $sql);
    $filas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
    mysqli_free_result($resultado);
    return $filas;
}

function mostrarError($mensaje, $codigo = 400) {
    http_response_code($codigo);

    echo '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Editar paciente - DUCCA</title>
        <link rel="stylesheet" href="../general/estilos.css">
    </head>
    <body>
        <main class="gestion-contenido">
            <section class="tarjeta">
                <h2>No se puede editar este paciente</h2>
                <p>' . escapar($mensaje) . '</p>
                <a href="listar.php">Volver al listado</a>
            </section>
        </main>
    </body>
    </html>';

    exit;
}

// =========================================
// VALIDAR DNI
// =========================================

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
    mostrarError("DNI no válido.");
}

$dniNumero = (int)$dni;

// =========================================
// BUSCAR PACIENTE
// =========================================

$stmt = mysqli_prepare(
    $conexion,
    "SELECT *
     FROM paciente
     WHERE dni_p = ?"
);

mysqli_stmt_bind_param($stmt, "i", $dniNumero);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$paciente) {
    mostrarError(
        "No se encontró el paciente.",
        404
    );
}

// =========================================
// BLOQUEAR PACIENTES ARCHIVADOS
// =========================================

if ($paciente["estado"] !== "activo") {
    mostrarError(
        "Este paciente está archivado. " .
        "Para modificarlo, primero debés reactivarlo.",
        403
    );
}

// =========================================
// OBRAS SOCIALES Y TRABAJOS
// =========================================

$obrasSociales = consultarTodos(
    $conexion,
    "SELECT id_obra_social, nombre_os
     FROM obra_social
     ORDER BY nombre_os"
);

$trabajos = consultarTodos(
    $conexion,
    "SELECT id_trabajo, nombre_t
     FROM trabajo
     ORDER BY nombre_t"
);

// =========================================
// DATOS ACTUALES
// =========================================

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

// =========================================
// TOKEN CSRF
// =========================================

// ESTE ES EL CAMPO QUE ESTABA FALLANDO.

if (empty($_SESSION["csrf_editar_paciente"])) {
    $_SESSION["csrf_editar_paciente"] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_editar_paciente"];

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

        /* TELÉFONO INTERNACIONAL */

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

        /* DOMICILIO */

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
            display: block;
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

    <a
        class="flecha-volver"
        href="listar.php"
        aria-label="Volver a pacientes"
        title="Volver a pacientes"
    >
        <img
            src="../general/flechita.png"
            alt="Flecha volver"
            width="30"
            height="30"
        >
    </a>

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
        <a href="../inicio%20sesion/index.php">
            Menú principal
        </a>

        <a href="../Exposiciones/listar.php">
            Exposiciones
        </a>

        <a href="../Trabajos/listar.php">
            Trabajos
        </a>

        <a href="../Relaciones/listar.php">
            Relaciones
        </a>

        <a href="../Estudios/estudios.php">
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
            el domicilio, la obra social
            y el trabajo del paciente.
        </p>

    </section>

    <section class="tarjeta">

        <form
            action="actualizar.php"
            method="POST"
            id="formPaciente"
            class="form-gestion"
        >

            <!-- DNI DEL PACIENTE -->

            <input
                type="hidden"
                name="dni_p"
                value="<?php echo escapar($paciente["dni_p"]); ?>"
            >

            <!-- TOKEN CSRF CORREGIDO -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo escapar($csrfToken); ?>"
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
                    value="<?php echo escapar($paciente["nombre_p"]); ?>"
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
                    value="<?php echo escapar($paciente["apellido"]); ?>"
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
                    value="<?php echo escapar($paciente["cuil"]); ?>"
                >
            </p>

            <!-- TELÉFONO CELULAR -->

            <div>

                <label for="telefono">
                    Teléfono celular
                </label>

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
                            <strong id="telefono_prefijo">
                                +54
                            </strong>

                            <small id="telefono_sigla">
                                ARG
                            </small>
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

                <p class="ayuda" id="telefono_ayuda">
                    Para Argentina se agrega el +54 9 automáticamente.
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
                    value="<?php echo escapar($paciente["mail"]); ?>"
                >
            </p>

            <hr>

            <!-- DOMICILIO -->

            <h2>Domicilio del paciente</h2>

            <p class="ayuda">
                Completá o modificá la dirección del paciente.
                En el listado se mostrará únicamente la localidad.
                Los pacientes antiguos pueden tener campos pendientes.
            </p>

            <div class="domicilio-ducca">

                <?php

                $camposDomicilio = [
                    [
                        "id" => "domicilio_calle",
                        "titulo" => "Calle",
                        "maximo" => 100,
                        "ancho" => true
                    ],
                    [
                        "id" => "domicilio_altura",
                        "titulo" => "Altura",
                        "maximo" => 15
                    ],
                    [
                        "id" => "domicilio_piso_depto",
                        "titulo" => "Piso / departamento (opcional)",
                        "maximo" => 30
                    ],
                    [
                        "id" => "domicilio_localidad",
                        "titulo" => "Ciudad / localidad",
                        "maximo" => 100
                    ],
                    [
                        "id" => "domicilio_provincia",
                        "titulo" => "Provincia / región",
                        "maximo" => 100
                    ],
                    [
                        "id" => "domicilio_codigo_postal",
                        "titulo" => "Código postal",
                        "maximo" => 15
                    ],
                    [
                        "id" => "domicilio_pais",
                        "titulo" => "País",
                        "maximo" => 100
                    ]
                ];

                ?>

                <?php foreach ($camposDomicilio as $campo) { ?>

                    <div class="campo-domicilio <?php
                        echo !empty($campo["ancho"])
                            ? "campo-ancho"
                            : "";
                    ?>">

                        <label for="<?php
                            echo escapar($campo["id"]);
                        ?>">
                            <?php echo escapar($campo["titulo"]); ?>
                        </label>

                        <input
                            type="text"
                            id="<?php echo escapar($campo["id"]); ?>"
                            name="<?php echo escapar($campo["id"]); ?>"
                            maxlength="<?php echo (int)$campo["maximo"]; ?>"
                            value="<?php
                                echo escapar(
                                    $paciente[$campo["id"]] ?? ""
                                );
                            ?>"
                        >

                    </div>

                <?php } ?>

            </div>

            <hr>

            <!-- OBRA SOCIAL -->

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
                value="<?php echo escapar($nombreObraActual); ?>"
                placeholder="Buscar obra social..."
            >

            <datalist id="lista_obras_sociales">

                <option value="Particular / Sin obra social"></option>

                <?php foreach ($obrasSociales as $obra) { ?>

                    <option
                        value="<?php echo escapar($obra["nombre_os"]); ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p id="mensaje_obra" class="ayuda"></p>

            <p>
                <button
                    type="button"
                    class="btn-administrar"
                    onclick="window.location.href='../Obra%20Social/listar.php'"
                >
                    Administrar obras sociales
                </button>
            </p>

            <hr>

            <!-- TRABAJO -->

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
                value="<?php echo escapar($nombreTrabajoActual); ?>"
                placeholder="Buscar trabajo..."
            >

            <datalist id="lista_trabajos">

                <?php foreach ($trabajos as $trabajo) { ?>

                    <option
                        value="<?php echo escapar($trabajo["nombre_t"]); ?>"
                    ></option>

                <?php } ?>

            </datalist>

            <p id="mensaje_trabajo" class="ayuda"></p>

            <p>
                <a
                    href="../Trabajos/listar.php"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Administrar trabajos
                </a>
            </p>

            <!-- GUARDAR Y CANCELAR -->

            <div class="acciones-finales">

                <button type="submit">
                    Guardar cambios
                </button>

                <button
                    type="button"
                    class="btn-cancelar-paciente"
                    onclick="window.location.href='listar.php'"
                >
                    Cancelar
                </button>

            </div>

        </form>

    </section>

</main>

<script>

// =========================================
// DATOS DE CATÁLOGOS
// =========================================

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

// =========================================
// CUIL CON GUIONES
// =========================================

function formatoCUIL(texto) {

    const n = String(texto)
        .replace(/\D/g, "")
        .slice(0, 11);

    if (n.length > 10) {
        return n.slice(0, 2) + "-" +
               n.slice(2, 10) + "-" +
               n.slice(10);
    }

    if (n.length > 2) {
        return n.slice(0, 2) + "-" +
               n.slice(2);
    }

    return n;
}

const campoCUIL = document.getElementById("cuil");

campoCUIL.value = formatoCUIL(campoCUIL.value);

campoCUIL.addEventListener("input", function () {

    const posicion =
        this.selectionStart ?? this.value.length;

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

// =========================================
// TELÉFONO INTERNACIONAL
// =========================================

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

const telefonoCodigo =
    document.getElementById("telefono_codigo");

const telefonoCampo =
    document.getElementById("telefono");

const telefonoBoton =
    document.getElementById("telefono_pais");

const telefonoLista =
    document.getElementById("telefono_lista");

const telefonoAyuda =
    document.getElementById("telefono_ayuda");

function telefonoFormateado(valor) {

    const n = String(valor).replace(/\D/g, "");

    if (telefonoCodigo.value !== "54") {
        return n;
    }

    if (n.startsWith("11")) {
        if (n.length <= 2) return n;

        if (n.length <= 6) {
            return n.slice(0, 2) + " " + n.slice(2);
        }

        return n.slice(0, 2) + " " +
               n.slice(2, 6) + "-" +
               n.slice(6);
    }

    if (n.startsWith("299")) {
        if (n.length <= 3) return n;

        if (n.length <= 6) {
            return n.slice(0, 3) + " " + n.slice(3);
        }

        return n.slice(0, 3) + " " +
               n.slice(3, 6) + "-" +
               n.slice(6);
    }

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

        telefonoAyuda.textContent =
            "Verificá el número antes de guardar.";

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

    telefonoCampo.value = telefonoFormateado(
        numeros
    );
});

// =========================================
// RECUPERAR TELÉFONO ACTUAL
// =========================================

const telefonoGuardado = <?php echo json_encode(
    (string)($paciente["telefono"] ?? ""),
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
); ?>;

function cargarTelefonoAnterior() {

    const limpio = telefonoGuardado.replace(
        /[^\d+]/g,
        ""
    );

    let codigo = "54";
    let numero = limpio.replace(/\D/g, "");
    let necesitaRevision = false;

    if (limpio.startsWith("+")) {

        const pais = telefonosPaises
            .slice()
            .sort(
                (a, b) =>
                    b[0].length - a[0].length
            )
            .find(
                p => limpio.startsWith("+" + p[0])
            );

        if (pais) {

            codigo = pais[0];

            numero = limpio
                .slice(1 + codigo.length)
                .replace(/\D/g, "");

            if (codigo === "54") {

                if (
                    numero.length === 11 &&
                    numero.startsWith("9")
                ) {
                    numero = numero.slice(1);
                } else {
                    necesitaRevision = true;
                }
            }

        } else {
            necesitaRevision = true;
        }

    } else if (
        numero.startsWith("549") &&
        numero.length === 13
    ) {

        codigo = "54";
        numero = numero.slice(3);

    } else if (
        numero.startsWith("54") &&
        numero.length === 12
    ) {

        codigo = "54";
        numero = numero.slice(2);
        necesitaRevision = true;

    } else {
        necesitaRevision = true;
    }

    configurarPaisTelefono(codigo);

    telefonoCampo.value = telefonoFormateado(
        numero
    );

    telefonoAyuda.textContent = necesitaRevision
        ? "Este teléfono necesita revisión. " +
          "Confirmá que sea un celular y verificá " +
          "el prefijo antes de guardar."
        : "Verificá el número antes de guardar.";
}

cargarTelefonoAnterior();

// =========================================
// VALIDAR TELÉFONO
// =========================================

function validarTelefono() {

    const numeros = telefonoCampo.value.replace(
        /\D/g,
        ""
    );

    if (
        telefonoCodigo.value === "54" &&
        numeros.length !== 10
    ) {

        alert(
            "El celular argentino debe tener " +
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

        alert("Revisá la longitud del teléfono.");

        telefonoCampo.focus();
        return false;
    }

    telefonoCampo.value = numeros;

    return true;
}

// =========================================
// OBRA SOCIAL
// =========================================

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

// =========================================
// TRABAJO
// =========================================

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

// =========================================
// GUARDADO FINAL
// =========================================

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

        if (!validarTelefono()) {

            evento.preventDefault();
            return;
        }

        campoCUIL.value = cuilNumeros;
    });

</script>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>
