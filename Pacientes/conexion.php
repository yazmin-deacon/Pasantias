
<?php
// =========================================
// 1. DATOS DE CONEXIÓN
// =========================================

mysqli_report(MYSQLI_REPORT_OFF);

$servidor = "localhost";
$usuario = "root";
$clave = "";
$base = "clinica";

// =========================================
// 2. CONECTAR A MYSQL
// =========================================

$conexion = mysqli_connect(
    $servidor,
    $usuario,
    $clave,
    $base
);

// =========================================
// 3. VERIFICAR CONEXIÓN
// =========================================

if (!$conexion) {
    die("Error de conexión a la base de datos.");
}

// =========================================
// 4. CONFIGURAR CARACTERES
// =========================================

mysqli_set_charset($conexion, "utf8mb4");
?>