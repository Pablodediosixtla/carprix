<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);

$year = (int) ($input['anio'] ?? 0);
$month = (int) ($input['mes'] ?? 0);
$teamId = max(0, (int) ($input['equipo_id'] ?? 0));
$allocations = is_array($input['metas'] ?? null) ? $input['metas'] : [];
$currentYear = rewardsCurrentYear();

if ($year < 2020 || $year > $currentYear + 2) {
    $con->close();
    errorResponse('El año de la meta no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}
if ($month < 1 || $month > 12) {
    $con->close();
    errorResponse('El mes de reserva no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'mes']);
}

$resolved = commercialGoalResolveTeam($con, $user, $teamId);
$leaderId = (int) $resolved['lider_id'];
$people = commercialGoalEligiblePeople($con, $resolved['scope_ids'], $leaderId);
$eligibleIds = array_map(static fn(array $row): int => (int) $row['id'], $people);
$eligibleSet = array_fill_keys($eligibleIds, true);

if ($eligibleIds === []) {
    $con->close();
    errorResponse('El equipo no tiene personas comerciales elegibles.', 422, 'GOAL_TEAM_EMPTY');
}

$new = [];
foreach ($allocations as $item) {
    if (!is_array($item)) continue;
    $uid = (int) ($item['usuario_id'] ?? 0);
    if ($uid <= 0 || !isset($eligibleSet[$uid])) {
        $con->close();
        errorResponse('La distribución contiene una persona fuera del alcance permitido.', 403, 'GOAL_USER_FORBIDDEN');
    }
    $new[$uid] = [
        'reserva' => max(0, (int) ($item['meta_reserva'] ?? 0)),
        'venta' => max(0, (int) ($item['meta_venta'] ?? 0)),
    ];
}

foreach ($eligibleIds as $uid) {
    if (!isset($new[$uid])) {
        $new[$uid] = ['reserva' => 0, 'venta' => 0];
    }
}

$newReserve = array_sum(array_column($new, 'reserva'));
$newSale = array_sum(array_column($new, 'venta'));
$fullAccess = canSetCommercialGoalTotals($user);

if (!$fullAccess) {
    $placeholders = implode(',', array_fill(0, count($eligibleIds), '?'));
    $types = str_repeat('i', count($eligibleIds)) . 'ii';
    $params = array_merge($eligibleIds, [$year, $month]);
    $stmt = $con->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN tipo='RESERVA' AND mes=? THEN meta ELSE 0 END),0) AS reserva,
            COALESCE(SUM(CASE WHEN tipo='VENTA' AND mes=0 THEN meta ELSE 0 END),0) AS venta
         FROM operativo_meta_usuario
         WHERE usuario_id IN ({$placeholders}) AND anio = ?"
    );
    if (!$stmt) databaseError($con);
    // Rebuild params for placeholder order: month, ids..., year
    $types = 'i' . str_repeat('i', count($eligibleIds)) . 'i';
    $params = array_merge([$month], $eligibleIds, [$year]);
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc() ?: ['reserva' => 0, 'venta' => 0];
    $stmt->close();
    $currentReserve = (int) $current['reserva'];
    $currentSale = (int) $current['venta'];

    if ($newReserve !== $currentReserve || $newSale !== $currentSale) {
        $con->close();
        errorResponse(
            'Como supervisor puedes redistribuir las metas de tu equipo, pero no cambiar el total asignado por tu jerarquía superior.',
            422,
            'GOAL_TOTAL_LOCKED',
            [
                'meta_reserva_actual' => $currentReserve,
                'meta_venta_actual' => $currentSale,
                'meta_reserva_enviada' => $newReserve,
                'meta_venta_enviada' => $newSale,
            ]
        );
    }
}

$actorId = (int) $user['id'];
try {
    $con->begin_transaction();
    foreach ($eligibleIds as $uid) {
        commercialGoalSaveValue(
            $con, $uid, 'RESERVA', $year, $month, $new[$uid]['reserva'],
            $leaderId, 'AJUSTE_MANUAL', $actorId, 'AJUSTE_MANUAL',
            'Redistribución manual de meta de reserva.'
        );
        commercialGoalSaveValue(
            $con, $uid, 'VENTA', $year, 0, $new[$uid]['venta'],
            $leaderId, 'AJUSTE_MANUAL', $actorId, 'AJUSTE_MANUAL',
            'Redistribución manual de meta anual de venta.'
        );
    }
    $con->commit();
} catch (Throwable $e) {
    $con->rollback();
    $con->close();
    errorResponse('No fue posible guardar la distribución de metas.', 500, 'GOAL_DISTRIBUTION_ERROR');
}

$con->close();
okResponse([
    'meta_reserva_total' => $newReserve,
    'meta_venta_total' => $newSale,
], 'Distribución de metas guardada correctamente.');
