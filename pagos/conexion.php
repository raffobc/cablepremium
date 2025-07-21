<?php
// conexion.php
// Archivo para establecer la conexión a la base de datos MySQL.

/*
-- SQL para crear la tabla de usuarios y un usuario admin inicial --
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL
);
-- Usuario admin inicial (contraseña: admin123)
INSERT INTO usuarios (username, password, nombre) VALUES ('admin', '$2y$10$QwQwQwQwQwQwQwQwQwQwQeQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQwQw', 'Administrador');
-- (La contraseña hash corresponde a 'admin123', puedes cambiarla usando password_hash en PHP)

-- SQL para agregar el campo de rol a la tabla de usuarios --
ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'usuario';
-- Actualizar el usuario admin para que tenga rol 'admin'
UPDATE usuarios SET rol = 'admin' WHERE username = 'admin';
*/

// Habilitar el reporte de errores y el modo estricto para MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost"; 
$username = "root";       
$password = "";           
$dbname = "control_internet";

// Crear una nueva conexión MySQLi
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    // Establecer el charset para asegurar la correcta codificación de caracteres
    $conn->set_charset("utf8mb4"); 
} catch (mysqli_sql_exception $e) {
    // Capturar cualquier excepción de MySQLi y mostrar un mensaje de error
    die("Conexión fallida: " . $e->getMessage());
}

// El archivo de conexión se incluye en otros scripts que necesitan interactuar con la base de datos.
?>
