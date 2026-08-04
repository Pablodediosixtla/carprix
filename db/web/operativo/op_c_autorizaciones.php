<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 15)));
$offset = ($page - 1) * $size;
$decision = cleanString($input['decision'] ?? 'Pendiente', 20);
$search = cleanString($input['search'] ?? '', 150);

$allowedDecisions = ['', 'Pendiente', 'Aprobado', 'Rechazado', 'Cancelado'];
if (!in_array($decision, $allowedDecisions, true)) {
    $con->close();
    errorResponse('Decisión no válida.', 422, 'VALIDATION_ERROR');
}

$where = ['1 = 1'];
$types = '';
$params = [];

if (!isSuperAdmin($user) && !hasAnyRole($user, ['ADMIN_OPERATIVO'])) {
    $where[] = 'c.aprobador_id = ?';
    $types .= 'i';
    $params[] = $user['id'];
}
if ($decision !== '') {
    $where[] = 'c.decision = ?';
    $types .= 's';
    $params[] = $decision;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(r.folio LIKE ? OR r.cliente_nombre LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) AS total
             FROM operativo_requerimiento_cambio c
             INNER JOIN operativo_requerimiento_compra r ON r.id = c.requerimiento_id
             INNER JOIN autos a ON a.id = r.auto_id
             WHERE {$whereSql}";
$countStmt = $con->prepare($countSql);
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            c.id, c.requerimiento_id, c.estatus_origen, c.estatus_solicitado,
            c.motivo, c.decision, c.comentario_decision,
            c.fecha_solicitud, c.fecha_decision, c.solicitado_por, c.aprobador_id,
            r.folio, r.cliente_nombre, r.cliente_telefono, r.estatus AS estatus_actual,
            a.id AS auto_id, a.marca, a.modelo, a.anio, a.img_principal,
            CONCAT(s.nombre, ' ', s.apellido_paterno) AS solicitante,
            CONCAT(ap.nombre, ' ', ap.apellido_paterno) AS aprobador
        FROM operativo_requerimiento_cambio c
        INNER JOIN operativo_requerimiento_compra r ON r.id = c.requerimiento_id
        INNER JOIN autos a ON a.id = r.auto_id
        INNER JOIN operativo_usuario s ON s.id = c.solicitado_por
        LEFT JOIN operativo_usuario ap ON ap.id = c.aprobador_id
        WHERE {$whereSql}
        ORDER BY
            CASE WHEN c.decision = 'Pendiente' THEN 0 ELSE 1 END,
            c.fecha_solicitud DESC,
            c.id DESC
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
    $row['requerimiento_id'] = (int) $row['requerimiento_id'];
    $row['auto_id'] = (int) $row['auto_id'];
    $row['anio'] = (int) $row['anio'];
    $row['solicitado_por'] = (int) $row['solicitado_por'];
    $row['aprobador_id'] = $row['aprobador_id'] !== null ? (int) $row['aprobador_id'] : null;
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
