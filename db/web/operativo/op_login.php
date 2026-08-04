<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$login = mb_strtolower(requireString($input, 'login', 'login', 150), 'UTF-8');
$password = (string) ($input['password'] ?? '');

if ($password === '') {
    errorResponse('La contraseña es obligatoria.', 422, 'VALIDATION_ERROR', ['field' => 'password']);
}

$con = connectDatabase();
$sql = "SELECT id, username, email, nombre, apellido_paterno, apellido_materno,
               telefono, password_hash, estatus, intentos_fallidos,
               bloqueado_hasta, debe_cambiar_password, ultimo_login_at
        FROM operativo_usuario
        WHERE username = ? OR email = ?
        LIMIT 1";
$stmt = $con->prepare($sql);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('ss', $login, $login);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    usleep(300000);
    $con->close();
    errorResponse('Credenciales incorrectas.', 401, 'INVALID_CREDENTIALS');
}

$userId = (int) $row['id'];
$status = (string) $row['estatus'];
$blockedUntil = $row['bloqueado_hasta'] ? strtotime((string) $row['bloqueado_hasta']) : null;

if ($status === 'Inactivo') {
    $con->close();
    errorResponse('El usuario se encuentra inactivo.', 403, 'USER_INACTIVE');
}

if ($status === 'Bloqueado' && (!$blockedUntil || $blockedUntil > time())) {
    $con->close();
    errorResponse('El usuario se encuentra bloqueado. Contacta a un administrador.', 423, 'USER_BLOCKED');
}

if ($blockedUntil && $blockedUntil > time()) {
    $minutes = max(1, (int) ceil(($blockedUntil - time()) / 60));
    $con->close();
    errorResponse("Demasiados intentos fallidos. Intenta nuevamente en {$minutes} minuto(s).", 423, 'TEMPORARILY_LOCKED');
}

if (!password_verify($password, (string) $row['password_hash'])) {
    $attempts = (int) $row['intentos_fallidos'] + 1;

    if ($attempts >= 5) {
        $lockMinutes = (int) (getenv('CARPRIX_LOGIN_LOCK_MINUTES') ?: 15);
        $update = $con->prepare("UPDATE operativo_usuario
                                 SET intentos_fallidos = 0,
                                     bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                                 WHERE id = ?");
        $update->bind_param('ii', $lockMinutes, $userId);
        $update->execute();
        $update->close();
        $con->close();
        errorResponse("Demasiados intentos fallidos. La cuenta fue bloqueada temporalmente por {$lockMinutes} minutos.", 423, 'TEMPORARILY_LOCKED');
    }

    $update = $con->prepare('UPDATE operativo_usuario SET intentos_fallidos = ? WHERE id = ?');
    $update->bind_param('ii', $attempts, $userId);
    $update->execute();
    $update->close();
    usleep(300000);
    $con->close();
    errorResponse('Credenciales incorrectas.', 401, 'INVALID_CREDENTIALS');
}

if ($status === 'Bloqueado' && $blockedUntil && $blockedUntil <= time()) {
    $status = 'Activo';
}

$newHash = null;
if (password_needs_rehash((string) $row['password_hash'], PASSWORD_DEFAULT)) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
}

$ip = clientIp();
$updateSql = "UPDATE operativo_usuario
              SET estatus = 'Activo',
                  intentos_fallidos = 0,
                  bloqueado_hasta = NULL,
                  ultimo_login_at = NOW(),
                  ultimo_login_ip = ?,
                  password_hash = COALESCE(?, password_hash)
              WHERE id = ?";
$update = $con->prepare($updateSql);
$update->bind_param('ssi', $ip, $newHash, $userId);
$update->execute();
$update->close();

$user = fetchUserContext($con, $userId);
if (!$user || $user['roles'] === []) {
    $con->close();
    errorResponse('El usuario no tiene roles activos asignados.', 403, 'USER_WITHOUT_ROLES');
}

session_regenerate_id(true);
setSessionUser($user);
$token = rotateCsrfToken();
$con->close();

okResponse([
    'usuario' => $user,
    'csrf_token' => $token,
], 'Inicio de sesión correcto.');
