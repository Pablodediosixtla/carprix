<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'SOLO_LECTURA']);

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 20)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 150);
$status = cleanString($input['estatus'] ?? '', 20);
$roleId = isset($input['rol_id']) && $input['rol_id'] !== '' ? (int) $input['rol_id'] : null;

$allowedStatuses = ['', 'Activo', 'Inactivo', 'Bloqueado'];
if (!in_array($status, $allowedStatuses, true)) {
    $con->close();
    errorResponse('El estatus enviado no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'estatus']);
}

$where = ['1 = 1'];
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.nombre LIKE ?
                 OR u.apellido_paterno LIKE ? OR u.apellido_materno LIKE ?)";
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($status !== '') {
    $where[] = 'u.estatus = ?';
    $types .= 's';
    $params[] = $status;
}

if ($roleId !== null && $roleId > 0) {
    $where[] = "EXISTS (
        SELECT 1 FROM operativo_usuario_rol fur
        WHERE fur.usuario_id = u.id AND fur.rol_id = ? AND fur.activo = 1
    )";
    $types .= 'i';
    $params[] = $roleId;
}

$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) AS total FROM operativo_usuario u WHERE {$whereSql}";
$countStmt = $con->prepare($countSql);
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            u.id, u.username, u.email, u.nombre, u.apellido_paterno,
            u.apellido_materno, u.telefono, u.estatus,
            u.debe_cambiar_password, u.ultimo_login_at,
            u.creado_en, u.actualizado_en,
            GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
        FROM operativo_usuario u
        LEFT JOIN operativo_usuario_rol ur ON ur.usuario_id = u.id AND ur.activo = 1
        LEFT JOIN operativo_rol r ON r.id = ur.rol_id AND r.activo = 1
        WHERE {$whereSql}
        GROUP BY
            u.id, u.username, u.email, u.nombre, u.apellido_paterno,
            u.apellido_materno, u.telefono, u.estatus,
            u.debe_cambiar_password, u.ultimo_login_at,
            u.creado_en, u.actualizado_en
        ORDER BY u.apellido_paterno, u.nombre, u.id
        LIMIT ? OFFSET ?";

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$size, $offset]);
$stmt = $con->prepare($sql);
bindDynamicParams($stmt, $listTypes, $listParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($rows as &$row) {
    $roles = trim((string) ($row['roles'] ?? ''));
    $row['id'] = (int) $row['id'];
    $row['debe_cambiar_password'] = (bool) $row['debe_cambiar_password'];
    $row['roles'] = $roles === '' ? [] : explode(',', $roles);
}
unset($row);

okResponse([
    'items' => $rows,
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
