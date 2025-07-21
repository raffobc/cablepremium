# 🚀 Guía para Subir Control de Pagos a Hosting

## 📋 **Requisitos del Hosting**

Tu hosting debe tener:
- ✅ **PHP 7.4 o superior**
- ✅ **MySQL 5.7 o superior** (o MariaDB 10.2+)
- ✅ **Soporte para sesiones PHP**
- ✅ **Acceso a phpMyAdmin** (recomendado)

## 📁 **Archivos Incluidos**

### **Archivos Principales:**
- `control_pagos_sistema.zip` - Todos los archivos PHP y CSS
- `estructura_base_datos.sql` - Estructura de la base de datos
- `conexion_hosting.php` - Configuración de ejemplo
- `README_HOSTING.md` - Esta guía

## 🔧 **Pasos para la Instalación**

### **Paso 1: Crear Base de Datos**

1. **Accede a tu panel de hosting** (cPanel, Plesk, etc.)
2. **Ve a phpMyAdmin** o gestor de bases de datos
3. **Crea una nueva base de datos:**
   - Nombre: `control_internet` (o el que prefieras)
   - Collation: `utf8mb4_unicode_ci`
4. **Importa la estructura:**
   - Ve a la pestaña "Importar"
   - Selecciona el archivo `estructura_base_datos.sql`
   - Haz clic en "Continuar"

### **Paso 2: Subir Archivos**

1. **Accede al administrador de archivos** de tu hosting
2. **Ve a la carpeta `public_html`** (o `www`, `htdocs`)
3. **Crea una carpeta** llamada `control_pagos`
4. **Sube todos los archivos** del ZIP a esa carpeta

### **Paso 3: Configurar Conexión**

1. **Edita el archivo `conexion.php`:**
   ```php
   $host = 'localhost';
   $dbname = 'tu_nombre_base_datos'; // El nombre que creaste
   $username = 'tu_usuario_mysql';   // Tu usuario de MySQL
   $password = 'tu_password_mysql';  // Tu contraseña de MySQL
   ```

2. **Guarda los cambios**

### **Paso 4: Configurar Permisos**

1. **Establece permisos de archivos:**
   - Archivos PHP: `644`
   - Archivos CSS: `644`
   - Carpetas: `755`

### **Paso 5: Probar la Instalación**

1. **Ve a tu sitio:** `https://tudominio.com/control_pagos/`
2. **Deberías ver la página de login**
3. **Credenciales por defecto:**
   - Usuario: `admin`
   - Contraseña: `admin123`

## 🔐 **Configuraciones de Seguridad**

### **Cambiar Contraseña por Defecto**
1. **Accede al sistema** con las credenciales por defecto
2. **Ve a "Usuarios"** (solo admin puede acceder)
3. **Edita el usuario admin** y cambia la contraseña

### **Configuraciones Recomendadas**
1. **Edita `conexion.php`** y agrega:
   ```php
   // Ocultar errores en producción
   ini_set('display_errors', 0);
   ini_set('log_errors', 1);
   ```

2. **Configura HTTPS** en tu hosting

## 🌐 **URLs del Sistema**

- **Login:** `https://tudominio.com/control_pagos/login.php`
- **Dashboard:** `https://tudominio.com/control_pagos/index.php`
- **Clientes:** `https://tudominio.com/control_pagos/clientes.php`
- **Pagos:** `https://tudominio.com/control_pagos/pagos.php`
- **Mapa:** `https://tudominio.com/control_pagos/mapa_clientes.php`

## 📱 **Acceso Móvil**

El sistema es **completamente responsivo** y funciona en:
- ✅ **Smartphones**
- ✅ **Tablets**
- ✅ **Computadoras**

## 🔧 **Solución de Problemas**

### **Error de Conexión a Base de Datos**
- Verifica que los datos de conexión sean correctos
- Asegúrate de que la base de datos existe
- Confirma que el usuario tiene permisos

### **Página en Blanco**
- Verifica que PHP esté habilitado
- Revisa los logs de errores del hosting
- Confirma que todos los archivos se subieron correctamente

### **Errores de Permisos**
- Verifica que los archivos tengan permisos 644
- Confirma que las carpetas tengan permisos 755

### **Problemas con Sesiones**
- Verifica que las sesiones PHP estén habilitadas
- Confirma que la zona horaria esté configurada

## 📞 **Soporte**

Si tienes problemas:
1. **Revisa los logs de errores** de tu hosting
2. **Verifica la configuración** de la base de datos
3. **Confirma que todos los archivos** están en su lugar

## 🎯 **Funcionalidades del Sistema**

- ✅ **Gestión de Clientes** (agregar, editar, activar/desactivar)
- ✅ **Registro de Pagos** (individual y múltiple)
- ✅ **Mapa de Clientes** con geolocalización
- ✅ **Estadísticas** y reportes
- ✅ **Gestión de Usuarios** (admin/usuario)
- ✅ **Búsqueda de Clientes**
- ✅ **Reporte de Pagos Pendientes**
- ✅ **Interfaz Responsiva**

¡Tu sistema de control de pagos está listo para usar! 🚀 