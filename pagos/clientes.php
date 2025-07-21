<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
checkSessionInactivity();
require_any_role(['admin','usuario']);
include 'header.php';
require_once 'conexion.php';

// --- Lógica para agregar un nuevo cliente ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["agregar_cliente"])) {
    $dni = trim($_POST["dni"]);
    $dni_val = validarDNI($dni, $conn);
    if ($dni_val !== true) {
        mostrarMensaje($dni_val, 'error');
        $conn->close();
        exit();
    }
    $nombre = htmlspecialchars(trim($_POST["nombre"]));
    $direccion = htmlspecialchars(trim($_POST["direccion"]));
    $tarifa = floatval($_POST["tarifa"]);
    $costo_instalacion = isset($_POST["costo_instalacion"]) ? floatval($_POST["costo_instalacion"]) : 0;
    $fecha_inicio_servicio = $_POST["fecha_inicio_servicio"];
    if (empty($fecha_inicio_servicio)) {
        $fecha_inicio_servicio = date('Y-m-d');
    }
    $latitud = isset($_POST["latitud"]) ? $_POST["latitud"] : null;
    $longitud = isset($_POST["longitud"]) ? $_POST["longitud"] : null;
    
    $stmt = $conn->prepare("INSERT INTO clientes (dni, nombre, direccion, tarifa, fecha_inicio_servicio, latitud, longitud, estado_cliente, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', 'Pendiente')");
    
    $stmt->bind_param("sssdsdd", $dni, $nombre, $direccion, $tarifa, $fecha_inicio_servicio, $latitud, $longitud);
    
    if ($stmt->execute()) {
        $nuevo_cliente_id = $stmt->insert_id;
        // Calcular fecha de próximo pago mensual (un mes después de la fecha de inicio)
        $fecha_inicio_dt = new DateTime($fecha_inicio_servicio);
        $fecha_proximo_pago_dt = clone $fecha_inicio_dt;
        $fecha_proximo_pago_dt->modify('+1 month');
        $fecha_proximo_pago = $fecha_proximo_pago_dt->format('Y-m-d');
        // Registrar pago de instalación como primer mes si corresponde
        if ($costo_instalacion > 0) {
            $fecha_pago = date('Y-m-d');
            $metodo_pago = 'Instalación';
            $periodo_cubierto = $fecha_inicio_dt->format('Y-m');
            $usuario_id = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : null;
            $stmt_pago = $conn->prepare("INSERT INTO pagos (cliente_id, usuario_id, fecha_pago, monto_pagado, metodo_pago, periodo_cubierto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_pago->bind_param("iisdss", $nuevo_cliente_id, $usuario_id, $fecha_pago, $costo_instalacion, $metodo_pago, $periodo_cubierto);
            $stmt_pago->execute();
            $stmt_pago->close();
        }
        // Actualizar el cliente con la fecha de próximo pago y dejarlo como pagado
        $stmt_update = $conn->prepare("UPDATE clientes SET fecha_proximo_pago = ?, estado_pago = 'Pagado' WHERE id = ?");
        $stmt_update->bind_param("si", $fecha_proximo_pago, $nuevo_cliente_id);
        $stmt_update->execute();
        $stmt_update->close();
        mostrarMensaje('¡Cliente agregado exitosamente!', 'success');
    } else {
        mostrarMensaje('Error al agregar cliente: ' . $stmt->error, 'error');
    }
    $stmt->close();
}

// --- Lógica de actualización de estado_pago para clientes activos ---
$hoy = date('Y-m-d');
$conn->query("UPDATE clientes SET estado_pago = 'Pendiente' WHERE estado_cliente = 'Activo' AND estado_pago != 'Pagado' AND fecha_proximo_pago IS NOT NULL AND fecha_proximo_pago != '0000-00-00' AND fecha_proximo_pago <= '$hoy'");

// --- Lógica de paginación ---
$page_sizes = [10, 20, 50, 100];
$page_size = isset($_GET['page_size']) && in_array((int)$_GET['page_size'], $page_sizes) ? (int)$_GET['page_size'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $page_size;

// --- Lógica de filtrado para mostrar clientes ---
$filter_state = isset($_GET['show']) ? $_GET['show'] : 'active'; // 'active', 'inactive', 'all'

$sql_where_clause = "";
switch ($filter_state) {
    case 'inactive':
        $sql_where_clause = " WHERE estado_cliente = 'Inactivo'";
        break;
    case 'all':
        $sql_where_clause = ""; // Sin filtro
        break;
    case 'active':
    default:
        $sql_where_clause = " WHERE estado_cliente = 'Activo'";
        break;
}

$sql_order_clause = " ORDER BY id DESC";
// --- Contar total de clientes para paginación ---
$sql_count = "SELECT COUNT(*) as total FROM clientes" . $sql_where_clause;
$result_count = $conn->query($sql_count);
$total_clientes = $result_count ? (int)$result_count->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_clientes / $page_size));

// --- Consulta paginada ---
$sql_full = "SELECT id, nombre, direccion, tarifa, fecha_inicio_servicio, estado_cliente, estado_pago FROM clientes" . $sql_where_clause . $sql_order_clause . " LIMIT $page_size OFFSET $offset";
$result_display = $conn->query($sql_full);

$overdue_clients = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Control de Pagos</title>
    <!-- Hoja de estilos principal -->
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Estilos específicos para componentes de esta página -->
    <style>
        /* ====== ESTILOS DEL MODAL ====== */
        /* ELIMINADO: .modal y .modal-content para evitar conflicto con Bootstrap */
        .close-button {
            color: #aaa; position: absolute; top: 15px; right: 25px;
            font-size: 28px; font-weight: bold; cursor: pointer;
        }
        .close-button:hover, .close-button:focus { color: #333; }

        /* ====== ESTILOS PARA LA TABLA RESPONSIVA ====== */
        @media screen and (max-width: 768px) {
            table { border: 0; }
            table thead { display: none; }
            table tr {
                display: block;
                margin-bottom: 1.5rem;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 1rem;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 0;
                border-bottom: 1px dotted #ccc;
                text-align: right;
            }
            table tr td:last-child { border-bottom: none; }
            table td::before {
                content: attr(data-label);
                font-weight: bold;
                color: #0056b3;
                text-align: left;
                margin-right: 1rem;
            }
        }

        /* ====== CONTROLES DE PAGINACIÓN Y FILTROS ====== */
        .page-size-form {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        .page-size-form label {
            margin-bottom: 0;
            font-weight: normal;
        }
        .pagination { display: inline-block; margin-top: 18px; }
        .pagination a, .pagination span {
            color: #007bff;
            padding: 8px 12px;
            text-decoration: none;
            transition: background-color .3s;
            border: 1px solid #ddd;
            margin: 0 2px;
            border-radius: 4px;
        }
        .pagination span { font-weight: bold; background-color: #007bff; color: white; border-color: #007bff; }
        .pagination a:hover { background-color: #f2f2f2; }
        
        /* ====== ESTILOS ADICIONALES ====== */
        .status-vencido { background-color: #fbeaea !important; color: #a71d2a; font-weight: bold; }
        .status-vencido a { color: #a71d2a; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Clientes</h1>

        <?php
        $rows = [];
        if ($result_display && $result_display->num_rows > 0) {
            while($row = $result_display->fetch_assoc()) {
                $rows[] = $row;
                if ($filter_state !== 'inactive' && isset($row['estado_pago']) && $row['estado_pago'] == 'Vencido') {
                    $overdue_clients[] = $row;
                }
            }
        }

        if (!empty($overdue_clients)) :
        ?>
        <div class="alert alert-warning">
            <h3 style="margin-top: 0;">&#9888; Alerta de Pagos Vencidos</h3>
            <p>Los siguientes clientes tienen pagos que requieren atención:</p>
            <ul style="padding-left: 20px; margin-bottom: 0;">
                <?php foreach ($overdue_clients as $client) : ?>
                    <li><a href="pagos.php?select_client_id=<?php echo htmlspecialchars($client['id']); ?>"><?php echo htmlspecialchars($client['nombre']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 20px;">
            <button id="openModalBtn" class="btn btn-success mb-3"><i class="bi bi-person-plus"></i> Agregar Nuevo Cliente</button>
            <input type="text" id="busquedaCliente" placeholder="Buscar cliente..." style="flex-grow: 1; min-width: 200px;">
            <div class="filter-links">
                Mostrar:
                <a href="clientes.php?show=active&page_size=<?php echo $page_size; ?>">Activos</a> |
                <a href="clientes.php?show=inactive&page_size=<?php echo $page_size; ?>">Inactivos</a> |
                <a href="clientes.php?show=all&page_size=<?php echo $page_size; ?>">Todos</a>
            </div>
        </div>

        <h2 class="mt-4">Listado de Clientes</h2>
        
        <form method="get" class="page-size-form mb-2">
            <input type="hidden" name="show" value="<?php echo htmlspecialchars($filter_state); ?>">
            <label for="page_size_table">Ver</label>
            <select name="page_size" id="page_size_table" onchange="this.form.submit()">
                <?php foreach ($page_sizes as $size): ?>
                    <option value="<?php echo $size; ?>" <?php if ($page_size == $size) echo 'selected'; ?>><?php echo $size; ?></option>
                <?php endforeach; ?>
            </select>
            <span>por página</span>
        </form>

        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Dirección</th>
                    <th>Tarifa</th>
                    <th>Inicio Servicio</th>
                    <th>Estado Cliente</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($rows)) {
                    foreach($rows as $row) {
                        $vencido_class = (isset($row['estado_pago']) && $row['estado_pago'] == 'Vencido') ? 'table-danger' : '';
                        echo "<tr class='" . $vencido_class . "'>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td><a href='pagos.php?select_client_id=" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['nombre']) . '</a></td>';
                        echo "<td>" . htmlspecialchars($row['direccion']) . "</td>";
                        echo "<td>S/ " . number_format($row['tarifa'], 2) . "</td>";
                        echo "<td>" . formatDateForDisplay($row['fecha_inicio_servicio']) . "</td>";
                        $clase_estado_cliente = ($row['estado_cliente'] == 'Activo') ? 'text-success' : 'text-secondary';
                        echo "<td class='" . $clase_estado_cliente . "'>" . htmlspecialchars($row['estado_cliente']) . "</td>";
                        echo "<td>";
                        echo '<button class="btn btn-sm btn-outline-primary editar-cliente-link" title="Editar" data-id="' . $row['id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '" data-direccion="' . htmlspecialchars($row['direccion']) . '" data-tarifa="' . $row['tarifa'] . '" data-fecha="' . $row['fecha_inicio_servicio'] . '"><i class="bi bi-pencil-square"></i></button> ';
                        echo '<a href="pagos.php?select_client_id=' . htmlspecialchars($row['id']) . '" class="btn btn-sm btn-outline-info" title="Ver Pagos"><i class="bi bi-receipt"></i></a> ';
                        echo '<button class="btn btn-sm btn-outline-danger eliminar-cliente-link" title="Eliminar" data-id="' . $row['id'] . '" data-nombre="' . htmlspecialchars($row['nombre']) . '"><i class="bi bi-trash"></i></button>';
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center py-4'>No hay clientes que coincidan con el filtro seleccionado.</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <div style="text-align:center;">
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $base_url = 'clientes.php?show=' . urlencode($filter_state) . '&page_size=' . $page_size;
                    $prev_page = max(1, $page - 1);
                    $next_page = min($total_pages, $page + 1);
                    if ($page > 1) echo '<a href="' . $base_url . '&page=1">&laquo;</a> <a href="' . $base_url . '&page=' . $prev_page . '">&lt;</a> ';
                    
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++) {
                        if ($i == $page) {
                            echo '<span>' . $i . '</span>';
                        } else {
                            echo '<a href="' . $base_url . '&page=' . $i . '">' . $i . '</a>';
                        }
                    }
                    if ($page < $total_pages) echo '<a href="' . $base_url . '&page=' . $next_page . '">&gt;</a> <a href="' . $base_url . '&page=' . $total_pages . '">&raquo;</a>';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modales para agregar, editar y eliminar cliente -->
    <!-- Modal Agregar Cliente (adaptar a Bootstrap) -->
    <div id="addClientModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form action="clientes.php" method="POST">
                <input type="hidden" name="agregar_cliente" value="1">
                        <div class="mb-2">
                            <label for="dni" class="form-label">DNI:</label>
                            <input type="text" id="dni" name="dni" pattern="\d{8}" maxlength="8" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="nombre" class="form-label">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="direccion" class="form-label">Dirección:</label>
                            <input type="text" id="direccion" name="direccion" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="tarifa" class="form-label">Tarifa (S/):</label>
                            <input type="number" step="0.01" id="tarifa" name="tarifa" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="costo_instalacion" class="form-label">Costo de Instalación (S/):</label>
                            <input type="number" step="0.01" id="costo_instalacion" name="costo_instalacion" min="0" class="form-control" placeholder="Ej: 50.00">
                        </div>
                        <div class="mb-2">
                            <label for="fecha_inicio_servicio" class="form-label">Fecha de Inicio del Servicio:</label>
                            <input type="date" id="fecha_inicio_servicio" name="fecha_inicio_servicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-2">
                            <label for="latitud" class="form-label">Latitud (GPS):</label>
                            <input type="text" id="latitud" name="latitud" pattern="^-?\d{1,2}\.\d+" step="any" class="form-control" required readonly>
                        </div>
                        <div class="mb-2">
                            <label for="longitud" class="form-label">Longitud (GPS):</label>
                            <input type="text" id="longitud" name="longitud" pattern="^-?\d{1,3}\.\d+" step="any" class="form-control" required readonly>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Agregar Cliente</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Editar Cliente -->
    <div id="modalEditarCliente" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="editar_cliente" value="1">
                        <input type="hidden" id="editar_id" name="editar_id">
                        <div class="mb-2">
                            <label for="editar_nombre" class="form-label">Nombre:</label>
                            <input type="text" id="editar_nombre" name="editar_nombre" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="editar_direccion" class="form-label">Dirección:</label>
                            <input type="text" id="editar_direccion" name="editar_direccion" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="editar_tarifa" class="form-label">Tarifa (S/):</label>
                            <input type="number" step="0.01" id="editar_tarifa" name="editar_tarifa" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="editar_fecha" class="form-label">Fecha de Inicio del Servicio:</label>
                            <input type="date" id="editar_fecha" name="editar_fecha" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Guardar Cambios</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Eliminar Cliente -->
    <div id="modalEliminarCliente" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" autocomplete="off">
                        <input type="hidden" name="eliminar_cliente" value="1">
                        <input type="hidden" id="eliminar_id" name="eliminar_id">
                        <p>¿Seguro que deseas eliminar al cliente <span id="eliminar_nombre" style="font-weight:bold;"></span>?</p>
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                        <button type="button" class="btn btn-secondary ms-2" id="cancelarEliminarCliente">Cancelar</button>
            </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Modal agregar cliente
        const openModalBtn = document.getElementById('openModalBtn');
        const addClientModal = new bootstrap.Modal(document.getElementById('addClientModal'));
        if(openModalBtn) { openModalBtn.onclick = function() { addClientModal.show(); } }
        // Modal editar cliente
        const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarCliente'));
        document.querySelectorAll('.editar-cliente-link').forEach(function(btn) {
            btn.onclick = function(e) {
                e.preventDefault();
                document.getElementById('editar_id').value = this.getAttribute('data-id');
                document.getElementById('editar_nombre').value = this.getAttribute('data-nombre');
                document.getElementById('editar_direccion').value = this.getAttribute('data-direccion');
                document.getElementById('editar_tarifa').value = this.getAttribute('data-tarifa');
                document.getElementById('editar_fecha').value = this.getAttribute('data-fecha');
                modalEditar.show();
            };
        });
        // Modal eliminar cliente
        const modalEliminar = new bootstrap.Modal(document.getElementById('modalEliminarCliente'));
        document.querySelectorAll('.eliminar-cliente-link').forEach(function(btn) {
            btn.onclick = function(e) {
                e.preventDefault();
                document.getElementById('eliminar_id').value = this.getAttribute('data-id');
                document.getElementById('eliminar_nombre').textContent = this.getAttribute('data-nombre');
                modalEliminar.show();
            };
        });
        document.getElementById('cancelarEliminarCliente').onclick = function() { modalEliminar.hide(); };


        // --- Lógica de Búsqueda en Tabla ---
        const busquedaInput = document.getElementById('busquedaCliente');
        if(busquedaInput) {
            busquedaInput.addEventListener('input', function() {
                const filtro = this.value.toLowerCase();
                const filas = document.querySelectorAll('table tbody tr');
                filas.forEach(fila => {
                    const texto = fila.textContent.toLowerCase();
                    fila.style.display = texto.includes(filtro) ? '' : 'none';
                });
            });
        }

        // --- Lógica del Formulario (dentro del modal) ---
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitud').value = position.coords.latitude.toFixed(6);
                document.getElementById('longitud').value = position.coords.longitude.toFixed(6);
            }, function(error) { console.warn('No se pudo obtener la ubicación:', error.message); });
        } else { console.warn('Geolocalización no soportada por este navegador.'); }

        const dniInput = document.getElementById('dni');
        const nombreInput = document.getElementById('nombre');
        // Agrego un span para mensajes de error debajo del campo DNI
        let dniMsg = document.getElementById('dni-msg');
        if (!dniMsg && dniInput) {
            dniMsg = document.createElement('span');
            dniMsg.id = 'dni-msg';
            dniMsg.style.display = 'block';
            dniMsg.style.color = 'red';
            dniMsg.style.fontSize = '0.95em';
            dniInput.parentNode.appendChild(dniMsg);
        }
        if (dniInput) {
            dniInput.addEventListener('input', function() {
                nombreInput.value = '';
                if (dniMsg) dniMsg.textContent = '';
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
                            // Si quieres también actualizar el campo DNI con el valor normalizado de la API:
                            if (data.dni && data.dni !== dniInput.value) {
                                dniInput.value = data.dni;
                            }
                            if (dniMsg) dniMsg.textContent = '';
                        } else {
                            if (dniMsg) dniMsg.textContent = 'No se encontró información para el DNI ingresado.';
                        }
                    })
                    .catch(err => {
                        if (dniMsg) dniMsg.textContent = 'Error al consultar el DNI. Intente nuevamente.';
                        nombreInput.value = '';
                    });
                }
            });
        }
    });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn->close();
?>