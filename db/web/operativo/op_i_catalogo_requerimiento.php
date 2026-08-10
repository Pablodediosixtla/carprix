<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO']);

$autoId = positiveInt($input['auto_id'] ?? null, 'auto_id');
$reason = cleanString($input['motivo'] ?? '', 500);
if ($reason === '') {
    $reason = 'Se solicita publicar el auto en el catálogo.';
}

$con->begin_transaction();
try {
    $autoStmt = $con->prepare('SELECT id, estatus FROM autos WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$autoStmt) {
        throw new RuntimeException($con->error);
    }
    $autoStmt->bind_param('i', $autoId);
    $autoStmt->execute();
    $auto = $autoStmt->get_result()->fetch_assoc();
    $autoStmt->close();

    if (!$auto) {
        throw new DomainException('AUTO_NOT_FOUND');
    }
    if ((string) $auto['estatus'] !== 'Oculto') {
        throw new DomainException('AUTO_NOT_HIDDEN');
    }

    $requestId = createCatalogPublicationRequest(
        $con,
        $autoId,
        (int) $user['id'],
        $reason
    );

    $con->commit();
    $con->close();
    okResponse([
        'id' => $requestId,
        'auto_id' => $autoId,
        'decision' => 'Pendiente',
    ], 'Requerimiento de publicación generado correctamente.', 201);
} catch (DomainException $e) {
    $con->rollback();
    $code = $e->getMessage();
    $con->close();
    match ($code) {
        'AUTO_NOT_FOUND' => errorResponse('Auto no encontrado.', 404, $code),
        'AUTO_NOT_HIDDEN' => errorResponse('Solo los autos ocultos pueden solicitar publicación.', 409, $code),
        default => errorResponse('No fue posible generar el requerimiento.', 400, $code),
    };
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
