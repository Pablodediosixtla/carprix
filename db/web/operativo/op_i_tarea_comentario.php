<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$taskId = positiveInt($input['tarea_id'] ?? null, 'tarea_id');
$comment = requireString($input, 'comentario', 'comentario', 2000);

$stmt = $con->prepare('SELECT id, creado_por, asignado_a, aprobador_id FROM operativo_tarea WHERE id = ? LIMIT 1');
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('i', $taskId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    $con->close();
    errorResponse('Tarea no encontrada.', 404, 'TASK_NOT_FOUND');
}
if (!canAccessTask($con, $user, $task)) {
    $con->close();
    errorResponse('No tienes acceso a esta tarea.', 403, 'TASK_FORBIDDEN');
}

$con->begin_transaction();
try {
    $insert = $con->prepare('INSERT INTO operativo_tarea_comentario (tarea_id, usuario_id, comentario) VALUES (?, ?, ?)');
    if (!$insert) {
        throw new RuntimeException($con->error);
    }
    $userId = (int) $user['id'];
    $insert->bind_param('iis', $taskId, $userId, $comment);
    if (!$insert->execute()) {
        throw new RuntimeException($insert->error, $insert->errno);
    }
    $commentId = (int) $con->insert_id;
    $insert->close();

    addTaskHistory($con, $taskId, 'COMENTARIO', null, null, mb_substr($comment, 0, 500, 'UTF-8'), $userId);

    $con->commit();
    $con->close();
    okResponse(['id' => $commentId], 'Comentario agregado.', 201);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
