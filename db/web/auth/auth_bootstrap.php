<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_response.php';
require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/auth_csrf.php';
require_once __DIR__ . '/auth_roles.php';
require_once __DIR__ . '/auth_guard.php';

function applyCorsHeaders(): void
{
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $defaultOrigins = [
        'http://localhost:3000',
        'http://localhost:8000',
        'https://carprix.com.mx',
        'https://www.carprix.com.mx',
    ];

    $configured = trim((string) (getenv('CARPRIX_ALLOWED_ORIGINS') ?: ''));
    $allowed = $configured === ''
        ? $defaultOrigins
        : array_values(array_filter(array_map('trim', explode(',', $configured))));

    if ($origin !== '') {
        if (!in_array($origin, $allowed, true)) {
            errorResponse('Origen no permitido.', 403, 'ORIGIN_NOT_ALLOWED');
        }

        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-Token');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}

function connectDatabase(): mysqli
{
    $absolute = '/home/site/wwwroot/db/conn/conn_db.php';
    $relative = dirname(__DIR__, 2) . '/conn/conn_db.php';

    if (file_exists($absolute)) {
        require_once $absolute;
    } elseif (file_exists($relative)) {
        require_once $relative;
    } else {
        errorResponse('No fue posible localizar la configuración de base de datos.', 500, 'DB_CONFIG_NOT_FOUND');
    }

    if (!function_exists('conectar')) {
        errorResponse('La conexión de base de datos no está disponible.', 500, 'DB_CONNECTOR_NOT_FOUND');
    }

    $con = conectar();
    if (!$con instanceof mysqli) {
        errorResponse('No fue posible conectar con la base de datos.', 500, 'DB_CONNECTION_FAILED');
    }

    $con->set_charset('utf8mb4');
    return $con;
}

function readRequestInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            errorResponse('El cuerpo JSON no es válido.', 400, 'INVALID_JSON');
        }
        return $decoded;
    }

    return is_array($_POST) ? $_POST : [];
}

function bootstrapApi(bool $requireCsrf = false): array
{
    applyCorsHeaders();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($method !== 'POST') {
        errorResponse('Método no permitido. Usa POST.', 405, 'METHOD_NOT_ALLOWED');
    }

    startOperativoSession();
    $input = readRequestInput();

    if ($requireCsrf) {
        validateCsrfToken($input);
    }

    return $input;
}

function cleanString(mixed $value, int $maxLength = 255): string
{
    $text = trim((string) $value);
    return mb_substr($text, 0, $maxLength, 'UTF-8');
}

function requireString(array $input, string $field, string $label, int $maxLength = 255): string
{
    $value = cleanString($input[$field] ?? '', $maxLength);
    if ($value === '') {
        errorResponse("El campo {$label} es obligatorio.", 422, 'VALIDATION_ERROR', ['field' => $field]);
    }
    return $value;
}

function positiveInt(mixed $value, string $field): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($number === false) {
        errorResponse("El campo {$field} debe ser un entero mayor que cero.", 422, 'VALIDATION_ERROR', ['field' => $field]);
    }
    return (int) $number;
}

function validateEmailAddress(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        errorResponse('El correo electrónico no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'email']);
    }
    return mb_strtolower($email, 'UTF-8');
}

function validateUsername(string $username): string
{
    if (!preg_match('/^[A-Za-z0-9._-]{4,80}$/', $username)) {
        errorResponse('El username debe tener entre 4 y 80 caracteres y solo puede contener letras, números, punto, guion y guion bajo.', 422, 'VALIDATION_ERROR', ['field' => 'username']);
    }
    return mb_strtolower($username, 'UTF-8');
}

function validatePasswordPolicy(string $password): void
{
    $valid = strlen($password) >= 10
        && strlen($password) <= 72
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);

    if (!$valid) {
        errorResponse(
            'La contraseña debe tener entre 10 y 72 caracteres e incluir mayúscula, minúscula, número y carácter especial.',
            422,
            'PASSWORD_POLICY_ERROR',
            ['field' => 'password']
        );
    }
}

function clientIp(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $first = trim(explode(',', $forwarded)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return mb_substr($first, 0, 45, 'UTF-8');
        }
    }

    return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45, 'UTF-8');
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$values): void
{
    if ($types === '') {
        return;
    }

    $params = [$types];
    foreach ($values as $key => &$value) {
        $params[] = &$value;
    }
    unset($value);

    if (!call_user_func_array([$stmt, 'bind_param'], $params)) {
        errorResponse('No fue posible preparar los parámetros de la consulta.', 500, 'DB_BIND_ERROR');
    }
}

function databaseError(mysqli $con, Throwable|string|null $error = null): never
{
    error_log('[CARPRIX OPERATIVO DB] ' . ($error instanceof Throwable ? $error->getMessage() : (string) $error) . ' | mysqli=' . $con->error);
    errorResponse('Ocurrió un error al procesar la operación.', 500, 'DATABASE_ERROR');
}
