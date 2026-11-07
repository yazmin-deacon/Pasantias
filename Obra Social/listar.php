
<?php
require_once "conexion.php";

// =========================================
// CONSULTAR OBRAS SOCIALES
// =========================================

$sql = "SELECT id_obra_social, nombre_os
        FROM obra_social
        ORDER BY nombre_os ASC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    die(
        "Error al consultar obras sociales: " .
        mysqli_error($conexion)
    );
}

$obrasSociales = mysqli_fetch_all(
    $resultado,
    MYSQLI_ASSOC
);

$cantidad = count($obrasSociales);

mysqli_free_result($resultado);

// =========================================
// FUNCIÓN PARA MOSTRAR DATOS SEGUROS
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

    <title>Administrar obras sociales</title>

    <!-- Acá podés colocar tu CSS -->

</head>

<body>

<h1>Administrar obras sociales</h1>

<!-- ================================= -->
<!-- BUSCAR O REGISTRAR OBRA SOCIAL -->
<!-- ================================= -->

<h2>Buscar obra social</h2>

<p>
    Escribí el nombre de una obra social para buscarla.
</p>

<p>
    Si no existe, escribí su nombre completo
    y presioná Enter para registrarla.
</p>

<form
    action="guardar.php"
    method="POST"
    id="formularioObraSocial"
>

    <!-- TOKEN DE SEGURIDAD -->

    <input
        type="hidden"
        name="token"
        value="<?php
            echo escapar(
                $_SESSION["token_obras_sociales"] ?? ""
            );
        ?>"
    >

    <!-- BUSCADOR -->

    <input
        type="text"
        name="nombre_os"
        id="buscar_obra_social"
        maxlength="100"
        placeholder="Buscar o escribir obra social..."
        autocomplete="off"
        style="width:350px;max-width:100%;"
    >

</form>

<p
    id="mensaje_busqueda"
    aria-live="polite"
></p>

<hr>

<!-- ================================= -->
<!-- CONTADORES -->
<!-- ================================= -->

<p>
    Obras sociales registradas:
    <strong>
        <?php echo $cantidad; ?>
    </strong>
</p>

<p>
    Obras sociales encontradas:
    <strong id="cantidad_encontradas">
        <?php echo $cantidad; ?>
    </strong>
</p>

<!-- ================================= -->
<!-- TABLA DE OBRAS SOCIALES -->
<!-- ================================= -->

<?php if ($cantidad === 0) { ?>

    <p>
        Todavía no hay obras sociales registradas.
    </p>

<?php } else { ?>

    <table
        border="1"
        cellpadding="8"
        id="tabla_obras_sociales"
    >

        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Editar</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($obrasSociales as $obra) { ?>

            <tr
                class="fila_obra_social"
                data-nombre="<?php
                    echo escapar($obra["nombre_os"]);
                ?>"
            >

                <!-- ID -->

                <td>
                    <?php
                        echo (int)$obra["id_obra_social"];
                    ?>
                </td>

                <!-- NOMBRE -->

                <td>
                    <?php
                        echo escapar($obra["nombre_os"]);
                    ?>
                </td>

                <!-- EDITAR -->

                <td>

                    <a href="editar.php?id_obra_social=<?php
                        echo (int)$obra["id_obra_social"];
                    ?>">
                        Editar
                    </a>

                </td>

            </tr>

        <?php } ?>

        </tbody>

    </table>

<?php } ?>

<!-- ================================= -->
<!-- VOLVER A PACIENTES -->
<!-- ================================= -->

<br>

<p>
    <a href="../Pacientes/listar.php">
        Volver al listado de pacientes
    </a>
</p>

<!-- ================================= -->
<!-- JAVASCRIPT -->
<!-- ================================= -->

<script>

// =========================================
// OBRAS SOCIALES DESDE MYSQL
// =========================================

const obrasSociales = <?php echo json_encode(
    $obrasSociales,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
); ?>;

// =========================================
// ELEMENTOS DEL FORMULARIO
// =========================================

const buscador = document.getElementById(
    "buscar_obra_social"
);

const formulario = document.getElementById(
    "formularioObraSocial"
);

const mensaje = document.getElementById(
    "mensaje_busqueda"
);

const contador = document.getElementById(
    "cantidad_encontradas"
);

const filas = document.querySelectorAll(
    ".fila_obra_social"
);

// =========================================
// NORMALIZAR NOMBRES
// =========================================

function normalizar(texto) {
    return String(texto)
        .trim()
        .replace(/\s+/g, " ")
        .toLocaleLowerCase("es");
}

// =========================================
// BUSCAR UNA OBRA SOCIAL EXACTA
// =========================================

function buscarObraExacta(nombre) {

    const nombreNormalizado = normalizar(nombre);

    return obrasSociales.find(obra =>
        normalizar(obra.nombre_os) === nombreNormalizado
    ) || null;
}

// =========================================
// FILTRAR MIENTRAS SE ESCRIBE
// =========================================

function filtrarObrasSociales() {

    const texto = normalizar(buscador.value);

    let encontradas = 0;

    filas.forEach(function(fila) {

        const nombre = normalizar(
            fila.dataset.nombre
        );

        const coincide = nombre.includes(texto);

        fila.style.display = coincide
            ? ""
            : "none";

        if (coincide) {
            encontradas++;
        }
    });

    contador.textContent = encontradas;

    if (texto === "") {
        mensaje.textContent = "";
        return;
    }

    // NOMBRE RESERVADO

    if (
        texto === normalizar(
            "Particular / Sin obra social"
        )
    ) {
        mensaje.textContent =
            "Ese nombre está reservado para pacientes " +
            "sin obra social.";
        return;
    }

    // OBRA SOCIAL EXISTENTE

    const obraExacta = buscarObraExacta(
        buscador.value
    );

    if (obraExacta) {

        mensaje.textContent =
            "Esta obra social ya está registrada: " +
            obraExacta.nombre_os;

        return;
    }

    // POSIBLES COINCIDENCIAS

    if (encontradas > 0) {

        mensaje.textContent =
            "Se encontraron " +
            encontradas +
            " obras sociales similares. " +
            "Si es una obra social nueva, " +
            "presioná Enter para registrarla.";

    } else {

        mensaje.textContent =
            "No existe una obra social con ese nombre. " +
            "Presioná Enter para registrarla.";
    }
}

// =========================================
// BUSCAR AUTOMÁTICAMENTE
// =========================================

buscador.addEventListener(
    "input",
    filtrarObrasSociales
);

// =========================================
// REGISTRAR AL PRESIONAR ENTER
// =========================================

formulario.addEventListener(
    "submit",
    function(evento) {

        const nombre = buscador.value
            .trim()
            .replace(/\s+/g, " ");

        // VALIDAR NOMBRE VACÍO

        if (nombre === "") {

            evento.preventDefault();

            mensaje.textContent =
                "Escribí el nombre de la obra social.";

            return;
        }

        // VALIDAR NOMBRE RESERVADO

        if (
            normalizar(nombre) ===
            normalizar("Particular / Sin obra social")
        ) {

            evento.preventDefault();

            mensaje.textContent =
                "Ese nombre está reservado y " +
                "no se puede registrar.";

            return;
        }

        // VALIDAR DUPLICADOS

        const existente = buscarObraExacta(nombre);

        if (existente) {

            evento.preventDefault();

            mensaje.textContent =
                "La obra social " +
                existente.nombre_os +
                " ya está registrada.";

            return;
        }

        // CONFIRMAR NUEVA OBRA SOCIAL

        const confirmar = confirm(
            '¿Querés registrar la obra social "' +
            nombre +
            '"?'
        );

        if (!confirmar) {

            evento.preventDefault();
            return;
        }

        // ENVIAR AL GUARDADO

        buscador.value = nombre;
    }
);

</script>

</body>
</html>

<?php mysqli_close($conexion); ?>
