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
            c.*,
            r.auto_id,
            r.estatus AS estatus_actual,
            a.estatus AS auto_estatus
         FROM operativo_requerimiento_cambio c
         INNER JOIN operativo_requerimiento_compra r ON r.id = c.requerimiento_id
         INNER JOIN autos a ON a.id = r.auto_id
         WHERE c.id = ?
         FOR UPDATE"
    );
    if (!$stmt) {
        databaseError($con);
    }
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

    // La autorización no depende del aprobador_id guardado: se valida contra
    // la jerarquía VIGENTE. Solo el manager directo puede resolverla, salvo
    // SUPER_ADMIN y ADMIN_OPERATIVO, que tienen acceso full.
    if (!canResolveHierarchyRequest($con, $user, (int) $change['solicitado_por'])) {
        throw new DomainException('FORBIDDEN');
    }
    if ((int) $change['solicitado_por'] === (int) $user['id'] && !hasFullRequestApprovalAccess($user)) {
        throw new DomainException('SELF_APPROVAL_NOT_ALLOWED');
    }

    $currentStatus = (string) $change['estatus_actual'];
    $requestedStatus = (string) $change['estatus_solicitado'];
    $autoStatus = (string) $change['auto_estatus'];

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
            if (!$conflictStmt) {
                databaseError($con);
            }
            $conflictAutoId = (int) $change['auto_id'];
            $conflictRequirementId = (int) $change['requerimiento_id'];
            $conflictStmt->bind_param('ii', $conflictAutoId, $conflictRequirementId);
            $conflictStmt->execute();
            $conflict = (bool) $conflictStmt->get_result()->fetch_row();
            $conflictStmt->close();
            if ($conflict) {
                throw new DomainException('AUTO_ALREADY_RESERVED');
            }

            if (in_array($autoStatus, ['Vendido', 'Oculto'], true)) {
                throw new DomainException('AUTO_STATUS_CHANGED');
            }
        }

        if ($requestedStatus === 'Vendido' && $autoStatus === 'Oculto') {
            throw new DomainException('AUTO_STATUS_CHANGED');
        }

        $dateColumn = $requestedStatus === 'Apartado'
            ? 'fecha_apartado = NOW(),'
            : ($requestedStatus === 'Vendido' ? 'fecha_venta = NOW(),' : '');
        $updateRequirement = $con->prepare(
            "UPDATE operativo_requerimiento_compra
             SET estatus = ?, {$dateColumn} fecha_actualizacion = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        if (!$updateRequirement) {
            databaseError($con);
        }
        $requirementId = (int) $change['requerimiento_id'];
        $updateRequirement->bind_param('si', $requestedStatus, $requirementId);
        if (!$updateRequirement->execute()) {
            $updateRequirement->close();
            databaseError($con);
        }
        $updateRequirement->close();

        if (in_array($requestedStatus, ['Apartado', 'Vendido'], true)) {
            $updateAuto = $con->prepare('UPDATE autos SET estatus = ? WHERE id = ?');
            if (!$updateAuto) {
                databaseError($con);
            }
            $autoId = (int) $change['auto_id'];
            $updateAuto->bind_param('si', $requestedStatus, $autoId);
            if (!$updateAuto->execute()) {
                $updateAuto->close();
                databaseError($con);
            }
            $updateAuto->close();
        }

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
        // Cuando se rechaza la primera transición comercial
        // Solicitado -> Apartado, el requerimiento completo queda Rechazado.
        // Esto evita que vuelva a mostrarse como Solicitado y que el usuario
        // pueda enviar nuevamente la misma solicitud de apartado.
        $rejectionFinalStatus = $currentStatus;

        if ($currentStatus === 'Solicitado' && $requestedStatus === 'Apartado') {
            $updateRejectedRequirement = $con->prepare(
                "UPDATE operativo_requerimiento_compra
                 SET estatus = 'Rechazado',
                     fecha_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            if (!$updateRejectedRequirement) {
                databaseError($con);
            }
            $rejectedRequirementId = (int) $change['requerimiento_id'];
            $updateRejectedRequirement->bind_param('i', $rejectedRequirementId);
            if (!$updateRejectedRequirement->execute()) {
                $updateRejectedRequirement->close();
                databaseError($con);
            }
            $updateRejectedRequirement->close();
            $rejectionFinalStatus = 'Rechazado';
        }

        addRequirementHistory(
            $con,
            (int) $change['requerimiento_id'],
            'RECHAZO',
            $currentStatus,
            $rejectionFinalStatus,
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
    if (!$updateChange) {
        databaseError($con);
    }
    $approverUserId = (int) $user['id'];
    $updateChange->bind_param('ssii', $decision, $comment, $approverUserId, $changeId);
    if (!$updateChange->execute()) {
        $updateChange->close();
        databaseError($con);
    }
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
        'FORBIDDEN' => errorResponse('Solo el manager directo del solicitante puede autorizar esta solicitud.', 403, $code),
        'SELF_APPROVAL_NOT_ALLOWED' => errorResponse('No puedes autorizar tu propia solicitud.', 403, $code),
        'REQUIREMENT_STATUS_CHANGED' => errorResponse('El estatus del requerimiento cambió antes de la autorización.', 409, $code),
        'AUTO_ALREADY_RESERVED' => errorResponse('El auto ya fue apartado en otro requerimiento.', 409, $code),
        'AUTO_STATUS_CHANGED' => errorResponse('El estatus del auto cambió y ya no permite completar esta autorización.', 409, $code),
        'INVALID_STATUS_TRANSITION' => errorResponse('La transición de estatus solicitada no está permitida.', 422, $code),
        default => errorResponse('No fue posible resolver la autorización.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
