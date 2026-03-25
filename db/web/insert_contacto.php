<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:3000', 'https://carprix.com.mx', 'https://www.carprix.com.mx'];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// Conexión a la base de datos
$path = realpath("/home/site/wwwroot/db/conn/conn_db.php");
if ($path && file_exists($path)) { include $path; } else { include "../conn/conn_db.php"; }

$in = json_decode(file_get_contents("php://input"), true) ?? [];

// Validación de campos obligatorios
if (empty($in['nombre']) || empty($in['email']) || empty($in['mensaje'])) {
    echo json_encode(["ok" => false, "error" => "Por favor, completa todos los campos requeridos."]);
    exit;
}

$nombre  = trim($in['nombre']);
$email   = trim($in['email']);
$asunto  = trim($in['asunto'] ?? 'Otro');
$mensaje = trim($in['mensaje']);

$con = conectar();
$sql = "INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)";

$stmt = $con->prepare($sql);
$stmt->bind_param("ssss", $nombre, $email, $asunto, $mensaje);

if ($stmt->execute()) {
    echo json_encode([
        "ok" => true, 
        "mensaje" => "Tu mensaje ha sido enviado. Nos pondremos en contacto contigo pronto."
    ]);
} else {
    echo json_encode(["ok" => false, "error" => "Error al guardar el mensaje: " . $con->error]);
}

$stmt->close();
$con->close();
?>