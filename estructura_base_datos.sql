-- Estructura de Base de Datos para Control de Pagos
-- Ejecutar este archivo en tu hosting para crear las tablas necesarias

-- Crear base de datos (si no existe)
-- CREATE DATABASE IF NOT EXISTS control_internet;
-- USE control_internet;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('admin', 'usuario') DEFAULT 'usuario',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(8) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    direccion TEXT NOT NULL,
    tarifa DECIMAL(10,2) NOT NULL,
    fecha_inicio_servicio DATE NOT NULL,
    fecha_ultimo_pago DATE NULL,
    fecha_proximo_pago DATE NULL,
    estado_pago ENUM('Pagado', 'Pendiente', 'Vencido') DEFAULT 'Pendiente',
    estado_cliente ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    latitud DECIMAL(10,8) NULL,
    longitud DECIMAL(11,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de pagos
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    fecha_pago DATE NOT NULL,
    monto_pagado DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    periodo_cubierto VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabla de historial de búsquedas (opcional)
CREATE TABLE IF NOT EXISTS busquedas_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    termino_busqueda VARCHAR(255) NOT NULL,
    criterios TEXT,
    fecha_busqueda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_fecha (usuario_id, fecha_busqueda),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla para almacenar consultas de DNI (versión extendida)
CREATE TABLE IF NOT EXISTS dni_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(8) NOT NULL,
    nombres VARCHAR(100),
    apellidoPaterno VARCHAR(100),
    apellidoMaterno VARCHAR(100),
    nombreCompleto VARCHAR(255),
    tipoDocumento VARCHAR(10),
    numeroDocumento VARCHAR(15),
    digitoVerificador VARCHAR(5),
    nombre VARCHAR(255) NOT NULL,
    fecha_consulta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insertar usuario administrador por defecto
-- Usuario: admin
-- Contraseña: admin123
INSERT INTO usuarios (username, password, nombre, rol) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Crear índices para mejorar el rendimiento
CREATE INDEX idx_clientes_dni ON clientes(dni);
CREATE INDEX idx_clientes_nombre ON clientes(nombre);
CREATE INDEX idx_clientes_estado ON clientes(estado_cliente, estado_pago);
CREATE INDEX idx_pagos_cliente_fecha ON pagos(cliente_id, fecha_pago);
CREATE INDEX idx_pagos_periodo ON pagos(periodo_cubierto); 