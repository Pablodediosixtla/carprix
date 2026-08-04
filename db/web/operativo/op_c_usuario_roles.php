<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'SOLO_LECTURA']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

$stmt = $con->prepare("SELECT
        r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema, r.activo AS rol_activo,
        ur.activo AS asignacion_activa, ur.asignado_por, ur.asignado_en,
        ur.revocado_por, ur.revocado_en
    FROM operativo_usuario_rol ur
    INNER JOIN operativo_rol r ON r.id = ur.rol_id
    WHERE ur.usuario_id = ?
    ORDER BY ur.activo DESC, r.nombre");
$stmt->bind_param('i', $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['es_sistema'] = (bool) $row['es_sistema'];
    $row['rol_activo'] = (bool) $row['rol_activo'];
    $row['asignacion_activa'] = (bool) $row['asignacion_activa'];
    $row['asignado_por'] = $row['asignado_por'] !== null ? (int) $row['asignado_por'] : null;
    $row['revocado_por'] = $row['revocado_por'] !== null ? (int) $row['revocado_por'] : null;
}
unset($row);

okResponse(['usuario' => $target, 'roles' => $rows]);
