<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$taskId = positiveInt($input['tarea_id'] ?? null, 'tarea_id');
$decision = requireString($input, 'decision', 'decisión', 20);
$comment = cleanString($input['comentario'] ?? '', 500);

if (!in_array($decision, ['Aprobado', 'Rechazado'], true)) {
    $con->close();
    errorResponse('La decisión debe ser Aprobado o Rechazado.', 422, 'VALIDATION_ERROR');
}
if ($decision === 'Rechazado' && $comment === '') {
    $con->close();
    errorResponse('Debes indicar el motivo del rechazo.', 422, 'VALIDATION_ERROR');
}

$con->begin_transaction();
try {
    $taskStmt = $con->prepare('SELECT * FROM operativo_tarea WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$taskStmt) {
        throw new RuntimeException($con->error);
    }
    $taskStmt->bind_param('i', $taskId);
    $taskStmt->execute();
    $task = $taskStmt->get_result()->fetch_assoc();
    $taskStmt->close();

    if (!$task) {
        throw new DomainException('TASK_NOT_FOUND');
    }
    if ((string) $task['estatus'] !== 'En revision') {
        throw new DomainException('TASK_NOT_IN_REVIEW');
    }
    if (!canApproveTask($con, $user, $task)) {
        throw new DomainException('TASK_APPROVAL_FORBIDDEN');
    }

    $approvalStmt = $con->prepare(
        "SELECT * FROM operativo_tarea_aprobacion
         WHERE tarea_id = ? AND decision = 'Pendiente'
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE"
    );
    if (!$approvalStmt) {
        throw new RuntimeException($con->error);
    }
    $approvalStmt->bind_param('i', $taskId);
    $approvalStmt->execute();
    $approval = $approvalStmt->get_result()->fetch_assoc();
    $approvalStmt->close();
    if (!$approval) {
        throw new DomainException('TASK_APPROVAL_NOT_FOUND');
    }

    $newStatus = $decision === 'Aprobado' ? 'Completada' : 'En progreso';
    $userId = (int) $user['id'];

    $updateApproval = $con->prepare(
        "UPDATE operativo_tarea_aprobacion
         SET decision = ?, comentario = NULLIF(?, ''), aprobador_id = ?, fecha_decision = NOW()
         WHERE id = ?"
    );
    if (!$updateApproval) {
        throw new RuntimeException($con->error);
    }
    $approvalId = (int) $approval['id'];
    $updateApproval->bind_param('ssii', $decision, $comment, $userId, $approvalId);
    if (!$updateApproval->execute()) {
        throw new RuntimeException($updateApproval->error, $updateApproval->errno);
    }
    $updateApproval->close();

    if ($decision === 'Aprobado') {
        $updateTask = $con->prepare("UPDATE operativo_tarea SET estatus = 'Completada', aprobador_id = ?, fecha_completada = NOW() WHERE id = ?");
    } else {
        $updateTask = $con->prepare("UPDATE operativo_tarea SET estatus = 'En progreso', aprobador_id = ? WHERE id = ?");
    }
    if (!$updateTask) {
        throw new RuntimeException($con->error);
    }
    $updateTask->bind_param('ii', $userId, $taskId);
    if (!$updateTask->execute()) {
        throw new RuntimeException($updateTask->error, $updateTask->errno);
    }
    $updateTask->close();

    addTaskHistory(
        $con,
        $taskId,
        $decision === 'Aprobado' ? 'APROBACION' : 'RECHAZO',
        'En revision',
        $newStatus,
        $comment !== '' ? $comment : ($decision === 'Aprobado' ? 'Tarea aprobada.' : 'Tarea rechazada.'),
        $userId
    );

    $con->commit();
    $con->close();
    okResponse(['estatus' => $newStatus], $decision === 'Aprobado' ? 'Tarea aprobada y completada.' : 'Tarea rechazada y regresada a En progreso.');
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'TASK_NOT_FOUND' => errorResponse('Tarea no encontrada.', 404, $code),
        'TASK_NOT_IN_REVIEW' => errorResponse('La tarea ya no se encuentra en revisión.', 409, $code),
        'TASK_APPROVAL_FORBIDDEN' => errorResponse('Solo el manager directo de la persona asignada puede aprobar esta tarea.', 403, $code),
        'TASK_APPROVAL_NOT_FOUND' => errorResponse('No existe una aprobación pendiente para esta tarea.', 409, $code),
        default => errorResponse('No fue posible resolver la aprobación.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
