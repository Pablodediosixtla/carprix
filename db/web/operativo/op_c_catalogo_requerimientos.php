<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 15)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 150);
$decision = cleanString($input['decision'] ?? '', 30);

$allowedDecisions = ['', 'Pendiente', 'Aprobado', 'Rechazado', 'Cancelado'];
if (!in_array($decision, $allowedDecisions, true)) {
    $con->close();
    errorResponse('Decisión de requerimiento no válida.', 422, 'VALIDATION_ERROR');
}

$where = ['1 = 1'];
$types = '';
$params = [];

if (!canViewAllCatalogRequests($user)) {
    if (canAuthorizeCatalogRequests($user)) {
        $where[] = '(cr.aprobador_id = ? OR cr.solicitado_por = ?)';
        $types .= 'ii';
        $params[] = (int) $user['id'];
        $params[] = (int) $user['id'];
    } else {
        $where[] = 'cr.solicitado_por = ?';
        $types .= 'i';
        $params[] = (int) $user['id'];
    }
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(CAST(cr.id AS CHAR) LIKE ? OR CAST(a.id AS CHAR) LIKE ?
                 OR a.marca LIKE ? OR a.modelo LIKE ?
                 OR su.nombre LIKE ? OR su.apellido_paterno LIKE ?)';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if ($decision !== '') {
    $where[] = 'cr.decision = ?';
    $types .= 's';
    $params[] = $decision;
}

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) AS total
             FROM operativo_catalogo_requerimiento cr
             INNER JOIN autos a ON a.id = cr.auto_id
             INNER JOIN operativo_usuario su ON su.id = cr.solicitado_por
             WHERE {$whereSql}";
$countStmt = $con->prepare($countSql);
if (!$countStmt) {
    databaseError($con);
}
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            cr.id,
            cr.auto_id,
            cr.tipo,
            cr.estatus_origen,
            cr.estatus_solicitado,
            cr.motivo,
            cr.solicitado_por,
            cr.aprobador_id,
            cr.decision,
            cr.comentario_decision,
            cr.fecha_solicitud,
            cr.fecha_decision,
            a.marca,
            a.modelo,
            a.anio,
            a.precio,
            a.img_principal,
            a.estatus AS auto_estatus,
            CONCAT_WS(' ', su.nombre, su.apellido_paterno, su.apellido_materno) AS solicitado_por_nombre,
            CONCAT_WS(' ', au.nombre, au.apellido_paterno, au.apellido_materno) AS aprobador_nombre
        FROM operativo_catalogo_requerimiento cr
        INNER JOIN autos a ON a.id = cr.auto_id
        INNER JOIN operativo_usuario su ON su.id = cr.solicitado_por
        LEFT JOIN operativo_usuario au ON au.id = cr.aprobador_id
        WHERE {$whereSql}
        ORDER BY
            CASE cr.decision WHEN 'Pendiente' THEN 0 ELSE 1 END,
            cr.fecha_solicitud DESC,
            cr.id DESC
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
$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['auto_id'] = (int) $row['auto_id'];
    $row['anio'] = (int) $row['anio'];
    $row['precio'] = (float) $row['precio'];
    $row['solicitado_por'] = (int) $row['solicitado_por'];
    $row['aprobador_id'] = $row['aprobador_id'] !== null ? (int) $row['aprobador_id'] : null;
}
unset($row);

okResponse([
    'items' => $rows,
    'permisos' => [
        'puede_autorizar' => canAuthorizeCatalogRequests($user),
        'puede_ver_todos' => canViewAllCatalogRequests($user),
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
