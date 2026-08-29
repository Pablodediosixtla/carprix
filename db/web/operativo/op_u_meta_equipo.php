<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$type = strtoupper(cleanString($input['tipo'] ?? '', 12));
$year = (int) ($input['anio'] ?? 0);
$month = (int) ($input['mes'] ?? 0);
$teamId = max(0, (int) ($input['equipo_id'] ?? 0));
$total = max(0, (int) ($input['meta_total'] ?? 0));

$currentYear = rewardsCurrentYear();
if (!in_array($type, ['RESERVA', 'VENTA'], true)) {
    $con->close();
    errorResponse('Tipo de meta no válido.', 422, 'VALIDATION_ERROR', ['field' => 'tipo']);
}
if ($year < 2020 || $year > $currentYear + 2) {
    $con->close();
    errorResponse('El año de la meta no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}
if ($type === 'RESERVA' && ($month < 1 || $month > 12)) {
    $con->close();
    errorResponse('El mes de reserva no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'mes']);
}
if ($type === 'VENTA') {
    $month = 0;
}

$resolved = commercialGoalResolveTeam($con, $user, $teamId);
$leaderId = (int) $resolved['lider_id'];
$people = commercialGoalEligiblePeople($con, $resolved['scope_ids'], $leaderId);
if ($people === []) {
    $con->close();
    errorResponse('El equipo seleccionado no tiene personas comerciales elegibles para prorratear la meta.', 422, 'GOAL_TEAM_EMPTY');
}

$count = count($people);
$base = intdiv($total, $count);
$remainder = $total % $count;
$actorId = (int) $user['id'];

try {
    $con->begin_transaction();
    foreach ($people as $index => $person) {
        $value = $base + ($index < $remainder ? 1 : 0);
        commercialGoalSaveValue(
            $con,
            (int) $person['id'],
            $type,
            $year,
            $month,
            $value,
            $leaderId,
            'PRORRATEO',
            $actorId,
            'PRORRATEO_EQUIPO',
            'Prorrateo automático de meta del equipo.'
        );
    }
    $con->commit();
} catch (Throwable $e) {
    $con->rollback();
    $con->close();
    errorResponse('No fue posible prorratear la meta del equipo.', 500, 'GOAL_PRORATE_ERROR');
}

$con->close();
okResponse([
    'tipo' => $type,
    'anio' => $year,
    'mes' => $month,
    'meta_total' => $total,
    'personas' => $count,
], 'Meta prorrateada correctamente.');
