<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con, true);

$currentPassword = (string) ($input['password_actual'] ?? '');
$newPassword = (string) ($input['password_nuevo'] ?? '');
$confirmation = (string) ($input['password_confirmacion'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
    $con->close();
    errorResponse('Debes capturar la contraseña actual, la nueva y su confirmación.', 422, 'VALIDATION_ERROR');
}

if ($newPassword !== $confirmation) {
    $con->close();
    errorResponse('La confirmación no coincide con la nueva contraseña.', 422, 'PASSWORD_CONFIRMATION_ERROR');
}

validatePasswordPolicy($newPassword);

$stmt = $con->prepare('SELECT password_hash FROM operativo_usuario WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
    $con->close();
    errorResponse('La contraseña actual es incorrecta.', 401, 'CURRENT_PASSWORD_INVALID');
}

if (password_verify($newPassword, (string) $row['password_hash'])) {
    $con->close();
    errorResponse('La nueva contraseña debe ser diferente a la actual.', 422, 'PASSWORD_REUSED');
}

$hash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $con->prepare("UPDATE operativo_usuario
                         SET password_hash = ?,
                             debe_cambiar_password = 0,
                             intentos_fallidos = 0,
                             bloqueado_hasta = NULL
                         WHERE id = ?");
$update->bind_param('si', $hash, $user['id']);
if (!$update->execute()) {
    $update->close();
    databaseError($con);
}
$update->close();

$user['debe_cambiar_password'] = false;
session_regenerate_id(true);
setSessionUser($user);
$token = rotateCsrfToken();
$con->close();

okResponse(['csrf_token' => $token], 'Contraseña actualizada correctamente.');
