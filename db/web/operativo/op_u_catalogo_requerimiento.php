<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);

$requestId = positiveInt($input['requerimiento_id'] ?? null, 'requerimiento_id');
$decision = cleanString($input['decision'] ?? '', 30);
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
        "SELECT cr.*, a.estatus AS auto_estatus
         FROM operativo_catalogo_requerimiento cr
         INNER JOIN autos a ON a.id = cr.auto_id
         WHERE cr.id = ?
         LIMIT 1
         FOR UPDATE"
    );
    if (!$stmt) {
        throw new RuntimeException($con->error);
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        throw new DomainException('CATALOG_REQUEST_NOT_FOUND');
    }
    if ((string) $request['decision'] !== 'Pendiente') {
        throw new DomainException('CATALOG_REQUEST_ALREADY_RESOLVED');
    }

    $isPrivileged = isSuperAdmin($user) || hasAnyRole($user, ['ADMIN_OPERATIVO']);
    $assignedApprover = $request['aprobador_id'] !== null ? (int) $request['aprobador_id'] : null;
    if (!$isPrivileged && $assignedApprover !== (int) $user['id']) {
        throw new DomainException('FORBIDDEN');
    }
    if ((int) $request['solicitado_por'] === (int) $user['id'] && !isSuperAdmin($user)) {
        throw new DomainException('SELF_APPROVAL_NOT_ALLOWED');
    }

    if ($decision === 'Aprobado') {
        if ((string) $request['auto_estatus'] !== 'Oculto') {
            throw new DomainException('AUTO_STATUS_CHANGED');
        }
        $updateAuto = $con->prepare("UPDATE autos SET estatus = 'Disponible' WHERE id = ?");
        if (!$updateAuto) {
            throw new RuntimeException($con->error);
        }
        $autoId = (int) $request['auto_id'];
        $updateAuto->bind_param('i', $autoId);
        if (!$updateAuto->execute()) {
            throw new RuntimeException($updateAuto->error, $updateAuto->errno);
        }
        $updateAuto->close();
    }

    $update = $con->prepare(
        "UPDATE operativo_catalogo_requerimiento
         SET decision = ?,
             comentario_decision = NULLIF(?, ''),
             aprobador_id = ?,
             fecha_decision = NOW()
         WHERE id = ?"
    );
    if (!$update) {
        throw new RuntimeException($con->error);
    }
    $approverId = (int) $user['id'];
    $update->bind_param('ssii', $decision, $comment, $approverId, $requestId);
    if (!$update->execute()) {
        throw new RuntimeException($update->error, $update->errno);
    }
    $update->close();

    $con->commit();
    $con->close();
    okResponse([], $decision === 'Aprobado'
        ? 'Publicación del auto autorizada correctamente.'
        : 'Requerimiento de publicación rechazado.');
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'CATALOG_REQUEST_NOT_FOUND' => errorResponse('Requerimiento de catálogo no encontrado.', 404, $code),
        'CATALOG_REQUEST_ALREADY_RESOLVED' => errorResponse('El requerimiento ya fue resuelto.', 409, $code),
        'FORBIDDEN' => errorResponse('No eres el autorizador asignado para este requerimiento.', 403, $code),
        'SELF_APPROVAL_NOT_ALLOWED' => errorResponse('No puedes autorizar tu propia solicitud.', 403, $code),
        'AUTO_STATUS_CHANGED' => errorResponse('El estatus del auto cambió antes de la autorización.', 409, $code),
        default => errorResponse('No fue posible resolver el requerimiento.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
