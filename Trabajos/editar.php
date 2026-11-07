<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: listar.php");
    exit;
}

$id = (int) $_GET["id"];

$sql = "SELECT id_trabajo, nombre_t
        FROM trabajo
        WHERE id_trabajo = ?";

$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$trabajo = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$trabajo) {
    mysqli_close($conexion);
    header("Location: listar.php?mensaje=No se encontró el trabajo");
    exit;
}

mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar trabajo - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>
        .btn-cancelar {
            display: inline-flex;
            width: auto;
            max-width: max-content;
            flex: 0 0 auto;
            align-self: flex-start;
            padding: 9px 16px;
            margin: 10px 0 0 10px;
            background-color: transparent;
            color: white;
            border: 1px solid white;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: 0.3s;
        }

        .btn-cancelar:hover {
            background-color: #f4c6d5;
            color: #800020;
            border-color: #f4c6d5;
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
            <h1>Editar trabajo</h1>
            <p>Modificá el nombre del trabajo seleccionado.</p>
        </section>

        <section class="tarjeta">

            <form class="form-gestion" action="actualizar.php" method="POST">

                <input
                    type="hidden"
                    name="id_trabajo"
                    value="<?php echo $trabajo["id_trabajo"]; ?>">

                <div class="campo">

                    <label for="nombre_t">Nombre del trabajo</label>

                    <input
                        type="text"
                        id="nombre_t"
                        name="nombre_t"
                        maxlength="50"
                        value="<?php echo htmlspecialchars($trabajo["nombre_t"]); ?>"
                        required>

                </div>

                <button type="submit">Guardar cambios</button>

                <a href="listar.php" class="btn-cancelar">
                    Cancelar
                </a>

            </form>

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