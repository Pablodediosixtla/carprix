<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);

if (!canManagePeople($currentUser)) {
    $con->close();
    errorResponse('No tienes permisos para consultar personas.', 403, 'FORBIDDEN');
}

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 20)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 150);
$status = cleanString($input['estatus'] ?? '', 20);

if (!in_array($status, ['', 'Activo', 'Inactivo', 'Bloqueado'], true)) {
    $con->close();
    errorResponse('El estatus no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'estatus']);
}

$isGlobalManager = canFullyManagePeople($currentUser);
$scopeIds = $isGlobalManager ? [] : hierarchyDescendantIds($con, (int) $currentUser['id']);

$where = ['1 = 1'];
$types = '';
$params = [];

if (!$isGlobalManager) {
    $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
    $where[] = "u.id IN ({$placeholders})";
    $types .= str_repeat('i', count($scopeIds));
    array_push($params, ...$scopeIds);
}

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

$whereSql = implode(' AND ', $where);
$countStmt = $con->prepare("SELECT COUNT(*) AS total FROM operativo_usuario u WHERE {$whereSql}");
if (!$countStmt) {
    databaseError($con);
}
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$sql = "SELECT
            u.id,
            u.username,
            u.email,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.telefono,
            u.fecha_nacimiento,
            u.estatus,
            u.debe_cambiar_password,
            u.ultimo_login_at,
            u.creado_en,
            j.supervisor_id,
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
            ON ur.usuario_id = u.id
           AND ur.activo = 1
        LEFT JOIN operativo_rol r
            ON r.id = ur.rol_id
           AND r.activo = 1
        WHERE {$whereSql}
        GROUP BY
            u.id, u.username, u.email, u.nombre, u.apellido_paterno,
            u.apellido_materno, u.telefono, u.fecha_nacimiento, u.estatus,
            u.debe_cambiar_password, u.ultimo_login_at, u.creado_en,
            j.supervisor_id, s.nombre, s.apellido_paterno, s.apellido_materno, s.username
        ORDER BY u.apellido_paterno, u.nombre, u.id
        LIMIT ? OFFSET ?";

$listTypes = $types . 'ii';
$listParams = array_merge($params, [$size, $offset]);
$stmt = $con->prepare($sql);
if (!$stmt) {
    databaseError($con);
}
bindDynamicParams($stmt, $listTypes, $listParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$supervisors = [];
if ($isGlobalManager) {
    $supervisorStmt = $con->prepare(
        "SELECT u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno
         FROM operativo_usuario u
         WHERE u.estatus = 'Activo'
           AND EXISTS (
                SELECT 1
                FROM operativo_usuario_rol ur2
                INNER JOIN operativo_rol r2
                    ON r2.id = ur2.rol_id
                   AND r2.activo = 1
                WHERE ur2.usuario_id = u.id
                  AND ur2.activo = 1
                  AND r2.codigo IN ('SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR')
           )
         ORDER BY u.apellido_paterno, u.nombre"
    );
    if (!$supervisorStmt) {
        databaseError($con);
    }
    $supervisorStmt->execute();
    $supervisors = $supervisorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $supervisorStmt->close();
}

foreach ($rows as &$row) {
    $rolesText = trim((string) ($row['roles'] ?? ''));
    $roles = $rolesText === '' ? [] : explode(',', $rolesText);
    $targetId = (int) $row['id'];

    $row['id'] = $targetId;
    $row['supervisor_id'] = $row['supervisor_id'] !== null ? (int) $row['supervisor_id'] : null;
    $row['debe_cambiar_password'] = (bool) $row['debe_cambiar_password'];
    $row['roles'] = $roles;
    $row['nivel'] = operationalLevelFromRoles($roles);
    $row['puede_editar_completo'] = canFullyManageTargetUser($con, $currentUser, $targetId);
    $row['puede_gestionar_estatus'] = canManageTargetStatusOrPassword($con, $currentUser, $targetId)
        && $targetId !== (int) $currentUser['id'];
    $row['puede_reset_password'] = canManageTargetStatusOrPassword($con, $currentUser, $targetId)
        && $targetId !== (int) $currentUser['id'];
}
unset($row);

foreach ($supervisors as &$supervisor) {
    $supervisor['id'] = (int) $supervisor['id'];
    $supervisor['nombre_completo'] = trim(implode(' ', array_filter([
        $supervisor['nombre'] ?? '',
        $supervisor['apellido_paterno'] ?? '',
        $supervisor['apellido_materno'] ?? '',
    ])));
}
unset($supervisor);

$levelLabels = [
    'VENDEDOR' => 'Vendedor',
    'SUPERVISOR' => 'Supervisor',
    'RESPONSABLE_INVENTARIO' => 'Responsable de inventario',
    'GERENTE_OPERACIONES' => 'Gerente de operaciones',
];
$allowedLevels = array_map(
    static fn(string $code): array => ['codigo' => $code, 'nombre' => $levelLabels[$code] ?? $code],
    personLevelsAllowed($currentUser)
);

$con->close();

okResponse([
    'items' => $rows,
    'supervisores' => $supervisors,
    'permisos' => [
        'puede_crear' => canCreatePeople($currentUser),
        'puede_editar_completo' => $isGlobalManager,
        'niveles_permitidos' => $allowedLevels,
        'es_super_admin' => isSuperAdmin($currentUser),
        'es_gerente' => hasAnyRole($currentUser, ['ADMIN_OPERATIVO']),
        'es_supervisor' => hasAnyRole($currentUser, ['AUTORIZADOR']) && !$isGlobalManager,
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
