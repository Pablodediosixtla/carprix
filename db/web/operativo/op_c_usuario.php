<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'SOLO_LECTURA']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$user = fetchUserContext($con, $userId);

if (!$user) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

$stmt = $con->prepare("SELECT
        r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema,
        ur.activo, ur.asignado_en, ur.revocado_en
    FROM operativo_usuario_rol ur
    INNER JOIN operativo_rol r ON r.id = ur.rol_id
    WHERE ur.usuario_id = ?
    ORDER BY ur.activo DESC, r.nombre");
$stmt->bind_param('i', $userId);
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($roles as &$role) {
    $role['id'] = (int) $role['id'];
    $role['es_sistema'] = (bool) $role['es_sistema'];
    $role['activo'] = (bool) $role['activo'];
}
unset($role);

$user['roles_detalle'] = $roles;
okResponse(['usuario' => $user]);
