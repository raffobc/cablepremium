<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
require_role('admin');
include 'header.php';
require_once 'conexion.php';
/**
 * Página de estadísticas y reportes del sistema de control de pagos.
 * Incluye gráficos y reportes de clientes y pagos.
 */
// 1. Recaudo por mes últimos 12 meses (por periodo_cubierto)
$labels = [];
$values = [];
for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $labels[] = date('M Y', strtotime("-$i months"));
    $sql_mes = "SELECT SUM(monto_pagado) as total FROM pagos WHERE periodo_cubierto = '$mes'";
    $res_mes = $conn->query($sql_mes)->fetch_assoc();
    $values[] = $res_mes['total'] ? floatval($res_mes['total']) : 0;
}
// Reporte: Top 5 clientes con más pagos registrados
$top_clientes = [];
$sql_top_clientes = "SELECT c.nombre, COUNT(p.id) as pagos FROM clientes c JOIN pagos p ON c.id = p.cliente_id GROUP BY c.id, c.nombre ORDER BY pagos DESC LIMIT 5";
$res_top_clientes = $conn->query($sql_top_clientes);
if ($res_top_clientes && $res_top_clientes->num_rows > 0) {
    while ($row = $res_top_clientes->fetch_assoc()) {
        $top_clientes[] = $row;
    }
}
// Reporte: Top 5 meses con mayor recaudación
$top_meses = [];
$sql_top_meses = "SELECT periodo_cubierto, SUM(monto_pagado) as total FROM pagos GROUP BY periodo_cubierto ORDER BY total DESC LIMIT 5";
$res_top_meses = $conn->query($sql_top_meses);
if ($res_top_meses && $res_top_meses->num_rows > 0) {
    while ($row = $res_top_meses->fetch_assoc()) {
        $top_meses[] = $row;
    }
}
// Reporte: Top 5 clientes con más deuda (mayor cantidad de meses pendientes)
$top_deuda = [];
$sql_deuda = "SELECT c.id, c.nombre, c.fecha_inicio_servicio FROM clientes c WHERE c.estado_cliente = 'Activo'";
$res_deuda = $conn->query($sql_deuda);
if ($res_deuda && $res_deuda->num_rows > 0) {
    while ($row = $res_deuda->fetch_assoc()) {
        $id = $row['id'];
        $fecha_inicio = $row['fecha_inicio_servicio'];
        if (empty($fecha_inicio) || $fecha_inicio === '0000-00-00') continue;
        $meses = [];
        $inicio = new DateTime($fecha_inicio);
        $fin = new DateTime(date('Y-m-01'));
        $fin->modify('first day of next month');
        while ($inicio < $fin) {
            $meses[] = $inicio->format('Y-m');
            $inicio->modify('+1 month');
        }
        $pagos = [];
        $sql_pagos = "SELECT periodo_cubierto FROM pagos WHERE cliente_id = $id";
        $result_pagos = $conn->query($sql_pagos);
        if ($result_pagos && $result_pagos->num_rows > 0) {
            while ($row_pago = $result_pagos->fetch_assoc()) {
                $pagos[] = $row_pago['periodo_cubierto'];
            }
        }
        $pendientes = array_diff($meses, $pagos);
        $top_deuda[] = [
            'nombre' => $row['nombre'],
            'pendientes' => count($pendientes)
        ];
    }
}
// Ordenar y tomar top 5
usort($top_deuda, function($a, $b) { return $b['pendientes'] <=> $a['pendientes']; });
$top_deuda = array_slice($top_deuda, 0, 5);
// --- Reporte de meses pendientes por cliente (integrado de reporte_pendientes.php) ---
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Recaudo Mensual</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Recaudo por Mes (Últimos 12 meses)</h1>
        <div class="chart-container">
            <canvas id="recaudoMesChart" height="110"></canvas>
        </div>
        <div style="max-width:900px;margin:40px auto 0 auto;">
            <h2 style="color:#0056b3;font-size:1.15em;margin-top:40px;">Top 5 Clientes con Más Pagos Registrados</h2>
            <table style="width:100%;margin-bottom:30px;border-collapse:collapse;background:#f8f9fa;">
                <thead><tr style="background:#e9ecef;"><th style="padding:8px;">Cliente</th><th style="padding:8px;">Pagos Registrados</th></tr></thead>
                <tbody>
                <?php foreach ($top_clientes as $c): ?>
                    <tr><td style="padding:8px;"> <?php echo htmlspecialchars($c['nombre']); ?> </td><td style="padding:8px;text-align:center;"> <?php echo $c['pagos']; ?> </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h2 style="color:#0056b3;font-size:1.15em;">Top 5 Meses con Mayor Recaudación</h2>
            <table style="width:100%;margin-bottom:30px;border-collapse:collapse;background:#f8f9fa;">
                <thead><tr style="background:#e9ecef;"><th style="padding:8px;">Mes</th><th style="padding:8px;">Total Recaudado</th></tr></thead>
                <tbody>
                <?php foreach ($top_meses as $m): ?>
                    <tr><td style="padding:8px;"> <?php echo htmlspecialchars($m['periodo_cubierto']); ?> </td><td style="padding:8px;text-align:center;">S/ <?php echo number_format($m['total'],2); ?> </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h2 style="color:#0056b3;font-size:1.15em;">Top 5 Clientes con Más Deuda (Meses Pendientes)</h2>
            <table style="width:100%;margin-bottom:30px;border-collapse:collapse;background:#f8f9fa;">
                <thead><tr style="background:#e9ecef;"><th style="padding:8px;">Cliente</th><th style="padding:8px;">Meses Pendientes</th></tr></thead>
                <tbody>
                <?php foreach ($top_deuda as $d): ?>
                    <tr><td style="padding:8px;"> <?php echo htmlspecialchars($d['nombre']); ?> </td><td style="padding:8px;text-align:center;"> <?php echo $d['pendientes']; ?> </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="container" style="max-width:1000px;margin:40px auto 40px auto;">
        <h2 style="color:#0056b3;margin-top:40px;">Reporte de Meses Pendientes de Pago por Cliente</h2>
        <table style="width:100%;border-collapse:collapse;">
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
                    <td style="color:#c82333;font-weight:bold;">
                        <?php echo empty($item['pendientes']) ? 'Ninguno' : implode(', ', $item['pendientes']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    const labels = <?php echo json_encode($labels); ?>;
    const data = <?php echo json_encode($values); ?>;
    const ctx = document.getElementById('recaudoMesChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Recaudado (S/)',
                data: data,
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1,
                borderRadius: 6,
                maxBarThickness: 38
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => `S/ ${ctx.parsed.y.toLocaleString('es-PE', {minimumFractionDigits:2})}` } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'S/ ' + v }
                }
            }
        }
    });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html> 