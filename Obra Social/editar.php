
<?php
require_once "conexion.php";

// =========================================
// 1. OBTENER ID
// =========================================

$id = filter_input(
    INPUT_GET,
    "id_obra_social",
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    die("ID de obra social no válido.");
}

// =========================================
// 2. CONSULTAR OBRA SOCIAL ACTUAL
// =========================================

$sql = "SELECT id_obra_social, nombre_os, activa
        FROM obra_social
        WHERE id_obra_social = ?";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta.");
}

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

$obra = mysqli_fetch_assoc($resultado);

mysqli_stmt_close($stmt);

if (!$obra) {
    die("No se encontró la obra social.");
}

// =========================================
// 3. CONSULTAR TODAS LAS OBRAS SOCIALES
// =========================================

$sql = "SELECT id_obra_social, nombre_os, activa
        FROM obra_social
        ORDER BY nombre_os ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar las obras sociales.");
}

$obrasSociales = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

// =========================================
// 4. FUNCIÓN PARA MOSTRAR TEXTO SEGURO
// =========================================

if (!function_exists("escapar")) {
    function escapar($valor) {

        return htmlspecialchars(
            (string)($valor ?? ""),
            ENT_QUOTES,
            "UTF-8"
        );
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Editar obra social</title>

    <!-- Conservá acá tu enlace CSS actual -->

</head>

<body>

<h1>Editar obra social</h1>

<p>
    Podés modificar el nombre de esta obra social.
    El sistema comprobará que no exista otra
    con el mismo nombre.
</p>

<form
    action="actualizar.php"
    method="POST"
    id="formObraSocial"
>

    <!-- TOKEN DE SEGURIDAD -->

    <input
        type="hidden"
        name="token"
        value="<?php
            echo escapar(
                $_SESSION["token_obras_sociales"]
            );
        ?>"
    >

    <!-- ID -->

    <input
        type="hidden"
        name="id_obra_social"
        value="<?php
            echo (int)$obra["id_obra_social"];
        ?>"
    >

    <!-- NOMBRE -->

    <p>
        <label for="nombre_os">
            Nombre de la obra social:
        </label>
    </p>

    <div style="position:relative;max-width:420px;">

        <input
            type="text"
            name="nombre_os"
            id="nombre_os"
            maxlength="100"
            autocomplete="off"
            required
            value="<?php
                echo escapar($obra["nombre_os"]);
            ?>"
            style="width:100%;box-sizing:border-box;"
        >

        <div
            id="sugerencias"
            style="
                display:none;
                border:1px solid #ccc;
                background:white;
                max-height:200px;
                overflow-y:auto;
            "
        ></div>

    </div>

    <!-- MENSAJE -->

    <p id="mensaje_nombre"
       aria-live="polite"></p>

    <!-- ESTADO -->

    <p>
        Estado actual:

        <strong>
            <?php
                echo (int)$obra["activa"] === 1
                    ? "Activa"
                    : "Inactiva";
            ?>
        </strong>
    </p>

    <!-- GUARDAR -->

    <p>
        <input
            type="submit"
            id="boton_guardar"
            value="Guardar cambios"
        >
    </p>

</form>

<p>
    <a href="listar.php">
        Volver al listado
    </a>
</p>

<script>

// =========================================
// 1. DATOS
// =========================================

const idObraActual = <?php
    echo (int)$obra["id_obra_social"];
?>;

const nombreOriginal = <?php echo json_encode(
    $obra["nombre_os"],
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

const obrasSociales = <?php echo json_encode(
    $obrasSociales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

// =========================================
// 2. ELEMENTOS
// =========================================

const campoNombre = document.getElementById(
    "nombre_os"
);

const sugerencias = document.getElementById(
    "sugerencias"
);

const mensaje = document.getElementById(
    "mensaje_nombre"
);

const botonGuardar = document.getElementById(
    "boton_guardar"
);

const formulario = document.getElementById(
    "formObraSocial"
);

// =========================================
// 3. NORMALIZAR
// =========================================

function normalizar(texto) {

    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");

}

// =========================================
// 4. BUSCAR DUPLICADOS
// =========================================

function buscarDuplicado() {

    const nombre = normalizar(
        campoNombre.value
    );

    if (!nombre) {
        return null;
    }

    return obrasSociales.find(obra => {

        const mismoNombre =
            normalizar(obra.nombre_os) === nombre;

        const esOtroRegistro =
            Number(obra.id_obra_social) !== idObraActual;

        return mismoNombre && esOtroRegistro;

    }) || null;
}

// =========================================
// 5. VALIDAR
// =========================================

function validarNombre() {

    const nombre = campoNombre.value.trim();

    if (!nombre) {

        mensaje.textContent = "";
        botonGuardar.disabled = true;

        return false;
    }

    if (
        normalizar(nombre) ===
        normalizar("Particular / Sin obra social")
    ) {

        mensaje.textContent =
            "Este nombre está reservado para pacientes " +
            "sin obra social.";

        botonGuardar.disabled = true;
        return false;
    }

    const duplicada = buscarDuplicado();

    if (duplicada) {

        mensaje.textContent =
            "Ya existe otra obra social con ese nombre: " +
            duplicada.nombre_os;

        botonGuardar.disabled = true;
        return false;
    }

    if (
        normalizar(nombre) ===
        normalizar(nombreOriginal)
    ) {

        mensaje.textContent =
            "Estás editando la misma obra social.";

    } else {

        mensaje.textContent =
            "Nombre disponible para actualizar.";
    }

    botonGuardar.disabled = false;

    return true;
}

// =========================================
// 6. SUGERENCIAS
// =========================================

function mostrarSugerencias() {

    const busqueda = normalizar(
        campoNombre.value
    );

    sugerencias.replaceChildren();

    if (!busqueda) {

        sugerencias.style.display = "none";
        return;
    }

    const coincidencias = obrasSociales

        .filter(obra =>

            Number(obra.id_obra_social) !==
                idObraActual &&

            normalizar(obra.nombre_os)
                .includes(busqueda)

        )

        .sort((a, b) => {

            const aEmpieza = normalizar(
                a.nombre_os
            ).startsWith(busqueda);

            const bEmpieza = normalizar(
                b.nombre_os
            ).startsWith(busqueda);

            if (aEmpieza !== bEmpieza) {
                return aEmpieza ? -1 : 1;
            }

            return a.nombre_os.localeCompare(
                b.nombre_os,
                "es"
            );
        })

        .slice(0, 10);

    if (coincidencias.length === 0) {

        sugerencias.style.display = "none";
        return;
    }

    coincidencias.forEach(obra => {

        const opcion = document.createElement(
            "div"
        );

        opcion.textContent =
            obra.nombre_os +
            (Number(obra.activa) === 1
                ? " (Activa)"
                : " (Inactiva)");

        opcion.style.padding = "8px";
        opcion.style.cursor = "pointer";

        opcion.addEventListener("click", () => {

            campoNombre.value = obra.nombre_os;

            sugerencias.style.display = "none";

            validarNombre();
        });

        sugerencias.appendChild(opcion);
    });

    sugerencias.style.display = "block";
}

// =========================================
// 7. EVENTOS
// =========================================

campoNombre.addEventListener(
    "input",
    function () {

        mostrarSugerencias();
        validarNombre();

    }
);

campoNombre.addEventListener(
    "focus",
    mostrarSugerencias
);

campoNombre.addEventListener(
    "blur",
    function () {

        campoNombre.value =
            campoNombre.value
                .trim()
                .replace(/\s+/g, " ");

        validarNombre();

    }
);

document.addEventListener(
    "click",
    function (evento) {

        if (
            evento.target !== campoNombre &&
            !sugerencias.contains(evento.target)
        ) {
            sugerencias.style.display = "none";
        }
    }
);

// =========================================
// 8. VALIDACIÓN FINAL
// =========================================

formulario.addEventListener(
    "submit",
    function (evento) {

        if (!validarNombre()) {

            evento.preventDefault();

            alert(
                "Revisá el nombre antes de guardar."
            );
        }
    }
);

validarNombre();

</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
