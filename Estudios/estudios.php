
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =====================================
// SESIÓN Y SEGURIDAD
// =====================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["token_estudios"])) {
    $_SESSION["token_estudios"] = bin2hex(
        random_bytes(32)
    );
}

// =====================================
// FUNCIONES
// =====================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function limpiarNombre($nombre) {
    return preg_replace(
        '/\s+/u',
        ' ',
        trim((string)$nombre)
    );
}

function normalizarNombre($nombre) {
    return mb_strtolower(
        limpiarNombre($nombre),
        "UTF-8"
    );
}

function redirigir($mensaje) {
    header(
        "Location: estudios.php?mensaje=" .
        urlencode($mensaje)
    );
    exit;
}

// =====================================
// REGISTRAR ESTUDIO DESDE EL BUSCADOR
// =====================================

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["registrar_estudio"])
) {

    $token = $_POST["token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals(
            $_SESSION["token_estudios"],
            $token
        )
    ) {
        http_response_code(403);
        die("Solicitud no válida.");
    }

    $nombre = limpiarNombre(
        $_POST["nombre_es"] ?? ""
    );

    if ($nombre === "") {
        redirigir("Escribí el nombre del estudio.");
    }

    if (mb_strlen($nombre, "UTF-8") > 50) {
        redirigir(
            "El nombre no puede superar 50 caracteres."
        );
    }

    // Verificar duplicados

    $resultado = mysqli_query(
        $conexion,
        "SELECT nombre_es FROM estudio"
    );

    if (!$resultado) {
        die("Error al verificar los estudios.");
    }

    $duplicado = false;

    while ($fila = mysqli_fetch_assoc($resultado)) {
        if (
            normalizarNombre($fila["nombre_es"]) ===
            normalizarNombre($nombre)
        ) {
            $duplicado = true;
            break;
        }
    }

    mysqli_free_result($resultado);

    if ($duplicado) {
        redirigir("Ese estudio ya está registrado.");
    }

    // Registrar

    $stmt = mysqli_prepare(
        $conexion,
        "INSERT INTO estudio (nombre_es) VALUES (?)"
    );

    if (!$stmt) {
        die("No se pudo preparar el registro.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $nombre
    );

    if (!mysqli_stmt_execute($stmt)) {
        $codigo = mysqli_stmt_errno($stmt);
        error_log(mysqli_stmt_error($stmt));

        mysqli_stmt_close($stmt);

        if ($codigo == 1062) {
            redirigir("Ese estudio ya existe.");
        }

        redirigir("No se pudo registrar el estudio.");
    }

    mysqli_stmt_close($stmt);

    redirigir(
        "Estudio registrado correctamente: " .
        $nombre
    );
}

// =====================================
// CATÁLOGO DE ESTUDIOS
// =====================================

$sql = "SELECT id_estudio, nombre_es
        FROM estudio
        ORDER BY nombre_es ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los estudios.");
}

$catalogo = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

// =====================================
// ESTUDIOS ASIGNADOS A CADA PACIENTE
// =====================================

// Cada estudio tiene su propio estado.

$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t,
            e.id_estudio,
            e.nombre_es,
            pe.estado

        FROM paciente p

        INNER JOIN paciente_estudio pe
            ON p.dni_p = pe.dni_paciente

        INNER JOIN estudio e
            ON pe.id_estudio = e.id_estudio

        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo

        ORDER BY
            p.apellido ASC,
            p.nombre_p ASC,
            p.dni_p ASC,
            e.nombre_es ASC,
            e.id_estudio ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die(
        "Error al consultar estudios asignados: " .
        mysqli_error($conexion)
    );
}

// Agrupar estudios por paciente.

$pacientes = [];

while ($fila = mysqli_fetch_assoc($resultado)) {

    $dni = (string)$fila["dni_p"];

    if (!isset($pacientes[$dni])) {

        $pacientes[$dni] = [
            "dni_p" => $fila["dni_p"],
            "nombre_p" => $fila["nombre_p"],
            "apellido" => $fila["apellido"],
            "nombre_t" => $fila["nombre_t"],
            "estudios" => []
        ];
    }

    $pacientes[$dni]["estudios"][] = [
        "id_estudio" => $fila["id_estudio"],
        "nombre_es" => $fila["nombre_es"],
        "estado" => $fila["estado"]
    ];
}

mysqli_free_result($resultado);

$mensaje = "";

if (
    isset($_GET["mensaje"]) &&
    is_string($_GET["mensaje"])
) {
    $mensaje = $_GET["mensaje"];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Administrar estudios - Clínica</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>
        .buscador-estudio {
            width: 100%;
            max-width: 450px;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
        }

        .mensaje-busqueda {
            margin-top: 12px;
            font-size: 14px;
        }

        .lista-tabla {
            margin: 0;
            padding-left: 20px;
            text-align: left;
        }

        .lista-tabla li {
            margin-bottom: 12px;
        }

        .lista-tabla li:last-child {
            margin-bottom: 0;
        }

        .lista-estados {
            list-style: none;
            padding-left: 0;
        }

        .lista-estados li {
            margin-bottom: 8px;
        }

        .btn-estado {
            padding: 8px 12px;
            border: 0;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-pendiente {
            background-color: #ba8427;
        }

        .btn-pendiente:hover {
            background-color: #996b1d;
        }

        .btn-realizado {
            background-color: #28845b;
        }

        .btn-realizado:hover {
            background-color: #1e6545;
        }

        .form-estado {
            margin: 0;
        }

        .acciones {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .acciones a,
        .acciones button {
            display: inline-block;
            padding: 9px 16px;
            border: 0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .acciones a {
            background-color: #e5e5e5;
            color: #333;
        }

        .btn-eliminar {
            background-color: #c94b4b;
            color: white;
        }

        .sin-resultados {
            display: none;
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

        <a href="../Exposiciones/listar.php">
            Exposiciones
        </a>

        <a href="../Trabajos/listar.php">
            Trabajos
        </a>

        <a href="../gestionar_relaciones.html">
            Relaciones
        </a>

        <a href="estudios.php">
            Estudios
        </a>
    </nav>

    <button type="button" id="btn-tema">
        Modo oscuro
    </button>

</header>

<main class="gestion-contenido">

    <section class="bienvenida">
        <h1>Administrar estudios</h1>

        <p>
            Consultá los estudios registrados
            y los asignados a cada paciente.
        </p>
    </section>

    <?php if ($mensaje !== "") { ?>

        <section class="tarjeta">
            <p><?php echo escapar($mensaje); ?></p>
        </section>

    <?php } ?>

    <!-- ================================= -->
    <!-- BUSCAR O REGISTRAR ESTUDIOS -->
    <!-- ================================= -->

    <section class="tarjeta">

        <h2>Buscar estudio</h2>

        <p>
            Escribí para buscar un estudio.
            Si no existe, presioná Enter para registrarlo.
        </p>

        <form
            action="estudios.php"
            method="POST"
            id="formularioEstudio"
        >

            <input
                type="hidden"
                name="token"
                value="<?php
                    echo escapar(
                        $_SESSION["token_estudios"]
                    );
                ?>"
            >

            <input
                type="hidden"
                name="registrar_estudio"
                value="1"
            >

            <input
                type="text"
                name="nombre_es"
                id="buscar_estudio"
                class="buscador-estudio"
                maxlength="50"
                placeholder="Buscar o escribir estudio..."
                autocomplete="off"
            >

        </form>

        <p
            id="mensaje_busqueda"
            class="mensaje-busqueda"
            aria-live="polite"
        ></p>

    </section>

    <!-- ================================= -->
    <!-- CATÁLOGO DE ESTUDIOS -->
    <!-- ================================= -->

    <section class="tarjeta">

        <h2>Estudios registrados</h2>

        <p>
            Total:
            <strong><?php echo count($catalogo); ?></strong>
        </p>

        <p>
            Encontrados:
            <strong id="cantidad_encontrados">
                <?php echo count($catalogo); ?>
            </strong>
        </p>

        <div class="tabla-contenedor">

            <table class="tabla-gestion">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Estudio</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($catalogo as $estudio) { ?>

                        <tr
                            class="fila_estudio"
                            data-nombre="<?php
                                echo escapar(
                                    $estudio["nombre_es"]
                                );
                            ?>"
                        >

                            <td>
                                <?php
                                    echo (int)$estudio["id_estudio"];
                                ?>
                            </td>

                            <td>
                                <?php
                                    echo escapar(
                                        $estudio["nombre_es"]
                                    );
                                ?>
                            </td>

                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

        <p
            id="sin_resultados"
            class="sin-resultados"
        >
            No se encontraron estudios.
        </p>

    </section>

    <!-- ================================= -->
    <!-- ESTUDIOS POR PACIENTE -->
    <!-- ================================= -->

    <section class="tarjeta">

        <h2>Estudios por paciente</h2>

        <p>
            Pacientes con estudios asignados:
            <strong>
                <?php echo count($pacientes); ?>
            </strong>
        </p>

        <p>
            <a href="agregar.php">
                Asignar estudios a un paciente
            </a>
        </p>

        <div class="tabla-contenedor">

            <table class="tabla-gestion">

                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Paciente</th>
                        <th>Trabajo</th>
                        <th>Cantidad</th>
                        <th>Estudios</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($pacientes as $paciente) { ?>

                    <tr>

                        <td>
                            <?php
                                echo escapar(
                                    $paciente["dni_p"]
                                );
                            ?>
                        </td>

                        <td>
                            <?php
                                echo escapar(
                                    $paciente["nombre_p"] .
                                    " " .
                                    $paciente["apellido"]
                                );
                            ?>
                        </td>

                        <td>
                            <?php
                                echo escapar(
                                    $paciente["nombre_t"]
                                    ?? "Sin trabajo"
                                );
                            ?>
                        </td>

                        <td>
                            <?php
                                echo count(
                                    $paciente["estudios"]
                                );
                            ?>
                        </td>

                        <!-- NOMBRES DE ESTUDIOS -->

                        <td>

                            <ul class="lista-tabla">

                                <?php foreach (
                                    $paciente["estudios"] as $estudio
                                ) { ?>

                                    <li>
                                        <?php
                                            echo escapar(
                                                $estudio["nombre_es"]
                                            );
                                        ?>
                                    </li>

                                <?php } ?>

                            </ul>

                        </td>

                        <!-- BOTONES DE ESTADO -->

                        <td>

                            <ul class="lista-estados">

                                <?php foreach (
                                    $paciente["estudios"] as $estudio
                                ) { ?>

                                    <li>

                                        <form
                                            class="form-estado"
                                            action="cambiar_estado.php"
                                            method="POST"
                                        >

                                            <input
                                                type="hidden"
                                                name="token"
                                                value="<?php
                                                    echo escapar(
                                                        $_SESSION["token_estudios"]
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="dni"
                                                value="<?php
                                                    echo escapar(
                                                        $paciente["dni_p"]
                                                    );
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_estudio"
                                                value="<?php
                                                    echo (int)$estudio["id_estudio"];
                                                ?>"
                                            >

                                            <?php
                                                $realizado =
                                                    $estudio["estado"]
                                                    === "Realizado";
                                            ?>

                                            <button
                                                type="submit"
                                                class="btn-estado <?php
                                                    echo $realizado
                                                        ? "btn-realizado"
                                                        : "btn-pendiente";
                                                ?>"
                                                title="<?php
                                                    echo $realizado
                                                        ? "Cambiar a Pendiente"
                                                        : "Cambiar a Realizado";
                                                ?>"
                                            >

                                                <?php
                                                    echo $realizado
                                                        ? "Realizado"
                                                        : "Pendiente";
                                                ?>

                                            </button>

                                        </form>

                                    </li>

                                <?php } ?>

                            </ul>

                        </td>

                        <!-- ACCIONES -->

                        <td>

                            <div class="acciones">

                                <a href="editar.php?dni=<?php
                                    echo urlencode(
                                        $paciente["dni_p"]
                                    );
                                ?>">
                                    Editar
                                </a>

                                <form
                                    action="eliminar.php"
                                    method="POST"
                                    onsubmit="return confirm(
                                        '¿Seguro que querés quitar los estudios asignados?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="dni"
                                        value="<?php
                                            echo escapar(
                                                $paciente["dni_p"]
                                            );
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-eliminar"
                                    >
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

    </section>

    <section class="tarjeta">
        <a href="../Pacientes/listar.php">
            Volver al listado de pacientes
        </a>
    </section>

</main>

<footer>
    <span>Clínica</span>
    <span>Administración de estudios</span>
</footer>

<!-- ================================= -->
<!-- BUSCADOR EN TIEMPO REAL -->
<!-- ================================= -->

<script>

const estudiosRegistrados = <?php echo json_encode(
    $catalogo,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

const buscador = document.getElementById(
    "buscar_estudio"
);

const formulario = document.getElementById(
    "formularioEstudio"
);

const mensaje = document.getElementById(
    "mensaje_busqueda"
);

const contador = document.getElementById(
    "cantidad_encontrados"
);

const filas = document.querySelectorAll(
    ".fila_estudio"
);

const sinResultados = document.getElementById(
    "sin_resultados"
);

function normalizar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

function buscarExacto(nombre) {
    return estudiosRegistrados.find(
        estudio =>
            normalizar(estudio.nombre_es) ===
            normalizar(nombre)
    ) || null;
}

function filtrarEstudios() {

    const texto = normalizar(buscador.value);

    let encontrados = 0;

    filas.forEach(fila => {

        const nombre = normalizar(
            fila.dataset.nombre
        );

        const coincide = nombre.includes(texto);

        fila.style.display = coincide ? "" : "none";

        if (coincide) {
            encontrados++;
        }
    });

    contador.textContent = encontrados;

    sinResultados.style.display =
        texto && encontrados === 0
            ? "block"
            : "none";

    if (!texto) {
        mensaje.textContent = "";
        return;
    }

    const existente = buscarExacto(texto);

    if (existente) {
        mensaje.textContent =
            "Este estudio ya está registrado: " +
            existente.nombre_es;
        return;
    }

    mensaje.textContent = encontrados > 0
        ? "Hay estudios similares. Si es nuevo, presioná Enter para registrarlo."
        : "No existe ese estudio. Presioná Enter para registrarlo.";
}

buscador.addEventListener(
    "input",
    filtrarEstudios
);

formulario.addEventListener(
    "submit",
    function(evento) {

        const nombre = buscador.value
            .trim()
            .replace(/\s+/g, " ");

        if (!nombre) {
            evento.preventDefault();

            mensaje.textContent =
                "Escribí el nombre del estudio.";
            return;
        }

        if (buscarExacto(nombre)) {
            evento.preventDefault();

            mensaje.textContent =
                "Ese estudio ya está registrado.";
            return;
        }

        if (!confirm(
            '¿Querés registrar el estudio "' +
            nombre +
            '"?'
        )) {
            evento.preventDefault();
            return;
        }

        buscador.value = nombre;
    }
);

</script>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>

<?php mysqli_close($conexion); ?>
