<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$premioId = positiveInt($input['premio_id'] ?? null, 'premio_id');
$usuarioId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$currentYear = rewardsCurrentYear();
$year = (int) ($input['anio'] ?? $currentYear);
$comment = cleanString($input['comentario'] ?? '', 700);
$actorId = (int) $user['id'];

if ($year < 2020 || $year > $currentYear) {
    $con->close();
    errorResponse('El año solicitado no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'anio']);
}

$prizeStmt = $con->prepare(
    "SELECT id, nombre, puntos_requeridos
     FROM operativo_recompensa_premio
     WHERE id = ?
     LIMIT 1"
);
if (!$prizeStmt) {
    databaseError($con);
}
$prizeStmt->bind_param('i', $premioId);
$prizeStmt->execute();
$prize = $prizeStmt->get_result()->fetch_assoc();
$prizeStmt->close();

if (!$prize) {
    $con->close();
    errorResponse('Premio no encontrado.', 404, 'PRIZE_NOT_FOUND');
}

$userStmt = $con->prepare(
    "SELECT id, nombre, apellido_paterno, estatus
     FROM operativo_usuario
     WHERE id = ?
     LIMIT 1"
);
if (!$userStmt) {
    databaseError($con);
}
$userStmt->bind_param('i', $usuarioId);
$userStmt->execute();
$target = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$target || (string) $target['estatus'] !== 'Activo') {
    $con->close();
    errorResponse('El usuario no existe o no se encuentra activo.', 404, 'USER_NOT_AVAILABLE');
}

$pointsStmt = $con->prepare(
    "SELECT COALESCE(SUM(puntos_aplicados), 0) AS puntos
     FROM operativo_recompensa_movimiento
     WHERE usuario_id = ?
       AND anio = ?"
);
if (!$pointsStmt) {
    databaseError($con);
}
$pointsStmt->bind_param('ii', $usuarioId, $year);
$pointsStmt->execute();
$pointsRow = $pointsStmt->get_result()->fetch_assoc();
$pointsStmt->close();

$balance = (int) ($pointsRow['puntos'] ?? 0);
$required = (int) $prize['puntos_requeridos'];

if ($balance < $required) {
    $con->close();
    errorResponse(
        'La persona todavía no alcanza los puntos requeridos para este premio.',
        422,
        'PRIZE_NOT_EARNED',
        ['puntos' => $balance, 'puntos_requeridos' => $required]
    );
}

$stmt = $con->prepare(
    "INSERT INTO operativo_recompensa_premio_otorgado
        (premio_id, usuario_id, anio, puntos_al_otorgar, otorgado_por, comentario)
     VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))"
);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('iiiiis', $premioId, $usuarioId, $year, $balance, $actorId, $comment);

if (!$stmt->execute()) {
    $errno = $stmt->errno;
    $stmt->close();
    $con->close();

    if ($errno === 1062) {
        errorResponse('Este premio ya fue otorgado a la persona durante el año seleccionado.', 409, 'PRIZE_ALREADY_AWARDED');
    }
    errorResponse('No fue posible registrar la entrega del premio.', 500, 'PRIZE_AWARD_ERROR');
}

$awardId = (int) $stmt->insert_id;
$stmt->close();
$con->close();

okResponse([
    'otorgamiento_id' => $awardId,
    'premio_id' => $premioId,
    'usuario_id' => $usuarioId,
    'anio' => $year,
    'puntos_al_otorgar' => $balance,
], 'Premio marcado como otorgado correctamente.', 201);
