<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Método no permitido',
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'expected' => 'POST',
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    exit();
}
if (!isset($_POST['dni']) || !preg_match('/^\d{8}$/', $_POST['dni'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'DNI inválido',
        'debug' => [
            'dni' => isset($_POST['dni']) ? $_POST['dni'] : null,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    exit();
}
$dni = $_POST['dni'];
$api_token = 'apis-token-17013.KXf2Iz1tglCzTaiOH1UIZbkv53R2p1Ai'; // Cambia por tu token real si es necesario
$api_url = "https://api.apis.net.pe/v2/reniec/dni?numero=$dni";

require_once 'conexion.php';

// Buscar primero en la base de datos local
$numeroDocumento_guardar = isset($data['numeroDocumento']) ? $conn->real_escape_string($data['numeroDocumento']) : $conn->real_escape_string($dni);
$result = $conn->query("SELECT nombreCompleto FROM dni_consultas WHERE numeroDocumento = '$numeroDocumento_guardar' ORDER BY fecha_consulta DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    // Devuelvo el resultado local y NO consulto la API
    echo json_encode([
        'nombre' => $row['nombreCompleto'],
        'fuente' => 'local'
    ]);
    $conn->close();
    exit();
}
// Si no está en la base, consultar la API
$ch = curl_init();
curl_setopt_array($ch, array(
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => 0, // Cambia a 1 en producción
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 2,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Referer: https://apis.net.pe/consulta-dni-api',
        'Authorization: Bearer ' . $api_token
    ),
));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de conexión a la API',
        'curl_error' => curl_error($ch),
        'debug' => [
            'api_url' => $api_url,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    curl_close($ch);
    exit();
}
curl_close($ch);
if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode([
        'error' => 'Error en la consulta a la API',
        'api_response' => $response,
        'http_code' => $http_code,
        'debug' => [
            'api_url' => $api_url,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    exit();
}
$data = json_decode($response, true);
$nombre = '';
if (isset($data['nombreCompleto'])) {
    $nombre = $data['nombreCompleto'];
} elseif (isset($data['nombres']) && isset($data['apellidoPaterno']) && isset($data['apellidoMaterno'])) {
    $nombre = $data['nombres'] . ' ' . $data['apellidoPaterno'] . ' ' . $data['apellidoMaterno'];
}
if (!$data || !$nombre) {
    http_response_code(404);
    echo json_encode([
        'error' => 'No se encontraron datos para el DNI',
        'api_response' => $response,
        'debug' => [
            'data' => $data,
            'nombre' => $nombre,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    exit();
}
// Al guardar, usa numeroDocumento en vez de dni
$nombre_guardar = $conn->real_escape_string($nombre);
$nombres_guardar = isset($data['nombres']) ? $conn->real_escape_string($data['nombres']) : null;
$apellidoPaterno_guardar = isset($data['apellidoPaterno']) ? $conn->real_escape_string($data['apellidoPaterno']) : null;
$apellidoMaterno_guardar = isset($data['apellidoMaterno']) ? $conn->real_escape_string($data['apellidoMaterno']) : null;
$nombreCompleto_guardar = isset($data['nombreCompleto']) ? $conn->real_escape_string($data['nombreCompleto']) : $conn->real_escape_string($nombre);
$tipoDocumento_guardar = isset($data['tipoDocumento']) ? $conn->real_escape_string($data['tipoDocumento']) : null;
$numeroDocumento_guardar = isset($data['numeroDocumento']) ? $conn->real_escape_string($data['numeroDocumento']) : $conn->real_escape_string($dni);
$digitoVerificador_guardar = isset($data['digitoVerificador']) ? $conn->real_escape_string($data['digitoVerificador']) : null;

$sql = "INSERT INTO dni_consultas (numeroDocumento, nombres, apellidoPaterno, apellidoMaterno, nombreCompleto, tipoDocumento, digitoVerificador) VALUES (
    '$numeroDocumento_guardar',
    " . ($nombres_guardar ? "'$nombres_guardar'" : 'NULL') . ",
    " . ($apellidoPaterno_guardar ? "'$apellidoPaterno_guardar'" : 'NULL') . ",
    " . ($apellidoMaterno_guardar ? "'$apellidoMaterno_guardar'" : 'NULL') . ",
    " . ($nombreCompleto_guardar ? "'$nombreCompleto_guardar'" : 'NULL') . ",
    " . ($tipoDocumento_guardar ? "'$tipoDocumento_guardar'" : 'NULL') . ",
    " . ($digitoVerificador_guardar ? "'$digitoVerificador_guardar'" : 'NULL') . "
)";
if (!$conn->query($sql)) {
    echo json_encode([
        'error' => 'Error al guardar en la base de datos',
        'debug' => [
            'query' => $sql,
            'conn_error' => $conn->error,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
    $conn->close();
    exit();
}
$conn->close();
// Devuelvo el nombre como 'nombre' para el frontend
$data['nombre'] = isset($data['nombreCompleto']) ? $data['nombreCompleto'] : $nombre;
$data['dni'] = isset($data['numeroDocumento']) ? $data['numeroDocumento'] : $dni;
$data['fuente'] = 'api';
echo json_encode($data); 