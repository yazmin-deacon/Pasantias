
<?php
// =========================================
// 1. VERIFICAR SESIÓN
// =========================================

session_start();

if (!isset($_SESSION["dni"])) {
    header("Location: login.html");
    exit();
}

// =========================================
// 2. CONECTAR A LA BASE DE DATOS
// =========================================

require_once __DIR__ . "/../Pacientes/conexion.php";

/** @var mysqli $conexion */

date_default_timezone_set("America/Argentina/Salta");

// =========================================
// 3. FUNCIÓN PARA MOSTRAR DATOS SEGUROS
// =========================================

function escapar($valor) {
    return htmlspecialchars(
        (string)($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// =========================================
// 4. OBTENER PRÓXIMOS TURNOS
// =========================================

$turnosPendientes = [];
$errorTurnos = false;

// Mostramos los próximos turnos activos.
// Primero aparecen los más cercanos.

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

        ORDER BY
            t.fecha ASC,
            t.hora ASC

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

// =========================================
// 5. CERRAR CONEXIÓN
// =========================================

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

    <title>Menú Principal - DUCCA</title>

    <link
        rel="stylesheet"
        href="../general/estilos.css"
    >

    <style>

        /* =====================================
           TURNOS EN LA PÁGINA PRINCIPAL
        ===================================== */

        /* Mantener las dimensiones
           de la tarjeta existente */

        .bloque-turnos {
            display: flex;
            flex-direction: column;
            min-height: 0;
            box-sizing: border-box;
        }

        /* Listado dentro del recuadro */

        .bloque-turnos .lista-turnos {
            list-style: none;
            padding: 0;
            margin: 12px 0;
            width: 100%;

            max-height: 230px;
            overflow-y: auto;
            overflow-x: hidden;

            min-height: 0;
            box-sizing: border-box;
        }

        /* Cada turno */

        .bloque-turnos .turno-item {
            padding: 12px;
            margin-bottom: 10px;

            border: 1px solid #dedede;
            border-radius: 10px;

            box-sizing: border-box;
        }

        /* Nombre del paciente */

        .bloque-turnos .turno-nombre {
            display: block;
            font-weight: 700;
            margin-bottom: 7px;
        }

        /* Fecha y hora */

        .bloque-turnos .turno-fecha {
            display: block;
            font-size: 13px;
            margin-bottom: 7px;
        }

        /* Estado del turno */

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

        /* Enlace al historial */

        .bloque-turnos .turno-historial {
            display: block;
            margin-top: 8px;
            font-size: 13px;
        }

        /* Mensaje cuando no hay turnos */

        .bloque-turnos .turnos-vacios {
            padding: 15px 5px;
            font-size: 14px;
            text-align: center;
        }

        /* Mantener visible el botón inferior */

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

            <img
                id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt="Logo DUCCA"
                height="150"
                width="175"
            >

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

                <button
                    type="button"
                    id="btn-configuracion"
                    aria-label="Abrir configuración"
                    aria-expanded="false"
                >
                    ⚙
                </button>

                <div
                    id="menu-configuracion"
                    class="menu-configuracion"
                    hidden
                >

                    <p class="titulo-menu">
                        Administración
                    </p>

                    <button
                        type="button"
                        id="btn-tema"
                        class="opcion-configuracion"
                    >
                        ☽ Modo oscuro
                    </button>

                    <div class="separador-menu"></div>

                    <a href="../Trabajos/Trabajos.php">
                        Trabajos
                    </a>

                    <a href="../administracion/exposiciones.php">
                        Exposiciones
                    </a>

                    <a href="../administracion/relaciones.php">
                        Relaciones
                    </a>

                    <a
                        href="cerrar_sesion.php"
                        class="cerrar-sesion-menu"
                    >
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

        <!-- ================================= -->
        <!-- BARRA LATERAL -->
        <!-- ================================= -->

        <aside class="barra-lateral">

            <!-- ================================= -->
            <!-- BUSCAR PACIENTE -->
            <!-- ================================= -->

            <section class="tarjeta bloque-buscador">

                <h2>BUSCAR PACIENTE</h2>

                <form
                    action="../Pacientes/buscar_paciente.php"
                    method="GET"
                >

                    <label for="buscar-paciente">
                        DNI o apellido:
                    </label>

                    <input
                        type="text"
                        id="buscar-paciente"
                        name="buscar"
                        placeholder="Ingrese DNI o apellido..."
                        required
                    >

                    <button type="submit">
                        BUSCAR
                    </button>

                </form>

            </section>

            <!-- ================================= -->
            <!-- TURNOS PENDIENTES -->
            <!-- ================================= -->

            <section class="tarjeta bloque-turnos">

                <h2>TURNOS PENDIENTES</h2>

                <ul
                    class="lista-turnos"
                    id="lista-turnos-db"
                >

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

                                <!-- NOMBRE Y APELLIDO -->

                                <span class="turno-nombre">

                                    <?php
                                    echo escapar(
                                        $turno["nombre_p"] .
                                        " " .
                                        $turno["apellido"]
                                    );
                                    ?>

                                </span>

                                <!-- FECHA Y HORA -->

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

                                <!-- ESTADO -->

                                <?php

                                $claseEstado =
                                    $turno["estado"] === "Confirmado"
                                    ? "estado-confirmado"
                                    : "estado-pendiente";

                                ?>

                                <span
                                    class="turno-estado <?php
                                        echo $claseEstado;
                                    ?>"
                                >

                                    <?php
                                    echo escapar(
                                        $turno["estado"]
                                    );
                                    ?>

                                </span>

                                <!-- ACCESO AL HISTORIAL -->

                                <a
                                    class="turno-historial"
                                    href="../historial/historial.php?dni_p=<?php
                                        echo urlencode(
                                            $turno["dni_paciente"]
                                        );
                                    ?>"
                                >
                                    Ver historial del paciente
                                </a>

                            </li>

                        <?php } ?>

                    <?php } ?>

                </ul>

                <a
                    href="../turnos/listar.php"
                    class="ver-turnos"
                >
                    Ver todos los turnos →
                </a>

            </section>

        </aside>

        <!-- ================================= -->
        <!-- MENÚ PRINCIPAL -->
        <!-- ================================= -->

        <section class="contenido-menu">

            <div class="opciones-menu">

                <!-- CARGAR PACIENTE -->

                <a
                    href="../Pacientes/alta.php"
                    class="tarjeta opcion-menu"
                >

                
                    <h2>CARGAR PACIENTE</h2>

                    <p>
                        Registrar un nuevo paciente
                        ingresando sus datos personales
                        y laborales.
                    </p>

                    <span>INGRESAR →</span>

                </a>

                <!-- VER PACIENTES -->

                <a
                    href="../Pacientes/listar.php"
                    class="tarjeta opcion-menu"
                >

                    
                    <h2>VER PACIENTES</h2>

                    <p>
                        Consultar todos los pacientes
                        registrados y acceder
                        a su información.
                    </p>

                    <span>INGRESAR →</span>

                </a>

                <!-- ADMINISTRAR ESTUDIOS -->

                <a
                    href="../Estudios/estudios.php"
                    class="tarjeta opcion-menu"
                >


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

    <!-- ================================= -->
    <!-- PIE DE PÁGINA -->
    <!-- ================================= -->

    <footer>

        <p>
            Proyecto Web Clínica - EPET N°20 (2026)
        </p>

    </footer>

    <!-- ================================= -->
    <!-- SCRIPTS -->
    <!-- ================================= -->

    <script src="../general/script.js"></script>

    <script src="../general/animaciones.js"></script>

    <script src="../general/modooscuro.js"></script>

    <script src="../configuracion.js"></script>

</body>
</html>
