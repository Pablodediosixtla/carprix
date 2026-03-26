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

$path = realpath("/home/site/wwwroot/db/conn/conn_db.php");
if ($path && file_exists($path)) { include $path; } else { include "../conn/conn_db.php"; }

$in = json_decode(file_get_contents("php://input"), true) ?? [];

if (!isset($in['marca']) || !isset($in['modelo']) || !isset($in['precio'])) {
    echo json_encode(["ok" => false, "error" => "Faltan datos obligatorios (marca, modelo, precio)"]);
    exit;
}

$marca = trim($in['marca']);
$modelo = trim($in['modelo']);
$tipo = trim($in['tipo'] ?? ''); 
$anio = (int)$in['anio'];
$precio = (float)$in['precio'];
$mensualidad = isset($in['mensualidad']) ? (float)$in['mensualidad'] : 0.00;
$ubicacion = trim($in['ubicacion']);
$kilometraje = (int)$in['kilometraje'];
$transmision = trim($in['transmision']);
$color = trim($in['color'] ?? '');
$motor = trim($in['motor'] ?? '');
$combustible = trim($in['combustible'] ?? '');
$pasajeros = trim($in['pasajeros'] ?? ''); 
$traccion = trim($in['traccion'] ?? '');
$duenos = isset($in['duenos']) ? (int)$in['duenos'] : null; // NUEVO CAMPO
$img_principal = trim($in['img_principal'] ?? '');

$con = conectar();
$sql = "INSERT INTO autos (marca, modelo, tipo, anio, precio, mensualidad, ubicacion, kilometraje, transmision, color, motor, combustible, pasajeros, traccion, duenos, img_principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
// sssiddsissssssis = 16 parámetros
$stmt->bind_param("sssiddsissssssis", $marca, $modelo, $tipo, $anio, $precio, $mensualidad, $ubicacion, $kilometraje, $transmision, $color, $motor, $combustible, $pasajeros, $traccion, $duenos, $img_principal);

if ($stmt->execute()) {
    echo json_encode(["ok" => true, "id_insertado" => $con->insert_id, "mensaje" => "Auto registrado con éxito"]);
} else {
    echo json_encode(["ok" => false, "error" => $con->error]);
}

$stmt->close();
$con->close();
?>