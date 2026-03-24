<?php
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Dominio oficial agregado
$allowed = ['http://localhost:3000', 'https://carprix.com.mx', 'https://www.carprix.com.mx'];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Vary: Origin");
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$path = realpath("/home/site/wwwroot/db/conn/conn_db.php");
if ($path && file_exists($path)) { include $path; } else { include "../conn/conn_db.php"; }

$in = json_decode(file_get_contents("php://input"), true) ?? [];
$id = isset($in['id']) ? (int)$in['id'] : null;

$con = conectar();

if ($id) {
    $sql = "SELECT * FROM autos WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $id);
} else {
    $sql = "SELECT * FROM autos ORDER BY fecha_carga DESC";
    $stmt = $con->prepare($sql);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $autos = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["ok" => true, "data" => $autos]);
} else {
    echo json_encode(["ok" => false, "error" => $con->error]);
}

$stmt->close();
$con->close();
?>