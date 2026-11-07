<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Agregar trabajo - Clínica</title>

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
            <h1>Agregar trabajo</h1>
            <p>Completá el nombre del nuevo trabajo.</p>
        </section>

        <section class="tarjeta">

            <form class="form-gestion" action="guardar.php" method="POST">

                <div class="campo">

                    <label for="nombre_t">Nombre del trabajo</label>

                    <input
                        type="text"
                        id="nombre_t"
                        name="nombre_t"
                        maxlength="50"
                        placeholder="Ej.: Administrativo"
                        required>

                </div>

                <button type="submit">Guardar trabajo</button>

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