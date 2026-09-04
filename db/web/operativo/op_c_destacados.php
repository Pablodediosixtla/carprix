<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

if (!canManageCatalog($user)) {
    $con->close();
    errorResponse('No tienes permisos para consultar la gestión de autos destacados.', 403, 'FORBIDDEN');
}

$page = max(1, (int) ($input['page'] ?? 1));
$size = min(100, max(1, (int) ($input['size'] ?? 25)));
$offset = ($page - 1) * $size;
$search = cleanString($input['search'] ?? '', 150);

$where = ["a.estatus <> 'Oculto'"];
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[] = '(CAST(a.id AS CHAR) LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}

$whereSql = implode(' AND ', $where);

$countStmt = $con->prepare("SELECT COUNT(*) AS total FROM autos a WHERE {$whereSql}");
if (!$countStmt) {
    databaseError($con);
}
$countParams = $params;
bindDynamicParams($countStmt, $types, $countParams);
$countStmt->execute();
$total = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$sql = "SELECT
            a.id,
            a.marca,
            a.modelo,
            a.tipo,
            a.anio,
            a.precio,
            a.kilometraje,
            a.ubicacion,
            a.img_principal,
            a.estatus,
            COALESCE(v.total_visitas, 0) AS total_visitas,
            v.ultima_visita_en,
            CASE WHEN d.auto_id IS NULL THEN 0 ELSE 1 END AS destacado,
            d.posicion,
            d.actualizado_en AS destacado_actualizado_en
        FROM autos a
        LEFT JOIN auto_detalle_visita v
            ON v.auto_id = a.id
        LEFT JOIN operativo_auto_destacado d
            ON d.auto_id = a.id
        WHERE {$whereSql}
        ORDER BY
            COALESCE(v.total_visitas, 0) DESC,
            CASE WHEN d.auto_id IS NULL THEN 1 ELSE 0 END ASC,
            a.fecha_modificacion DESC,
            a.id DESC
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

$featuredCount = 0;
$featuredResult = $con->query('SELECT COUNT(*) AS total FROM operativo_auto_destacado');
if ($featuredResult instanceof mysqli_result) {
    $featuredCount = (int) ($featuredResult->fetch_assoc()['total'] ?? 0);
    $featuredResult->free();
}

$con->close();

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $row['anio'] = (int) $row['anio'];
    $row['precio'] = (float) $row['precio'];
    $row['kilometraje'] = (int) $row['kilometraje'];
    $row['total_visitas'] = (int) $row['total_visitas'];
    $row['destacado'] = (bool) $row['destacado'];
    $row['posicion'] = $row['posicion'] !== null ? (int) $row['posicion'] : null;
}
unset($row);

okResponse([
    'items' => $rows,
    'destacados' => [
        'seleccionados' => $featuredCount,
        'maximo' => 3,
    ],
    'permisos' => [
        'puede_editar' => canManageCatalog($user),
    ],
    'pagination' => [
        'page' => $page,
        'size' => $size,
        'total' => $total,
        'pages' => (int) ceil($total / $size),
    ],
]);
