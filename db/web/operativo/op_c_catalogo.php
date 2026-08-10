<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 12)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 150);
$status = cleanString($input['estatus'] ?? '', 30);
$type = cleanString($input['tipo'] ?? '', 50);
$location = cleanString($input['ubicacion'] ?? '', 100);

$allowedStatuses = ['', 'Disponible', 'Apartado', 'Vendido', 'Oculto'];
if (!in_array($status, $allowedStatuses, true)) {
    $con->close();
    errorResponse('El estatus del catálogo no es válido.', 422, 'VALIDATION_ERROR');
}

$where = ['1 = 1'];
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(a.marca LIKE ? OR a.modelo LIKE ? OR CAST(a.id AS CHAR) LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'a.estatus = ?';
    $types .= 's';
    $params[] = $status;
}
if ($type !== '') {
    $where[] = 'a.tipo = ?';
    $types .= 's';
    $params[] = $type;
}
if ($location !== '') {
    $where[] = 'a.ubicacion = ?';
    $types .= 's';
    $params[] = $location;
}

$whereSql = implode(' AND ', $where);
$countStmt = $con->prepare("SELECT COUNT(*) AS total FROM autos a WHERE {$whereSql}");
if (!$countStmt) {
    databaseError($con);
}
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$sql = "SELECT
            a.id, a.marca, a.modelo, a.tipo, a.anio, a.precio, a.mensualidad,
            a.ubicacion, a.kilometraje, a.transmision, a.color, a.motor,
            a.combustible, a.pasajeros, a.traccion, a.duenos,
            a.img_principal, a.estatus, a.fecha_carga, a.fecha_modificacion,
            (
                SELECT cr.id
                FROM operativo_catalogo_requerimiento cr
                WHERE cr.auto_id = a.id
                  AND cr.decision = 'Pendiente'
                ORDER BY cr.id DESC
                LIMIT 1
            ) AS requerimiento_catalogo_pendiente_id
        FROM autos a
        WHERE {$whereSql}
        ORDER BY a.fecha_modificacion DESC, a.id DESC
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

$typesResult = $con->query("SELECT DISTINCT tipo FROM autos WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo")
    ->fetch_all(MYSQLI_ASSOC);
$locationsResult = $con->query("SELECT DISTINCT ubicacion FROM autos WHERE ubicacion IS NOT NULL AND ubicacion <> '' ORDER BY ubicacion")
    ->fetch_all(MYSQLI_ASSOC);
$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['anio'] = (int) $row['anio'];
    $row['precio'] = (float) $row['precio'];
    $row['mensualidad'] = (float) $row['mensualidad'];
    $row['kilometraje'] = (int) $row['kilometraje'];
    $row['pasajeros'] = $row['pasajeros'] !== null ? (int) $row['pasajeros'] : null;
    $row['duenos'] = $row['duenos'] !== null ? (int) $row['duenos'] : null;
    $row['requerimiento_catalogo_pendiente_id'] = $row['requerimiento_catalogo_pendiente_id'] !== null
        ? (int) $row['requerimiento_catalogo_pendiente_id']
        : null;
}
unset($row);

okResponse([
    'items' => $rows,
    'filtros' => [
        'tipos' => array_column($typesResult, 'tipo'),
        'ubicaciones' => array_column($locationsResult, 'ubicacion'),
    ],
    'permisos' => [
        'puede_editar' => canManageCatalog($user),
        'puede_autorizar_catalogo' => canAuthorizeCatalogRequests($user),
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
