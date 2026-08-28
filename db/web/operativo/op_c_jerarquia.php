<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR', 'INVENTARIO', 'VENTAS', 'RH']);

$isEditable = hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'RH']);
$visibleIds = hierarchyVisibleUserIds($con, $user);
$where = '1 = 1';
$types = '';
$params = [];

if (!$isEditable) {
    $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
    $where = "u.id IN ({$placeholders})";
    $types = str_repeat('i', count($visibleIds));
    $params = $visibleIds;
}

$sql = "SELECT
            u.id AS usuario_id,
            u.username,
            CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS usuario_nombre,
            u.estatus AS usuario_estatus,
            j.id AS jerarquia_id,
            j.supervisor_id,
            j.activo,
            CONCAT_WS(' ', s.nombre, s.apellido_paterno, s.apellido_materno) AS supervisor_nombre,
            s.username AS supervisor_username,
            GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
        FROM operativo_usuario u
        LEFT JOIN operativo_usuario_jerarquia j
            ON j.usuario_id = u.id
           AND j.activo = 1
        LEFT JOIN operativo_usuario s
            ON s.id = j.supervisor_id
        LEFT JOIN operativo_usuario_rol ur
            ON ur.usuario_id = u.id AND ur.activo = 1
        LEFT JOIN operativo_rol r
            ON r.id = ur.rol_id AND r.activo = 1
        WHERE {$where}
        GROUP BY
            u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno, u.estatus,
            j.id, j.supervisor_id, j.activo,
            s.nombre, s.apellido_paterno, s.apellido_materno, s.username
        ORDER BY u.apellido_paterno, u.nombre";
$stmt = $con->prepare($sql);
if (!$stmt) {
    databaseError($con);
}
bindDynamicParams($stmt, $types, $params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($rows as &$row) {
    $roles = trim((string) ($row['roles'] ?? ''));
    $row['usuario_id'] = (int) $row['usuario_id'];
    $row['jerarquia_id'] = $row['jerarquia_id'] !== null ? (int) $row['jerarquia_id'] : null;
    $row['supervisor_id'] = $row['supervisor_id'] !== null ? (int) $row['supervisor_id'] : null;
    $row['activo'] = $row['activo'] !== null ? (bool) $row['activo'] : false;
    $row['roles'] = $roles === '' ? [] : explode(',', $roles);
}
unset($row);

okResponse([
    'items' => $rows,
    'permisos' => [
        'puede_editar' => $isEditable,
    ],
]);
