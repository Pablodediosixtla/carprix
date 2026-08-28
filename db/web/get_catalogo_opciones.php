<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:3000', 'https://carprix.com.mx', 'https://www.carprix.com.mx'];
if ($origin !== '' && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$path = realpath('/home/site/wwwroot/db/conn/conn_db.php');
if ($path !== false && file_exists($path)) {
    require_once $path;
} else {
    require_once __DIR__ . '/../conn/conn_db.php';
}

$con = conectar();
if (!$con instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No fue posible conectar con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$con->set_charset('utf8mb4');

function distinctCatalogValues(mysqli $con, string $column, bool $numeric = false): array
{
    $allowedColumns = [
        'marca', 'modelo', 'anio', 'color', 'transmision', 'tipo',
        'combustible', 'traccion', 'tipo_interior', 'interior',
        'material_interior', 'tapiceria'
    ];
    if (!in_array($column, $allowedColumns, true)) {
        return [];
    }

    $order = $numeric ? "CAST({$column} AS UNSIGNED) DESC" : "{$column} ASC";
    $sql = "SELECT DISTINCT {$column} AS valor
            FROM autos
            WHERE estatus <> 'Oculto'
              AND {$column} IS NOT NULL
              AND TRIM(CAST({$column} AS CHAR)) <> ''
            ORDER BY {$order}";
    $result = $con->query($sql);
    if (!$result) {
        return [];
    }

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $value = trim((string) ($row['valor'] ?? ''));
        if ($value !== '') {
            $values[] = $numeric ? (int) $value : $value;
        }
    }
    $result->free();
    return array_values(array_unique($values, SORT_REGULAR));
}

$interiorColumn = null;
$candidateInteriorColumns = ['tipo_interior', 'interior', 'material_interior', 'tapiceria'];
$columnResult = $con->query("SELECT COLUMN_NAME
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'autos'");
$existingColumns = [];
if ($columnResult) {
    $existingColumns = array_map(
        static fn(array $row): string => (string) $row['COLUMN_NAME'],
        $columnResult->fetch_all(MYSQLI_ASSOC)
    );
    $columnResult->free();
}
foreach ($candidateInteriorColumns as $candidate) {
    if (in_array($candidate, $existingColumns, true)) {
        $interiorColumn = $candidate;
        break;
    }
}

$modelsResult = $con->query(
    "SELECT DISTINCT marca, modelo
     FROM autos
     WHERE estatus <> 'Oculto'
       AND marca IS NOT NULL AND TRIM(marca) <> ''
       AND modelo IS NOT NULL AND TRIM(modelo) <> ''
     ORDER BY marca, modelo"
);
$models = [];
if ($modelsResult) {
    while ($row = $modelsResult->fetch_assoc()) {
        $brand = trim((string) ($row['marca'] ?? ''));
        $model = trim((string) ($row['modelo'] ?? ''));
        if ($brand !== '' && $model !== '') {
            $models[] = ['marca' => $brand, 'modelo' => $model];
        }
    }
    $modelsResult->free();
}

$data = [
    'marcas' => distinctCatalogValues($con, 'marca'),
    'modelos' => $models,
    'anios' => distinctCatalogValues($con, 'anio', true),
    'colores' => distinctCatalogValues($con, 'color'),
    'transmisiones' => distinctCatalogValues($con, 'transmision'),
    'tipos' => distinctCatalogValues($con, 'tipo'),
    'combustibles' => distinctCatalogValues($con, 'combustible'),
    'tracciones' => distinctCatalogValues($con, 'traccion'),
    'interior_campo' => $interiorColumn,
    'interiores' => $interiorColumn !== null ? distinctCatalogValues($con, $interiorColumn) : [],
];

$con->close();

echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
