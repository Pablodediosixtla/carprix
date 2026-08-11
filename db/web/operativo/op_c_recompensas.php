<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);

$currentYear = rewardsCurrentYear();
$year = isset($input['anio']) ? (int) $input['anio'] : $currentYear;
if ($year < 2020 || $year > $currentYear) {
    $con->close();
    errorResponse('El año consultado no es válido.', 422, 'VALIDATION_ERROR');
}

$userId = (int) $user['id'];

$yearsStmt = $con->prepare(
    "SELECT DISTINCT anio FROM operativo_recompensa_movimiento WHERE usuario_id = ? ORDER BY anio DESC"
);
if (!$yearsStmt) {
    databaseError($con);
}
$yearsStmt->bind_param('i', $userId);
$yearsStmt->execute();
$availableYears = array_map('intval', array_column($yearsStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'anio'));
$yearsStmt->close();
if (!in_array($currentYear, $availableYears, true)) {
    array_unshift($availableYears, $currentYear);
}
$availableYears = array_values(array_unique($availableYears));
rsort($availableYears, SORT_NUMERIC);

$summaryStmt = $con->prepare(
    "SELECT
        COALESCE(SUM(puntos_aplicados), 0) AS saldo,
        COALESCE(SUM(CASE WHEN puntos_aplicados > 0 THEN puntos_aplicados ELSE 0 END), 0) AS ganados,
        COALESCE(SUM(CASE WHEN puntos_aplicados < 0 THEN ABS(puntos_aplicados) ELSE 0 END), 0) AS descontados,
        COUNT(*) AS movimientos
     FROM operativo_recompensa_movimiento
     WHERE usuario_id = ? AND anio = ?"
);
if (!$summaryStmt) {
    databaseError($con);
}
$summaryStmt->bind_param('ii', $userId, $year);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];
$summaryStmt->close();

$movementsStmt = $con->prepare(
    "SELECT
        m.id, m.puntos_aplicados, m.origen, m.referencia_tipo, m.referencia_id,
        m.comentario, m.creado_en,
        rc.codigo, rc.nombre AS recompensa_nombre,
        cat.nombre AS categoria_nombre, cat.tipo AS categoria_tipo,
        CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) AS asignado_por_nombre
     FROM operativo_recompensa_movimiento m
     INNER JOIN operativo_recompensa_catalogo rc ON rc.id = m.catalogo_id
     INNER JOIN operativo_recompensa_categoria cat ON cat.id = rc.categoria_id
     INNER JOIN operativo_usuario a ON a.id = m.asignado_por
     WHERE m.usuario_id = ? AND m.anio = ?
     ORDER BY m.creado_en DESC, m.id DESC
     LIMIT 100"
);
if (!$movementsStmt) {
    databaseError($con);
}
$movementsStmt->bind_param('ii', $userId, $year);
$movementsStmt->execute();
$movements = $movementsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$movementsStmt->close();

$prizeResult = $con->query(
    "SELECT id, nombre, descripcion, puntos_requeridos, orden
     FROM operativo_recompensa_premio
     WHERE activo = 1
     ORDER BY puntos_requeridos ASC, orden ASC, id ASC"
);
if (!$prizeResult) {
    databaseError($con);
}
$prizes = $prizeResult->fetch_all(MYSQLI_ASSOC);
$balance = (int) ($summary['saldo'] ?? 0);
foreach ($prizes as &$prize) {
    $required = (int) $prize['puntos_requeridos'];
    $prize['id'] = (int) $prize['id'];
    $prize['puntos_requeridos'] = $required;
    $prize['alcanzado'] = $balance >= $required;
    $prize['faltantes'] = max(0, $required - $balance);
    $prize['progreso'] = $required > 0 ? min(100, max(0, round(($balance / $required) * 100, 1))) : 100;
}
unset($prize);

foreach ($movements as &$movement) {
    $movement['id'] = (int) $movement['id'];
    $movement['puntos_aplicados'] = (int) $movement['puntos_aplicados'];
    $movement['referencia_id'] = $movement['referencia_id'] !== null ? (int) $movement['referencia_id'] : null;
}
unset($movement);

$assignableIds = rewardAssignableUserIds($con, $user);
$con->close();

okResponse([
    'anio' => $year,
    'anio_actual' => $currentYear,
    'anios_disponibles' => $availableYears,
    'resumen' => [
        'saldo' => $balance,
        'ganados' => (int) ($summary['ganados'] ?? 0),
        'descontados' => (int) ($summary['descontados'] ?? 0),
        'movimientos' => (int) ($summary['movimientos'] ?? 0),
    ],
    'premios' => $prizes,
    'movimientos' => $movements,
    'puede_asignar' => $assignableIds !== [],
    'puede_gestionar' => canManageRewardsCatalog($user),
]);
