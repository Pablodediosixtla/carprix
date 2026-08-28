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

/**
 * Normaliza el filtro múltiple de meses.
 * Ausente, vacío o inválido equivale a todos los meses.
 */
function analyticsNormalizeMonths(mixed $value): array
{
    if (!is_array($value)) {
        return range(1, 12);
    }

    $months = [];
    foreach ($value as $month) {
        $number = (int) $month;
        if ($number >= 1 && $number <= 12) {
            $months[$number] = true;
        }
    }

    if ($months === []) {
        return range(1, 12);
    }

    $result = array_map('intval', array_keys($months));
    sort($result, SORT_NUMERIC);
    return $result;
}

$months = analyticsNormalizeMonths($input['meses'] ?? null);
$isGlobal = hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'RH']);
$baseScopeIds = $isGlobal ? null : hierarchyDescendantIds($con, (int) $user['id']);
$selectedTeamId = max(0, (int) ($input['equipo_id'] ?? 0));
$selectedUserId = max(0, (int) ($input['usuario_id'] ?? 0));

function analyticsScopeClause(?array $ids, string $column, string &$types, array &$params): string
{
    if ($ids === null) {
        return '';
    }
    if ($ids === []) {
        return ' AND 1 = 0';
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types .= str_repeat('i', count($ids));
    array_push($params, ...array_map('intval', $ids));
    return " AND {$column} IN ({$placeholders})";
}

function analyticsMonthClause(array $months, string $column, string &$types, array &$params): string
{
    if (count($months) >= 12) {
        return '';
    }
    if ($months === []) {
        return ' AND 1 = 0';
    }

    $placeholders = implode(',', array_fill(0, count($months), '?'));
    $types .= str_repeat('i', count($months));
    array_push($params, ...array_map('intval', $months));
    return " AND MONTH({$column}) IN ({$placeholders})";
}

function analyticsIntersectScope(array $candidateIds, ?array $allowedIds): array
{
    $candidateIds = array_values(array_unique(array_map('intval', $candidateIds)));
    if ($allowedIds === null) {
        return $candidateIds;
    }

    $allowed = array_fill_keys(array_map('intval', $allowedIds), true);
    return array_values(array_filter(
        $candidateIds,
        static fn(int $id): bool => isset($allowed[$id])
    ));
}

function analyticsPeople(mysqli $con, ?array $scopeIds): array
{
    $where = '';
    $types = '';
    $params = [];

    if ($scopeIds !== null) {
        if ($scopeIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
        $where = "WHERE u.id IN ({$placeholders})";
        $types = str_repeat('i', count($scopeIds));
        $params = array_map('intval', $scopeIds);
    }

    $stmt = $con->prepare(
        "SELECT
            u.id,
            u.username,
            u.nombre,
            u.apellido_paterno,
            u.apellido_materno,
            u.estatus
         FROM operativo_usuario u
         {$where}
         ORDER BY u.apellido_paterno, u.apellido_materno, u.nombre, u.id"
    );
    if (!$stmt) {
        databaseError($con);
    }
    if ($types !== '') {
        bindDynamicParams($stmt, $types, $params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['nombre_completo'] = trim(implode(' ', array_filter([
            $row['nombre'] ?? '',
            $row['apellido_paterno'] ?? '',
            $row['apellido_materno'] ?? '',
        ])));
    }
    unset($row);

    return $rows;
}

/**
 * Equipos disponibles para el dashboard.
 * Un equipo es una rama jerárquica encabezada por un Gerente de Operaciones
 * o un Supervisor que tenga al menos un subordinado directo activo en la
 * estructura jerárquica.
 */
function analyticsTeams(mysqli $con, ?array $baseScopeIds): array
{
    $whereScope = '';
    $types = '';
    $params = [];

    if ($baseScopeIds !== null) {
        if ($baseScopeIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($baseScopeIds), '?'));
        $whereScope = " AND u.id IN ({$placeholders})";
        $types = str_repeat('i', count($baseScopeIds));
        $params = array_map('intval', $baseScopeIds);
    }

    $sql = "SELECT
                u.id,
                u.username,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles,
                COUNT(DISTINCT j.usuario_id) AS subordinados_directos
            FROM operativo_usuario u
            INNER JOIN operativo_usuario_rol ur
                ON ur.usuario_id = u.id
               AND ur.activo = 1
            INNER JOIN operativo_rol r
                ON r.id = ur.rol_id
               AND r.activo = 1
               AND r.codigo IN ('ADMIN_OPERATIVO', 'AUTORIZADOR')
            INNER JOIN operativo_usuario_jerarquia j
                ON j.supervisor_id = u.id
               AND j.activo = 1
            WHERE u.estatus = 'Activo'{$whereScope}
            GROUP BY
                u.id, u.username, u.nombre, u.apellido_paterno, u.apellido_materno
            HAVING subordinados_directos > 0
            ORDER BY
                CASE WHEN FIND_IN_SET('ADMIN_OPERATIVO', roles) > 0 THEN 0 ELSE 1 END,
                u.apellido_paterno, u.apellido_materno, u.nombre, u.id";

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        databaseError($con);
    }
    if ($types !== '') {
        bindDynamicParams($stmt, $types, $params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['subordinados_directos'] = (int) $row['subordinados_directos'];
        $roles = array_filter(array_map('trim', explode(',', (string) ($row['roles'] ?? ''))));
        $row['roles'] = array_values($roles);
        $row['tipo'] = in_array('ADMIN_OPERATIVO', $row['roles'], true) ? 'Gerencia' : 'Supervisor';
        $row['nombre_completo'] = trim(implode(' ', array_filter([
            $row['nombre'] ?? '',
            $row['apellido_paterno'] ?? '',
            $row['apellido_materno'] ?? '',
        ])));
        $row['etiqueta'] = $row['tipo'] . ' · ' . ($row['nombre_completo'] !== '' ? $row['nombre_completo'] : $row['username']);
    }
    unset($row);

    return $rows;
}

$teams = analyticsTeams($con, $baseScopeIds);
$teamScopeIds = $baseScopeIds;
$selectedTeam = null;

if ($selectedTeamId > 0) {
    foreach ($teams as $team) {
        if ((int) $team['id'] === $selectedTeamId) {
            $selectedTeam = $team;
            break;
        }
    }

    if ($selectedTeam === null) {
        $con->close();
        errorResponse('El equipo seleccionado no pertenece a tu alcance.', 403, 'ANALYTICS_TEAM_FORBIDDEN');
    }

    $teamScopeIds = analyticsIntersectScope(
        hierarchyDescendantIds($con, $selectedTeamId),
        $baseScopeIds
    );
}

$people = analyticsPeople($con, $teamScopeIds);
$peopleIds = array_map(static fn(array $person): int => (int) $person['id'], $people);

if ($selectedUserId > 0 && !in_array($selectedUserId, $peopleIds, true)) {
    $con->close();
    errorResponse('La persona seleccionada no pertenece al equipo o alcance actual.', 403, 'ANALYTICS_SCOPE_FORBIDDEN');
}

$targetIds = $selectedUserId > 0 ? [$selectedUserId] : $teamScopeIds;

function analyticsCountByDate(
    mysqli $con,
    string $dateColumn,
    int $year,
    array $months,
    ?array $ids,
    string $extra = ''
): int {
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta', 'fecha_actualizacion'];
    if (!in_array($dateColumn, $allowed, true)) {
        return 0;
    }

    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, $dateColumn, $types, $params);
    $userScope = analyticsScopeClause($ids, 'asignado_a', $types, $params);

    $sql = "SELECT COUNT(*) AS total
            FROM operativo_requerimiento_compra
            WHERE {$dateColumn} IS NOT NULL
              AND YEAR({$dateColumn}) = ?{$monthScope}{$extra}{$userScope}";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        databaseError($con);
    }
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

function analyticsRewardSummary(mysqli $con, int $year, array $months, ?array $ids): array
{
    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, 'creado_en', $types, $params);
    $userScope = analyticsScopeClause($ids, 'usuario_id', $types, $params);

    $stmt = $con->prepare(
        "SELECT
            COALESCE(SUM(puntos_aplicados), 0) AS netos,
            COALESCE(SUM(CASE WHEN puntos_aplicados > 0 THEN puntos_aplicados ELSE 0 END), 0) AS positivos,
            COALESCE(SUM(CASE WHEN puntos_aplicados < 0 THEN ABS(puntos_aplicados) ELSE 0 END), 0) AS negativos,
            SUM(CASE WHEN puntos_aplicados > 0 THEN 1 ELSE 0 END) AS reconocimientos,
            COUNT(*) AS movimientos
         FROM operativo_recompensa_movimiento
         WHERE anio = ?{$monthScope}{$userScope}"
    );
    if (!$stmt) {
        databaseError($con);
    }
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

function analyticsMonthlyCounts(mysqli $con, string $dateColumn, int $year, array $months, ?array $ids): array
{
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta'];
    if (!in_array($dateColumn, $allowed, true)) {
        return array_fill(0, 12, 0);
    }

    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, $dateColumn, $types, $params);
    $userScope = analyticsScopeClause($ids, 'asignado_a', $types, $params);

    $stmt = $con->prepare(
        "SELECT MONTH({$dateColumn}) AS mes, COUNT(*) AS total
         FROM operativo_requerimiento_compra
         WHERE {$dateColumn} IS NOT NULL
           AND YEAR({$dateColumn}) = ?{$monthScope}{$userScope}
         GROUP BY MONTH({$dateColumn})"
    );
    if (!$stmt) {
        databaseError($con);
    }
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $values = array_fill(0, 12, 0);
    foreach ($rows as $row) {
        $month = (int) ($row['mes'] ?? 0);
        if ($month >= 1 && $month <= 12) {
            $values[$month - 1] = (int) $row['total'];
        }
    }
    return $values;
}

function analyticsCountsByUser(
    mysqli $con,
    string $dateColumn,
    int $year,
    array $months,
    ?array $ids
): array {
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta'];
    if (!in_array($dateColumn, $allowed, true)) {
        return [];
    }

    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, $dateColumn, $types, $params);
    $userScope = analyticsScopeClause($ids, 'asignado_a', $types, $params);

    $stmt = $con->prepare(
        "SELECT asignado_a AS usuario_id, COUNT(*) AS total
         FROM operativo_requerimiento_compra
         WHERE {$dateColumn} IS NOT NULL
           AND YEAR({$dateColumn}) = ?{$monthScope}{$userScope}
         GROUP BY asignado_a"
    );
    if (!$stmt) {
        databaseError($con);
    }
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['usuario_id']] = (int) $row['total'];
    }
    return $map;
}

function analyticsRewardsByUser(mysqli $con, int $year, array $months, ?array $ids): array
{
    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, 'creado_en', $types, $params);
    $userScope = analyticsScopeClause($ids, 'usuario_id', $types, $params);

    $stmt = $con->prepare(
        "SELECT
            usuario_id,
            COALESCE(SUM(puntos_aplicados), 0) AS puntos,
            SUM(CASE WHEN puntos_aplicados > 0 THEN 1 ELSE 0 END) AS reconocimientos
         FROM operativo_recompensa_movimiento
         WHERE anio = ?{$monthScope}{$userScope}
         GROUP BY usuario_id"
    );
    if (!$stmt) {
        databaseError($con);
    }
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['usuario_id']] = [
            'puntos' => (int) ($row['puntos'] ?? 0),
            'reconocimientos' => (int) ($row['reconocimientos'] ?? 0),
        ];
    }
    return $map;
}

function analyticsRanking(mysqli $con, int $year, array $months, ?array $ids): array
{
    $people = analyticsPeople($con, $ids);
    if ($people === []) {
        return [];
    }

    $requests = analyticsCountsByUser($con, 'fecha_solicitud', $year, $months, $ids);
    $reserved = analyticsCountsByUser($con, 'fecha_apartado', $year, $months, $ids);
    $sold = analyticsCountsByUser($con, 'fecha_venta', $year, $months, $ids);
    $rewards = analyticsRewardsByUser($con, $year, $months, $ids);

    foreach ($people as &$person) {
        $id = (int) $person['id'];
        $person['solicitudes'] = (int) ($requests[$id] ?? 0);
        $person['apartados'] = (int) ($reserved[$id] ?? 0);
        $person['vendidos'] = (int) ($sold[$id] ?? 0);
        $person['puntos'] = (int) ($rewards[$id]['puntos'] ?? 0);
        $person['reconocimientos'] = (int) ($rewards[$id]['reconocimientos'] ?? 0);
        $person['conversion'] = $person['solicitudes'] > 0
            ? round(($person['vendidos'] / $person['solicitudes']) * 100, 1)
            : 0.0;
    }
    unset($person);

    usort($people, static function (array $a, array $b): int {
        foreach (['vendidos', 'apartados', 'solicitudes', 'puntos'] as $field) {
            $comparison = ((int) $b[$field]) <=> ((int) $a[$field]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }
        return strcasecmp((string) $a['nombre_completo'], (string) $b['nombre_completo']);
    });

    return $people;
}

function analyticsEventRows(
    mysqli $con,
    string $dateColumn,
    string $movement,
    int $year,
    array $months,
    ?array $ids,
    int $limit = 160
): array {
    $allowed = ['fecha_solicitud', 'fecha_apartado', 'fecha_venta'];
    if (!in_array($dateColumn, $allowed, true)) {
        return [];
    }

    $types = 'i';
    $params = [$year];
    $monthScope = analyticsMonthClause($months, "r.{$dateColumn}", $types, $params);
    $userScope = analyticsScopeClause($ids, 'r.asignado_a', $types, $params);
    $limit = max(1, min(300, $limit));

    $stmt = $con->prepare(
        "SELECT
            r.id AS requerimiento_id,
            r.folio,
            r.auto_id,
            r.cliente_nombre,
            r.monto_propuesto,
            r.estatus,
            r.asignado_a,
            r.{$dateColumn} AS fecha_evento,
            a.marca,
            a.modelo,
            a.anio,
            u.username AS responsable_username,
            CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS responsable_nombre
         FROM operativo_requerimiento_compra r
         INNER JOIN autos a ON a.id = r.auto_id
         INNER JOIN operativo_usuario u ON u.id = r.asignado_a
         WHERE r.{$dateColumn} IS NOT NULL
           AND YEAR(r.{$dateColumn}) = ?{$monthScope}{$userScope}
         ORDER BY r.{$dateColumn} DESC, r.id DESC
         LIMIT {$limit}"
    );
    if (!$stmt) {
        databaseError($con);
    }
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['requerimiento_id'] = (int) $row['requerimiento_id'];
        $row['auto_id'] = (int) $row['auto_id'];
        $row['asignado_a'] = (int) $row['asignado_a'];
        $row['anio'] = (int) $row['anio'];
        $row['monto_propuesto'] = $row['monto_propuesto'] !== null ? (float) $row['monto_propuesto'] : null;
        $row['movimiento'] = $movement;
    }
    unset($row);

    return $rows;
}

function analyticsDetail(mysqli $con, int $year, array $months, ?array $ids): array
{
    if (count($months) >= 12) {
        return [
            'activo' => false,
            'meses' => $months,
            'total' => 0,
            'items' => [],
        ];
    }

    $total =
        analyticsCountByDate($con, 'fecha_solicitud', $year, $months, $ids) +
        analyticsCountByDate($con, 'fecha_apartado', $year, $months, $ids) +
        analyticsCountByDate($con, 'fecha_venta', $year, $months, $ids);

    $items = array_merge(
        analyticsEventRows($con, 'fecha_solicitud', 'Solicitud', $year, $months, $ids, 300),
        analyticsEventRows($con, 'fecha_apartado', 'Apartado', $year, $months, $ids, 300),
        analyticsEventRows($con, 'fecha_venta', 'Vendido', $year, $months, $ids, 300)
    );

    usort($items, static function (array $a, array $b): int {
        $aTime = strtotime((string) ($a['fecha_evento'] ?? '')) ?: 0;
        $bTime = strtotime((string) ($b['fecha_evento'] ?? '')) ?: 0;
        if ($aTime === $bTime) {
            return ((int) $b['requerimiento_id']) <=> ((int) $a['requerimiento_id']);
        }
        return $bTime <=> $aTime;
    });

    $items = array_slice($items, 0, 300);

    return [
        'activo' => true,
        'meses' => $months,
        'total' => $total,
        'items' => $items,
        'limitado' => $total > count($items),
    ];
}

$requests = analyticsCountByDate($con, 'fecha_solicitud', $year, $months, $targetIds);
$reserved = analyticsCountByDate($con, 'fecha_apartado', $year, $months, $targetIds);
$sold = analyticsCountByDate($con, 'fecha_venta', $year, $months, $targetIds);
$rejected = analyticsCountByDate(
    $con,
    'fecha_actualizacion',
    $year,
    $months,
    $targetIds,
    " AND estatus = 'Rechazado'"
);
$reward = analyticsRewardSummary($con, $year, $months, $targetIds);
$conversion = $requests > 0 ? round(($sold / $requests) * 100, 1) : 0.0;

$monthly = [
    'solicitudes' => analyticsMonthlyCounts($con, 'fecha_solicitud', $year, $months, $targetIds),
    'apartados' => analyticsMonthlyCounts($con, 'fecha_apartado', $year, $months, $targetIds),
    'vendidos' => analyticsMonthlyCounts($con, 'fecha_venta', $year, $months, $targetIds),
];

$ranking = analyticsRanking($con, $year, $months, $targetIds);
$detail = analyticsDetail($con, $year, $months, $targetIds);

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

$scopeLabel = $isGlobal ? 'Organización completa' : 'Mi equipo completo';
$teamGeneralLabel = $isGlobal ? 'Organización completa' : 'Mi equipo completo';

if ($selectedTeam !== null) {
    $scopeLabel = 'Equipo · ' . ($selectedTeam['nombre_completo'] ?: $selectedTeam['username']);
}

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
    'meses_seleccionados' => $months,
    'equipos' => $teams,
    'equipo_seleccionado' => $selectedTeamId,
    'personas' => $people,
    'usuario_seleccionado' => $selectedUserId,
    'alcance' => [
        'global' => $isGlobal,
        'etiqueta' => $scopeLabel,
        'equipo_general' => $teamGeneralLabel,
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
    'detalle' => $detail,
    'ranking' => $ranking,
]);
