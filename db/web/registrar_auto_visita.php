<?php
declare(strict_types=1);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
    'http://localhost:3000',
    'https://carprix.com.mx',
    'https://www.carprix.com.mx',
];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Método no permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = realpath('/home/site/wwwroot/db/conn/conn_db.php');
if ($path && file_exists($path)) {
    require_once $path;
} else {
    require_once __DIR__ . '/../conn/conn_db.php';
}

$input = json_decode(file_get_contents('php://input'), true);
$autoId = is_array($input) ? (int) ($input['auto_id'] ?? $input['id'] ?? 0) : 0;

if ($autoId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'El auto_id es obligatorio.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$con = conectar();
if (!$con instanceof mysqli) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'No fue posible conectar con la base de datos.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$con->set_charset('utf8mb4');

$check = $con->prepare(
    "SELECT id
     FROM autos
     WHERE id = ?
       AND estatus <> 'Oculto'
     LIMIT 1"
);
if (!$check) {
    $con->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No fue posible validar el auto.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$check->bind_param('i', $autoId);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    $con->close();
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'Auto no encontrado o no visible.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $con->prepare(
    "INSERT INTO auto_detalle_visita
        (auto_id, total_visitas, ultima_visita_en)
     VALUES (?, 1, NOW())
     ON DUPLICATE KEY UPDATE
        total_visitas = total_visitas + 1,
        ultima_visita_en = NOW()"
);
if (!$stmt) {
    $con->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No fue posible registrar la visita.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param('i', $autoId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    $con->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No fue posible registrar la visita.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$count = 0;
$countStmt = $con->prepare('SELECT total_visitas FROM auto_detalle_visita WHERE auto_id = ? LIMIT 1');
if ($countStmt) {
    $countStmt->bind_param('i', $autoId);
    $countStmt->execute();
    $row = $countStmt->get_result()->fetch_assoc();
    $count = (int) ($row['total_visitas'] ?? 0);
    $countStmt->close();
}
$con->close();

echo json_encode([
    'ok' => true,
    'data' => [
        'auto_id' => $autoId,
        'total_visitas' => $count,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
