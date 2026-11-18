<?php
// ==========================================
// 1. VERIFICAR SESIÓN
// ==========================================

session_start();

if (!isset($_SESSION["dni"])) {
    header("Location: login.html");
    exit();
}

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

date_default_timezone_set("America/Argentina/Salta");

function escapar($valor)
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==========================================
// 2. BUSCADOR AJAX DE PACIENTES
// ==========================================
// El propio index.php recibe las búsquedas
// y responde sin recargar la página.

if (isset($_GET["buscar_ajax"])) {

    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");

    $busqueda = trim(
        (string) $_GET["buscar_ajax"]
    );

    $busqueda = mb_substr(
        $busqueda,
        0,
        60,
        "UTF-8"
    );

    if ($busqueda === "") {
        echo json_encode([]);
        exit;
    }

    $numeros = preg_replace(
        '/\D/',
        '',
        $busqueda
    );

    $textoLike = "%" . $busqueda . "%";

    $dniLike = $numeros !== ""
        ? "%" . $numeros . "%"
        : $textoLike;

    $sql = "SELECT
                dni_p,
                nombre_p,
                apellido

            FROM paciente

            WHERE nombre_p LIKE ?
               OR apellido LIKE ?
               OR CONCAT(
                    nombre_p,
                    ' ',
                    apellido
                  ) LIKE ?
               OR CAST(dni_p AS CHAR) LIKE ?

            ORDER BY nombre_p, apellido

            LIMIT 8";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            "error" => "No se pudo realizar la búsqueda."
        ]);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $textoLike,
        $textoLike,
        $textoLike,
        $dniLike
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        http_response_code(500);

        echo json_encode([
            "error" => "No se pudo realizar la búsqueda."
        ]);

        exit;
    }

    $resultado = mysqli_stmt_get_result($stmt);

    $pacientes = mysqli_fetch_all(
        $resultado,
        MYSQLI_ASSOC
    );

    mysqli_stmt_close($stmt);
    mysqli_close($conexion);

    echo json_encode(
        $pacientes,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// ==========================================
// 3. OBTENER PRÓXIMOS TURNOS
// ==========================================

$turnosPendientes = [];
$errorTurnos = false;

$sql = "SELECT
            t.id_turno,
            t.dni_paciente,
            t.fecha,
            t.hora,
            t.estado,
            p.nombre_p,
            p.apellido

        FROM turno t

        INNER JOIN paciente p
            ON t.dni_paciente = p.dni_p

        WHERE t.estado IN (
            'Pendiente',
            'Confirmado'
        )

        AND TIMESTAMP(t.fecha, t.hora) >= NOW()

        ORDER BY t.fecha ASC, t.hora ASC

        LIMIT 10";

$stmt = mysqli_prepare($conexion, $sql);

if ($stmt) {

    if (mysqli_stmt_execute($stmt)) {

        $resultado = mysqli_stmt_get_result($stmt);

        if ($resultado) {
            $turnosPendientes = mysqli_fetch_all(
                $resultado,
                MYSQLI_ASSOC
            );
        } else {
            $errorTurnos = true;
        }

    } else {
        $errorTurnos = true;
    }

    mysqli_stmt_close($stmt);

} else {
    $errorTurnos = true;
}

mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Menú Principal - DUCCA</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        /* ====================================
           BUSCADOR DEL MENÚ PRINCIPAL
        ==================================== */

        .bloque-buscador {
            display: flex;
            flex-direction: column;
            box-sizing: border-box;

            /* Mantiene contenido dentro
               del espacio de la tarjeta */

            max-height: 340px;
            overflow: hidden;
            min-width: 0;
            min-height: 0;
        }

        .bloque-buscador form {
            margin-bottom: 8px;
        }

        .bloque-buscador input {
            width: 100%;
            box-sizing: border-box;
        }

        /* Lista de coincidencias */

        .resultados-buscador {
            list-style: none;
            padding: 0;
            margin: 4px 0;

            max-height: 100px;
            overflow-y: auto;
            overflow-x: hidden;

            border-radius: 7px;
            box-sizing: border-box;
        }

        .resultados-buscador li {
            margin: 0;
            padding: 0;
        }

        .resultado-paciente {
            display: block;
            width: 100%;
            padding: 7px 9px;

            background: transparent;
            color: inherit;
            border: 1px solid #ddd;
            border-radius: 6px;

            font: inherit;
            text-align: left;
            cursor: pointer;
            box-sizing: border-box;
        }

        .resultado-paciente:hover,
        .resultado-paciente:focus {
            background: rgba(180, 120, 150, 0.12);
        }

        .resultado-paciente strong {
            display: block;
            font-size: 13px;
        }

        .resultado-paciente small {
            display: block;
            margin-top: 2px;
            font-size: 12px;
        }

        /* Ficha del paciente seleccionado */

        .paciente-encontrado {
            padding: 9px;
            margin-top: 6px;

            border: 1px solid #ddd;
            border-radius: 8px;

            overflow-y: auto;
            max-height: 112px;

            box-sizing: border-box;
        }

        .paciente-encontrado h3 {
            font-size: 14px;
            margin: 0 0 5px;
        }

        .paciente-encontrado p {
            font-size: 12px;
            margin: 4px 0;
        }

        .paciente-encontrado a {
            display: inline-block;
            margin-top: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .mensaje-busqueda {
            font-size: 12px;
            margin: 4px 0;
        }

        [hidden] {
            display: none !important;
        }

        /* ====================================
           TURNOS PENDIENTES
        ==================================== */

        .bloque-turnos {
            display: flex;
            flex-direction: column;
            min-height: 0;
            box-sizing: border-box;
        }

        .bloque-turnos .lista-turnos {
            list-style: none;
            padding: 0;
            margin: 12px 0;
            width: 100%;

            max-height: 230px;
            min-height: 0;

            overflow-y: auto;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        .bloque-turnos .turno-item {
            padding: 12px;
            margin-bottom: 10px;

            border: 1px solid #dedede;
            border-radius: 10px;

            box-sizing: border-box;
        }

        .bloque-turnos .turno-nombre {
            display: block;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .bloque-turnos .turno-fecha {
            display: block;
            font-size: 13px;
            margin-bottom: 7px;
        }

        .bloque-turnos .turno-estado {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 7px;

            font-size: 12px;
            font-weight: bold;
        }

        .bloque-turnos .estado-pendiente {
            background: #fff0cf;
            color: #8b5900;
        }

        .bloque-turnos .estado-confirmado {
            background: #dce9ff;
            color: #174a9c;
        }

        .bloque-turnos .turno-historial {
            display: block;
            margin-top: 8px;
            font-size: 13px;
        }

        .bloque-turnos .turnos-vacios {
            padding: 15px 5px;
            font-size: 14px;
            text-align: center;
        }

        .bloque-turnos .ver-turnos {
            display: block;
            margin-top: auto;
        }
    </style>
</head>

<body>

    <!-- ================================= -->
    <!-- ENCABEZADO -->
    <!-- ================================= -->

    <header>

        <div class="logo">

            <img id="logo" src="../general/Logo minimalista DUCCA con hojas orgánicas.png" alt="Logo DUCCA" height="150"
                width="175">

        </div>

        <nav class="navegacion-superior">

            <span class="perfil-usuario">

                Usuario:

                <strong>
                    <?php
                    echo escapar(
                        $_SESSION["nombre"] ?? "Usuario"
                    );
                    ?>
                </strong>

            </span>

            <div class="contenedor-configuracion">

                <button type="button" id="btn-configuracion" aria-label="Abrir configuración" aria-expanded="false">
                    ⚙
                </button>

                <div id="menu-configuracion" class="menu-configuracion" hidden>

                    <p class="titulo-menu">
                        Administración
                    </p>

                    <button type="button" id="btn-tema" class="opcion-configuracion">
                        ☽ Modo oscuro
                    </button>

                    <div class="separador-menu"></div>

                    <a href="../Trabajos/listar.php">
                        Trabajos
                    </a>

                    <a href="../Exposiciones/listar.php">
                        Exposiciones
                    </a>

                    <a href="../Relaciones/listar.php">
                        Relaciones
                    </a>

                    <a href="cerrar_sesion.php" class="cerrar-sesion-menu">
                        Cerrar sesión
                    </a>

                </div>

            </div>

        </nav>

    </header>

    <!-- ================================= -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ================================= -->

    <main class="menu-layout">

        <aside class="barra-lateral">

            <!-- ================================= -->
            <!-- BUSCADOR INTELIGENTE -->
            <!-- ================================= -->

            <section class="tarjeta bloque-buscador">

                <h2>BUSCAR PACIENTE</h2>

                <form id="form-buscar-paciente">

                    <label for="buscar-paciente">
                        DNI, nombre o apellido:
                    </label>

                    <input type="text" id="buscar-paciente" name="buscar" placeholder="Buscar paciente..."
                        autocomplete="off" maxlength="60" required>

                    <button type="submit">
                        BUSCAR
                    </button>

                </form>

                <!-- SUGERENCIAS EN EL RECUADRO -->

                <ul id="resultados-pacientes" class="resultados-buscador" aria-label="Pacientes encontrados" hidden>
                </ul>

                <p id="mensaje-busqueda" class="mensaje-busqueda" aria-live="polite" hidden></p>

                <!-- FICHA DENTRO DEL MISMO RECUADRO -->

                <div id="paciente-encontrado" class="paciente-encontrado" hidden>

                    <h3 id="nombre-encontrado"></h3>

                    <p>
                        DNI:
                        <span id="dni-encontrado"></span>
                    </p>

                    <a id="enlace-historial" href="#">
                        Ver historial médico →
                    </a>

                </div>

            </section>

            <!-- ================================= -->
            <!-- TURNOS PENDIENTES -->
            <!-- ================================= -->

            <section class="tarjeta bloque-turnos">

                <h2>TURNOS PENDIENTES</h2>

                <ul class="lista-turnos" id="lista-turnos-db">

                    <?php if ($errorTurnos) { ?>

                        <li class="turnos-vacios">
                            No se pudieron cargar los turnos.
                        </li>

                    <?php } elseif (empty($turnosPendientes)) { ?>

                        <li class="turnos-vacios">
                            No hay próximos turnos pendientes
                            ni confirmados.
                        </li>

                    <?php } else { ?>

                        <?php foreach (
                            $turnosPendientes as $turno
                        ) { ?>

                            <li class="turno-item">

                                <span class="turno-nombre">

                                    <?php
                                    echo escapar(
                                        $turno["nombre_p"] .
                                        " " .
                                        $turno["apellido"]
                                    );
                                    ?>

                                </span>

                                <span class="turno-fecha">

                                

                                    <?php
                                    echo escapar(
                                        date(
                                            "d/m/Y",
                                            strtotime(
                                                $turno["fecha"]
                                            )
                                        )
                                    );
                                    ?>

                                    &nbsp;

                                    <?php
                                    echo escapar(
                                        substr(
                                            $turno["hora"],
                                            0,
                                            5
                                        )
                                    );
                                    ?>

                                    hs

                                </span>

                                <?php
                                $claseEstado =
                                    $turno["estado"] === "Confirmado"
                                    ? "estado-confirmado"
                                    : "estado-pendiente";
                                ?>

                                <span class="turno-estado <?php
                                echo $claseEstado;
                                ?>">

                                    <?php
                                    echo escapar(
                                        $turno["estado"]
                                    );
                                    ?>

                                </span>

                                <a class="turno-historial" href="../historial/historial.php?dni_p=<?php
                                echo urlencode(
                                    $turno["dni_paciente"]
                                );
                                ?>">
                                    Ver historial del paciente
                                </a>

                            </li>

                        <?php } ?>

                    <?php } ?>

                </ul>

                <a href="../turnos/listar.php" class="ver-turnos">
                    Ver todos los turnos →
                </a>

            </section>

        </aside>

        <!-- ================================= -->
        <!-- MENÚ PRINCIPAL -->
        <!-- ================================= -->

        <section class="contenido-menu">

            <div class="opciones-menu">

                <a href="../Pacientes/alta.php" class="tarjeta opcion-menu">

                    <div class="icono-menu"></div>

                    <h2>CARGAR PACIENTE</h2>

                    <p>
                        Registrar un nuevo paciente
                        ingresando sus datos personales
                        y laborales.
                    </p>

                    <span>INGRESAR →</span>

                </a>

                <a href="../Pacientes/listar.php" class="tarjeta opcion-menu">

                    <div class="icono-menu"></div>

                    <h2>VER PACIENTES</h2>

                    <p>
                        Consultar todos los pacientes
                        registrados y acceder
                        a su información.
                    </p>

                    <span>INGRESAR →</span>

                </a>

                <a href="../Estudios/estudios.php" class="tarjeta opcion-menu">

                    <div class="icono-menu"></div>

                    <h2>ADMINISTRAR ESTUDIOS</h2>

                    <p>
                        Consultar y administrar
                        los estudios disponibles
                        en el sistema.
                    </p>

                    <span>INGRESAR →</span>

                </a>

            </div>

        </section>

    </main>

    <footer>
        <p>Proyecto Web Clínica - EPET N°20 (2026)</p>
    </footer>

    <!-- ================================= -->
    <!-- BUSCADOR EN TIEMPO REAL -->
    <!-- ================================= -->

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            const formulario = document.getElementById(
                "form-buscar-paciente"
            );

            const campo = document.getElementById(
                "buscar-paciente"
            );

            const lista = document.getElementById(
                "resultados-pacientes"
            );

            const mensaje = document.getElementById(
                "mensaje-busqueda"
            );

            const ficha = document.getElementById(
                "paciente-encontrado"
            );

            const nombreEncontrado = document.getElementById(
                "nombre-encontrado"
            );

            const dniEncontrado = document.getElementById(
                "dni-encontrado"
            );

            const enlaceHistorial = document.getElementById(
                "enlace-historial"
            );

            let pacientesActuales = [];
            let temporizador = null;
            let controlador = null;

            function formatearDNI(valor) {
                return String(valor)
                    .replace(/\D/g, "")
                    .replace(
                        /\B(?=(\d{3})+(?!\d))/g,
                        "."
                    );
            }

            function mostrarMensaje(texto) {
                mensaje.textContent = texto;
                mensaje.hidden = !texto;
            }

            function limpiarResultados() {
                lista.replaceChildren();
                lista.hidden = true;
                pacientesActuales = [];
            }

            function seleccionarPaciente(paciente) {

                const dni = String(paciente.dni_p);

                nombreEncontrado.textContent =
                    paciente.nombre_p + " " +
                    paciente.apellido;

                dniEncontrado.textContent =
                    formatearDNI(dni);

                enlaceHistorial.href =
                    "../historial/historial.php?dni_p=" +
                    encodeURIComponent(dni);

                ficha.hidden = false;

                limpiarResultados();
                mostrarMensaje("");
            }

            function mostrarResultados(pacientes) {

                limpiarResultados();

                pacientesActuales = pacientes;

                if (pacientes.length === 0) {
                    mostrarMensaje(
                        "No se encontraron pacientes."
                    );
                    return;
                }

                mostrarMensaje("");

                lista.hidden = false;

                pacientes.forEach(paciente => {

                    const elemento = document.createElement(
                        "li"
                    );

                    const boton = document.createElement(
                        "button"
                    );

                    boton.type = "button";
                    boton.className = "resultado-paciente";

                    const nombre = document.createElement(
                        "strong"
                    );

                    nombre.textContent =
                        paciente.nombre_p + " " +
                        paciente.apellido;

                    const dni = document.createElement(
                        "small"
                    );

                    dni.textContent =
                        "DNI: " + formatearDNI(
                            paciente.dni_p
                        );

                    boton.appendChild(nombre);
                    boton.appendChild(dni);

                    boton.addEventListener("click", () => {
                    window.location.href =
                    "../historial/historial.php?dni_p=" +
                    encodeURIComponent(paciente.dni_p);
                    });

                    elemento.appendChild(boton);
                    lista.appendChild(elemento);
                });
            }

            async function buscarPacientes() {

                const busqueda = campo.value.trim();

                if (controlador) {
                    controlador.abort();
                }

                if (!busqueda) {
                    limpiarResultados();
                    mostrarMensaje("");
                    return;
                }

                controlador = new AbortController();

                const actual = controlador;

                try {

                    const url =
                        window.location.pathname +
                        "?buscar_ajax=" +
                        encodeURIComponent(busqueda);

                    const respuesta = await fetch(url, {
                        signal: actual.signal,
                        credentials: "same-origin",
                        cache: "no-store"
                    });

                    if (!respuesta.ok) {
                        throw new Error(
                            "Error al buscar pacientes."
                        );
                    }

                    const pacientes = await respuesta.json();

                    if (!Array.isArray(pacientes)) {
                        throw new Error(
                            "Respuesta no válida."
                        );
                    }

                    if (actual.signal.aborted) {
                        return;
                    }

                    mostrarResultados(pacientes);

                } catch (error) {

                    if (error.name === "AbortError") {
                        return;
                    }

                    limpiarResultados();
                    mostrarMensaje(
                        "No se pudo realizar la búsqueda."
                    );
                }
            }

            // Buscar mientras se escribe.

            campo.addEventListener("input", function () {

                clearTimeout(temporizador);

                if (controlador) {
                    controlador.abort();
                }

                ficha.hidden = true;

                if (!campo.value.trim()) {
                    limpiarResultados();
                    mostrarMensaje("");
                    return;
                }

                temporizador = setTimeout(
                    buscarPacientes,
                    200
                );
            });

            // El botón Buscar selecciona el
            // primer resultado si hay coincidencias.

            formulario.addEventListener(
                "submit",
                async function (evento) {

                    evento.preventDefault();

                    clearTimeout(temporizador);

                    if (
                        pacientesActuales.length > 0
                    ) {
                        seleccionarPaciente(
                            pacientesActuales[0]
                        );
                        return;
                    }

                    await buscarPacientes();

                    if (
                        pacientesActuales.length > 0
                    ) {
                        seleccionarPaciente(
                            pacientesActuales[0]
                        );
                    }
                }
            );

        });
    </script>

    <!-- SCRIPTS ORIGINALES -->

    <script src="../general/script.js"></script>
    <script src="../general/animaciones.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/configuracion.js"></script>

</body>

</html>