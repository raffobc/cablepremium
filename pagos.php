<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
require_any_role(['admin','usuario']);
include 'header.php';
// pagos.php
/**
 * Página de registro y gestión de pagos de clientes.
 * Permite buscar clientes, registrar pagos, editar datos y mostrar mensajes de usuario.
 */

include 'conexion.php'; // Incluye el archivo de conexión a la base de datos

$cliente_buscado = null;
$cliente_id_seleccionado = null;
$search_term = '';
$search_results = [];
$payment_history = []; // Array para almacenar el historial de pagos del cliente seleccionado
$edit_client_data = null; // Variable para almacenar los datos del cliente si se está editando

// --- Lógica para cargar datos del cliente para edición (dentro de pagos.php) ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $client_id_for_edit = intval($_GET['id']);
    $sentencia = $conn->prepare("SELECT id, dni, nombre, direccion, tarifa, fecha_inicio_servicio, fecha_ultimo_pago, fecha_proximo_pago, estado_pago, estado_cliente FROM clientes WHERE id = ?");
    $sentencia->bind_param("i", $client_id_for_edit);
    $sentencia->execute();
    $resultado = $sentencia->get_result();
    if ($resultado->num_rows > 0) {
        $edit_client_data = $resultado->fetch_assoc();
        // Redirigir para mostrar la sección de edición y los detalles del cliente
        $cliente_id_seleccionado = $client_id_for_edit; // Para que se muestren los detalles del cliente abajo
    } else {
        mostrarMensaje('Error: Cliente no encontrado para editar.', 'error');
    }
    $sentencia->close();
}

// --- Lógica para actualizar cliente (después de editar en pagos.php) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["actualizar_cliente"])) {
    $client_id = intval($_POST["client_id"]);
    $dni = trim($_POST["dni"]);
    $dni_val = validarDNI($dni, $conn, $client_id);
    if ($dni_val !== true) {
        mostrarMensaje($dni_val, 'error');
        $conn->close();
        exit();
    }
    $nombre = htmlspecialchars(trim($_POST["nombre"]));
    $direccion = htmlspecialchars(trim($_POST["direccion"]));
    $tarifa = floatval($_POST["tarifa"]);
    $fecha_inicio_servicio = $_POST["fecha_inicio_servicio"];
    $sentencia = $conn->prepare("UPDATE clientes SET dni = ?, nombre = ?, direccion = ?, tarifa = ?, fecha_inicio_servicio = ? WHERE id = ?");
    $sentencia->bind_param("ssssdi", $dni, $nombre, $direccion, $tarifa, $fecha_inicio_servicio, $client_id);
    if ($sentencia->execute()) {
        mostrarMensaje('¡Cliente actualizado exitosamente!', 'success');
        $edit_client_data = null; // Limpiar datos de edición después de actualizar
        $cliente_id_seleccionado = $client_id; // Recargar los detalles del cliente
    } else {
        mostrarMensaje('Error al actualizar cliente: ' . $sentencia->error, 'error');
    }
    $sentencia->close();
}

// --- Lógica para dar de baja/activar cliente (en pagos.php) ---
if (isset($_GET['action']) && ($_GET['action'] == 'deactivate' || $_GET['action'] == 'activate') && isset($_GET['id'])) {
    $client_id = intval($_GET['id']);
    $new_status = ($_GET['action'] == 'deactivate') ? 'Inactivo' : 'Activo';

    $sentencia = $conn->prepare("UPDATE clientes SET estado_cliente = ? WHERE id = ?");
    $sentencia->bind_param("si", $new_status, $client_id);

    if ($sentencia->execute()) {
        mostrarMensaje('¡Estado del cliente actualizado a ' . $new_status . ' exitosamente!', 'success');
        $cliente_id_seleccionado = $client_id; // Recargar los detalles del cliente
    } else {
        mostrarMensaje('Error al actualizar estado del cliente: ' . $sentencia->error, 'error');
    }
    $sentencia->close();
}


// --- Lógica para buscar clientes ---
// Se busca clientes activos e inactivos si se usa el filtro 'all' para el buscador,
// de lo contrario, solo activos.
$filter_search_status = " AND estado_cliente = 'Activo'"; // Por defecto, buscar solo activos
$show_all_search_clients = isset($_GET['search_show']) && $_GET['search_show'] === 'all';
if ($show_all_search_clients) {
    $filter_search_status = ""; // Si se pide, no filtrar por estado en la búsqueda
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["buscar_cliente"])) {
    $search_term = htmlspecialchars(trim($_GET["search_term"]));
    if (!empty($search_term)) {
        $sentencia = $conn->prepare("SELECT id, nombre, direccion, tarifa, fecha_inicio_servicio, fecha_ultimo_pago, fecha_proximo_pago, estado_pago, estado_cliente FROM clientes WHERE nombre LIKE ?" . $filter_search_status . " ORDER BY nombre ASC");
        $like_term = '%' . $search_term . '%';
        $sentencia->bind_param("s", $like_term);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        while ($cliente = $resultado->fetch_assoc()) {
            $search_results[] = $cliente;
        }
        $sentencia->close();
    }
}

// --- Lógica para seleccionar un cliente de los resultados de búsqueda o después de un pago/edición ---
// Esto se ejecuta después de todas las lógicas GET y POST que puedan afectar $cliente_id_seleccionado
if (isset($_GET['select_client_id']) || (isset($_POST["registrar_pago"]) && isset($_POST['cliente_id'])) || (isset($_POST["actualizar_cliente"]) && isset($_POST['client_id'])) || (isset($_GET['action']) && ($_GET['action'] == 'deactivate' || $_GET['action'] == 'activate') && isset($_GET['id']))) {
    $cliente_id_seleccionado = isset($_GET['select_client_id']) ? intval($_GET['select_client_id']) : (isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : intval($_POST['client_id']));

    $sentencia = $conn->prepare("SELECT id, nombre, direccion, tarifa, fecha_inicio_servicio, fecha_ultimo_pago, fecha_proximo_pago, estado_pago, estado_cliente FROM clientes WHERE id = ?");
    $sentencia->bind_param("i", $cliente_id_seleccionado);
    $sentencia->execute();
    $resultado = $sentencia->get_result();
    $cliente_buscado = $resultado->fetch_assoc();
    $sentencia->close();

    // Obtener el historial de pagos para el cliente seleccionado
    if ($cliente_buscado) {
        $sentencia_pagos = $conn->prepare("SELECT fecha_pago, monto_pagado, metodo_pago, periodo_cubierto, created_at FROM pagos WHERE cliente_id = ? ORDER BY id ASC");
        $sentencia_pagos->bind_param("i", $cliente_id_seleccionado);
        $sentencia_pagos->execute();
        $resultado_pagos = $sentencia_pagos->get_result();
        while ($pago_row = $resultado_pagos->fetch_assoc()) {
            $payment_history[] = $pago_row;
        }
        $sentencia_pagos->close();
    }
}


// --- Lógica para registrar un pago (insertar en pagos y actualizar clientes) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["registrar_pago"])) {
    $cliente_id = intval($_POST["cliente_id"]);
    $fecha_pago_actual = date("Y-m-d");
    $monto_pagado = floatval($_POST["monto_pagado"]);
    $metodo_pago = htmlspecialchars(trim($_POST["metodo_pago"]));

    $sentencia_get_client_info = $conn->prepare("SELECT fecha_inicio_servicio, fecha_proximo_pago FROM clientes WHERE id = ?");
    $sentencia_get_client_info->bind_param("i", $cliente_id);
    $sentencia_get_client_info->execute();
    $resultado_client_info = $sentencia_get_client_info->get_result();
    $current_client_data = $resultado_client_info->fetch_assoc();
    $sentencia_get_client_info->close();

    if ($current_client_data) {
        $fecha_inicio = new DateTime($current_client_data['fecha_inicio_servicio']);
        $dia_de_pago = $fecha_inicio->format('d');

        // Determinar el periodo_cubierto para este pago (basado en la fecha del próximo pago que se estaba esperando)
        if (!empty($current_client_data['fecha_proximo_pago']) && $current_client_data['fecha_proximo_pago'] !== '0000-00-00') {
            $periodo_cubierto_dt = new DateTime($current_client_data['fecha_proximo_pago']);
            $periodo_cubierto = $periodo_cubierto_dt->format('Y-m');
        } else {
            $periodo_cubierto_dt = new DateTime($current_client_data['fecha_inicio_servicio']);
            $periodo_cubierto = $periodo_cubierto_dt->format('Y-m');
        }

        // Calcular la nueva fecha de próximo pago (siempre al mes siguiente, manteniendo el día original)
        $nueva_fecha_proximo_pago_dt = !empty($current_client_data['fecha_proximo_pago']) && $current_client_data['fecha_proximo_pago'] !== '0000-00-00'
            ? new DateTime($current_client_data['fecha_proximo_pago'])
            : new DateTime($current_client_data['fecha_inicio_servicio']);
        $nueva_fecha_proximo_pago_dt->modify('+1 month');
        // Asegurarse de que el día sea válido para el próximo mes (ej. si era 31 y el próximo mes solo tiene 30 días)
        $nueva_fecha_proximo_pago_dt->setDate($nueva_fecha_proximo_pago_dt->format('Y'), $nueva_fecha_proximo_pago_dt->format('m'), min($dia_de_pago, (int)$nueva_fecha_proximo_pago_dt->format('t')));
        $nueva_fecha_proximo_pago = $nueva_fecha_proximo_pago_dt->format('Y-m-d');


        // Iniciar transacción para asegurar atomicidad
        $conn->begin_transaction();
        
        try {
            // 1. Insertar el registro de pago en la tabla 'pagos'
            $sentencia_insert_pago = $conn->prepare("INSERT INTO pagos (cliente_id, fecha_pago, monto_pagado, metodo_pago, periodo_cubierto) VALUES (?, ?, ?, ?, ?)");
            $sentencia_insert_pago->bind_param("isdss", $cliente_id, $fecha_pago_actual, $monto_pagado, $metodo_pago, $periodo_cubierto);
            if (!$sentencia_insert_pago->execute()) {
                throw new Exception("Error al insertar pago: " . $sentencia_insert_pago->error);
            }
            $sentencia_insert_pago->close();

            // 2. Actualizar la tabla 'clientes' (fecha de último pago, próxima fecha de pago y estado)
            $sentencia_update_cliente = $conn->prepare("UPDATE clientes SET fecha_ultimo_pago = ?, fecha_proximo_pago = ?, estado_pago = 'Pagado' WHERE id = ?");
            $sentencia_update_cliente->bind_param("ssi", $fecha_pago_actual, $nueva_fecha_proximo_pago, $cliente_id);
            if (!$sentencia_update_cliente->execute()) {
                throw new Exception("Error al actualizar cliente: " . $sentencia_update_cliente->error);
            }
            $sentencia_update_cliente->close();

            $conn->commit(); // Confirmar la transacción
            // --- Modal de confirmación visual ---
            echo '<div id="modalConfirmacionPago" class="modal-confirmacion-pago" style="display:flex;">';
            echo '<div class="modal-confirmacion-content">';
            echo '<span class="close-confirmacion" id="closeConfirmacion">&times;</span>';
            echo '<h3>¡Pago registrado exitosamente!</h3>';
            echo '<ul style="list-style:none;padding-left:0;">';
            echo '<li><b>Cliente:</b> ' . htmlspecialchars($cliente_buscado ? $cliente_buscado["nombre"] : "") . '</li>';
            echo '<li><b>Fecha:</b> ' . formatDateForDisplay($fecha_pago_actual) . '</li>';
            echo '<li><b>Monto:</b> S/ ' . number_format($monto_pagado, 2) . '</li>';
            echo '<li><b>Método:</b> ' . htmlspecialchars($metodo_pago) . '</li>';
            echo '<li><b>Periodo cubierto:</b> ' . htmlspecialchars($periodo_cubierto) . '</li>';
            echo '</ul>';
            echo '<div style="text-align:right;margin-top:15px;"><button id="btnCerrarConfirmacion" style="background:#28a745;color:#fff;padding:8px 18px;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">Aceptar</button></div>';
            echo '</div></div>';
            echo '<script>document.getElementById("btnCerrarConfirmacion").onclick = function() { document.getElementById("modalConfirmacionPago").style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; }; document.getElementById("closeConfirmacion").onclick = function() { document.getElementById("modalConfirmacionPago").style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; }; window.onclick = function(event) { var modal = document.getElementById("modalConfirmacionPago"); if (event.target == modal) { modal.style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; } };</script>';
            echo '<style>.modal-confirmacion-pago { display: flex; position: fixed; z-index: 10000; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); align-items: center; justify-content: center; } .modal-confirmacion-content { background: #fff; padding: 24px 18px 18px 18px; border-radius: 8px; max-width: 400px; width: 95vw; box-shadow: 0 2px 8px #0003; position: relative; } .close-confirmacion { position: absolute; top: 10px; right: 18px; font-size: 1.7em; color: #888; cursor: pointer; font-weight: bold; } @media (max-width: 600px) { .modal-confirmacion-content { max-width: 98vw; padding: 12px 4px 8px 4px; } }</style>';
            // --- Fin modal ---
        } catch (Exception $e) {
            $conn->rollback(); // Revertir la transacción si algo falla
            mostrarMensaje('Error en el registro del pago: ' . $e->getMessage(), 'error');
        }

    } else {
        mostrarMensaje('Error: Cliente no encontrado para registrar el pago.', 'error');
    }
}

// --- Lógica para registrar pagos múltiples de meses seleccionados ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["registrar_pago_multiple"])) {
    $cliente_id = intval($_POST["cliente_id"]);
    $metodo_pago = htmlspecialchars(trim($_POST["metodo_pago"]));
    $meses_pendientes = isset($_POST['meses_pendientes']) ? $_POST['meses_pendientes'] : [];
    $monto_total_personalizado = isset($_POST['monto_total_anual']) ? floatval($_POST['monto_total_anual']) : null;
    if (empty($meses_pendientes)) {
        mostrarMensaje('Debes seleccionar al menos un mes para registrar el pago.', 'error');
    } else {
        // Obtener la tarifa del cliente
        $sentencia = $conn->prepare("SELECT tarifa, nombre FROM clientes WHERE id = ?");
        $sentencia->bind_param("i", $cliente_id);
        $sentencia->execute();
        $resultado = $sentencia->get_result();
        $cliente = $resultado->fetch_assoc();
        $sentencia->close();
        if (!$cliente) {
            mostrarMensaje('Cliente no encontrado.', 'error');
        } else {
            $tarifa = floatval($cliente['tarifa']);
            $nombre_cliente = $cliente['nombre'];
            $fecha_pago_actual = date("Y-m-d");
            $conn->begin_transaction();
            try {
                // Calcular descuento si corresponde
                $total = $tarifa * count($meses_pendientes);
                $descuento = 0;
                $meses_sorted = $meses_pendientes;
                sort($meses_sorted);
                $consecutivos = true;
                for ($i = 1; $i < count($meses_sorted); $i++) {
                    $prev = DateTime::createFromFormat('Y-m', $meses_sorted[$i-1]);
                    $curr = DateTime::createFromFormat('Y-m', $meses_sorted[$i]);
                    $prev->modify('+1 month');
                    if ($prev->format('Y-m') !== $curr->format('Y-m')) {
                        $consecutivos = false;
                        break;
                    }
                }
                if (count($meses_pendientes) === 12 && $consecutivos) {
                    if ($monto_total_personalizado !== null && $monto_total_personalizado > 0) {
                        $total = $monto_total_personalizado;
                        $descuento = $tarifa * 12 - $total;
                    } else {
                        $descuento = $total * 0.10;
                        $total = $total - $descuento;
                    }
                }
                foreach ($meses_pendientes as $periodo_cubierto) {
                    $sentencia_insert = $conn->prepare("INSERT INTO pagos (cliente_id, fecha_pago, monto_pagado, metodo_pago, periodo_cubierto) VALUES (?, ?, ?, ?, ?)");
                    if (count($meses_pendientes) === 12 && $consecutivos) {
                        $monto = round($total / 12, 2);
                    } else {
                        $monto = $tarifa;
                    }
                    $sentencia_insert->bind_param("isdss", $cliente_id, $fecha_pago_actual, $monto, $metodo_pago, $periodo_cubierto);
                    if (!$sentencia_insert->execute()) {
                        throw new Exception("Error al insertar pago para el periodo $periodo_cubierto: " . $sentencia_insert->error);
                    }
                    $sentencia_insert->close();
                }
                // Actualizar la tabla clientes: fecha_ultimo_pago = hoy, fecha_proximo_pago = mes siguiente al último mes pagado
                $ultimo_mes_pagado = max($meses_pendientes);
                $fecha_inicio = new DateTime($ultimo_mes_pagado . '-01');
                $fecha_inicio->modify('+1 month');
                $fecha_proximo_pago = $fecha_inicio->format('Y-m-d');
                $sentencia_update = $conn->prepare("UPDATE clientes SET fecha_ultimo_pago = ?, fecha_proximo_pago = ?, estado_pago = 'Pagado' WHERE id = ?");
                $sentencia_update->bind_param("ssi", $fecha_pago_actual, $fecha_proximo_pago, $cliente_id);
                if (!$sentencia_update->execute()) {
                    throw new Exception("Error al actualizar cliente: " . $sentencia_update->error);
                }
                $sentencia_update->close();
                $conn->commit();
                // --- Modal de confirmación visual para pago múltiple ---
                echo '<div id="modalConfirmacionPago" class="modal-confirmacion-pago" style="display:flex;">';
                echo '<div class="modal-confirmacion-content">';
                echo '<span class="close-confirmacion" id="closeConfirmacion">&times;</span>';
                echo '<h3>¡Pagos registrados exitosamente!</h3>';
                echo '<ul style="list-style:none;padding-left:0;">';
                echo '<li><b>Cliente:</b> ' . htmlspecialchars($nombre_cliente) . '</li>';
                echo '<li><b>Fecha:</b> ' . formatDateForDisplay($fecha_pago_actual) . '</li>';
                echo '<li><b>Monto total:</b> S/ ' . number_format($total, 2) . '</li>';
                echo '<li><b>Método:</b> ' . htmlspecialchars($metodo_pago) . '</li>';
                echo '<li><b>Meses pagados:</b> ' . implode(', ', array_map('htmlspecialchars', $meses_pendientes)) . '</li>';
                if ($descuento > 0) { echo '<li style="color:#28a745;"><b>Descuento aplicado:</b> S/ ' . number_format($descuento,2) . '</li>'; }
                echo '</ul>';
                echo '<div style="text-align:right;margin-top:15px;"><button id="btnCerrarConfirmacion" style="background:#28a745;color:#fff;padding:8px 18px;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">Aceptar</button></div>';
                echo '</div></div>';
                echo '<script>document.getElementById("btnCerrarConfirmacion").onclick = function() { document.getElementById("modalConfirmacionPago").style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; }; document.getElementById("closeConfirmacion").onclick = function() { document.getElementById("modalConfirmacionPago").style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; }; window.onclick = function(event) { var modal = document.getElementById("modalConfirmacionPago"); if (event.target == modal) { modal.style.display = "none"; window.location.href = "pagos.php?select_client_id=' . $cliente_id . '"; } };</script>';
                echo '<style>.modal-confirmacion-pago { display: flex; position: fixed; z-index: 10000; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.4); align-items: center; justify-content: center; } .modal-confirmacion-content { background: #fff; padding: 24px 18px 18px 18px; border-radius: 8px; max-width: 400px; width: 95vw; box-shadow: 0 2px 8px #0003; position: relative; } .close-confirmacion { position: absolute; top: 10px; right: 18px; font-size: 1.7em; color: #888; cursor: pointer; font-weight: bold; } @media (max-width: 600px) { .modal-confirmacion-content { max-width: 98vw; padding: 12px 4px 8px 4px; } }</style>';
                // --- Fin modal ---
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                mostrarMensaje($e->getMessage(), 'error');
            }
        }
    }
}
// --- Fin lógica de pago múltiple ---

// Mostrar mensaje de éxito si viene por GET
if (isset($_GET['pagos_exito'])) {
    $meses_pagados = explode(',', htmlspecialchars($_GET['pagos_exito']));
    echo '<div id="modalExitoPago" style="display:flex;position:fixed;z-index:99999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">';
    echo '<div style="background:#fff;padding:25px 20px 20px 20px;border-radius:8px;max-width:400px;width:95vw;box-shadow:0 2px 8px #0003;position:relative;">';
    echo '<h3>¡Pagos registrados exitosamente!</h3>';
    echo '<div><b>Meses pagados:</b><ul style="margin:8px 0 0 18px;padding:0;">';
    foreach ($meses_pagados as $mes) {
        echo '<li>' . trim($mes) . '</li>';
    }
    echo '</ul></div>';
    echo '<div style="text-align:right;margin-top:15px;"><button id="btnCerrarExitoPago" style="background:#28a745;color:#fff;padding:8px 18px;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">Aceptar</button></div>';
    echo '</div></div>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Pagos - Control de Pagos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Registrar Pagos y Gestión de Cliente (Detallada)</h1>

        <h2>Buscar Cliente</h2>
        <form action="pagos.php" method="GET">
            <input type="hidden" name="buscar_cliente" value="1">
            <label for="search_term">Buscar por Nombre:</label>
            <input type="text" id="search_term" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Ingrese nombre del cliente">
            <div class="filter-links" style="display: inline-block; margin-left: 15px;">
                <input type="checkbox" id="show_all_search_clients" name="search_show" value="all" <?php echo $show_all_search_clients ? 'checked' : ''; ?>>
                <label for="show_all_search_clients">Incluir Inactivos</label>
            </div>
            <button type="submit">Buscar</button>
        </form>

        <?php if (!empty($search_results)): ?>
            <h3>Resultados de la Búsqueda:</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Dirección</th>
                        <th>Tarifa</th>
                        <th>Próximo Pago</th>
                        <th>Estado Pago</th>
                        <th>Estado Cliente</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['id']); ?></td>
                            <td><?php echo htmlspecialchars($client['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($client['direccion']); ?></td>
                            <td>S/ <?php echo number_format($client['tarifa'], 2); ?></td>
                            <td><?php echo formatDateForDisplay($client['fecha_proximo_pago']); ?></td>
                            <td>
                                <?php 
                                    $clase_estado = '';
                                    if ($client['estado_pago'] === 'Pagado') {
                                        $clase_estado = 'status-pagado';
                                    } else if ($client['estado_pago'] === 'Vencido') {
                                        $clase_estado = 'status-vencido';
                                    } else {
                                        $clase_estado = 'status-pendiente';
                                    }
                                    echo "<span class='" . $clase_estado . "'>" . htmlspecialchars($client['estado_pago']) . "</span>";
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $clase_estado_cliente = ($client['estado_cliente'] == 'Activo') ? 'status-activo' : 'status-inactivo';
                                    echo "<span class='" . $clase_estado_cliente . "'>" . htmlspecialchars($client['estado_cliente']) . "</span>";
                                ?>
                            </td>
                            <td>
                                <a href="pagos.php?select_client_id=<?php echo htmlspecialchars($client['id']); ?>" class="btn-select">Seleccionar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($_GET["buscar_cliente"]) && empty($search_results) && !empty($search_term)): ?>
            <p>No se encontraron clientes con el nombre "<?php echo htmlspecialchars($search_term); ?>".</p>
        <?php endif; ?>

        <?php if ($edit_client_data): ?>
            <h2>Editar Cliente</h2>
            <form method="POST" action="pagos.php" style="max-width:400px;background:#f8f9fa;padding:18px 16px 12px 16px;border-radius:8px;margin-bottom:30px;">
                <input type="hidden" name="actualizar_cliente" value="1">
                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($edit_client_data['id']); ?>">
                <label for="dni">DNI:</label>
                <input type="text" id="dni" name="dni" value="<?php echo htmlspecialchars($edit_client_data['dni']); ?>" pattern="\d{8}" maxlength="8" required>
                <label for="nombre">Nombre:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($edit_client_data['nombre']); ?>" required>
                <label for="direccion">Dirección:</label>
                <input type="text" id="direccion" name="direccion" value="<?php echo htmlspecialchars($edit_client_data['direccion']); ?>" required>
                <label for="tarifa">Tarifa (S/):</label>
                <input type="number" step="0.01" id="tarifa" name="tarifa" value="<?php echo htmlspecialchars($edit_client_data['tarifa']); ?>" required>
                <label for="fecha_inicio_servicio">Fecha de Inicio del Servicio:</label>
                <input type="date" id="fecha_inicio_servicio" name="fecha_inicio_servicio" value="<?php echo htmlspecialchars($edit_client_data['fecha_inicio_servicio']); ?>" required>
                <button type="submit">Guardar Cambios</button>
                <a href="pagos.php?select_client_id=<?php echo htmlspecialchars($edit_client_data['id']); ?>" class="btn-cancelar" style="margin-left:10px;">Cancelar</a>
            </form>
            <script>
            // Búsqueda automática de RENIEC al escribir el DNI (solo si tiene 8 dígitos)
            document.addEventListener('DOMContentLoaded', function() {
                var dniInput = document.getElementById('dni');
                var nombreInput = document.getElementById('nombre');
                if (dniInput) {
                    dniInput.addEventListener('input', function() {
                        if (/^\d{8}$/.test(dniInput.value)) {
                            fetch('https://apiperu.dev/api/dni/' + dniInput.value + '?api_token=40803687280aad599cf8654f6493a7dbabf5c9b5e552730d43ec871d757d5e4a')
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success && data.data) {
                                        nombreInput.value = data.data.nombre_completo;
                                    }
                                });
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>

        <!-- ****** INICIO DE SECCIONES REORDENADAS ****** -->

        <?php if ($cliente_buscado && !$edit_client_data): ?>
            <h2>Detalles del Cliente y Registro de Pago: <?php echo htmlspecialchars($cliente_buscado['nombre']); ?></h2>
            <p><strong>Dirección:</strong> <?php echo htmlspecialchars($cliente_buscado['direccion']); ?></p>
            <p><strong>Tarifa:</strong> S/ <?php echo number_format($cliente_buscado['tarifa'], 2); ?></p>
            <p><strong>Fecha Inicio Servicio:</strong> <?php echo formatDateForDisplay($cliente_buscado['fecha_inicio_servicio']); ?></p>
            <p><strong>Último Pago Registrado:</strong> <?php echo formatDateForDisplay($cliente_buscado['fecha_ultimo_pago']); ?></p>
            <p><strong>Próximo Pago Estimado:</strong> <?php echo formatDateForDisplay($cliente_buscado['fecha_proximo_pago']); ?></p>
            <p><strong>Estado Actual (Cliente):</strong> 
                <?php 
                    $clase_estado_cliente = ($cliente_buscado['estado_cliente'] == 'Activo') ? 'status-activo' : 'status-inactivo';
                    echo "<span class='" . $clase_estado_cliente . "'>" . htmlspecialchars($cliente_buscado['estado_cliente']) . "</span>";
                ?>
            </p>
            <!-- <p><strong>Estado Pago Actual (Según tabla clientes):</strong> 
                <?php 
                    $clase_estado = '';
                    if ($cliente_buscado['estado_pago'] === 'Pagado') {
                        $clase_estado = 'status-pagado';
                    } else if ($cliente_buscado['estado_pago'] === 'Vencido') {
                        $clase_estado = 'status-vencido';
                    } else {
                        $clase_estado = 'status-pendiente';
                    }
                    echo "<span class='" . $clase_estado . "'>" . htmlspecialchars($cliente_buscado['estado_pago']) . "</span>";
                ?>
            </p> -->
            <!-- Resumen financiero del cliente -->
            <?php
            // Total pagado históricamente
            $sql_total_pagado = "SELECT SUM(monto_pagado) as total FROM pagos WHERE cliente_id = " . intval($cliente_buscado['id']);
            $res_total_pagado = $conn->query($sql_total_pagado);
            $total_pagado = $res_total_pagado && $res_total_pagado->num_rows > 0 ? floatval($res_total_pagado->fetch_assoc()['total']) : 0;
            // Meses pagados
            $sql_meses_pagados = "SELECT COUNT(DISTINCT periodo_cubierto) as pagados FROM pagos WHERE cliente_id = " . intval($cliente_buscado['id']);
            $res_meses_pagados = $conn->query($sql_meses_pagados);
            $meses_pagados = $res_meses_pagados && $res_meses_pagados->num_rows > 0 ? intval($res_meses_pagados->fetch_assoc()['pagados']) : 0;
            // Meses posibles (desde inicio hasta hoy)
            $fecha_inicio = $cliente_buscado['fecha_inicio_servicio'];
            $hoy = date('Y-m-01');
            $meses_posibles = 0;
            if (!empty($fecha_inicio) && $fecha_inicio !== '0000-00-00') {
                $meses_array = obtenerMesesEntre($fecha_inicio, $hoy);
                $meses_posibles = count($meses_array);
            }
            // Última vez que pagó
            $sql_ultimo_pago = "SELECT fecha_pago, monto_pagado FROM pagos WHERE cliente_id = " . intval($cliente_buscado['id']) . " ORDER BY fecha_pago DESC LIMIT 1";
            $res_ultimo_pago = $conn->query($sql_ultimo_pago);
            $ultimo_pago = $res_ultimo_pago && $res_ultimo_pago->num_rows > 0 ? $res_ultimo_pago->fetch_assoc() : null;
            // Próximo mes a vencer
            $meses_pendientes = obtenerMesesPendientes($cliente_buscado['id'], $fecha_inicio, $conn);
            $proximo_mes_vencer = !empty($meses_pendientes) ? min($meses_pendientes) : null;
            ?>
            <div class="resumen-financiero" style="background:#f8f9fa;border-radius:8px;padding:14px 18px;margin-bottom:18px;max-width:600px;box-shadow:0 2px 8px #0001;">
                <h3 style="margin-top:0;">Resumen Financiero</h3>
                <ul style="list-style:none;padding-left:0;">
                    <li><b>Total pagado:</b> S/ <?php echo number_format($total_pagado, 2); ?></li>
                    <li><b>Meses pagados:</b> <?php echo $meses_pagados; ?> / <?php echo $meses_posibles; ?><?php if ($meses_posibles > 0) { echo ' (' . round($meses_pagados*100/$meses_posibles) . '%)'; } ?></li>
                    <li><b>Último pago:</b> <?php echo $ultimo_pago ? formatDateForDisplay($ultimo_pago['fecha_pago']) . ' (S/ ' . number_format($ultimo_pago['monto_pagado'],2) . ')' : 'N/A'; ?></li>
                    <li><b>Próximo mes a vencer:</b> <?php echo $proximo_mes_vencer ? htmlspecialchars($proximo_mes_vencer) : 'Ninguno'; ?></li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($cliente_buscado): ?>
            <div class="action-buttons" style="margin-bottom: 20px;">
                <a href="pagos.php?action=edit&id=<?php echo htmlspecialchars($cliente_buscado['id']); ?>" class="edit">Editar Cliente</a>
                <?php if ($cliente_buscado['estado_cliente'] == 'Activo'): ?>
                    <a href="pagos.php?action=deactivate&id=<?php echo htmlspecialchars($cliente_buscado['id']); ?>" class="deactivate">Dar de Baja</a>
                <?php else: ?>
                    <a href="pagos.php?action=activate&id=<?php echo htmlspecialchars($cliente_buscado['id']); ?>" class="activate">Activar Cliente</a>
                <?php endif; ?>
                <button type="button" id="btnVerHistorial" class="btn-historial">Ver historial de pagos</button>
            </div>
        <?php endif; ?>

        <?php
        // Mostrar meses pendientes con checkboxes si hay un cliente buscado o seleccionado
        if ($cliente_buscado) {
            $hoy = date('Y-m-01');
            $fecha_inicio = $cliente_buscado['fecha_inicio_servicio'];
            $meses = [];
            $pendientes = [];
            $futuros = [];
            if (!empty($fecha_inicio) && $fecha_inicio !== '0000-00-00') {
                $meses = obtenerMesesEntre($fecha_inicio, $hoy);
                $pendientes = obtenerMesesPendientes($cliente_buscado['id'], $fecha_inicio, $conn);
                // Calcular meses futuros (solo los dos siguientes si no hay pendientes)
                $ultimo_mes = (count($meses) > 0) ? end($meses) : date('Y-m');
                $futuros = [];
                $dt = new DateTime($ultimo_mes . '-01');
                for ($i = 1; $i <= 2; $i++) {
                    $dt->modify('+1 month');
                    $futuros[] = $dt->format('Y-m');
                }
                // Excluir los que ya están pagados
                // Para esto, obtenemos los pagos ya realizados:
                $pagos = [];
                $sql_pagos = "SELECT periodo_cubierto FROM pagos WHERE cliente_id = " . intval($cliente_buscado['id']);
                $resultado_pagos = $conn->query($sql_pagos);
                if ($resultado_pagos && $resultado_pagos->num_rows > 0) {
                    while ($row_pago = $resultado_pagos->fetch_assoc()) {
                        $pagos[] = $row_pago['periodo_cubierto'];
                    }
                }
                $futuros = array_values(array_diff($futuros, $pagos));
            }
            echo '<h3>Meses Pendientes y Adelantados para Pago</h3>';
            if (empty($pendientes) && empty($futuros)) {
                echo '<p style="color:green;">Ningún mes pendiente ni futuro disponible para pago.</p>';
            } else {
                echo '<form method="POST" action="pagos.php" onsubmit="return confirmarPagoMeses();">';
                echo '<input type="hidden" name="cliente_id" value="' . htmlspecialchars($cliente_buscado['id']) . '">';
                echo '<input type="hidden" name="registrar_pago_multiple" value="1">';
                echo '<ul id="lista-meses" style="color:#c82333; font-weight:bold; list-style:none; padding-left:0;">';
                // Mostrar pendientes
                if (!empty($pendientes)) {
                    $total_pendientes = count($pendientes);
                    foreach ($pendientes as $i => $mes) {
                        $is_last = ($i === $total_pendientes - 1);
                        $disabled = $is_last ? '' : 'disabled';
                        echo '<li><label><input type="checkbox" name="meses_pendientes[]" value="' . htmlspecialchars($mes) . '" class="mes-checkbox" checked ' . $disabled . '> ' . htmlspecialchars($mes) . ' <span style="color:#888;font-weight:normal;">(pendiente)</span></label></li>';
                    }
                }
                // Si no hay pendientes, mostrar los adelantados (inicialmente solo el primero, el segundo se agrega con el botón)
                if (empty($pendientes) && !empty($futuros)) {
                    echo '<li id="adelantado-0" style="display:list-item;"><label><input type="checkbox" name="meses_pendientes[]" value="' . htmlspecialchars($futuros[0]) . '" class="mes-checkbox adelantado-checkbox" checked disabled> ' . htmlspecialchars($futuros[0]) . ' <span style="color:#888;font-weight:normal;">(adelantado)</span></label></li>';
                    if (isset($futuros[1])) {
                        echo '<li id="adelantado-1" style="display:none;"><label><input type="checkbox" name="meses_pendientes[]" value="' . htmlspecialchars($futuros[1]) . '" class="mes-checkbox adelantado-checkbox" disabled> ' . htmlspecialchars($futuros[1]) . ' <span style="color:#888;font-weight:normal;">(adelantado)</span></label></li>';
                    }
                }
                echo '</ul>';
                // Botones para agregar/eliminar mes adelantado
                if (empty($pendientes) && count($futuros) > 1) {
                    echo '<button type="button" id="btnAgregarMes" style="margin-right:8px;">Agregar mes</button>';
                    echo '<button type="button" id="btnEliminarMes">Eliminar mes</button>';
                }
                echo '<br><label for="metodo_pago_multi">Método de Pago:</label> ';
                echo '<select id="metodo_pago_multi" name="metodo_pago" required>';
                echo '<option value="Efectivo">Efectivo</option>';
                echo '<option value="Transferencia">Transferencia</option>';
                echo '<option value="Tarjeta">Tarjeta</option>';
                echo '<option value="Otro">Otro</option>';
                echo '</select><br><br>';
                echo '<div id="resumen_pago" style="margin-bottom:10px;font-weight:bold;"></div>';
                echo '<button type="submit">Registrar Pago de Meses Seleccionados</button>';
                // Eliminar botón de pago anual y su lógica asociada
                // --- En el formulario de meses pendientes ---
                // Eliminar este bloque:
                // if (!empty($pendientes) || !empty($futuros)) {
                //     ...
                //     echo '<br><button type="button" id="btnPagoAnual" ...>Pago anual ...</button>';
                //     echo '<script>window.mesesAnualDisponibles = ...</script>';
                // }
                // --- Eliminar el modal de pago anual y scripts asociados ---
                // Eliminar el div con id="modalPagoAnual" y el bloque <script> relacionado con btnPagoAnual, modalPagoAnual, etc.
                echo '</form>';
            }
        }
        ?>

        <!-- ****** FIN DE SECCIONES REORDENADAS ****** -->

        <!-- Modal de historial de pagos -->
        <div id="modalHistorial" class="modal-historial" style="display:none;">
            <div class="modal-historial-content">
                <span class="close-historial" id="closeHistorial">&times;</span>
                <h3>Historial de Pagos de <?php echo htmlspecialchars($cliente_buscado ? $cliente_buscado['nombre'] : 'Cliente'); ?></h3>
                <?php if (!empty($payment_history)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha Pago</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Periodo Cubierto</th>
                            <th>Registrado el</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $pago): ?>
                            <tr>
                                <td><?php echo formatDateForDisplay($pago['fecha_pago']); ?></td>
                                <td>S/ <?php echo number_format($pago['monto_pagado'], 2); ?></td>
                                <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                                <td><?php echo htmlspecialchars($pago['periodo_cubierto']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($pago['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No hay historial de pagos para este cliente.</p>
                <?php endif; ?>
            </div>
        </div>
        <script>
        // Solo añadir el listener si el botón existe
        if (document.getElementById('btnVerHistorial')) {
            document.getElementById('btnVerHistorial').onclick = function() {
                document.getElementById('modalHistorial').style.display = 'flex';
            };
        }
        if (document.getElementById('closeHistorial')) {
            document.getElementById('closeHistorial').onclick = function() {
                document.getElementById('modalHistorial').style.display = 'none';
            };
        }
        window.addEventListener('click', function(event) {
            var modal = document.getElementById('modalHistorial');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
        </script>
    </div>

    <script>
    // Calcular total y descuento antes de enviar
    const tarifa = <?php echo json_encode($cliente_buscado ? $cliente_buscado['tarifa'] : 0); ?>;
    const descuentoAnual = 0.10;
    function mesesConsecutivos(meses) {
        if (meses.length < 2) return true;
        meses.sort();
        for (let i = 1; i < meses.length; i++) {
            let prev = new Date(meses[i-1] + '-01');
            let curr = new Date(meses[i] + '-01');
            prev.setMonth(prev.getMonth() + 1);
            if (prev.getFullYear() !== curr.getFullYear() || prev.getMonth() !== curr.getMonth()) {
                return false;
            }
        }
        return true;
    }
    function actualizarResumenPago() {
        const resumenDiv = document.getElementById('resumen_pago');
        if (!resumenDiv) return; // Salir si el elemento no existe
        const checks = Array.from(document.querySelectorAll('.mes-checkbox:checked'));
        const meses = checks.map(cb => cb.value);
        let total = tarifa * meses.length;
        let descuento = 0;
        let resumen = '';
        if (meses.length === 12 && mesesConsecutivos(meses)) {
            descuento = total * descuentoAnual;
            total = total - descuento;
            resumen = `<span style='color:#c82333;'>Descuento 10%: S/ ${descuento.toFixed(2)}</span><br><span style='color:green;'>Total a pagar: S/ ${total.toFixed(2)}</span>`;
        } else {
            resumen = `Total a pagar: S/ ${total.toFixed(2)}`;
        }
        resumenDiv.innerHTML = resumen;
        // Guardar para confirmación
        resumenDiv.setAttribute('data-descuento', descuento.toFixed(2));
        resumenDiv.setAttribute('data-total', total.toFixed(2));
    }
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('mes-checkbox')) {
            actualizarResumenPago();
        }
    });
    document.addEventListener('DOMContentLoaded', actualizarResumenPago);
    function confirmarPagoMeses() {
        const checkboxes = document.querySelectorAll("input[name='meses_pendientes[]']:checked");
        if (checkboxes.length === 0) {
            alert("Debes seleccionar al menos un mes para registrar el pago.");
            return false;
        }
        let meses = Array.from(checkboxes).map(cb => cb.value).join(', ');
        let resumenDiv = document.getElementById('resumen_pago');
        let descuento = resumenDiv.getAttribute('data-descuento');
        let total = resumenDiv.getAttribute('data-total');
        let mensaje = "¿Confirmas el pago de los siguientes meses?\n" + meses + "\n";
        if (parseFloat(descuento) > 0) {
            mensaje += `Descuento 10%: S/ ${descuento}\n`;
        }
        mensaje += `Total a pagar: S/ ${total}`;
        return confirm(mensaje);
    }
    // Lógica para agregar/eliminar meses adelantados
    if (document.getElementById('btnAgregarMes')) {
        document.getElementById('btnAgregarMes').onclick = function() {
            var li1 = document.getElementById('adelantado-1');
            if (li1 && li1.style.display === 'none') {
                li1.style.display = 'list-item';
                var cb = li1.querySelector('input[type="checkbox"]');
                cb.checked = true;
                cb.disabled = false;
            }
        };
    }
    if (document.getElementById('btnEliminarMes')) {
        document.getElementById('btnEliminarMes').onclick = function() {
            var li1 = document.getElementById('adelantado-1');
            if (li1 && li1.style.display !== 'none') {
                var cb = li1.querySelector('input[type="checkbox"]');
                cb.checked = false;
                cb.disabled = true;
                li1.style.display = 'none';
            }
        };
    }
    if (document.getElementById('btnCerrarExitoPago')) {
        document.getElementById('btnCerrarExitoPago').onclick = function() {
            window.location.href = 'pagos.php';
        };
    }
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const listaMeses = document.getElementById('lista-meses');
    if (!listaMeses) return;
    const checkboxes = Array.from(listaMeses.querySelectorAll('.mes-checkbox'));
    function actualizarHabilitados() {
        // Solo aplica si hay más de un pendiente
        let lastChecked = -1;
        for (let i = checkboxes.length - 1; i >= 0; i--) {
            if (checkboxes[i].checked) {
                lastChecked = i;
                break;
            }
        }
        // Todos los anteriores a lastChecked deben estar checked y disabled
        for (let i = 0; i < checkboxes.length; i++) {
            if (i < lastChecked) {
                checkboxes[i].checked = true;
                checkboxes[i].disabled = true;
            } else if (i === lastChecked) {
                checkboxes[i].disabled = false;
            } else {
                checkboxes[i].disabled = false;
            }
        }
    }
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            // Si se desmarca, habilitar el anterior
            actualizarHabilitados();
        });
    });
    actualizarHabilitados();
});
</script>
</body>
</html>
<?php
// Cierra la conexión a la base de datos al final del script.
$conn->close();
?>
<?php include 'footer.php'; ?>