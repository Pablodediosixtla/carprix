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

if (!isset($in['marca']) || !isset($in['modelo']) || !isset($in['nombre']) || !isset($in['tel'])) {
    echo json_encode(["ok" => false, "error" => "Faltan datos obligatorios"]);
    exit;
}

$marca        = trim($in['marca']);
$modelo       = trim($in['modelo']);
$version      = trim($in['version'] ?? '');
$anio         = (int)$in['anio'];
$km           = (int)$in['km'];
$color        = trim($in['color'] ?? '');
$transmision  = trim($in['transmision'] ?? '');
$tipo_factura = trim($in['tipo_factura'] ?? '');
$propietarios = trim($in['propietarios'] ?? '');
$nombre       = trim($in['nombre']);
$tel          = trim($in['tel']);
$coments      = trim($in['comentarios'] ?? '');

$con = conectar();
$sql = "INSERT INTO prospectos_ventas (marca, modelo, version, anio, kilometraje, color, transmision, tipo_factura, propietarios, nombre_cliente, telefono, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $con->prepare($sql);
// sssiisssssss = 12 parámetros (s=string, i=integer)
$stmt->bind_param("sssiisssssss", $marca, $modelo, $version, $anio, $km, $color, $transmision, $tipo_factura, $propietarios, $nombre, $tel, $coments);

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