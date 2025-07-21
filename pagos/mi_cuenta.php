<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'utils.php';
require_once 'conexion.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}
include 'header.php';
$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_contrasena'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $nueva_contrasena2 = $_POST['nueva_contrasena2'] ?? '';
    $resultado = cambiarContrasenaUsuario($usuario_id, $nueva_contrasena, $nueva_contrasena2, $conn, $contrasena_actual);
    if ($resultado['ok']) {
        $mensaje = $resultado['mensaje'];
    } else {
        $error = $resultado['mensaje'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi cuenta</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h1>Mi cuenta</h1>
    <?php if ($mensaje): ?><div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <h2>Cambiar contraseña</h2>
    <form method="POST" autocomplete="off" style="max-width:400px;">
        <input type="hidden" name="cambiar_contrasena" value="1">
        <label for="contrasena_actual">Contraseña actual:</label>
        <input type="password" id="contrasena_actual" name="contrasena_actual" required minlength="4">
        <label for="nueva_contrasena">Nueva contraseña:</label>
        <input type="password" id="nueva_contrasena" name="nueva_contrasena" required minlength="4">
        <label for="nueva_contrasena2">Repetir nueva contraseña:</label>
        <input type="password" id="nueva_contrasena2" name="nueva_contrasena2" required minlength="4">
        <button type="submit">Cambiar contraseña</button>
    </form>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?> 