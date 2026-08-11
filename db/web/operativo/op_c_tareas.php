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
$priority = cleanString($input['prioridad'] ?? '', 20);

if ($status !== '' && !in_array($status, taskStatusValues(), true)) {
    $con->close();
    errorResponse('Estatus de tarea no válido.', 422, 'VALIDATION_ERROR');
}
if ($priority !== '' && !in_array($priority, ['Baja', 'Media', 'Alta', 'Urgente'], true)) {
    $con->close();
    errorResponse('Prioridad no válida.', 422, 'VALIDATION_ERROR');
}

$where = ['1 = 1'];
$types = '';
$params = [];
$userId = (int) $user['id'];

if (!isSuperAdmin($user)) {
    $visibleIds = hierarchyDescendantIds($con, $userId);
    $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
    $where[] = "(t.asignado_a IN ({$placeholders}) OR t.creado_por = ? OR t.aprobador_id = ?)";
    $types .= str_repeat('i', count($visibleIds)) . 'ii';
    foreach ($visibleIds as $id) {
        $params[] = (int) $id;
    }
    $params[] = $userId;
    $params[] = $userId;
}

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(t.folio LIKE ? OR t.titulo LIKE ? OR t.descripcion LIKE ?
                 OR au.nombre LIKE ? OR au.apellido_paterno LIKE ?
                 OR cu.nombre LIKE ? OR cu.apellido_paterno LIKE ?)';
    $types .= 'sssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 't.estatus = ?';
    $types .= 's';
    $params[] = $status;
}
if ($priority !== '') {
    $where[] = 't.prioridad = ?';
    $types .= 's';
    $params[] = $priority;
}

$whereSql = implode(' AND ', $where);

$countStmt = $con->prepare(
    "SELECT COUNT(*) AS total
     FROM operativo_tarea t
     INNER JOIN operativo_usuario au ON au.id = t.asignado_a
     INNER JOIN operativo_usuario cu ON cu.id = t.creado_por
     WHERE {$whereSql}"
);
if (!$countStmt) {
    databaseError($con);
}
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$sql = "SELECT
            t.id, t.folio, t.titulo, t.descripcion, t.prioridad, t.estatus,
            t.fecha_inicio, t.fecha_fin, t.fecha_completada,
            t.creado_por, t.asignado_a, t.aprobador_id, t.requiere_aprobacion,
            t.creado_en, t.actualizado_en,
            CONCAT_WS(' ', cu.nombre, cu.apellido_paterno, cu.apellido_materno) AS creado_por_nombre,
            CONCAT_WS(' ', au.nombre, au.apellido_paterno, au.apellido_materno) AS asignado_a_nombre,
            CONCAT_WS(' ', ap.nombre, ap.apellido_paterno, ap.apellido_materno) AS aprobador_nombre,
            pa.id AS aprobacion_pendiente_id,
            pa.fecha_solicitud AS aprobacion_pendiente_fecha,
            (SELECT COUNT(*) FROM operativo_tarea_comentario tc WHERE tc.tarea_id = t.id) AS comentarios_total
        FROM operativo_tarea t
        INNER JOIN operativo_usuario cu ON cu.id = t.creado_por
        INNER JOIN operativo_usuario au ON au.id = t.asignado_a
        LEFT JOIN operativo_usuario ap ON ap.id = t.aprobador_id
        LEFT JOIN operativo_tarea_aprobacion pa
            ON pa.id = (
                SELECT ta2.id
                FROM operativo_tarea_aprobacion ta2
                WHERE ta2.tarea_id = t.id
                  AND ta2.decision = 'Pendiente'
                ORDER BY ta2.id DESC
                LIMIT 1
            )
        WHERE {$whereSql}
        ORDER BY
            CASE t.estatus
                WHEN 'En revision' THEN 0
                WHEN 'En progreso' THEN 1
                WHEN 'Pendiente' THEN 2
                WHEN 'Completada' THEN 3
                ELSE 4
            END,
            CASE WHEN t.fecha_fin IS NULL THEN 1 ELSE 0 END,
            t.fecha_fin ASC,
            t.actualizado_en DESC
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

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['creado_por'] = (int) $row['creado_por'];
    $row['asignado_a'] = (int) $row['asignado_a'];
    $row['aprobador_id'] = $row['aprobador_id'] !== null ? (int) $row['aprobador_id'] : null;
    $row['requiere_aprobacion'] = (bool) $row['requiere_aprobacion'];
    $row['aprobacion_pendiente_id'] = $row['aprobacion_pendiente_id'] !== null ? (int) $row['aprobacion_pendiente_id'] : null;
    $row['comentarios_total'] = (int) $row['comentarios_total'];

    $statusValue = (string) $row['estatus'];
    $isAssignee = (int) $row['asignado_a'] === $userId;
    $isCreator = (int) $row['creado_por'] === $userId;
    $row['permisos'] = [
        'puede_iniciar' => $isAssignee && $statusValue === 'Pendiente',
        'puede_completar' => $isAssignee && in_array($statusValue, ['Pendiente', 'En progreso'], true),
        'puede_cancelar' => ($isCreator || isSuperAdmin($user)) && !in_array($statusValue, ['Completada', 'Cancelada'], true),
        'puede_aprobar' => $statusValue === 'En revision'
            && $row['aprobacion_pendiente_id'] !== null
            && canApproveTask($con, $user, $row),
        'puede_comentar' => canAccessTask($con, $user, $row),
    ];
}
unset($row);

// Resumen bajo el mismo alcance de visibilidad, sin filtros de búsqueda.
$scopeWhere = ['1 = 1'];
$scopeTypes = '';
$scopeParams = [];
if (!isSuperAdmin($user)) {
    $visibleIds = hierarchyDescendantIds($con, $userId);
    $placeholders = implode(',', array_fill(0, count($visibleIds), '?'));
    $scopeWhere[] = "(t.asignado_a IN ({$placeholders}) OR t.creado_por = ? OR t.aprobador_id = ?)";
    $scopeTypes .= str_repeat('i', count($visibleIds)) . 'ii';
    foreach ($visibleIds as $id) {
        $scopeParams[] = (int) $id;
    }
    $scopeParams[] = $userId;
    $scopeParams[] = $userId;
}
$scopeSql = implode(' AND ', $scopeWhere);
$summaryStmt = $con->prepare(
    "SELECT
        SUM(t.estatus = 'Pendiente') AS pendientes,
        SUM(t.estatus = 'En progreso') AS en_progreso,
        SUM(t.estatus = 'En revision') AS en_revision,
        SUM(t.estatus = 'Completada') AS completadas
     FROM operativo_tarea t
     WHERE {$scopeSql}"
);
if (!$summaryStmt) {
    databaseError($con);
}
bindDynamicParams($summaryStmt, $scopeTypes, $scopeParams);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
$summaryStmt->close();
$con->close();

okResponse([
    'items' => $rows,
    'resumen' => [
        'pendientes' => (int) ($summary['pendientes'] ?? 0),
        'en_progreso' => (int) ($summary['en_progreso'] ?? 0),
        'en_revision' => (int) ($summary['en_revision'] ?? 0),
        'completadas' => (int) ($summary['completadas'] ?? 0),
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
