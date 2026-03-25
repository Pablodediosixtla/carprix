<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Dominios permitidos según tu configuración oficial
$allowed = ['http://localhost:3000', 'https://carprix.com.mx', 'https://www.carprix.com.mx'];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

// Ruta absoluta para Azure
$path = realpath("/home/site/wwwroot/db/conn/conn_db.php");
if ($path && file_exists($path)) { include $path; } else { include "../conn/conn_db.php"; }

$in = json_decode(file_get_contents("php://input"), true) ?? [];

// Validación de campos requeridos
if (!isset($in['marca']) || !isset($in['modelo']) || !isset($in['nombre']) || !isset($in['tel'])) {
    echo json_encode(["ok" => false, "error" => "Faltan datos de contacto o del vehículo"]);
    exit;
}

$marca      = trim($in['marca']);
$modelo     = trim($in['modelo']);
$anio       = (int)$in['anio'];
$km         = (int)$in['km'];
$nombre     = trim($in['nombre']);
$tel        = trim($in['tel']);
$coments    = trim($in['comentarios'] ?? '');

$con = conectar();
$sql = "INSERT INTO prospectos_ventas (marca, modelo, anio, kilometraje, nombre_cliente, telefono, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
// s = string, i = integer
$stmt->bind_param("ssiisss", $marca, $modelo, $anio, $km, $nombre, $tel, $coments);

if ($stmt->execute()) {
    echo json_encode([
        "ok" => true, 
        "id_prospecto" => $con->insert_id, 
        "mensaje" => "Solicitud registrada correctamente. Un asesor te contactará."
    ]);
} else {
    echo json_encode(["ok" => false, "error" => $con->error]);
}

$stmt->close();
$con->close();
?>