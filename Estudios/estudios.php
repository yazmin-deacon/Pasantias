```php
<?php
// BLOQUE 1 - Conexión a la base de datos
require_once __DIR__ . "/../Pacientes/conexion.php";

// BLOQUE 2 - Consultar pacientes, estudios y estados
$sql = "SELECT
            p.dni_p,
            p.nombre_p,
            p.apellido,
            t.nombre_t,
            COUNT(DISTINCT pe.id_estudio) AS cantidad,

            GROUP_CONCAT(
                DISTINCT e.nombre_es
                ORDER BY e.nombre_es
                SEPARATOR ', '
            ) AS estudios,

            GROUP_CONCAT(
                DISTINCT CONCAT(
                    e.nombre_es,
                    ': ',
                    COALESCE(h.estado, 'Pendiente')
                )
                ORDER BY e.nombre_es
                SEPARATOR ' | '
            ) AS estados

        FROM paciente p

        LEFT JOIN trabajo t
            ON p.id_trabajo = t.id_trabajo

        LEFT JOIN paciente_estudio pe
            ON p.dni_p = pe.dni_paciente

        LEFT JOIN estudio e
            ON pe.id_estudio = e.id_estudio

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

// BLOQUE 3 - Contar pacientes
$cantidad = mysqli_num_rows($resultado);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudios por paciente - Clínica</title>
    <link rel="stylesheet" href="../general/estilos.css">
</head>

<body>

<header>
    <div class="logo">
        <img id="logo" src="../general/Logo minimalista DUCCA con hojas orgánicas.png" alt="Logo DUCA" height="150"
            width="175">
    </div>

    <nav>
        <a href="menu_principal.html">Menú principal</a>
        <a href="gestionar_exposiciones.html">Exposiciones</a>
        <a href="gestionar_trabajos.html">Trabajos</a>
        <a href="gestionar_relaciones.html">Relaciones</a>
        <a href="gestionar_estudios.html">Estudios</a>
    </nav>

    <button type="button" id="btn-tema">Modo oscuro</button>
</header>

<main class="gestion-contenido">

    <section class="bienvenida">
        <h1>Estudios por paciente</h1>
        <p>Consultá los estudios médicos asignados a cada paciente.</p>
    </section>

    <section class="tarjeta">

        <h2>Pacientes registrados</h2>

        <p>Pacientes registrados: <?php echo $cantidad; ?></p>

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
                        <th>Estudios asignados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    <?php while ($paciente = mysqli_fetch_assoc($resultado)) { ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($paciente["dni_p"]); ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $paciente["nombre_p"] . " " . $paciente["apellido"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $paciente["nombre_t"] ?? "Sin trabajo"
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo $paciente["cantidad"]; ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $paciente["estudios"] ?? "Sin estudios asignados"
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $paciente["estados"] ?? "Sin estudios asignados"
                                );
                                ?>
                            </td>

                            <td class="acciones">

                                <a href="editar.php?dni=<?php echo urlencode($paciente["dni_p"]); ?>"
                                   class="btn-secundario">
                                    Editar
                                </a>

                                <form action="eliminar.php"
                                      method="POST"
                                      style="display:inline;"
                                      onsubmit="return confirm('¿Seguro que querés eliminar los estudios asignados a este paciente?')">

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

<?php
// BLOQUE 4 - Cerrar conexión
mysqli_close($conexion);
?>
```