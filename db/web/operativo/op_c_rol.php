<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'SOLO_LECTURA']);

$roleId = positiveInt($input['rol_id'] ?? null, 'rol_id');
$stmt = $con->prepare("SELECT
        r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema,
        r.activo, r.creado_en, r.actualizado_en,
        COUNT(DISTINCT CASE WHEN ur.activo = 1 THEN ur.usuario_id END) AS usuarios_activos
    FROM operativo_rol r
    LEFT JOIN operativo_usuario_rol ur ON ur.rol_id = r.id
    WHERE r.id = ?
    GROUP BY r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema,
             r.activo, r.creado_en, r.actualizado_en");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
$stmt->close();
$con->close();

if (!$role) {
    errorResponse('Rol no encontrado.', 404, 'ROLE_NOT_FOUND');
}

$role['id'] = (int) $role['id'];
$role['es_sistema'] = (bool) $role['es_sistema'];
$role['activo'] = (bool) $role['activo'];
$role['usuarios_activos'] = (int) $role['usuarios_activos'];
okResponse(['rol' => $role]);
