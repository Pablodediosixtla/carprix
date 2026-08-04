<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$roleId = positiveInt($input['rol_id'] ?? null, 'rol_id');

$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

$stmt = $con->prepare('SELECT id, codigo, activo FROM operativo_rol WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$role || !(bool) $role['activo']) {
    $con->close();
    errorResponse('El rol no existe o está inactivo.', 404, 'ROLE_NOT_AVAILABLE');
}

if ($role['codigo'] === 'SUPER_ADMIN' && !isSuperAdmin($currentUser)) {
    $con->close();
    errorResponse('Solo un superadministrador puede asignar el rol SUPER_ADMIN.', 403, 'FORBIDDEN_ROLE_ASSIGNMENT');
}

$sql = "INSERT INTO operativo_usuario_rol
            (usuario_id, rol_id, activo, asignado_por, asignado_en, revocado_por, revocado_en)
        VALUES (?, ?, 1, ?, CURRENT_TIMESTAMP, NULL, NULL)
        ON DUPLICATE KEY UPDATE
            activo = 1,
            asignado_por = VALUES(asignado_por),
            asignado_en = CURRENT_TIMESTAMP,
            revocado_por = NULL,
            revocado_en = NULL";
$assign = $con->prepare($sql);
$assign->bind_param('iii', $userId, $roleId, $currentUser['id']);
if (!$assign->execute()) {
    $assign->close();
    databaseError($con);
}
$assign->close();
$con->close();
okResponse([], 'Rol asignado correctamente.');
