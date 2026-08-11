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
$status = cleanString($input['estatus'] ?? '', 30);

if (!in_array($status, array_merge([''], requirementAllowedStatuses()), true)) {
    $con->close();
    errorResponse('Estatus de requerimiento no válido.', 422, 'VALIDATION_ERROR');
}

$where = ['1 = 1'];
$types = '';
$params = [];

$visibleUserIds = requirementVisibleUserIds($con, $user);
if ($visibleUserIds !== null) {
    if ($visibleUserIds === []) {
        $where[] = '1 = 0';
    } else {
        $placeholders = implode(',', array_fill(0, count($visibleUserIds), '?'));
        $where[] = "(r.creado_por IN ({$placeholders}) OR r.asignado_a IN ({$placeholders}))";
        $types .= str_repeat('i', count($visibleUserIds) * 2);
        $params = array_merge($params, $visibleUserIds, $visibleUserIds);
    }
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(r.folio LIKE ? OR r.cliente_nombre LIKE ? OR r.cliente_telefono LIKE ?
                 OR a.marca LIKE ? OR a.modelo LIKE ? OR CAST(a.id AS CHAR) LIKE ?)';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'r.estatus = ?';
    $types .= 's';
    $params[] = $status;
}

$whereSql = implode(' AND ', $where);
$countSql = "SELECT COUNT(*) AS total
             FROM operativo_requerimiento_compra r
             INNER JOIN autos a ON a.id = r.auto_id
             WHERE {$whereSql}";
$countStmt = $con->prepare($countSql);
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            r.id, r.folio, r.auto_id, r.cliente_nombre, r.cliente_telefono,
            r.cliente_email, r.cliente_identificacion, r.monto_propuesto,
            r.forma_pago, r.comentarios, r.estatus, r.creado_por, r.asignado_a,
            r.fecha_solicitud, r.fecha_actualizacion, r.fecha_apartado, r.fecha_venta,
            a.marca, a.modelo, a.anio, a.precio, a.img_principal,
            CONCAT(cu.nombre, ' ', cu.apellido_paterno) AS creado_por_nombre,
            CONCAT(au.nombre, ' ', au.apellido_paterno) AS asignado_a_nombre,
            pc.id AS cambio_pendiente_id,
            pc.estatus_solicitado AS cambio_pendiente_estatus,
            pc.fecha_solicitud AS cambio_pendiente_fecha
        FROM operativo_requerimiento_compra r
        INNER JOIN autos a ON a.id = r.auto_id
        INNER JOIN operativo_usuario cu ON cu.id = r.creado_por
        INNER JOIN operativo_usuario au ON au.id = r.asignado_a
        LEFT JOIN operativo_requerimiento_cambio pc
            ON pc.requerimiento_id = r.id
           AND pc.decision = 'Pendiente'
        WHERE {$whereSql}
        ORDER BY r.fecha_actualizacion DESC, r.id DESC
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
    $row['auto_id'] = (int) $row['auto_id'];
    $row['anio'] = (int) $row['anio'];
    $row['precio'] = (float) $row['precio'];
    $row['monto_propuesto'] = $row['monto_propuesto'] !== null ? (float) $row['monto_propuesto'] : null;
    $row['cambio_pendiente_id'] = $row['cambio_pendiente_id'] !== null
        ? (int) $row['cambio_pendiente_id']
        : null;
}
unset($row);

okResponse([
    'items' => $rows,
    'permisos' => [
        'puede_crear' => canCreateRequirement($user),
        'puede_solicitar_cambio' => canCreateRequirement($user),
        'puede_ver_todos' => canViewAllRequirements($user),
        'alcance_jerarquia' => $visibleUserIds !== null,
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
