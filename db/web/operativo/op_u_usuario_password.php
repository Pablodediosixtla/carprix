<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$temporaryPassword = (string) ($input['password_temporal'] ?? '');
validatePasswordPolicy($temporaryPassword);

$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

if (hasAnyRole($target, ['SUPER_ADMIN']) && !isSuperAdmin($currentUser)) {
    $con->close();
    errorResponse('Solo un superadministrador puede restablecer la contraseña de otro superadministrador.', 403, 'FORBIDDEN');
}

$hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
$stmt = $con->prepare("UPDATE operativo_usuario
                       SET password_hash = ?, debe_cambiar_password = 1,
                           intentos_fallidos = 0, bloqueado_hasta = NULL,
                           estatus = IF(estatus = 'Bloqueado', 'Activo', estatus)
                       WHERE id = ?");
$stmt->bind_param('si', $hash, $userId);
if (!$stmt->execute()) {
    $stmt->close();
    databaseError($con);
}
$stmt->close();
$con->close();

okResponse([], 'Contraseña temporal asignada correctamente. El usuario deberá cambiarla en su próximo acceso.');
