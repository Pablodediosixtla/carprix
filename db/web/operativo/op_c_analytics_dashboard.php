<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR', 'RH']);

$currentYear = rewardsCurrentYear();
$year = isset($input['anio']) ? (int) $input['anio'] : $currentYear;
if ($year < 2020 || $year > $currentYear + 1) {
    $con->close();
    errorResponse('El año consultado no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}

$isGlobal = hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'RH']);
$scopeIds = $isGlobal ? null : hierarchyDescendantIds($con, (int) $user['id']);
$selectedUserId = max(0, (int) ($input['usuario_id'] ?? 0));

if ($selectedUserId > 0) {
    if ($scopeIds !== null && !in_array($selectedUserId, $scopeIds, true)) {
        $con->close();
        errorResponse('La persona seleccionada no pertenece a tu alcance jerárquico.', 403, 'ANALYTICS_SCOPE_FORBIDDEN');
    }
    if ($scopeIds === null) {
        $check = $con->prepare('SELECT id FROM operativo_usuario WHERE id = ? LIMIT 1');
        $check->bind_param('i', $selectedUserId);
        $check->execute();
        $exists = (bool) $check->get_result()->fetch_row();
        $check->close();
        if (!$exists) {
            $con->close();
            errorResponse('La persona seleccionada no existe.', 404, 'USER_NOT_FOUND');
        }
    }
}

$targetIds = $selectedUserId > 0 ? [$selectedUserId] : $scopeIds;

function analyticsScopeClause(?array $ids, string $column, string &$types, array &$params): string
{
    if ($ids === null) return '';
    if ($ids === []) return ' AND 1 = 0';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types .= str_repeat('i', count($ids));
    array_push($params, ...$ids);
    return " AND {$column} IN ({$placeholders})";
}

function analyticsCountByDate(mysqli $con, string $dateColumn, int $year, ?array $ids, string $extra = ''): int
{
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta', 'fecha_actualizacion'];
    if (!in_array($dateColumn, $allowed, true)) return 0;
    $types = 'i';
    $params = [$year];
    $scope = analyticsScopeClause($ids, 'asignado_a', $types, $params);
    $sql = "SELECT COUNT(*) AS total
            FROM operativo_requerimiento_compra
            WHERE {$dateColumn} IS NOT NULL
              AND YEAR({$dateColumn}) = ?{$extra}{$scope}";
    $stmt = $con->prepare($sql);
    if (!$stmt) databaseError($con);
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

function analyticsRewardSummary(mysqli $con, int $year, ?array $ids): array
{
    $types = 'i';
    $params = [$year];
    $scope = analyticsScopeClause($ids, 'usuario_id', $types, $params);
    $stmt = $con->prepare(
        "SELECT
            COALESCE(SUM(puntos_aplicados), 0) AS netos,
            COALESCE(SUM(CASE WHEN puntos_aplicados > 0 THEN puntos_aplicados ELSE 0 END), 0) AS positivos,
            COALESCE(SUM(CASE WHEN puntos_aplicados < 0 THEN ABS(puntos_aplicados) ELSE 0 END), 0) AS negativos,
            SUM(CASE WHEN puntos_aplicados > 0 THEN 1 ELSE 0 END) AS reconocimientos,
            COUNT(*) AS movimientos
         FROM operativo_recompensa_movimiento
         WHERE anio = ?{$scope}"
    );
    if (!$stmt) databaseError($con);
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    return [
        'netos' => (int) ($row['netos'] ?? 0),
        'positivos' => (int) ($row['positivos'] ?? 0),
        'negativos' => (int) ($row['negativos'] ?? 0),
        'reconocimientos' => (int) ($row['reconocimientos'] ?? 0),
        'movimientos' => (int) ($row['movimientos'] ?? 0),
    ];
}

function analyticsMonthlyCounts(mysqli $con, string $dateColumn, int $year, ?array $ids): array
{
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta'];
    if (!in_array($dateColumn, $allowed, true)) return array_fill(0, 12, 0);
    $types = 'i';
    $params = [$year];
    $scope = analyticsScopeClause($ids, 'asignado_a', $types, $params);
    $stmt = $con->prepare(
        "SELECT MONTH({$dateColumn}) AS mes, COUNT(*) AS total
         FROM operativo_requerimiento_compra
         WHERE {$dateColumn} IS NOT NULL
           AND YEAR({$dateColumn}) = ?{$scope}
         GROUP BY MONTH({$dateColumn})"
    );
    if (!$stmt) databaseError($con);
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $values = array_fill(0, 12, 0);
    foreach ($rows as $row) {
        $month = (int) ($row['mes'] ?? 0);
        if ($month >= 1 && $month <= 12) $values[$month - 1] = (int) $row['total'];
    }
    return $values;
}

function analyticsPeople(mysqli $con, ?array $scopeIds): array
{
    $where = '';
    $types = '';
    $params = [];
    if ($scopeIds !== null) {
        if ($scopeIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
        $where = "WHERE u.id IN ({$placeholders})";
        $types = str_repeat('i', count($scopeIds));
        $params = $scopeIds;
    }
    $stmt = $con->prepare(
        "SELECT u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno, u.estatus
         FROM operativo_usuario u
         {$where}
         ORDER BY u.apellido_paterno, u.apellido_materno, u.nombre, u.id"
    );
    if (!$stmt) databaseError($con);
    if ($types !== '') bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['nombre_completo'] = trim(implode(' ', array_filter([
            $row['nombre'] ?? '', $row['apellido_paterno'] ?? '', $row['apellido_materno'] ?? ''
        ])));
    }
    unset($row);
    return $rows;
}

$people = analyticsPeople($con, $scopeIds);

$requests = analyticsCountByDate($con, 'fecha_solicitud', $year, $targetIds);
$reserved = analyticsCountByDate($con, 'fecha_apartado', $year, $targetIds);
$sold = analyticsCountByDate($con, 'fecha_venta', $year, $targetIds);
$rejected = analyticsCountByDate($con, 'fecha_actualizacion', $year, $targetIds, " AND estatus = 'Rechazado'");
$reward = analyticsRewardSummary($con, $year, $targetIds);
$conversion = $requests > 0 ? round(($sold / $requests) * 100, 1) : 0.0;

$monthly = [
    'solicitudes' => analyticsMonthlyCounts($con, 'fecha_solicitud', $year, $targetIds),
    'apartados' => analyticsMonthlyCounts($con, 'fecha_apartado', $year, $targetIds),
    'vendidos' => analyticsMonthlyCounts($con, 'fecha_venta', $year, $targetIds),
];

// Ranking: para vista individual regresa una sola fila; para grupo respeta el alcance.
$rankingWhere = '';
$rankingTypes = 'iiiii';
$rankingParams = [$year, $year, $year, $year, $year];
if ($targetIds !== null) {
    if ($targetIds === []) {
        $rankingWhere = 'WHERE 1 = 0';
    } else {
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $rankingWhere = "WHERE u.id IN ({$placeholders})";
        $rankingTypes .= str_repeat('i', count($targetIds));
        array_push($rankingParams, ...$targetIds);
    }
}
$rankingSql = "SELECT
        u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno, u.estatus,
        (SELECT COUNT(*) FROM operativo_requerimiento_compra r
          WHERE r.asignado_a = u.id AND YEAR(r.fecha_solicitud) = ?) AS solicitudes,
        (SELECT COUNT(*) FROM operativo_requerimiento_compra r
          WHERE r.asignado_a = u.id AND r.fecha_apartado IS NOT NULL AND YEAR(r.fecha_apartado) = ?) AS apartados,
        (SELECT COUNT(*) FROM operativo_requerimiento_compra r
          WHERE r.asignado_a = u.id AND r.fecha_venta IS NOT NULL AND YEAR(r.fecha_venta) = ?) AS vendidos,
        (SELECT COALESCE(SUM(m.puntos_aplicados), 0) FROM operativo_recompensa_movimiento m
          WHERE m.usuario_id = u.id AND m.anio = ?) AS puntos,
        (SELECT COUNT(*) FROM operativo_recompensa_movimiento m
          WHERE m.usuario_id = u.id AND m.anio = ? AND m.puntos_aplicados > 0) AS reconocimientos
    FROM operativo_usuario u
    {$rankingWhere}
    ORDER BY vendidos DESC, apartados DESC, solicitudes DESC, puntos DESC,
             u.apellido_paterno, u.nombre";
$rankingStmt = $con->prepare($rankingSql);
if (!$rankingStmt) databaseError($con);
bindDynamicParams($rankingStmt, $rankingTypes, $rankingParams);
$rankingStmt->execute();
$ranking = $rankingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rankingStmt->close();
foreach ($ranking as &$row) {
    $row['id'] = (int) $row['id'];
    $row['solicitudes'] = (int) $row['solicitudes'];
    $row['apartados'] = (int) $row['apartados'];
    $row['vendidos'] = (int) $row['vendidos'];
    $row['puntos'] = (int) $row['puntos'];
    $row['reconocimientos'] = (int) $row['reconocimientos'];
    $row['conversion'] = $row['solicitudes'] > 0 ? round(($row['vendidos'] / $row['solicitudes']) * 100, 1) : 0.0;
    $row['nombre_completo'] = trim(implode(' ', array_filter([
        $row['nombre'] ?? '', $row['apellido_paterno'] ?? '', $row['apellido_materno'] ?? ''
    ])));
}
unset($row);

$years = [$currentYear];
$yearResult = $con->query(
    "SELECT DISTINCT anio FROM (
        SELECT YEAR(fecha_solicitud) AS anio FROM operativo_requerimiento_compra WHERE fecha_solicitud IS NOT NULL
        UNION
        SELECT YEAR(fecha_apartado) AS anio FROM operativo_requerimiento_compra WHERE fecha_apartado IS NOT NULL
        UNION
        SELECT YEAR(fecha_venta) AS anio FROM operativo_requerimiento_compra WHERE fecha_venta IS NOT NULL
        UNION
        SELECT anio FROM operativo_recompensa_movimiento
     ) x WHERE anio IS NOT NULL ORDER BY anio DESC"
);
if ($yearResult) {
    $years = array_values(array_unique(array_merge(
        [$currentYear],
        array_map('intval', array_column($yearResult->fetch_all(MYSQLI_ASSOC), 'anio'))
    )));
    rsort($years, SORT_NUMERIC);
    $yearResult->free();
}

$scopeLabel = 'Mi grupo completo';
if ($isGlobal) $scopeLabel = 'Organización completa';
if ($selectedUserId > 0) {
    foreach ($people as $person) {
        if ((int) $person['id'] === $selectedUserId) {
            $scopeLabel = (string) $person['nombre_completo'];
            break;
        }
    }
}

$con->close();

okResponse([
    'anio' => $year,
    'anios_disponibles' => $years,
    'personas' => $people,
    'usuario_seleccionado' => $selectedUserId,
    'alcance' => [
        'global' => $isGlobal,
        'etiqueta' => $scopeLabel,
    ],
    'resumen' => [
        'solicitudes' => $requests,
        'apartados' => $reserved,
        'vendidos' => $sold,
        'rechazados' => $rejected,
        'conversion' => $conversion,
        'reconocimientos' => $reward['reconocimientos'],
        'puntos_netos' => $reward['netos'],
        'puntos_positivos' => $reward['positivos'],
        'puntos_negativos' => $reward['negativos'],
        'movimientos_recompensa' => $reward['movimientos'],
    ],
    'mensual' => $monthly,
    'ranking' => $ranking,
]);
