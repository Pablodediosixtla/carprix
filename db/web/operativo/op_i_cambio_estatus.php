<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'VENTAS']);

$requirementId = positiveInt($input['requerimiento_id'] ?? null, 'requerimiento_id');
$requestedStatus = requireString($input, 'estatus_solicitado', 'estatus solicitado', 30);
$reason = requireString($input, 'motivo', 'motivo', 500);

$con->begin_transaction();
try {
    $stmt = $con->prepare(
        'SELECT id, auto_id, estatus, creado_por, asignado_a
         FROM operativo_requerimiento_compra
         WHERE id = ?
         FOR UPDATE'
    );
    $stmt->bind_param('i', $requirementId);
    $stmt->execute();
    $requirement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$requirement) {
        throw new DomainException('REQUIREMENT_NOT_FOUND');
    }
    if (!canViewAllRequirements($user)
        && (int) $requirement['creado_por'] !== (int) $user['id']
        && (int) $requirement['asignado_a'] !== (int) $user['id']) {
        throw new DomainException('FORBIDDEN');
    }

    $currentStatus = (string) $requirement['estatus'];
    validateRequirementTransition($currentStatus, $requestedStatus);

    $pendingStmt = $con->prepare(
        "SELECT id FROM operativo_requerimiento_cambio
         WHERE requerimiento_id = ? AND decision = 'Pendiente'
         LIMIT 1"
    );
    $pendingStmt->bind_param('i', $requirementId);
    $pendingStmt->execute();
    $hasPending = (bool) $pendingStmt->get_result()->fetch_row();
    $pendingStmt->close();
    if ($hasPending) {
        throw new DomainException('PENDING_CHANGE_EXISTS');
    }

    if ($requestedStatus === 'Apartado') {
        $conflictStmt = $con->prepare(
            "SELECT id FROM operativo_requerimiento_compra
             WHERE auto_id = ? AND estatus = 'Apartado' AND id <> ?
             LIMIT 1"
        );
        $conflictAutoId = (int) $requirement['auto_id'];
        $conflictStmt->bind_param('ii', $conflictAutoId, $requirementId);
        $conflictStmt->execute();
        $conflict = (bool) $conflictStmt->get_result()->fetch_row();
        $conflictStmt->close();
        if ($conflict) {
            throw new DomainException('AUTO_ALREADY_RESERVED');
        }
    }

    $approverId = resolveHierarchyApprover($con, (int) $user['id']);
    if ($approverId === null && !isSuperAdmin($user)) {
        throw new DomainException('HIERARCHY_NOT_CONFIGURED');
    }

    $insert = $con->prepare(
        "INSERT INTO operativo_requerimiento_cambio
            (requerimiento_id, estatus_origen, estatus_solicitado, motivo,
             solicitado_por, aprobador_id, decision)
         VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')"
    );
    $requesterId = (int) $user['id'];
    $insert->bind_param(
        'isssii',
        $requirementId,
        $currentStatus,
        $requestedStatus,
        $reason,
        $requesterId,
        $approverId
    );
    $insert->execute();
    $changeId = (int) $con->insert_id;
    $insert->close();

    addRequirementHistory(
        $con,
        $requirementId,
        'SOLICITUD_CAMBIO',
        $currentStatus,
        $requestedStatus,
        $reason,
        (int) $user['id']
    );

    $con->commit();
    $con->close();
    okResponse([
        'solicitud_cambio_id' => $changeId,
        'aprobador_id' => $approverId,
    ], 'Solicitud de cambio enviada para autorización.', 201);
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'REQUIREMENT_NOT_FOUND' => errorResponse('Requerimiento no encontrado.', 404, $code),
        'FORBIDDEN' => errorResponse('No tienes acceso a este requerimiento.', 403, $code),
        'PENDING_CHANGE_EXISTS' => errorResponse('Ya existe una solicitud de cambio pendiente.', 409, $code),
        'AUTO_ALREADY_RESERVED' => errorResponse('El auto ya se encuentra apartado en otro requerimiento.', 409, $code),
        'HIERARCHY_NOT_CONFIGURED' => errorResponse('No tienes un supervisor configurado para autorizar el cambio.', 409, $code),
        'INVALID_STATUS_TRANSITION' => errorResponse('La transición de estatus solicitada no está permitida.', 422, $code),
        default => errorResponse('No fue posible solicitar el cambio.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
