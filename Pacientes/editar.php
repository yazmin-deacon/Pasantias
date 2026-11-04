
<?php
require_once "conexion.php";

// Obtener DNI
$dni = $_GET["dni"] ?? "";

if (!ctype_digit($dni)) {
    die("DNI no válido.");
}

// Consultar paciente
$sql = "SELECT * FROM paciente WHERE dni_p = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$paciente = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$paciente) {
    die("No se encontró el paciente.");
}

// Consultar trabajos
$sql = "SELECT id_trabajo, nombre_t FROM trabajo ORDER BY nombre_t";
$resultado = mysqli_query($conexion, $sql);
$trabajos = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $trabajos[] = $fila;
}

// Consultar exposiciones
$sql = "SELECT id_exposicion, nombre_ex FROM exposicion ORDER BY nombre_ex";
$resultado = mysqli_query($conexion, $sql);
$exposiciones = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones[] = $fila;
}

// Consultar estudios
$sql = "SELECT id_estudio, nombre_es FROM estudio ORDER BY nombre_es";
$resultado = mysqli_query($conexion, $sql);
$estudios = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios[] = $fila;
}

// Exposiciones actuales del paciente
$sql = "SELECT id_exposicion FROM paciente_exposicion WHERE dni_paciente = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$exposiciones_paciente = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones_paciente[] = (int)$fila["id_exposicion"];
}

mysqli_stmt_close($stmt);

// Estudios actuales del paciente
$sql = "SELECT id_estudio FROM paciente_estudio WHERE dni_paciente = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $dni);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$estudios_paciente = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios_paciente[] = (int)$fila["id_estudio"];
}

mysqli_stmt_close($stmt);

// Relaciones trabajo - exposiciones
$sql = "SELECT id_trabajo, id_exposicion FROM trabajo_exposicion";
$resultado = mysqli_query($conexion, $sql);
$exposiciones_trabajo = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $exposiciones_trabajo[$fila["id_trabajo"]][] = (int)$fila["id_exposicion"];
}

// Relaciones trabajo - estudios
$sql = "SELECT id_trabajo, id_estudio FROM trabajo_estudio";
$resultado = mysqli_query($conexion, $sql);
$estudios_trabajo = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $estudios_trabajo[$fila["id_trabajo"]][] = (int)$fila["id_estudio"];
}

// Obtener nombre del trabajo actual
$nombre_trabajo_actual = "";

if ($paciente["id_trabajo"] !== null) {
    foreach ($trabajos as $trabajo) {
        if ((int)$trabajo["id_trabajo"] === (int)$paciente["id_trabajo"]) {
            $nombre_trabajo_actual = $trabajo["nombre_t"];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar paciente</title>
</head>
<body>

<h1>Editar paciente</h1>

<form action="actualizar.php" method="POST">

    <input type="hidden" name="dni_p" value="<?php echo htmlspecialchars($paciente["dni_p"]); ?>">

    <h2>Datos personales</h2>

    <p>DNI:
        <input type="text" value="<?php echo htmlspecialchars($paciente["dni_p"]); ?>" disabled>
    </p>

    <p>Nombre:
        <input type="text" name="nombre_p" maxlength="30" value="<?php echo htmlspecialchars($paciente["nombre_p"]); ?>" required>
    </p>

    <p>Apellido:
        <input type="text" name="apellido" maxlength="30" value="<?php echo htmlspecialchars($paciente["apellido"]); ?>" required>
    </p>

    <p>CUIL:
        <input type="text" name="cuil" maxlength="11" value="<?php echo htmlspecialchars($paciente["cuil"] ?? ""); ?>">
    </p>

    <p>Teléfono:
        <input type="text" name="telefono" maxlength="20" value="<?php echo htmlspecialchars($paciente["telefono"]); ?>" required>
    </p>

    <p>Mail:
        <input type="email" name="mail" maxlength="100" value="<?php echo htmlspecialchars($paciente["mail"]); ?>" required>
    </p>

    <p>Obra social:
        <input type="text" name="obra_social" maxlength="50" value="<?php echo htmlspecialchars($paciente["obra_social"]); ?>" required>
    </p>

    <hr>

    <h2>Trabajo</h2>

    <p>Buscar o escribir trabajo:</p>

    <input
        type="text"
        name="nombre_trabajo"
        id="nombre_trabajo"
        list="trabajos_disponibles"
        maxlength="50"
        value="<?php echo htmlspecialchars($nombre_trabajo_actual); ?>"
        placeholder="Escribí para buscar un trabajo..."
        autocomplete="off"
    >

    <datalist id="trabajos_disponibles">
        <?php foreach ($trabajos as $trabajo) { ?>
            <option value="<?php echo htmlspecialchars($trabajo["nombre_t"]); ?>">
        <?php } ?>
    </datalist>

    <hr>

    <h2>Exposiciones del paciente</h2>

    <p>Podés buscar, agregar o quitar exposiciones.</p>

    <p>Buscar exposición:</p>
    <input type="text" id="buscar_exposiciones" placeholder="Escribí para buscar...">

    <div id="lista_exposiciones">

        <?php foreach ($exposiciones as $exposicion) { ?>
            <p class="opcion-exposicion">
                <label>
                    <input
                        type="checkbox"
                        name="exposiciones[]"
                        value="<?php echo $exposicion["id_exposicion"]; ?>"
                        data-exposicion="<?php echo $exposicion["id_exposicion"]; ?>"
                        <?php echo in_array((int)$exposicion["id_exposicion"], $exposiciones_paciente) ? "checked" : ""; ?>
                    >
                    <?php echo htmlspecialchars($exposicion["nombre_ex"]); ?>
                </label>
            </p>
        <?php } ?>

        <?php if (count($exposiciones) == 0) { ?>
            <p>No hay exposiciones cargadas.</p>
        <?php } ?>

    </div>

    <p>Agregar una exposición nueva:</p>

    <input type="text" id="nueva_exposicion" maxlength="50" placeholder="Nombre de la exposición">

    <button type="button" onclick="agregarExposicion()">Agregar exposición</button>

    <div id="exposiciones_nuevas"></div>

    <hr>

    <h2>Estudios del paciente</h2>

    <p>Podés buscar, agregar o quitar estudios.</p>

    <p>Buscar estudio:</p>
    <input type="text" id="buscar_estudios" placeholder="Escribí para buscar...">

    <div id="lista_estudios">

        <?php foreach ($estudios as $estudio) { ?>
            <p class="opcion-estudio">
                <label>
                    <input
                        type="checkbox"
                        name="estudios[]"
                        value="<?php echo $estudio["id_estudio"]; ?>"
                        data-estudio="<?php echo $estudio["id_estudio"]; ?>"
                        <?php echo in_array((int)$estudio["id_estudio"], $estudios_paciente) ? "checked" : ""; ?>
                    >
                    <?php echo htmlspecialchars($estudio["nombre_es"]); ?>
                </label>
            </p>
        <?php } ?>

        <?php if (count($estudios) == 0) { ?>
            <p>No hay estudios cargados.</p>
        <?php } ?>

    </div>

    <p>Agregar un estudio nuevo:</p>

    <input type="text" id="nuevo_estudio" maxlength="50" placeholder="Nombre del estudio">

    <button type="button" onclick="agregarEstudio()">Agregar estudio</button>

    <div id="estudios_nuevos"></div>

    <br><br>

    <input type="submit" value="Guardar cambios">

</form>

<br>
<a href="listar.php">Volver al listado de pacientes</a>

<script>
const trabajos = <?php echo json_encode($trabajos); ?>;

const exposicionesPorTrabajo = <?php echo json_encode($exposiciones_trabajo); ?>;
const estudiosPorTrabajo = <?php echo json_encode($estudios_trabajo); ?>;

const exposicionesOriginales = <?php echo json_encode($exposiciones_paciente); ?>;
const estudiosOriginales = <?php echo json_encode($estudios_paciente); ?>;

const nombreTrabajoOriginal = <?php echo json_encode($nombre_trabajo_actual); ?>;

const selectorTrabajo = document.getElementById("nombre_trabajo");

// Buscar el trabajo escrito
function obtenerTrabajo() {
    const nombre = selectorTrabajo.value.trim().toLowerCase();

    return trabajos.find(trabajo =>
        trabajo.nombre_t.toLowerCase() === nombre
    );
}

// Actualizar exposiciones y estudios cuando cambia el trabajo
function actualizarSelecciones() {

    const trabajo = obtenerTrabajo();

    let exposicionesSeleccionadas = [];
    let estudiosSeleccionados = [];

    if (
        selectorTrabajo.value.trim().toLowerCase() ===
        nombreTrabajoOriginal.trim().toLowerCase()
    ) {
        exposicionesSeleccionadas = exposicionesOriginales;
        estudiosSeleccionados = estudiosOriginales;

    } else if (trabajo) {

        exposicionesSeleccionadas =
            exposicionesPorTrabajo[trabajo.id_trabajo] || [];

        estudiosSeleccionados =
            estudiosPorTrabajo[trabajo.id_trabajo] || [];
    }

    document.querySelectorAll("[data-exposicion]").forEach(casilla => {
        casilla.checked = exposicionesSeleccionadas.includes(
            Number(casilla.dataset.exposicion)
        );
    });

    document.querySelectorAll("[data-estudio]").forEach(casilla => {
        casilla.checked = estudiosSeleccionados.includes(
            Number(casilla.dataset.estudio)
        );
    });
}

selectorTrabajo.addEventListener("change", actualizarSelecciones);

// Buscar exposiciones
document.getElementById("buscar_exposiciones").addEventListener("input", function() {

    const busqueda = this.value.toLowerCase();

    document.querySelectorAll(".opcion-exposicion").forEach(opcion => {

        opcion.style.display =
            opcion.textContent.toLowerCase().includes(busqueda) ? "" : "none";
    });
});

// Buscar estudios
document.getElementById("buscar_estudios").addEventListener("input", function() {

    const busqueda = this.value.toLowerCase();

    document.querySelectorAll(".opcion-estudio").forEach(opcion => {

        opcion.style.display =
            opcion.textContent.toLowerCase().includes(busqueda) ? "" : "none";
    });
});

// Agregar exposición nueva
function agregarExposicion() {

    const campo = document.getElementById("nueva_exposicion");
    const nombre = campo.value.trim();

    if (nombre === "") {
        alert("Escribí el nombre de la exposición.");
        return;
    }

    const existente = Array.from(
        document.querySelectorAll("[data-exposicion]")
    ).find(casilla =>
        casilla.parentElement.textContent.trim().toLowerCase() === nombre.toLowerCase()
    );

    if (existente) {
        existente.checked = true;
        campo.value = "";
        return;
    }

    const repetida = Array.from(
        document.querySelectorAll('input[name="nueva_exposicion[]"]')
    ).some(input => input.value.toLowerCase() === nombre.toLowerCase());

    if (repetida) {
        alert("Esa exposición ya fue agregada.");
        return;
    }

    const oculto = document.createElement("input");
    oculto.type = "hidden";
    oculto.name = "nueva_exposicion[]";
    oculto.value = nombre;

    const elemento = document.createElement("p");
    elemento.textContent = "Se agregará: " + nombre + " ";

    const quitar = document.createElement("button");
    quitar.type = "button";
    quitar.textContent = "Quitar";
    quitar.onclick = function() {
        oculto.remove();
        elemento.remove();
    };

    elemento.appendChild(quitar);

    document.getElementById("exposiciones_nuevas").appendChild(oculto);
    document.getElementById("exposiciones_nuevas").appendChild(elemento);

    campo.value = "";
}

// Agregar estudio nuevo
function agregarEstudio() {

    const campo = document.getElementById("nuevo_estudio");
    const nombre = campo.value.trim();

    if (nombre === "") {
        alert("Escribí el nombre del estudio.");
        return;
    }

    const existente = Array.from(
        document.querySelectorAll("[data-estudio]")
    ).find(casilla =>
        casilla.parentElement.textContent.trim().toLowerCase() === nombre.toLowerCase()
    );

    if (existente) {
        existente.checked = true;
        campo.value = "";
        return;
    }

    const repetido = Array.from(
        document.querySelectorAll('input[name="nuevo_estudio[]"]')
    ).some(input => input.value.toLowerCase() === nombre.toLowerCase());

    if (repetido) {
        alert("Ese estudio ya fue agregado.");
        return;
    }

    const oculto = document.createElement("input");
    oculto.type = "hidden";
    oculto.name = "nuevo_estudio[]";
    oculto.value = nombre;

    const elemento = document.createElement("p");
    elemento.textContent = "Se agregará: " + nombre + " ";

    const quitar = document.createElement("button");
    quitar.type = "button";
    quitar.textContent = "Quitar";
    quitar.onclick = function() {
        oculto.remove();
        elemento.remove();
    };

    elemento.appendChild(quitar);

    document.getElementById("estudios_nuevos").appendChild(oculto);
    document.getElementById("estudios_nuevos").appendChild(elemento);

    campo.value = "";
}
</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
