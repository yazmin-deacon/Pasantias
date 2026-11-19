
<?php

// =========================================
// DUCCA - VERIFICAR SESIÓN
// =========================================

// Iniciar la sesión si todavía no está iniciada.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Comprobar que el usuario inició sesión.
if (!isset($_SESSION["dni"])) {
    header("Location: /pasantias/inicio%20sesion/login.html");
    exit();
}
