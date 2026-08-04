<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$status = requireString($input, 'estatus', 'estatus', 20);

if (!in_array($status, ['Activo', 'Inactivo', 'Bloqueado'], true)) {
    $con->close();
    errorResponse('El estatus no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'estatus']);
}

if ($userId === (int) $currentUser['id'] && $status !== 'Activo') {
    $con->close();
    errorResponse('No puedes inactivar o bloquear tu propio usuario.', 409, 'SELF_STATUS_CHANGE_NOT_ALLOWED');
}

$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

if (hasAnyRole($target, ['SUPER_ADMIN'])) {
    if (!isSuperAdmin($currentUser)) {
        $con->close();
        errorResponse('Solo un superadministrador puede modificar el estatus de otro superadministrador.', 403, 'FORBIDDEN');
    }

    if ($status !== 'Activo') {
        $count = $con->query("SELECT COUNT(DISTINCT u.id) AS total
                             FROM operativo_usuario u
                             INNER JOIN operativo_usuario_rol ur ON ur.usuario_id = u.id AND ur.activo = 1
                             INNER JOIN operativo_rol r ON r.id = ur.rol_id AND r.activo = 1
                             WHERE u.estatus = 'Activo' AND r.codigo = 'SUPER_ADMIN'")
                     ->fetch_assoc();
        if ((int) $count['total'] <= 1) {
            $con->close();
            errorResponse('No puedes desactivar al último superadministrador activo.', 409, 'LAST_SUPER_ADMIN');
        }
    }
}

if ($status === 'Activo') {
    $stmt = $con->prepare("UPDATE operativo_usuario
                           SET estatus = 'Activo', intentos_fallidos = 0, bloqueado_hasta = NULL
                           WHERE id = ?");
    $stmt->bind_param('i', $userId);
} else {
    $stmt = $con->prepare('UPDATE operativo_usuario SET estatus = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $userId);
}

if (!$stmt->execute()) {
    $stmt->close();
    databaseError($con);
}
$stmt->close();
$updated = fetchUserContext($con, $userId);
$con->close();

okResponse(['usuario' => $updated], 'Estatus actualizado correctamente.');
