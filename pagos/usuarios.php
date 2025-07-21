<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
require_role('admin');
require_once 'conexion.php';
include 'header.php';
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_usuario'])) {
    $nuevo_username = trim($_POST['nuevo_username'] ?? '');
    $nuevo_nombre = trim($_POST['nuevo_nombre'] ?? '');
    $nuevo_password = $_POST['nuevo_password'] ?? '';
    $nuevo_password2 = $_POST['nuevo_password2'] ?? '';
    $nuevo_rol = $_POST['nuevo_rol'] ?? 'usuario';
    if ($nuevo_username === '' || $nuevo_nombre === '' || $nuevo_password === '' || $nuevo_password2 === '' || $nuevo_rol === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif ($nuevo_password !== $nuevo_password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($nuevo_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO usuarios (username, password, nombre, rol) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $nuevo_username, $hash, $nuevo_nombre, $nuevo_rol);
        try {
            $stmt->execute();
            $mensaje = 'Usuario creado exitosamente.';
        } catch (mysqli_sql_exception $e) {
            if ($conn->errno === 1062) {
                $error = 'El nombre de usuario ya existe.';
            } else {
                $error = 'Error al crear usuario: ' . $e->getMessage();
            }
        }
        $stmt->close();
    }
}
if (isset($_POST['importar_csv']) && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
    if (isset($_FILES['csv_clientes']) && $_FILES['csv_clientes']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['csv_clientes']['tmp_name'];
        $handle = fopen($fileTmp, 'r');
        $row = 0;
        $importados = 0;
        $errores = 0;
        $errores_detalle = [];
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $row++;
            if ($row == 1 && strtolower($data[0]) == 'dni') continue;
            $dni = trim($data[0] ?? '');
            $nombre = trim($data[1] ?? '');
            $direccion = trim($data[2] ?? '');
            $tarifa = floatval($data[3] ?? 0);
            $fecha_inicio = trim($data[4] ?? '');
            $latitud = trim($data[5] ?? '');
            $longitud = trim($data[6] ?? '');
            $dni_val = validarDNI($dni, $conn);
            if ($dni_val !== true || $nombre === '' || $direccion === '' || $tarifa <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
                $errores++;
                $errores_detalle[] = "Fila $row: " . ($dni_val !== true ? $dni_val : 'Datos inválidos.');
                continue;
            }
            $stmt = $conn->prepare("INSERT INTO clientes (dni, nombre, direccion, tarifa, fecha_inicio_servicio, latitud, longitud, estado_cliente, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, 'Activo', 'Pendiente')");
            $stmt->bind_param("ssssdds", $dni, $nombre, $direccion, $tarifa, $fecha_inicio, $latitud, $longitud);
            if ($stmt->execute()) {
                $importados++;
            } else {
                $errores++;
                $errores_detalle[] = "Fila $row: Error al insertar.";
            }
            $stmt->close();
        }
        fclose($handle);
        mostrarMensaje("Importación finalizada. Importados: $importados. Errores: $errores.", $errores > 0 ? 'warning' : 'success');
        if ($errores > 0 && !empty($errores_detalle)) {
            echo '<ul style="color:#c82333;">';
            foreach ($errores_detalle as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
            echo '</ul>';
        }
    } else {
        mostrarMensaje('Error al subir el archivo CSV.', 'error');
    }
}
if (isset($_GET['eliminar_usuario']) && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
    $id_eliminar = intval($_GET['eliminar_usuario']);
    if ($id_eliminar !== $_SESSION['usuario_id']) {
        $stmt = $conn->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id_eliminar);
        if ($stmt->execute()) {
            $mensaje = 'Usuario eliminado correctamente.';
        } else {
            $error = 'Error al eliminar usuario.';
        }
        $stmt->close();
    } else {
        $error = 'No puedes eliminar tu propio usuario.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena_admin']) && isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
    $id_usuario = intval($_POST['id_usuario']);
    $nueva_contrasena = $_POST['nueva_contrasena_admin'] ?? '';
    $nueva_contrasena2 = $_POST['nueva_contrasena2_admin'] ?? '';
    $resultado = cambiarContrasenaUsuario($id_usuario, $nueva_contrasena, $nueva_contrasena2, $conn);
    if ($resultado['ok']) {
        $mensaje = $resultado['mensaje'];
    } else {
        $error = $resultado['mensaje'];
    }
}
$usuarios = [];
$res = $conn->query('SELECT id, username, nombre, rol FROM usuarios ORDER BY id ASC');
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $usuarios[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Gestión de Usuarios</h1>
    <?php if ($mensaje): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <h2>Registrar Nuevo Usuario</h2>
    <form method="POST" autocomplete="off" style="max-width:400px;">
        <input type="hidden" name="nuevo_usuario" value="1">
        <label for="nuevo_username">Usuario:</label>
        <input type="text" id="nuevo_username" name="nuevo_username" required maxlength="50">
        <label for="nuevo_nombre">Nombre:</label>
        <input type="text" id="nuevo_nombre" name="nuevo_nombre" required maxlength="100">
        <label for="nuevo_password">Contraseña:</label>
        <input type="password" id="nuevo_password" name="nuevo_password" required minlength="4">
        <label for="nuevo_password2">Repetir Contraseña:</label>
        <input type="password" id="nuevo_password2" name="nuevo_password2" required minlength="4">
        <label for="nuevo_rol">Rol:</label>
        <select id="nuevo_rol" name="nuevo_rol" required>
            <option value="usuario">Usuario</option>
            <option value="admin">Administrador</option>
        </select>
        <button type="submit">Crear Usuario</button>
    </form>
    <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
<button id="btnAbrirImportarClientes" style="margin:18px 0 10px 0;">Importar Clientes</button>
<div id="modalImportarClientes" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
  <div style="background:#fff;padding:25px 20px 20px 20px;border-radius:8px;max-width:400px;width:95vw;box-shadow:0 2px 8px #0003;position:relative;">
    <span id="cerrarModalImportar" style="position:absolute;top:10px;right:18px;font-size:1.7em;color:#888;cursor:pointer;font-weight:bold;">&times;</span>
    <h2 style="margin-top:0;">Importar Clientes desde CSV</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="csv_clientes" accept=".csv" required> <br><br>
      <button type="submit" name="importar_csv">Importar CSV</button>
      <br><small>Formato: dni,nombre,direccion,tarifa,fecha_inicio_servicio,latitud,longitud</small>
    </form>
  </div>
</div>
<script>
document.getElementById('btnAbrirImportarClientes').onclick = function() {
  document.getElementById('modalImportarClientes').style.display = 'flex';
};
document.getElementById('cerrarModalImportar').onclick = function() {
  document.getElementById('modalImportarClientes').style.display = 'none';
};
window.onclick = function(event) {
  var modal = document.getElementById('modalImportarClientes');
  if (event.target == modal) {
    modal.style.display = 'none';
  }
};
</script>
<?php endif; ?>
    <h2 style="margin-top:40px;">Usuarios Registrados</h2>
    <table style="width:100%;max-width:600px;">
        <thead><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Rol</th><?php if ($_SESSION['usuario_rol'] === 'admin') echo '<th>Acción</th>'; ?></tr></thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td data-label="ID"><?php echo $u['id']; ?></td>
                    <td data-label="Usuario"><?php echo htmlspecialchars($u['username']); ?></td>
                    <td data-label="Nombre"><?php echo htmlspecialchars($u['nombre']); ?></td>
                    <td data-label="Rol"><?php echo htmlspecialchars($u['rol']); ?></td>
                    <?php if ($_SESSION['usuario_rol'] === 'admin'): ?>
                        <td data-label="Acción" style="min-width:120px; display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                            <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                                <button type="button" class="btn-cambiar-pass" onclick="abrirModalPass(<?php echo $u['id']; ?>)">Cambiar contraseña</button>
                                <form method="GET" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
                                    <input type="hidden" name="eliminar_usuario" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn-eliminar-usuario">Eliminar</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#888;">(Tú)</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<!-- Modal para cambiar contraseña -->
<div id="modalCambiarPass" style="display:none;position:fixed;z-index:99999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
  <div id="modalCambiarPassContent" style="background:#fff;padding:25px 20px 20px 20px;border-radius:8px;max-width:350px;width:95vw;box-shadow:0 2px 8px #0003;position:relative;">
    <span id="cerrarModalCambiarPass" style="position:absolute;top:10px;right:18px;font-size:1.7em;color:#888;cursor:pointer;font-weight:bold;">&times;</span>
    <h2 style="margin-top:0;font-size:1.2em;">Cambiar contraseña</h2>
    <form method="POST" id="formCambiarPassModal" autocomplete="off">
      <input type="hidden" name="cambiar_contrasena_admin" value="1">
      <input type="hidden" name="id_usuario" id="modal_id_usuario" value="">
      <label for="modal_nueva_contrasena_admin">Nueva contraseña:</label>
      <input type="password" name="nueva_contrasena_admin" id="modal_nueva_contrasena_admin" placeholder="Nueva contraseña" required minlength="4" style="width:100%;margin-bottom:8px;">
      <label for="modal_nueva_contrasena2_admin">Repetir contraseña:</label>
      <input type="password" name="nueva_contrasena2_admin" id="modal_nueva_contrasena2_admin" placeholder="Repetir" required minlength="4" style="width:100%;margin-bottom:12px;">
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="submit">Guardar</button>
        <button type="button" onclick="cerrarModalPass()">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>
<script>
function abrirModalPass(id) {
    document.getElementById('modalCambiarPass').style.display = 'flex';
    document.getElementById('modal_id_usuario').value = id;
    document.getElementById('modal_nueva_contrasena_admin').value = '';
    document.getElementById('modal_nueva_contrasena2_admin').value = '';
    setTimeout(function(){
        document.getElementById('modal_nueva_contrasena_admin').focus();
    }, 200);
}
function cerrarModalPass() {
    document.getElementById('modalCambiarPass').style.display = 'none';
}
document.getElementById('cerrarModalCambiarPass').onclick = cerrarModalPass;
window.onclick = function(event) {
    var modal = document.getElementById('modalCambiarPass');
    if (event.target == modal) {
        cerrarModalPass();
    }
};
</script>
</body>
</html>
<?php $conn->close(); ?> 