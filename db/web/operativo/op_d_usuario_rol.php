<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$roleId = positiveInt($input['rol_id'] ?? null, 'rol_id');

$stmt = $con->prepare("SELECT r.codigo
                       FROM operativo_usuario_rol ur
                       INNER JOIN operativo_rol r ON r.id = ur.rol_id
                       WHERE ur.usuario_id = ? AND ur.rol_id = ? AND ur.activo = 1
                       LIMIT 1");
$stmt->bind_param('ii', $userId, $roleId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    $con->close();
    errorResponse('La asignación activa no existe.', 404, 'USER_ROLE_NOT_FOUND');
}

if ($assignment['codigo'] === 'SUPER_ADMIN') {
    if (!isSuperAdmin($currentUser)) {
        $con->close();
        errorResponse('Solo un superadministrador puede revocar el rol SUPER_ADMIN.', 403, 'FORBIDDEN_ROLE_REVOCATION');
    }

    if ($userId === (int) $currentUser['id']) {
        $con->close();
        errorResponse('No puedes revocar tu propio rol SUPER_ADMIN.', 409, 'SELF_ROLE_REVOCATION_NOT_ALLOWED');
    }

    $count = $con->query("SELECT COUNT(DISTINCT u.id) AS total
                         FROM operativo_usuario u
                         INNER JOIN operativo_usuario_rol ur ON ur.usuario_id = u.id AND ur.activo = 1
                         INNER JOIN operativo_rol r ON r.id = ur.rol_id AND r.activo = 1
                         WHERE u.estatus = 'Activo' AND r.codigo = 'SUPER_ADMIN'")
                 ->fetch_assoc();
    if ((int) $count['total'] <= 1) {
        $con->close();
        errorResponse('No puedes revocar el rol del último superadministrador activo.', 409, 'LAST_SUPER_ADMIN');
    }
}

$countStmt = $con->prepare('SELECT COUNT(*) AS total FROM operativo_usuario_rol WHERE usuario_id = ? AND activo = 1');
$countStmt->bind_param('i', $userId);
$countStmt->execute();
$activeRoles = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

if ($activeRoles <= 1) {
    $con->close();
    errorResponse('El usuario debe conservar al menos un rol activo.', 409, 'LAST_USER_ROLE');
}

$update = $con->prepare("UPDATE operativo_usuario_rol
                         SET activo = 0, revocado_por = ?, revocado_en = CURRENT_TIMESTAMP
                         WHERE usuario_id = ? AND rol_id = ? AND activo = 1");
$update->bind_param('iii', $currentUser['id'], $userId, $roleId);
if (!$update->execute()) {
    $update->close();
    databaseError($con);
}
$update->close();
$con->close();
okResponse([], 'Rol revocado correctamente.');
