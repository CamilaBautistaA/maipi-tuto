<?php
// ============================================================
//  login.php  -  Valida correo + DNI (contraseña) y abre sesión
// ============================================================
session_start();

// Solo acepta envíos del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// ---- Conexión ----
$host       = "localhost";
$usuario    = "root";
$password   = "";            // vacío si tu MySQL no tiene contraseña
$base_datos = "universidad";

$conexion = new mysqli($host, $usuario, $password, $base_datos);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
$conexion->set_charset("utf8mb4");

// ---- Datos del formulario ----
$correo_ingresado = trim($_POST['correo'] ?? '');
$clave_ingresada  = trim($_POST['clave'] ?? '');   // el DNI

// ---- Buscar al alumno ----
$sql  = "SELECT email_institucional FROM sheet1
         WHERE email_institucional = ? AND numero_doc_identidad = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $correo_ingresado, $clave_ingresada);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $alumno = $resultado->fetch_assoc();

    session_regenerate_id(true);                         // seguridad de sesión
    $_SESSION['email'] = $alumno['email_institucional']; // dashboard.php lee esto

    $stmt->close();
    $conexion->close();
    header('Location: dashboard.php');
    exit;
}

// ---- Datos incorrectos: vuelve al login con aviso ----
$stmt->close();
$conexion->close();
header('Location: index.html?error=1');
exit;