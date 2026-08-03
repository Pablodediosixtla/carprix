<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost:3000',
    'https://carprix.com.mx',
    'https://www.carprix.com.mx'
];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Método no permitido. Usa POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$connectionPath = realpath('/home/site/wwwroot/db/conn/conn_db.php');
if ($connectionPath !== false && file_exists($connectionPath)) {
    require_once $connectionPath;
} else {
    require_once __DIR__ . '/../conn/conn_db.php';
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function textValue(array $input, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $input) && $input[$key] !== null) {
            return trim((string) $input[$key]);
        }
    }

    return $default;
}

function nullableText(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function normalizeMoney(string $value): float
{
    $normalized = str_replace([',', '$', ' '], '', $value);
    return is_numeric($normalized) ? max(0, (float) $normalized) : 0.0;
}

/**
 * Compatibilidad con el vende.js de tres pasos existente.
 * Ese archivo guarda correo, refrendo, ubicación e imperfecciones
 * dentro de `comentarios`. El endpoint también acepta esos datos
 * como propiedades JSON separadas.
 */
function parseLegacyComments(string $comments): array
{
    $parsed = [
        'correo_cliente' => '',
        'refrendo_estatus' => '',
        'refrendo_adeudo_monto' => 0.0,
        'estado_vehiculo' => '',
        'municipio_vehiculo' => '',
        'imperfecciones' => ''
    ];

    if ($comments === '') {
        return $parsed;
    }

    if (preg_match('/(?:^|\|)\s*Correo:\s*([^|]+)/iu', $comments, $match)) {
        $parsed['correo_cliente'] = trim($match[1]);
    }

    if (preg_match('/(?:^|\|)\s*Refrendo:\s*(Al corriente|Con adeudo)/iu', $comments, $match)) {
        $refrendoValue = trim($match[1]);
        if (strcasecmp($refrendoValue, 'Al corriente') === 0) {
            $parsed['refrendo_estatus'] = 'Al corriente';
        } elseif (strcasecmp($refrendoValue, 'Con adeudo') === 0) {
            $parsed['refrendo_estatus'] = 'Con adeudo';
        }
    }

    if (preg_match('/adeudo aproximado:\s*\$?\s*([0-9.,]+)/iu', $comments, $match)) {
        $parsed['refrendo_adeudo_monto'] = normalizeMoney($match[1]);
    }

    if (preg_match('/(?:^|\|)\s*Ubicación del vehículo:\s*([^|]+)/iu', $comments, $match)) {
        $location = trim($match[1]);
        $lastComma = strrpos($location, ',');

        if ($lastComma !== false) {
            $parsed['municipio_vehiculo'] = trim(substr($location, 0, $lastComma));
            $parsed['estado_vehiculo'] = trim(substr($location, $lastComma + 1));
        } else {
            $parsed['municipio_vehiculo'] = $location;
        }
    }

    if (preg_match('/(?:^|\|)\s*Imperfecciones:\s*(.+)$/iu', $comments, $match)) {
        $parsed['imperfecciones'] = trim($match[1]);
    }

    return $parsed;
}

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody ?: '', true);

if (!is_array($input)) {
    respond(400, [
        'ok' => false,
        'error' => 'El cuerpo de la solicitud debe ser un JSON válido.'
    ]);
}

$marca = textValue($input, ['marca']);
$modelo = textValue($input, ['modelo']);
$version = textValue($input, ['version']);
$anio = (int) textValue($input, ['anio'], '0');
$kilometraje = (int) textValue($input, ['km', 'kilometraje'], '0');

$color = nullableText(textValue($input, ['color']));
$transmision = nullableText(textValue($input, ['transmision']));
$tipoFactura = nullableText(textValue($input, ['tipo_factura']));
$propietarios = nullableText(textValue($input, ['propietarios']));

$nombreCliente = textValue($input, ['nombre', 'nombre_cliente']);
$telefono = textValue($input, ['tel', 'telefono']);
$comentarios = textValue($input, ['comentarios']);

$legacy = parseLegacyComments($comentarios);

$correoCliente = textValue(
    $input,
    ['correo_cliente', 'correo', 'email'],
    $legacy['correo_cliente']
);

$refrendoEstatus = textValue(
    $input,
    ['refrendo_estatus', 'refrendo'],
    $legacy['refrendo_estatus']
);

$refrendoAdeudoRaw = textValue(
    $input,
    ['refrendo_adeudo_monto', 'refrendo_adeudo'],
    (string) $legacy['refrendo_adeudo_monto']
);
$refrendoAdeudoMonto = normalizeMoney($refrendoAdeudoRaw);

$imperfecciones = textValue(
    $input,
    ['imperfecciones'],
    $legacy['imperfecciones']
);

$estadoVehiculo = textValue(
    $input,
    ['estado_vehiculo', 'estado'],
    $legacy['estado_vehiculo']
);

$municipioVehiculo = textValue(
    $input,
    ['municipio_vehiculo', 'municipio'],
    $legacy['municipio_vehiculo']
);

$errors = [];

if ($marca === '') {
    $errors[] = 'La marca es obligatoria.';
}
if ($modelo === '') {
    $errors[] = 'El modelo es obligatorio.';
}
if ($version === '') {
    $errors[] = 'La versión es obligatoria.';
}
if ($anio < 1980 || $anio > ((int) date('Y') + 1)) {
    $errors[] = 'El año del vehículo no es válido.';
}
if ($kilometraje < 0) {
    $errors[] = 'El kilometraje no puede ser negativo.';
}
if ($color === null) {
    $errors[] = 'El color del vehículo es obligatorio.';
}
if ($transmision === null) {
    $errors[] = 'La transmisión del vehículo es obligatoria.';
}
if ($tipoFactura === null) {
    $errors[] = 'El tipo de factura es obligatorio.';
}
if ($propietarios === null) {
    $errors[] = 'El número de propietarios es obligatorio.';
}
if ($nombreCliente === '') {
    $errors[] = 'El nombre del cliente es obligatorio.';
}
if (strlen(preg_replace('/\D+/', '', $telefono)) < 10) {
    $errors[] = 'El teléfono debe contener al menos 10 dígitos.';
}
if ($correoCliente === '' || !filter_var($correoCliente, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El correo electrónico no es válido.';
}
if (!in_array($refrendoEstatus, ['Al corriente', 'Con adeudo'], true)) {
    $errors[] = 'Selecciona un estado de refrendo válido.';
}
if ($refrendoEstatus === 'Al corriente') {
    $refrendoAdeudoMonto = 0.0;
}
if ($refrendoEstatus === 'Con adeudo' && $refrendoAdeudoMonto <= 0) {
    $errors[] = 'Indica el monto aproximado del adeudo de refrendo.';
}
if ($imperfecciones === '') {
    $errors[] = 'Describe las imperfecciones del vehículo.';
}
if ($estadoVehiculo === '') {
    $errors[] = 'El estado donde se encuentra el vehículo es obligatorio.';
}
if ($municipioVehiculo === '') {
    $errors[] = 'El municipio donde se encuentra el vehículo es obligatorio.';
}

if ($errors !== []) {
    respond(422, [
        'ok' => false,
        'error' => implode(' ', $errors),
        'detalles' => $errors
    ]);
}

$connection = conectar();
if (!$connection instanceof mysqli) {
    error_log('insert_prospecto.php: no fue posible conectar con la base de datos.');
    respond(500, [
        'ok' => false,
        'error' => 'No fue posible conectar con la base de datos.'
    ]);
}

$connection->set_charset('utf8mb4');

$sql = <<<SQL
INSERT INTO prospectos_ventas (
    marca,
    modelo,
    version,
    anio,
    kilometraje,
    color,
    transmision,
    tipo_factura,
    propietarios,
    nombre_cliente,
    telefono,
    correo_cliente,
    refrendo_estatus,
    refrendo_adeudo_monto,
    imperfecciones,
    estado_vehiculo,
    municipio_vehiculo,
    comentarios
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;

$statement = $connection->prepare($sql);
if (!$statement instanceof mysqli_stmt) {
    error_log('insert_prospecto.php prepare error: ' . $connection->error);
    $connection->close();
    respond(500, [
        'ok' => false,
        'error' => 'No fue posible preparar el registro de la solicitud.'
    ]);
}

$statement->bind_param(
    'sssiissssssssdssss',
    $marca,
    $modelo,
    $version,
    $anio,
    $kilometraje,
    $color,
    $transmision,
    $tipoFactura,
    $propietarios,
    $nombreCliente,
    $telefono,
    $correoCliente,
    $refrendoEstatus,
    $refrendoAdeudoMonto,
    $imperfecciones,
    $estadoVehiculo,
    $municipioVehiculo,
    $comentarios
);

if (!$statement->execute()) {
    error_log('insert_prospecto.php execute error: ' . $statement->error);
    $statement->close();
    $connection->close();
    respond(500, [
        'ok' => false,
        'error' => 'No fue posible registrar la solicitud. Inténtalo nuevamente.'
    ]);
}

$prospectId = $connection->insert_id;

$statement->close();
$connection->close();

respond(201, [
    'ok' => true,
    'id_prospecto' => $prospectId,
    'mensaje' => 'Solicitud registrada correctamente. Un asesor te contactará.'
]);
