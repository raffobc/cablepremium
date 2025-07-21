<?php
session_start();
require_once 'utils.php';
checkSessionInactivity();
require_any_role(['admin','usuario']);
include 'header.php';
require_once 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nuevo Cliente</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Agregar Nuevo Cliente</h2>
    <form action="clientes.php" method="POST" class="card p-4 shadow-sm">
        <input type="hidden" name="agregar_cliente" value="1">
        <div class="mb-3">
            <label for="dni" class="form-label">DNI:</label>
            <input type="text" id="dni" name="dni" pattern="\d{8}" maxlength="8" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre:</label>
            <input type="text" id="nombre" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección:</label>
            <input type="text" id="direccion" name="direccion" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="tarifa" class="form-label">Tarifa (S/):</label>
            <input type="number" step="0.01" id="tarifa" name="tarifa" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="costo_instalacion" class="form-label">Costo de Instalación (S/):</label>
            <input type="number" step="0.01" id="costo_instalacion" name="costo_instalacion" min="0" class="form-control" placeholder="Ej: 50.00">
        </div>
        <div class="mb-3">
            <label for="fecha_inicio_servicio" class="form-label">Fecha de Inicio del Servicio:</label>
            <input type="date" id="fecha_inicio_servicio" name="fecha_inicio_servicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="mb-3">
            <label for="latitud" class="form-label">Latitud (GPS):</label>
            <input type="text" id="latitud" name="latitud" pattern="^-?\d{1,2}\.\d+" step="any" class="form-control" required readonly>
        </div>
        <div class="mb-3">
            <label for="longitud" class="form-label">Longitud (GPS):</label>
            <input type="text" id="longitud" name="longitud" pattern="^-?\d{1,3}\.\d+" step="any" class="form-control" required readonly>
        </div>
        <button type="submit" class="btn btn-success w-100">Agregar Cliente</button>
        <a href="clientes.php" class="btn btn-secondary w-100 mt-2">Cancelar</a>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Geolocalización
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitud').value = position.coords.latitude.toFixed(6);
            document.getElementById('longitud').value = position.coords.longitude.toFixed(6);
        }, function(error) { console.warn('No se pudo obtener la ubicación:', error.message); });
    } else { console.warn('Geolocalización no soportada por este navegador.'); }

    // Autocompletar nombre por DNI usando backend seguro
    const dniInput = document.getElementById('dni');
    const nombreInput = document.getElementById('nombre');
    const direccionInput = document.getElementById('direccion');
    if (dniInput) {
        dniInput.addEventListener('input', function() {
            nombreInput.value = '';
            direccionInput.value = '';
            if (/^\d{8}$/.test(dniInput.value)) {
                fetch('consulta_dni.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'dni=' + encodeURIComponent(dniInput.value)
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.nombre) {
                        nombreInput.value = data.nombre;
                    }
                })
                .catch(err => {
                    nombreInput.value = '';
                    direccionInput.value = '';
                });
            }
        });
    }
});
</script>
<?php include 'footer.php'; ?>
</body>
</html> 