<?php
// Archivo de configuración para hosting
// Cambia estos valores según tu hosting

// Configuración de la base de datos
$host = 'localhost'; // O la IP de tu servidor MySQL
$dbname = 'tu_nombre_base_datos'; // Cambia por el nombre de tu base de datos
$username = 'tu_usuario_mysql'; // Cambia por tu usuario de MySQL
$password = 'tu_password_mysql'; // Cambia por tu contraseña de MySQL

// Crear conexión
$conn = new mysqli($host, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8");

// Configurar zona horaria (opcional)
date_default_timezone_set('America/Lima');

// Configuraciones adicionales para hosting
ini_set('display_errors', 0); // Ocultar errores en producción
ini_set('log_errors', 1); // Guardar errores en log
error_reporting(E_ALL);

// Configurar límites de memoria y tiempo si es necesario
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

?> 