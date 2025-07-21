<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'utils.php';
require_role('admin');
include 'header.php';
require_once 'conexion.php';
// --- Actualiza estado_pago a 'Vencido' para clientes activos cuyo próximo pago ya venció y no está pagado ---
$hoy = date('Y-m-d');
$conn->query("UPDATE clientes SET estado_pago = 'Vencido' WHERE estado_cliente = 'Activo' AND estado_pago != 'Pagado' AND fecha_proximo_pago IS NOT NULL AND fecha_proximo_pago != '0000-00-00' AND fecha_proximo_pago < '$hoy'");
// Estadísticas generales
// 1. Total de clientes activos
$sql_activos = "SELECT COUNT(*) as total FROM clientes WHERE estado_cliente = 'Activo'";
$total_activos = $conn->query($sql_activos)->fetch_assoc()['total'];
// 2. Total de clientes inactivos
$sql_inactivos = "SELECT COUNT(*) as total FROM clientes WHERE estado_cliente = 'Inactivo'";
$total_inactivos = $conn->query($sql_inactivos)->fetch_assoc()['total'];
// 3. Total de clientes con pagos pendientes (meses no pagados)
$total_pendientes = 0;
$sql_clientes = "SELECT id, fecha_inicio_servicio FROM clientes WHERE estado_cliente = 'Activo'";
$res_clientes = $conn->query($sql_clientes);
$hoy = date('Y-m-01');
if ($res_clientes && $res_clientes->num_rows > 0) {
    while ($cli = $res_clientes->fetch_assoc()) {
        $id = $cli['id'];
        $fecha_inicio = $cli['fecha_inicio_servicio'];
        if (empty($fecha_inicio) || $fecha_inicio === '0000-00-00') continue;
        $pendientes = obtenerMesesPendientes($id, $fecha_inicio, $conn);
        if (count($pendientes) > 0) {
            $total_pendientes++;
        }
    }
}
// 4. Total de clientes al día
$total_aldia = $total_activos - $total_pendientes;
// 5. Total de pagos registrados
$sql_pagos = "SELECT COUNT(*) as total FROM pagos";
$total_pagos = $conn->query($sql_pagos)->fetch_assoc()['total'];
// 6. Total recaudado
$sql_recaudado = "SELECT SUM(monto_pagado) as total FROM pagos";
$total_recaudado = $conn->query($sql_recaudado)->fetch_assoc()['total'] ?? 0;
// 7. Pagos registrados este mes
$mes_actual = date('Y-m');
$sql_pagos_mes = "SELECT COUNT(*) as total, SUM(monto_pagado) as suma FROM pagos WHERE DATE_FORMAT(fecha_pago, '%Y-%m') = '$mes_actual'";
$res_pagos_mes = $conn->query($sql_pagos_mes)->fetch_assoc();
$total_pagos_mes = $res_pagos_mes['total'];
$total_recaudado_mes = $res_pagos_mes['suma'] ?? 0;
// 8. Recaudo por mes últimos 12 meses (por periodo_cubierto)
$labels = [];
$values = [];
for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $labels[] = date('M Y', strtotime("-$i months"));
    $sql_mes = "SELECT SUM(monto_pagado) as total FROM pagos WHERE periodo_cubierto = '$mes'";
    $res_mes = $conn->query($sql_mes)->fetch_assoc();
    $values[] = $res_mes['total'] ? floatval($res_mes['total']) : 0;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Control de Pagos de Internet</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Estadísticas Generales del Sistema</h1>
        <div class="stats-grid">
            <div class="stat-card blue">
                <h2><?php echo $total_activos; ?></h2>
                <div class="stat-label">Clientes Activos</div>
            </div>
            <div class="stat-card red">
                <h2><?php echo $total_inactivos; ?></h2>
                <div class="stat-label">Clientes Inactivos</div>
            </div>
            <div class="stat-card orange">
                <h2><?php echo $total_pendientes; ?></h2>
                <div class="stat-label">Clientes con Pagos Pendientes</div>
            </div>
            <div class="stat-card green">
                <h2><?php echo $total_aldia; ?></h2>
                <div class="stat-label">Clientes al Día</div>
            </div>
            <div class="stat-card purple">
                <h2><?php echo $total_pagos; ?></h2>
                <div class="stat-label">Pagos Registrados</div>
            </div>
            <div class="stat-card teal">
                <h2><span class="stat-currency">S/</span> <?php echo number_format($total_recaudado, 2); ?></h2>
                <div class="stat-label">Total Recaudado</div>
            </div>
            <div class="stat-card yellow">
                <h2><?php echo $total_pagos_mes; ?></h2>
                <div class="stat-label">Pagos este Mes</div>
                <div style="font-size:0.95em;color:#888;">Recaudado: <b>S/ <?php echo number_format($total_recaudado_mes, 2); ?></b></div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
