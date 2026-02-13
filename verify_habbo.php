<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Load user from database
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT id, username, habbo_username, ccf_code, verified FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
} else {
    echo "Usuario no encontrado.";
    exit;
}

$stmt->close();

if ($user['verified']) {
    echo "Ya estás verificado.";
    header("Location: perfil.php");
    exit;
}

// Fetch Habbo profile data using API
$api_url = "https://www.habbo.es/api/public/users?name=" . urlencode($user['habbo_username']);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Accept-Language: es-ES,es;q=0.8,en-US;q=0.5,en;q=0.3',
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$json_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($json_response === false || $http_code != 200) {
    echo "Error al acceder a la API de Habbo. Código HTTP: " . $http_code;
    exit;
}

$data = json_decode($json_response, true);
if ($data === null || !isset($data['motto'])) {
    echo "No se pudo obtener el motto de la API.";
    exit;
}

$motto = trim($data['motto']);

if (strpos($motto, $user['ccf_code']) !== false) {
    $update_stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $user['id']);
    $update_stmt->execute();
    $update_stmt->close();

    echo "Verificación exitosa.";
    header("Location: perfil.php");
    exit;
} else {
    echo "El código no se encuentra en tu motto. Asegúrate de tener: " . $user['ccf_code'] . " en tu motto. Motto encontrado: " . htmlspecialchars($motto);
}

$conn->close();
?>
