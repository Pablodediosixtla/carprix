<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$taskId = positiveInt($input['tarea_id'] ?? null, 'tarea_id');
$action = requireString($input, 'accion', 'acción', 30);
$comment = cleanString($input['comentario'] ?? '', 500);

if (!in_array($action, ['iniciar', 'completar', 'cancelar'], true)) {
    $con->close();
    errorResponse('Acción de tarea no válida.', 422, 'VALIDATION_ERROR');
}

$con->begin_transaction();
try {
    $stmt = $con->prepare('SELECT * FROM operativo_tarea WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException($con->error);
    }
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$task) {
        throw new DomainException('TASK_NOT_FOUND');
    }
    if (!canAccessTask($con, $user, $task)) {
        throw new DomainException('TASK_FORBIDDEN');
    }

    $userId = (int) $user['id'];
    $status = (string) $task['estatus'];
    $isAssignee = (int) $task['asignado_a'] === $userId;
    $isCreator = (int) $task['creado_por'] === $userId;

    if ($action === 'iniciar') {
        if (!$isAssignee) {
            throw new DomainException('TASK_ASSIGNEE_ONLY');
        }
        if ($status !== 'Pendiente') {
            throw new DomainException('TASK_STATUS_INVALID');
        }

        $update = $con->prepare("UPDATE operativo_tarea SET estatus = 'En progreso' WHERE id = ?");
        if (!$update) {
            throw new RuntimeException($con->error);
        }
        $update->bind_param('i', $taskId);
        if (!$update->execute()) {
            throw new RuntimeException($update->error, $update->errno);
        }
        $update->close();

        addTaskHistory($con, $taskId, 'ESTATUS', $status, 'En progreso', $comment !== '' ? $comment : 'Tarea iniciada.', $userId);
        $newStatus = 'En progreso';
        $message = 'Tarea iniciada correctamente.';
    } elseif ($action === 'completar') {
        if (!$isAssignee) {
            throw new DomainException('TASK_ASSIGNEE_ONLY');
        }
        if (!in_array($status, ['Pendiente', 'En progreso'], true)) {
            throw new DomainException('TASK_STATUS_INVALID');
        }

        $requiresApproval = (bool) $task['requiere_aprobacion'];
        $managerId = $requiresApproval ? currentDirectManagerId($con, (int) $task['asignado_a']) : null;
        if ($requiresApproval && $managerId === null) {
            $storedApprover = $task['aprobador_id'] !== null ? (int) $task['aprobador_id'] : 0;
            $managerId = $storedApprover > 0 ? $storedApprover : null;
        }

        if ($requiresApproval && $managerId !== null) {
            $pendingStmt = $con->prepare("SELECT id FROM operativo_tarea_aprobacion WHERE tarea_id = ? AND decision = 'Pendiente' LIMIT 1");
            if (!$pendingStmt) {
                throw new RuntimeException($con->error);
            }
            $pendingStmt->bind_param('i', $taskId);
            $pendingStmt->execute();
            $hasPending = (bool) $pendingStmt->get_result()->fetch_row();
            $pendingStmt->close();
            if ($hasPending) {
                throw new DomainException('TASK_APPROVAL_PENDING');
            }

            $insertApproval = $con->prepare(
                "INSERT INTO operativo_tarea_aprobacion
                    (tarea_id, solicitado_por, aprobador_id, decision)
                 VALUES (?, ?, ?, 'Pendiente')"
            );
            if (!$insertApproval) {
                throw new RuntimeException($con->error);
            }
            $insertApproval->bind_param('iii', $taskId, $userId, $managerId);
            if (!$insertApproval->execute()) {
                throw new RuntimeException($insertApproval->error, $insertApproval->errno);
            }
            $insertApproval->close();

            $update = $con->prepare("UPDATE operativo_tarea SET estatus = 'En revision', aprobador_id = ? WHERE id = ?");
            if (!$update) {
                throw new RuntimeException($con->error);
            }
            $update->bind_param('ii', $managerId, $taskId);
            if (!$update->execute()) {
                throw new RuntimeException($update->error, $update->errno);
            }
            $update->close();

            addTaskHistory($con, $taskId, 'SOLICITUD_APROBACION', $status, 'En revision', $comment !== '' ? $comment : 'Tarea enviada a revisión del manager.', $userId);
            $newStatus = 'En revision';
            $message = 'Tarea enviada a revisión de tu manager.';
        } else {
            $update = $con->prepare("UPDATE operativo_tarea SET estatus = 'Completada', fecha_completada = NOW() WHERE id = ?");
            if (!$update) {
                throw new RuntimeException($con->error);
            }
            $update->bind_param('i', $taskId);
            if (!$update->execute()) {
                throw new RuntimeException($update->error, $update->errno);
            }
            $update->close();

            addTaskHistory($con, $taskId, 'ESTATUS', $status, 'Completada', $comment !== '' ? $comment : 'Tarea completada.', $userId);
            $newStatus = 'Completada';
            $message = 'Tarea completada correctamente.';
        }
    } else {
        if (!$isCreator && !isSuperAdmin($user)) {
            throw new DomainException('TASK_CREATOR_ONLY');
        }
        if (in_array($status, ['Completada', 'Cancelada'], true)) {
            throw new DomainException('TASK_STATUS_INVALID');
        }
        if ($comment === '') {
            throw new DomainException('TASK_CANCEL_COMMENT_REQUIRED');
        }

        $updateApproval = $con->prepare("UPDATE operativo_tarea_aprobacion SET decision = 'Cancelado', comentario = ?, fecha_decision = NOW() WHERE tarea_id = ? AND decision = 'Pendiente'");
        if (!$updateApproval) {
            throw new RuntimeException($con->error);
        }
        $updateApproval->bind_param('si', $comment, $taskId);
        if (!$updateApproval->execute()) {
            throw new RuntimeException($updateApproval->error, $updateApproval->errno);
        }
        $updateApproval->close();

        $update = $con->prepare("UPDATE operativo_tarea SET estatus = 'Cancelada' WHERE id = ?");
        if (!$update) {
            throw new RuntimeException($con->error);
        }
        $update->bind_param('i', $taskId);
        if (!$update->execute()) {
            throw new RuntimeException($update->error, $update->errno);
        }
        $update->close();

        addTaskHistory($con, $taskId, 'CANCELACION', $status, 'Cancelada', $comment, $userId);
        $newStatus = 'Cancelada';
        $message = 'Tarea cancelada.';
    }

    $con->commit();
    $con->close();
    okResponse(['estatus' => $newStatus], $message);
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'TASK_NOT_FOUND' => errorResponse('Tarea no encontrada.', 404, $code),
        'TASK_FORBIDDEN' => errorResponse('No tienes acceso a esta tarea.', 403, $code),
        'TASK_ASSIGNEE_ONLY' => errorResponse('Solo la persona asignada puede ejecutar esta acción.', 403, $code),
        'TASK_CREATOR_ONLY' => errorResponse('Solo quien creó la tarea puede cancelarla.', 403, $code),
        'TASK_STATUS_INVALID' => errorResponse('El estatus actual de la tarea no permite esta acción.', 409, $code),
        'TASK_APPROVAL_PENDING' => errorResponse('La tarea ya tiene una aprobación pendiente.', 409, $code),
        'TASK_CANCEL_COMMENT_REQUIRED' => errorResponse('Debes indicar el motivo de cancelación.', 422, $code),
        default => errorResponse('No fue posible actualizar la tarea.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
