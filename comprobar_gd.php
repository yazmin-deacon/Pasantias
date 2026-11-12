
<?php

if (extension_loaded("gd")) {
    echo "GD ACTIVADO CORRECTAMENTE";
} else {
    echo "GD NO ESTA ACTIVADO";
}

echo "<p>PHP INI: " .
    htmlspecialchars(php_ini_loaded_file() ?: "Ninguno") .
    "</p>";
?>
