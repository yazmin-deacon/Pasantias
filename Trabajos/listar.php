<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        ORDER BY nombre_t ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los trabajos: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);

$mensaje = "";

if (isset($_GET["mensaje"])) {
    $mensaje = $_GET["mensaje"];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrar trabajos - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .acciones {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .acciones form {
            margin: 0;
            padding: 0;
        }

        .acciones a,
        .acciones button {
            display: inline-block;
            padding: 9px 16px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: 0.3s;
        }

        .btn-editar {
            background-color: #e5e5e5;
            color: #333;
        }

        .btn-editar:hover {
            background-color: #d0d0d0;
        }

        .btn-eliminar {
            background-color: #c94b4b;
            color: white;
        }

        .btn-eliminar:hover {
            background-color: #a93636;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">
            <img id="logo"
                src="../general/Logo minimalista DUCCA con hojas orgánicas.png"
                alt="Logo DUCA"
                height="150"
                width="175">
        </div>

        <nav>
            <a href="../inicio sesion/index.php">Menú principal</a>
            <a href="../gestionar_exposiciones.html">Exposiciones</a>
            <a href="listar.php">Trabajos</a>
            <a href="../gestionar_relaciones.html">Relaciones</a>
            <a href="../gestionar_estudios.html">Estudios</a>
        </nav>

        <button type="button" id="btn-tema">Modo oscuro</button>
    </header>

    <main class="gestion-contenido">

        <section class="bienvenida">
            <h1>Administrar trabajos</h1>
            <p>Registrá y mantené actualizado el catálogo de trabajos.</p>
        </section>

        <?php if ($mensaje != "") { ?>
            <section class="tarjeta">
                <p><?php echo htmlspecialchars($mensaje); ?></p>
            </section>
        <?php } ?>

        <section class="tarjeta">

            <h2>Agregar trabajo</h2>

            <a href="agregar.php" class="btn-secundario">
                Nuevo trabajo
            </a>

        </section>

        <section class="tarjeta">

            <h2>Trabajos registrados</h2>

            <p>Trabajos registrados: <?php echo $cantidad; ?></p>

            <br>

            <div class="tabla-contenedor">

                <table class="tabla-gestion">

                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($trabajo = mysqli_fetch_assoc($resultado)) { ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($trabajo["nombre_t"]); ?>
                                </td>

                                <td class="acciones">

                                    <a class="btn-editar"
                                        href="editar.php?id=<?php echo $trabajo["id_trabajo"]; ?>">
                                        Editar
                                    </a>

                                    <form action="eliminar.php" method="POST"
                                        onsubmit="return confirm('¿Seguro que querés eliminar este trabajo?')">

                                        <input type="hidden"
                                            name="id_trabajo"
                                            value="<?php echo $trabajo["id_trabajo"]; ?>">

                                        <button type="submit" class="btn-eliminar">
                                            Eliminar
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <footer>
        <span>Clínica</span>
        <span>Administración de trabajos</span>
    </footer>

    <script src="../general/script.js"></script>
    <script src="../general/modooscuro.js"></script>
    <script src="../general/animaciones.js"></script>

</body>
</html>

<?php
mysqli_close($conexion);
?>