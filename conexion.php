<?php
// ===== BLOQUE 1 — Modo de aviso de errores y datos de conexión =====
mysqli_report(MYSQLI_REPORT_OFF);
$servidor = "localhost";
$usuario  = "root";
$clave    = "";
$base     = "escuela";
 
// ===== BLOQUE 2 — Abrir la conexión =====
$conexion = mysqli_connect($servidor, $usuario, $clave, $base);
 
// ===== BLOQUE 3 — Controlar si la conexión falló =====
if (!$conexion) {
    echo "Error de conexión: " . mysqli_connect_error();
    exit;
}
 
// ===== BLOQUE 4 — Definir el juego de caracteres =====
mysqli_set_charset($conexion, "utf8mb4");
?>
