<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$targetId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$catalogId = positiveInt($input['catalogo_id'] ?? null, 'catalogo_id');
$comment = cleanString($input['comentario'] ?? '', 700);

if (!canGrantRewardToUser($con, $user, $targetId)) {
    $con->close();
    errorResponse('Solo puedes asignar recompensas a personas de tu línea subordinada. No puedes asignártelas a ti mismo.', 403, 'REWARD_TARGET_FORBIDDEN');
}

$stmt = $con->prepare(
    "SELECT rc.id, rc.nombre, rc.puntos, cat.tipo
     FROM operativo_recompensa_catalogo rc
     INNER JOIN operativo_recompensa_categoria cat
        ON cat.id = rc.categoria_id
       AND cat.activo = 1
     WHERE rc.id = ?
       AND rc.activo = 1
       AND rc.permite_asignacion_manual = 1
       AND rc.origen = 'MANUAL'
     LIMIT 1"
);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('i', $catalogId);
$stmt->execute();
$rule = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rule) {
    $con->close();
    errorResponse('La recompensa seleccionada ya no está disponible.', 404, 'REWARD_NOT_AVAILABLE');
}

$points = rewardSignedPoints((string) $rule['tipo'], (int) $rule['puntos']);
if ((string) $rule['tipo'] === 'RESTA' && $comment === '') {
    $con->close();
    errorResponse('Debes indicar el motivo cuando se descuentan puntos.', 422, 'REWARD_COMMENT_REQUIRED');
}
if ($points === 0) {
    $con->close();
    errorResponse('La recompensa seleccionada tiene valor de cero puntos.', 422, 'REWARD_ZERO_POINTS');
}

$year = rewardsCurrentYear();
$actorId = (int) $user['id'];
$origin = 'MANUAL';
$insert = $con->prepare(
    "INSERT INTO operativo_recompensa_movimiento
        (usuario_id, catalogo_id, anio, puntos_aplicados, origen, asignado_por, comentario)
     VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''))"
);
if (!$insert) {
    databaseError($con);
}
$insert->bind_param('iiiisis', $targetId, $catalogId, $year, $points, $origin, $actorId, $comment);
if (!$insert->execute()) {
    $insert->close();
    databaseError($con);
}
$movementId = (int) $insert->insert_id;
$insert->close();
$con->close();

okResponse([
    'movimiento_id' => $movementId,
    'puntos_aplicados' => $points,
], $points > 0 ? 'Recompensa asignada correctamente.' : 'Descuento de puntos registrado correctamente.', 201);
