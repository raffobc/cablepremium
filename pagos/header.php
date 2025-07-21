<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<link rel="stylesheet" href="style.css">
<head>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<div class="navbar-bg">
    <div class="navbar">
        <!-- Usuario + botón cerrar sesión -->
        <div class="navbar-user">
            <?php if (isset($_SESSION['usuario_id'])): ?>
                <span style="font-size:0.98em; color:#FFFFFF;">
                    <?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? $_SESSION['usuario_username']); ?>
                </span>
                <!-- <a href="logout.php" class="logout-btn">Cerrar sesión</a> -->
            <?php endif; ?>
        </div>
        <!-- Botón de menú móvil -->
        <button id="menu-toggle" class="menu-toggle" aria-label="Abrir menú">&#9776;</button>
        <!-- Enlaces de navegación -->
        <div class="navbar-links" id="navbar-links">
            <a href="index.php">Inicio</a>
            <a href="clientes.php">Gestión de Clientes</a>
            <a href="mapa_clientes.php">Mapa de Clientes</a>
            <a href="pagos.php">Registrar Pagos</a>
            <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin'): ?>
                <a href="usuarios.php">Usuarios</a>
                <a href="estadisticas.php">Estadísticas</a>
            <?php endif; ?>
            <a href="mi_cuenta.php">Mi cuenta</a>
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('menu-toggle');
    var links = document.getElementById('navbar-links');
    if (toggle && links) {
        toggle.addEventListener('click', function(e) {
            links.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 600 && links.classList.contains('show')) {
                if (!links.contains(e.target) && e.target !== toggle) {
                    links.classList.remove('show');
                }
            }
        });
    }
});
</script> 