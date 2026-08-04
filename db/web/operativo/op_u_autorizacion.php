<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);

$changeId = positiveInt($input['solicitud_cambio_id'] ?? null, 'solicitud_cambio_id');
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
    $stmt = $con->prepare(
        "SELECT
            c.*, r.auto_id, r.estatus AS estatus_actual
         FROM operativo_requerimiento_cambio c
         INNER JOIN operativo_requerimiento_compra r ON r.id = c.requerimiento_id
         WHERE c.id = ?
         FOR UPDATE"
    );
    $stmt->bind_param('i', $changeId);
    $stmt->execute();
    $change = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$change) {
        throw new DomainException('CHANGE_NOT_FOUND');
    }
    if ((string) $change['decision'] !== 'Pendiente') {
        throw new DomainException('CHANGE_ALREADY_RESOLVED');
    }

    $isPrivileged = isSuperAdmin($user) || hasAnyRole($user, ['ADMIN_OPERATIVO']);
    if (!$isPrivileged && (int) $change['aprobador_id'] !== (int) $user['id']) {
        throw new DomainException('FORBIDDEN');
    }
    if ((int) $change['solicitado_por'] === (int) $user['id'] && !isSuperAdmin($user)) {
        throw new DomainException('SELF_APPROVAL_NOT_ALLOWED');
    }

    $currentStatus = (string) $change['estatus_actual'];
    $requestedStatus = (string) $change['estatus_solicitado'];

    if ($decision === 'Aprobado') {
        if ($currentStatus !== (string) $change['estatus_origen']) {
            throw new DomainException('REQUIREMENT_STATUS_CHANGED');
        }
        validateRequirementTransition($currentStatus, $requestedStatus);

        if ($requestedStatus === 'Apartado') {
            $conflictStmt = $con->prepare(
                "SELECT id FROM operativo_requerimiento_compra
                 WHERE auto_id = ?
                   AND estatus = 'Apartado'
                   AND id <> ?
                 LIMIT 1"
            );
            $conflictAutoId = (int) $change['auto_id'];
            $conflictRequirementId = (int) $change['requerimiento_id'];
            $conflictStmt->bind_param('ii', $conflictAutoId, $conflictRequirementId);
            $conflictStmt->execute();
            $conflict = (bool) $conflictStmt->get_result()->fetch_row();
            $conflictStmt->close();
            if ($conflict) {
                throw new DomainException('AUTO_ALREADY_RESERVED');
            }
        }

        $dateColumn = $requestedStatus === 'Apartado'
            ? 'fecha_apartado = NOW(),'
            : ($requestedStatus === 'Vendido' ? 'fecha_venta = NOW(),' : '');
        $updateRequirement = $con->prepare(
            "UPDATE operativo_requerimiento_compra
             SET estatus = ?, {$dateColumn} fecha_actualizacion = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $requirementId = (int) $change['requerimiento_id'];
        $updateRequirement->bind_param('si', $requestedStatus, $requirementId);
        $updateRequirement->execute();
        $updateRequirement->close();

        addRequirementHistory(
            $con,
            (int) $change['requerimiento_id'],
            'APROBACION',
            $currentStatus,
            $requestedStatus,
            $comment !== '' ? $comment : 'Cambio autorizado.',
            (int) $user['id']
        );
    } else {
        addRequirementHistory(
            $con,
            (int) $change['requerimiento_id'],
            'RECHAZO',
            $currentStatus,
            $requestedStatus,
            $comment,
            (int) $user['id']
        );
    }

    $updateChange = $con->prepare(
        "UPDATE operativo_requerimiento_cambio
         SET decision = ?, comentario_decision = NULLIF(?, ''),
             aprobador_id = ?, fecha_decision = NOW()
         WHERE id = ?"
    );
    $approverUserId = (int) $user['id'];
    $updateChange->bind_param('ssii', $decision, $comment, $approverUserId, $changeId);
    $updateChange->execute();
    $updateChange->close();

    $con->commit();
    $con->close();
    okResponse([], $decision === 'Aprobado'
        ? 'Cambio de estatus autorizado correctamente.'
        : 'Solicitud de cambio rechazada.');
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'CHANGE_NOT_FOUND' => errorResponse('Solicitud de cambio no encontrada.', 404, $code),
        'CHANGE_ALREADY_RESOLVED' => errorResponse('La solicitud ya fue resuelta.', 409, $code),
        'FORBIDDEN' => errorResponse('No eres el autorizador asignado.', 403, $code),
        'SELF_APPROVAL_NOT_ALLOWED' => errorResponse('No puedes autorizar tu propia solicitud.', 403, $code),
        'REQUIREMENT_STATUS_CHANGED' => errorResponse('El estatus del requerimiento cambió antes de la autorización.', 409, $code),
        'AUTO_ALREADY_RESERVED' => errorResponse('El auto ya fue apartado en otro requerimiento.', 409, $code),
        'INVALID_STATUS_TRANSITION' => errorResponse('La transición de estatus solicitada no está permitida.', 422, $code),
        default => errorResponse('No fue posible resolver la autorización.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
