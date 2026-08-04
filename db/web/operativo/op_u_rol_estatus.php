<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN']);

$roleId = positiveInt($input['rol_id'] ?? null, 'rol_id');
$active = filter_var($input['activo'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($active === null) {
    $con->close();
    errorResponse('El valor activo debe ser true o false.', 422, 'VALIDATION_ERROR', ['field' => 'activo']);
}

$stmt = $con->prepare('SELECT codigo FROM operativo_rol WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$role) {
    $con->close();
    errorResponse('Rol no encontrado.', 404, 'ROLE_NOT_FOUND');
}

if ($role['codigo'] === 'SUPER_ADMIN' && !$active) {
    $con->close();
    errorResponse('El rol SUPER_ADMIN no puede desactivarse.', 409, 'PROTECTED_ROLE');
}

$value = $active ? 1 : 0;
$update = $con->prepare('UPDATE operativo_rol SET activo = ? WHERE id = ?');
$update->bind_param('ii', $value, $roleId);
if (!$update->execute()) {
    $update->close();
    databaseError($con);
}
$update->close();
$con->close();
okResponse([], 'Estatus del rol actualizado correctamente.');
