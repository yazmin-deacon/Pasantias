
<?php
// BLOQUE 1 - Conexión
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar pacientes que tengan estudios asignados
$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t,
            COUNT(DISTINCT pe.id_estudio) AS cantidad,
            GROUP_CONCAT(
                e.nombre_es
                ORDER BY e.nombre_es
                SEPARATOR '|||'
            ) AS estudios,
            GROUP_CONCAT(
                COALESCE(h.estado, 'Pendiente')
                ORDER BY e.nombre_es
                SEPARATOR '|||'
            ) AS estados

        FROM paciente p

        INNER JOIN paciente_estudio pe
            ON p.dni_p = pe.dni_paciente

        INNER JOIN estudio e
            ON pe.id_estudio = e.id_estudio

        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo

        LEFT JOIN (
            SELECT
                dni_paciente,
                id_estudio,
                CASE
                    WHEN SUM(estado = 'Pendiente') > 0
                    THEN 'Pendiente'
                    ELSE 'Realizado'
                END AS estado
            FROM historial_medico
            GROUP BY dni_paciente, id_estudio
        ) h
            ON p.dni_p = h.dni_paciente
            AND pe.id_estudio = h.id_estudio

        GROUP BY
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t

        ORDER BY p.apellido ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar los pacientes: " . mysqli_error($conexion));
}

$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Estudios por paciente - Clínica</title>

    <link rel="stylesheet" href="../general/estilos.css">

    <style>

        .acciones {
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            flex-wrap:wrap;
        }

        .acciones form {
            margin:0;
            padding:0;
        }

        .acciones a,
        .acciones button,
        .btn-cancelar {
            display:inline-block;
            padding:9px 16px;
            border:0;
            border-radius:8px;
            text-decoration:none;
            font-size:14px;
            font-family:inherit;
            font-weight:600;
            cursor:pointer;
            text-align:center;
            transition:0.3s;
        }

        .acciones a {
            background:#e5e5e5;
            color:#333;
        }

        .acciones a:hover {
            background:#d0d0d0;
        }

        .btn-eliminar {
            background:#c94b4b;
            color:white;
        }

        .btn-eliminar:hover {
            background:#a93636;
        }

        .btn-cancelar {
            background:transparent;
            color:white;
            margin-left:8px;
            padding:9px 12px;
        }

        .btn-cancelar:hover {
            background:#f3b5c8;
            color:#762b45;
        }

        /* Listas dentro de la tabla */

        .lista-tabla {
            margin:0;
            padding-left:20px;
            text-align:left;
        }

        .lista-tabla li {
            margin-bottom:5px;
        }

        .lista-tabla li:last-child {
            margin-bottom:0;
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
        <a href="../Trabajos/Trabajos.php">Trabajos</a>
        <a href="../gestionar_relaciones.html">Relaciones</a>
        <a href="estudios.php">Estudios</a>
    </nav>

    <button type="button" id="btn-tema">Modo oscuro</button>

</header>

<main class="gestion-contenido">

    <section class="bienvenida">

        <h1>Estudios por paciente</h1>

        <p>Consultá y administrá los estudios médicos asignados a cada paciente.</p>

    </section>

    <section class="tarjeta">

        <h2>Pacientes registrados</h2>

        <p>Pacientes con estudios asignados: <?php echo $cantidad; ?></p>

        <br>

        <a href="agregar.php">Asignar estudios a un paciente</a>

        <br><br>

        <div class="tabla-contenedor">

            <table class="tabla-gestion">

                <thead>

                    <tr>
                        <th>DNI</th>
                        <th>Paciente</th>
                        <th>Trabajo</th>
                        <th>Cantidad de estudios</th>
                        <th>Estudios</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>

                </thead>

                <tbody>

                    <?php while ($paciente = mysqli_fetch_assoc($resultado)) { ?>

                        <?php
                        $estudios = explode("|||", $paciente["estudios"]);
                        $estados = explode("|||", $paciente["estados"]);
                        ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($paciente["dni_p"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($paciente["nombre_p"] . " " . $paciente["apellido"]); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($paciente["nombre_t"] ?? "Sin trabajo"); ?>
                            </td>

                            <td>
                                <?php echo (int) $paciente["cantidad"]; ?>
                            </td>

                            <td>

                                <ul class="lista-tabla">

                                    <?php foreach ($estudios as $estudio) { ?>

                                        <li>
                                            <?php echo htmlspecialchars($estudio); ?>
                                        </li>

                                    <?php } ?>

                                </ul>

                            </td>

                            <td>

                                <ul class="lista-tabla">

                                    <?php foreach ($estados as $estado) { ?>

                                        <li>
                                            <?php echo htmlspecialchars($estado); ?>
                                        </li>

                                    <?php } ?>

                                </ul>

                            </td>

                            <td class="acciones">

                                <a href="editar.php?dni=<?php echo urlencode($paciente["dni_p"]); ?>">
                                    Editar
                                </a>

                                <form action="eliminar.php"
                                      method="POST"
                                      onsubmit="return confirm('¿Seguro que querés quitar los estudios asignados a este paciente?')">

                                    <input type="hidden"
                                           name="dni"
                                           value="<?php echo htmlspecialchars($paciente["dni_p"]); ?>">

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
    <span>Administración de estudios por paciente</span>

</footer>

<script src="../general/script.js"></script>
<script src="../general/modooscuro.js"></script>
<script src="../general/animaciones.js"></script>

</body>
</html>

<?php mysqli_close($conexion); ?>
