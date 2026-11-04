
<?php
require_once "sesion.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Menú Principal - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">
</head>

<body>

    <header>
        <div class="logo">
            <img
                id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt=""
                height="150"
                width="175"
            >
        </div>

        <nav>
            <span class="perfil-usuario">
                Usuario: <strong><?php echo htmlspecialchars($_SESSION["nombre"]); ?></strong>
            </span>

            <a href="cerrar_sesion.php">Cerrar sesión</a>

            <button type="button" id="btn-tema">
                🌙Modo oscuro
            </button>
        </nav>
    </header>


    <main class="menu-layout">

        <!-- BARRA LATERAL -->
        <aside class="barra-lateral">

            <!-- BUSCADOR -->
            <section class="tarjeta bloque-buscador">

                <h2>BUSCAR PACIENTE</h2>

                <form action="../Paciente/buscar_paciente.php" method="GET">

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

                    <button type="submit">BUSCAR</button>

                </form>

            </section>


            <!-- TURNOS PENDIENTES -->
            <section class="tarjeta bloque-turnos">

                <h2>TURNOS PENDIENTES</h2>

                <ul class="lista-turnos" id="lista-turnos-db">

                    <!-- Turnos cargados desde la base de datos -->

                </ul>

                <a href="#" class="ver-turnos">
                    Ver todos los turnos →
                </a>

            </section>

        </aside>


        <!-- MENU PRINCIPAL -->
        <section class="contenido-menu">

            <div class="opciones-menu">

                <a href="../Paciente/alta.php" class="tarjeta opcion-menu">

                    <div class="icono-menu">👤+</div>

                    <h2>CARGAR PACIENTE</h2>

                    <p>
                        Registrar un nuevo paciente
                        ingresando sus datos personales
                        y laborales.
                    </p>

                    <span>INGRESAR →</span>

                </a>


                <a href="../Paciente/listar.php" class="tarjeta opcion-menu">

                    <div class="icono-menu">📋</div>

                    <h2>VER PACIENTES</h2>

                    <p>
                        Consultar todos los pacientes
                        registrados y acceder a su información.
                    </p>

                    <span>INGRESAR →</span>

                </a>


                <a href="../Paciente/agregar_examen.php" class="tarjeta opcion-menu">

                    <div class="icono-menu">🩺</div>

                    <h2>AGREGAR ESTUDIO</h2>

                    <p>
                        Asignar un nuevo estudio médico
                        a un paciente registrado.
                    </p>

                    <span>INGRESAR →</span>

                </a>

            </div>

        </section>

    </main>


    <footer>
        <p>Proyecto Web Clínica - EPET N°20 (2026)</p>
    </footer>


    <script src="../general/script.js"></script>
    <script src="../general/animaciones.js"></script>
    <script src="../general/modooscuro.js"></script>

</body>
</html>
