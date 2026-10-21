
<?php
// ===== BLOQUE 1 — Traer la conexión =====
require_once "conexion.php";

// ===== BLOQUE 2 — Recibir el DNI =====
if (isset($_POST["dni_p"])) {
    $dni_p = intval($_POST["dni_p"]);
    $confirmado = true;
} elseif (isset($_GET["dni_p"])) {
    $dni_p = intval($_GET["dni_p"]);
    $confirmado = false;
} else {
    $dni_p = 0;
    $confirmado = false;
}

if ($dni_p <= 0) {
    echo "<h1>DNI inválido</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}

// ===== BLOQUE 3 — Si viene la confirmación, eliminar =====
if ($confirmado) {
    $sql = "DELETE FROM paciente WHERE dni_p = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $dni_p);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        header("Location: listar.php");
        exit;
    }

    // ===== BLOQUE 4 — Mostrar el error =====
    if (mysqli_errno($conexion) == 1451) {
        echo "<h1>No se puede eliminar</h1>";
        echo "<p>Este paciente tiene estudios u otros datos asociados. Primero eliminá o cambiá esos datos.</p>";
    } else {
        echo "<h1>Error al eliminar</h1>";
        echo "<p>" . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
    }

    mysqli_stmt_close($stmt);
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_close($conexion);
    exit;
}

// ===== BLOQUE 5 — Buscar el paciente =====
$sql = "SELECT nombre_p, apellido FROM paciente WHERE dni_p = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni_p);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) == 0) {
    echo "<h1>No existe ningún paciente con DNI " . $dni_p . "</h1>";
    echo "<p><a href='listar.php'>Volver al listado</a></p>";
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
    exit;
}

$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Eliminar paciente</title>
</head>
<body>
    <!-- ===== BLOQUE 6 — Pantalla de confirmación ===== -->
    <h1>¿Eliminar paciente?</h1>

    <p>
        Vas a eliminar a
        <strong><?php echo htmlspecialchars($paciente["nombre_p"] . " " . $paciente["apellido"]); ?></strong>.
        Esta acción no se puede deshacer.
    </p>

    <form action="eliminar.php" method="post">
        <input type="hidden" name="dni_p" value="<?php echo $dni_p; ?>">
        <input type="submit" value="Sí, eliminar">
    </form>

    <p><a href="listar.php">No, volver al listado</a></p>
</body>
</html>
