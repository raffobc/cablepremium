<?php
// utils.php

/**
 * Formatea una fecha de la base de datos a 'DD/MM/YYYY' o 'N/A' si es inválida.
 * @param string|null $dateString
 * @return string
 */
function formatDateForDisplay($dateString) {
    $dateString = trim($dateString);
    if (empty($dateString) || $dateString === '0000-00-00') {
        return 'N/A';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $dateString);
    if ($dt !== false) {
        return $dt->format('d/m/Y');
    }
    return 'N/A';
}

/**
 * Devuelve un array de meses (Y-m) entre dos fechas (inclusive el mes de inicio, hasta el mes anterior al de fin).
 * @param string $fecha_inicio (YYYY-MM-DD)
 * @param string $fecha_fin (YYYY-MM-DD)
 * @return array
 */
function obtenerMesesEntre($fecha_inicio, $fecha_fin) {
    $meses = [];
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('first day of next month');
    while ($inicio < $fin) {
        $meses[] = $inicio->format('Y-m');
        $inicio->modify('+1 month');
    }
    return $meses;
}

/**
 * Devuelve un array de meses pendientes de pago para un cliente dado.
 * @param int $cliente_id
 * @param string $fecha_inicio (YYYY-MM-DD)
 * @param mysqli $conn
 * @param string|null $fecha_fin (YYYY-MM-DD), por defecto el mes actual
 * @return array
 */
function obtenerMesesPendientes($cliente_id, $fecha_inicio, $conn, $fecha_fin = null) {
    if (empty($fecha_inicio) || $fecha_inicio === '0000-00-00') return [];
    $hoy = $fecha_fin ?? date('Y-m-01');
    $meses = obtenerMesesEntre($fecha_inicio, $hoy);
    $pagos = [];
    $sql_pagos = "SELECT periodo_cubierto FROM pagos WHERE cliente_id = ?";
    $stmt = $conn->prepare($sql_pagos);
    $stmt->bind_param('i', $cliente_id);
    $stmt->execute();
    $result_pagos = $stmt->get_result();
    if ($result_pagos && $result_pagos->num_rows > 0) {
        while ($row_pago = $result_pagos->fetch_assoc()) {
            $pagos[] = $row_pago['periodo_cubierto'];
        }
    }
    $stmt->close();
    $pendientes = array_values(array_diff($meses, $pagos));
    sort($pendientes); // Ordenar del más antiguo al más nuevo
    return $pendientes;
}

/**
 * Muestra un mensaje de usuario con estilo según el tipo.
 * @param string $mensaje
 * @param string $tipo (success, error, warning, info)
 */
function mostrarMensaje($mensaje, $tipo = 'success') {
    $clase = 'alert-success';
    if ($tipo === 'error') $clase = 'alert-error';
    elseif ($tipo === 'warning') $clase = 'alert-warning';
    elseif ($tipo === 'info') $clase = 'alert-info';
    echo "<div class='alert $clase'>" . htmlspecialchars($mensaje) . "</div>";
}

/**
 * Requiere que el usuario tenga un rol específico para acceder a la página.
 */
function require_role($role) {
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_rol'] ?? '') !== $role) {
        echo '<div class="container"><h2>Acceso denegado</h2></div>';
        include 'footer.php';
        exit();
    }
}

/**
 * Requiere que el usuario tenga alguno de los roles especificados.
 */
function require_any_role($roles = []) {
    if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_rol'] ?? '', $roles)) {
        echo '<div class="container"><h2>Acceso denegado</h2></div>';
        include 'footer.php';
        exit();
    }
}

/**
 * Valida el formato y unicidad del DNI en la tabla clientes.
 * @param string $dni
 * @param mysqli $conn
 * @param int|null $exclude_id (opcional) - para excluir un cliente al editar
 * @return true|string Devuelve true si es válido, o un mensaje de error si no lo es
 */
function validarDNI($dni, $conn, $exclude_id = null) {
    if (!preg_match('/^\d{8}$/', $dni)) {
        return 'El DNI debe tener exactamente 8 dígitos.';
    }
    if ($exclude_id) {
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE dni = ? AND id != ? LIMIT 1");
        $stmt->bind_param("si", $dni, $exclude_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE dni = ? LIMIT 1");
        $stmt->bind_param("s", $dni);
    }
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return 'Ya existe un cliente registrado con ese DNI.';
    }
    $stmt->close();
    return true;
}

/**
 * Cambia la contraseña de un usuario.
 * @param int $usuario_id
 * @param string $nueva_contrasena
 * @param string $nueva_contrasena2
 * @param mysqli $conn
 * @param string|null $contrasena_actual (opcional, si se requiere verificar la actual)
 * @return array ['ok'=>bool, 'mensaje'=>string]
 */
function cambiarContrasenaUsuario($usuario_id, $nueva_contrasena, $nueva_contrasena2, $conn, $contrasena_actual = null) {
    if ($nueva_contrasena === '' || $nueva_contrasena2 === '') {
        return ['ok'=>false, 'mensaje'=>'Todos los campos de contraseña son obligatorios.'];
    } elseif ($nueva_contrasena !== $nueva_contrasena2) {
        return ['ok'=>false, 'mensaje'=>'Las contraseñas no coinciden.'];
    } elseif (strlen($nueva_contrasena) < 4) {
        return ['ok'=>false, 'mensaje'=>'La nueva contraseña debe tener al menos 4 caracteres.'];
    }
    if ($contrasena_actual !== null) {
        $stmt = $conn->prepare('SELECT password FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (!password_verify($contrasena_actual, $row['password'])) {
                $stmt->close();
                return ['ok'=>false, 'mensaje'=>'La contraseña actual es incorrecta.'];
            }
        } else {
            $stmt->close();
            return ['ok'=>false, 'mensaje'=>'Usuario no encontrado.'];
        }
        $stmt->close();
    }
    $nuevo_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
    $stmt2->bind_param('si', $nuevo_hash, $usuario_id);
    if ($stmt2->execute()) {
        $stmt2->close();
        return ['ok'=>true, 'mensaje'=>'Contraseña actualizada exitosamente.'];
    } else {
        $stmt2->close();
        return ['ok'=>false, 'mensaje'=>'Error al actualizar la contraseña.'];
    }
}


// Aquí puedes agregar más funciones de utilidad para todo el sistema. 