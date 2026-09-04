<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$path = realpath('/home/site/wwwroot/db/conn/conn_db.php');
if ($path && file_exists($path)) {
    require_once $path;
} else {
    require_once __DIR__ . '/../conn/conn_db.php';
}

function featuredPublicImagePath(mixed $value): string
{
    $path = trim((string) $value);
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
        return $path;
    }
    return '/' . ltrim(str_replace('\\', '/', $path), './');
}

function normalizeFeaturedAuto(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['anio'] = (int) $row['anio'];
    $row['precio'] = (float) $row['precio'];
    $row['kilometraje'] = (int) $row['kilometraje'];
    $row['total_visitas'] = (int) ($row['total_visitas'] ?? 0);
    $row['img_principal'] = featuredPublicImagePath($row['img_principal'] ?? '');
    unset($row['posicion']);
    return $row;
}

$con = conectar();
if (!$con instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No fue posible conectar con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$con->set_charset('utf8mb4');

$sql = "SELECT
            d.posicion,
            a.id, a.marca, a.modelo, a.tipo, a.anio, a.precio,
            a.mensualidad, a.ubicacion, a.kilometraje, a.transmision,
            a.color, a.motor, a.combustible, a.pasajeros, a.traccion,
            a.duenos, a.img_principal, a.estatus,
            COALESCE(v.total_visitas, 0) AS total_visitas
        FROM operativo_auto_destacado d
        INNER JOIN autos a
            ON a.id = d.auto_id
        LEFT JOIN auto_detalle_visita v
            ON v.auto_id = a.id
        WHERE d.posicion BETWEEN 1 AND 3
          AND a.estatus = 'Disponible'
        ORDER BY
            COALESCE(v.total_visitas, 0) DESC,
            d.actualizado_en DESC,
            d.posicion ASC
        LIMIT 3";

$result = $con->query($sql);
$data = [];
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = normalizeFeaturedAuto($row);
    }
    $result->free();
}

$con->close();

echo json_encode([
    'ok' => true,
    'data' => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
