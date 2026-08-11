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
    if (!$stmt) {
        databaseError($con);
    }
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
    if (!$pendingStmt) {
        databaseError($con);
    }
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
        if (!$conflictStmt) {
            databaseError($con);
        }
        $conflictAutoId = (int) $requirement['auto_id'];
        $conflictStmt->bind_param('ii', $conflictAutoId, $requirementId);
        $conflictStmt->execute();
        $conflict = (bool) $conflictStmt->get_result()->fetch_row();
        $conflictStmt->close();
        if ($conflict) {
            throw new DomainException('AUTO_ALREADY_RESERVED');
        }
    }

    // La autorización se asigna al manager DIRECTO del solicitante.
    // SUPER_ADMIN y ADMIN_OPERATIVO pueden generar solicitudes sin manager,
    // ya que ellos tienen acceso full para resolver requerimientos.
    $approverId = resolveHierarchyApprover($con, (int) $user['id']);
    if ($approverId === null && !hasFullRequestApprovalAccess($user)) {
        throw new DomainException('HIERARCHY_NOT_CONFIGURED');
    }
    if ($approverId !== null && !userHasActiveRole(
        $con,
        $approverId,
        ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']
    )) {
        throw new DomainException('HIERARCHY_APPROVER_ROLE_REQUIRED');
    }

    $insert = $con->prepare(
        "INSERT INTO operativo_requerimiento_cambio
            (requerimiento_id, estatus_origen, estatus_solicitado, motivo,
             solicitado_por, aprobador_id, decision)
         VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')"
    );
    if (!$insert) {
        databaseError($con);
    }
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
    if (!$insert->execute()) {
        $insert->close();
        databaseError($con);
    }
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
        'HIERARCHY_NOT_CONFIGURED' => errorResponse('No tienes un manager directo configurado para autorizar el cambio.', 409, $code),
        'HIERARCHY_APPROVER_ROLE_REQUIRED' => errorResponse('Tu manager directo no cuenta con permisos de autorización.', 409, $code),
        'INVALID_STATUS_TRANSITION' => errorResponse('La transición de estatus solicitada no está permitida.', 422, $code),
        default => errorResponse('No fue posible solicitar el cambio.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
