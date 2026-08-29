<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);

$currentYear = rewardsCurrentYear();
$year = isset($input['anio']) ? (int) $input['anio'] : $currentYear;
$month = isset($input['mes']) ? (int) $input['mes'] : (int) date('n');
$teamId = max(0, (int) ($input['equipo_id'] ?? 0));

if ($year < 2020 || $year > $currentYear + 2) {
    $con->close();
    errorResponse('El año de la meta no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}
if ($month < 1 || $month > 12) {
    $con->close();
    errorResponse('El mes de la meta no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'mes']);
}

$teams = commercialGoalTeamOptions($con, $user);
$resolved = commercialGoalResolveTeam($con, $user, $teamId);
$teamId = (int) $resolved['equipo_id'];
$leaderId = (int) $resolved['lider_id'];
$people = commercialGoalEligiblePeople($con, $resolved['scope_ids'], $leaderId);
$ids = array_map(static fn(array $row): int => (int) $row['id'], $people);

$goalMap = [];
if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids)) . 'i';
    $params = array_merge($ids, [$year]);
    $stmt = $con->prepare(
        "SELECT usuario_id, tipo, mes, meta
         FROM operativo_meta_usuario
         WHERE usuario_id IN ({$placeholders})
           AND anio = ?
           AND ((tipo = 'RESERVA' AND mes = ?) OR (tipo = 'VENTA' AND mes = 0))"
    );
    if (!$stmt) databaseError($con);
    $types .= 'i';
    $params[] = $month;
    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $uid = (int) $row['usuario_id'];
        $goalMap[$uid] ??= ['reserva' => 0, 'venta' => 0];
        if ((string) $row['tipo'] === 'RESERVA') {
            $goalMap[$uid]['reserva'] = (int) $row['meta'];
        } else {
            $goalMap[$uid]['venta'] = (int) $row['meta'];
        }
    }
}

$totalReserve = 0;
$totalSale = 0;
foreach ($people as &$person) {
    $uid = (int) $person['id'];
    $person['meta_reserva'] = (int) ($goalMap[$uid]['reserva'] ?? 0);
    $person['meta_venta'] = (int) ($goalMap[$uid]['venta'] ?? 0);
    $totalReserve += $person['meta_reserva'];
    $totalSale += $person['meta_venta'];
}
unset($person);

$years = range($currentYear - 2, $currentYear + 1);
rsort($years, SORT_NUMERIC);
$canSetTotal = canSetCommercialGoalTotals($user);

$con->close();
okResponse([
    'anio' => $year,
    'mes' => $month,
    'anios' => $years,
    'equipos' => $teams,
    'equipo_seleccionado' => $teamId,
    'equipo_etiqueta' => $resolved['etiqueta'],
    'personas' => $people,
    'totales' => [
        'reserva' => $totalReserve,
        'venta' => $totalSale,
    ],
    'permisos' => [
        'cambiar_total' => $canSetTotal,
        'redistribuir' => true,
        'total_bloqueado' => !$canSetTotal,
    ],
], 'Metas cargadas correctamente.');
