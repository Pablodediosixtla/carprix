<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'SOLO_LECTURA']);

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 50)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 100);
$activeFilter = $input['activo'] ?? null;

$where = ['1 = 1'];
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(r.codigo LIKE ? OR r.nombre LIKE ? OR r.descripcion LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}

if ($activeFilter !== null && $activeFilter !== '') {
    $active = filter_var($activeFilter, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($active === null) {
        $con->close();
        errorResponse('El filtro activo no es válido.', 422, 'VALIDATION_ERROR');
    }
    $where[] = 'r.activo = ?';
    $types .= 'i';
    $params[] = $active ? 1 : 0;
}

$whereSql = implode(' AND ', $where);
$countStmt = $con->prepare("SELECT COUNT(*) AS total FROM operativo_rol r WHERE {$whereSql}");
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema,
            r.activo, r.creado_en, r.actualizado_en,
            COUNT(DISTINCT CASE WHEN ur.activo = 1 THEN ur.usuario_id END) AS usuarios_activos
        FROM operativo_rol r
        LEFT JOIN operativo_usuario_rol ur ON ur.rol_id = r.id
        WHERE {$whereSql}
        GROUP BY r.id, r.codigo, r.nombre, r.descripcion, r.es_sistema,
                 r.activo, r.creado_en, r.actualizado_en
        ORDER BY r.es_sistema DESC, r.nombre
        LIMIT ? OFFSET ?";
$listParams = array_merge($params, [$size, $offset]);
$listTypes = $types . 'ii';
$stmt = $con->prepare($sql);
bindDynamicParams($stmt, $listTypes, $listParams);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['es_sistema'] = (bool) $row['es_sistema'];
    $row['activo'] = (bool) $row['activo'];
    $row['usuarios_activos'] = (int) $row['usuarios_activos'];
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
