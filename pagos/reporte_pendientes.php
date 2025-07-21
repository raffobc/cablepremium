<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
require_role('admin');
include 'header.php';
require_once 'conexion.php';

/**
 * Reporte detallado de clientes con pagos pendientes por mes.
 * Muestra los meses no pagados para cada cliente activo.
 */

// Obtener todos los clientes activos
$sql = "SELECT id, nombre, direccion, fecha_inicio_servicio FROM clientes WHERE estado_cliente = 'Activo' ORDER BY nombre ASC";
$result = $conn->query($sql);
$clientes = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$reporte = [];
$hoy = date('Y-m-01');
foreach ($clientes as $cliente) {
    $id = $cliente['id'];
    $fecha_inicio = $cliente['fecha_inicio_servicio'];
    if (empty($fecha_inicio) || $fecha_inicio === '0000-00-00') continue;
    $pendientes = obtenerMesesPendientes($id, $fecha_inicio, $conn);
    $reporte[] = [
        'nombre' => $cliente['nombre'],
        'direccion' => $cliente['direccion'],
        'fecha_inicio' => $fecha_inicio,
        'pendientes' => $pendientes
    ];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Meses Pendientes de Pago</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .pendientes { color: #c82333; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reporte de Meses Pendientes de Pago por Cliente</h1>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Dirección</th>
                    <th>Fecha de Inicio</th>
                    <th>Meses Pendientes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reporte as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($item['direccion']); ?></td>
                    <td><?php echo htmlspecialchars($item['fecha_inicio']); ?></td>
                    <td class="pendientes">
                        <?php echo empty($item['pendientes']) ? 'Ninguno' : implode(', ', $item['pendientes']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <a href="index.php">Volver al inicio</a>
    </div>
</body>
</html>
<?php include 'footer.php'; ?> 