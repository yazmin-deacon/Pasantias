
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["dni"])) {
    header("Location: /pasantias/inicio%20sesion/login.html");
    exit();
}
