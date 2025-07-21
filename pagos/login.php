<?php
// login.php
session_start();
require_once 'conexion.php';

// Si ya está logueado, redirigir al index
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(strtolower($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Usuario y contraseña requeridos.';
    } else {
        $stmt = $conn->prepare('SELECT id, username, password, nombre, rol FROM usuarios WHERE LOWER(username) = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['usuario_id'] = $row['id'];
                $_SESSION['usuario_nombre'] = $row['nombre'];
                $_SESSION['usuario_username'] = $row['username'];
                $_SESSION['usuario_rol'] = $row['rol'];
                if ($row['rol'] === 'admin') {
                    header('Location: index.php');
                } else {
                    header('Location: clientes.php');
                }
                exit();
            } else {
                $error = 'Contraseña incorrecta.';
            }
        } else {
            $error = 'Usuario no encontrado.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST" autocomplete="off">
            <label for="username">Usuario:</label>
            <input type="text" id="username" name="username" required autofocus>
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html> 