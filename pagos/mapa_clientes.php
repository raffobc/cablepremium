<?php
session_start();
require_once 'utils.php';
require_any_role(['admin','usuario']);
include 'header.php';
require_once 'conexion.php';

// Obtener todos los clientes con coordenadas y fechas de pago
$sql = "SELECT id, nombre, direccion, latitud, longitud, estado_pago, fecha_proximo_pago FROM clientes WHERE latitud IS NOT NULL AND longitud IS NOT NULL AND latitud != '' AND longitud != ''";
$result = $conn->query($sql);
$clientes = [];

date_default_timezone_set('America/Lima'); 

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        $color = 'blue';

        if (empty($row['fecha_proximo_pago']) || $row['fecha_proximo_pago'] === '0000-00-00') {
            $color = 'red';
        } else {
            $fecha_actual = new DateTime();
            $fecha_actual->setTime(0, 0, 0);
            $fecha_proximo_pago = new DateTime($row['fecha_proximo_pago']);
            $fecha_proximo_pago->setTime(0, 0, 0);

            $intervalo = $fecha_actual->diff($fecha_proximo_pago);
            $dias_diferencia = (int)$intervalo->format('%r%a');

            if ($dias_diferencia < 0) {
                $color = 'red';
            }
            else {
                if ($row['estado_pago'] !== 'Pagado' && $dias_diferencia <= 5) {
                    $color = 'yellow';
                }
            }
        }
        
        $row['color'] = $color;
        $clientes[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Clientes</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
<div class="container">
    <h1>Clientes en el Mapa</h1>
    <div id="map"></div>
    <div id="map-legend" style="max-width:1100px;margin:18px auto 0 auto;display:flex;flex-wrap:wrap;gap:20px;align-items:center;justify-content:center;font-size:1.08em;background:#f8f9fa;padding:10px 18px;border-radius:8px;box-shadow:0 2px 8px #0001;">
        <span><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png" style="vertical-align:middle;width:22px;"> Deuda vencida / Sin fecha</span>
        <span><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png" style="vertical-align:middle;width:22px;"> Vence en 5 días o menos</span>
        <span><img src="https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png" style="vertical-align:middle;width:22px;"> Al día / Pagado</span>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// El código PHP no necesita cambios. La lógica de agrupación se maneja aquí en Javascript.
const clientes = <?php echo json_encode($clientes); ?>;
let lat = -9.19, lng = -75.0152, zoom = 6;

if (clientes.length > 0) {
    const clientesConCoords = clientes.filter(c => c.latitud && c.longitud);
    if (clientesConCoords.length > 0) {
        let sumLat = 0, sumLng = 0;
        clientesConCoords.forEach(c => { 
            sumLat += parseFloat(c.latitud); 
            sumLng += parseFloat(c.longitud); 
        });
        lat = sumLat / clientesConCoords.length;
        lng = sumLng / clientesConCoords.length;
        zoom = 13;
    }
}

const map = L.map('map').setView([lat, lng], zoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

const iconos = {
    red: new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png', shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
    yellow: new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png', shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] }),
    blue: new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png', shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] })
};

// --- LÓGICA PARA AGRUPAR CLIENTES POR COORDENADAS ---
const clientesAgrupados = {};
clientes.forEach(c => {
    if (c.latitud && c.longitud) {
        const key = `${c.latitud},${c.longitud}`;
        if (!clientesAgrupados[key]) {
            clientesAgrupados[key] = [];
        }
        clientesAgrupados[key].push(c);
    }
});

// --- LÓGICA PARA CREAR MARCADORES Y POPUPS AGRUPADOS ---
for (const key in clientesAgrupados) {
    const grupo = clientesAgrupados[key];
    const primerCliente = grupo[0];
    
    // Determinar el color del marcador con prioridad (Rojo > Amarillo > Azul)
    let colorFinal = 'blue';
    if (grupo.some(c => c.color === 'red')) {
        colorFinal = 'red';
    } else if (grupo.some(c => c.color === 'yellow')) {
        colorFinal = 'yellow';
    }

    // Construir el contenido del popup
    let popupContent = '';
    if (grupo.length > 1) {
        popupContent += `<b>${grupo.length} clientes en esta ubicación:</b><hr style='margin: 5px 0;'>`;
        grupo.forEach(cliente => {
            popupContent += `<div class="popup-client-entry"><b><a href='pagos.php?select_client_id=${cliente.id}'>${cliente.nombre}</a></b><br><small>${cliente.direccion}</small></div>`;
        });
    } else {
        popupContent = `<b><a href='pagos.php?select_client_id=${primerCliente.id}'>${primerCliente.nombre}</a></b><br>${primerCliente.direccion}`;
    }

    // Crear un único marcador para el grupo
    const marker = L.marker([primerCliente.latitud, primerCliente.longitud], { icon: iconos[colorFinal] || iconos.blue }).addTo(map);
    marker.bindPopup(popupContent);
}

L.Control.CentrarUbicacion = L.Control.extend({
    onAdd: function(map) {
        var btn = L.DomUtil.create('button', 'leaflet-bar leaflet-control leaflet-control-custom');
        btn.title = 'Centrar en mi ubicación';
        btn.style.cssText = 'background:#fff;width:38px;height:38px;display:flex;align-items:center;justify-content:center;border:none;border-radius:4px;box-shadow:0 2px 8px #0002;';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="color: #333;"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10S2 17.523 2 12m10-8a8 8 0 1 0 0 16 8 8 0 0 0 0-16"/></svg>`;
        L.DomEvent.on(btn, 'click', function(e) {
            L.DomEvent.stop(e);
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(p => map.setView([p.coords.latitude, p.coords.longitude], 16), () => alert('No se pudo obtener tu ubicación.'));
            } else { alert('Geolocalización no soportada.'); }
        });
        return btn;
    },
});
L.control.centrarUbicacion = (opts) => new L.Control.CentrarUbicacion(opts);
L.control.centrarUbicacion({ position: 'topleft' }).addTo(map);
</script>
<?php include 'footer.php'; ?>
</body>
</html>