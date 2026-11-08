
<?php
require_once __DIR__ . "/../Pacientes/conexion.php";

// =========================================
// 1. CONSULTAR OBRAS SOCIALES
// =========================================

$sql = "SELECT id_obra_social, nombre_os, activa
        FROM obra_social
        ORDER BY nombre_os ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die("Error al consultar obras sociales.");
}

$obrasSociales = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

mysqli_free_result($resultado);

// =========================================
// 2. FUNCIÓN PARA MOSTRAR TEXTO SEGURO
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

    <title>Agregar obra social</title>

    <!-- Conservá acá tu enlace CSS si ya tenés uno -->

</head>

<body>

<h1>Agregar obra social</h1>

<p>
    Escribí el nombre de la obra social.
    Mientras escribís, aparecerán las que
    ya están registradas.
</p>

<form action="guardar.php"
      method="POST"
      id="formObraSocial">

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
            placeholder="Ej: OSDE, ISSN, PAMI..."
            autocomplete="off"
            required
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

    <!-- MENSAJE DE VALIDACIÓN -->

    <p id="mensaje_nombre"
       aria-live="polite"></p>

    <!-- GUARDAR -->

    <p>
        <input
            type="submit"
            id="boton_guardar"
            value="Guardar obra social"
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
// 1. OBRAS SOCIALES DESDE MYSQL
// =========================================

const obrasSociales = <?php echo json_encode(
    $obrasSociales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

// =========================================
// 2. ELEMENTOS HTML
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
// 3. NORMALIZAR NOMBRES
// =========================================

function normalizar(texto) {

    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");

}

// =========================================
// 4. BUSCAR SI YA EXISTE
// =========================================

function buscarDuplicado() {

    const nombre = normalizar(
        campoNombre.value
    );

    if (!nombre) {
        return null;
    }

    return obrasSociales.find(obra =>
        normalizar(obra.nombre_os) === nombre
    ) || null;

}

// =========================================
// 5. VALIDAR NOMBRE
// =========================================

function validarNombre() {

    const nombre = campoNombre.value.trim();

    if (nombre === "") {

        mensaje.textContent = "";
        botonGuardar.disabled = false;

        return false;
    }

    if (
        normalizar(nombre) ===
        normalizar("Particular / Sin obra social")
    ) {

        mensaje.textContent =
            "Ese nombre está reservado para pacientes " +
            "sin obra social.";

        botonGuardar.disabled = true;
        return false;
    }

    const duplicado = buscarDuplicado();

    if (duplicado) {

        mensaje.textContent =
            "Esta obra social ya está registrada: " +
            duplicado.nombre_os +
            (Number(duplicado.activa) === 1
                ? " (Activa)."
                : " (Inactiva).");

        botonGuardar.disabled = true;
        return false;
    }

    mensaje.textContent =
        "Nombre disponible. Se registrará al guardar.";

    botonGuardar.disabled = false;

    return true;
}

// =========================================
// 6. MOSTRAR SUGERENCIAS
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
// 8. VALIDAR ANTES DE ENVIAR
// =========================================

formulario.addEventListener(
    "submit",
    function (evento) {

        if (!validarNombre()) {

            evento.preventDefault();

            alert(
                "Revisá el nombre de la obra social."
            );
        }
    }
);

</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
