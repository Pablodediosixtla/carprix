<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'VENTAS']);

$autoId = positiveInt($input['auto_id'] ?? null, 'auto_id');
$clientName = requireString($input, 'cliente_nombre', 'nombre del cliente', 150);
$clientPhone = requireString($input, 'cliente_telefono', 'teléfono del cliente', 20);
$clientEmail = cleanString($input['cliente_email'] ?? '', 150);
$clientId = cleanString($input['cliente_identificacion'] ?? '', 100);
$amount = ($input['monto_propuesto'] ?? '') === '' ? null : (float) $input['monto_propuesto'];
$payment = cleanString($input['forma_pago'] ?? 'Contado', 30);
$comments = cleanString($input['comentarios'] ?? '', 3000);
$assignedTo = isset($input['asignado_a']) && (int) $input['asignado_a'] > 0
    ? (int) $input['asignado_a']
    : (int) $user['id'];

if ($clientEmail !== '') {
    validateEmailAddress($clientEmail);
}
if (!in_array($payment, ['Contado', 'Financiamiento', 'Otro'], true)) {
    $con->close();
    errorResponse('Forma de pago no válida.', 422, 'VALIDATION_ERROR');
}
if ($amount !== null && $amount <= 0) {
    $con->close();
    errorResponse('El monto propuesto debe ser mayor que cero.', 422, 'VALIDATION_ERROR');
}
if ($assignedTo !== (int) $user['id'] && !hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO'])) {
    $con->close();
    errorResponse('No puedes asignar el requerimiento a otro usuario.', 403, 'FORBIDDEN');
}

$autoStmt = $con->prepare('SELECT id, estatus FROM autos WHERE id = ? LIMIT 1');
$autoStmt->bind_param('i', $autoId);
$autoStmt->execute();
$auto = $autoStmt->get_result()->fetch_assoc();
$autoStmt->close();
if (!$auto) {
    $con->close();
    errorResponse('Auto no encontrado.', 404, 'AUTO_NOT_FOUND');
}
if ((string) $auto['estatus'] !== 'Disponible') {
    $con->close();
    errorResponse('El auto no se encuentra disponible para un nuevo requerimiento.', 409, 'AUTO_NOT_AVAILABLE');
}

$userStmt = $con->prepare("SELECT id FROM operativo_usuario WHERE id = ? AND estatus = 'Activo' LIMIT 1");
$userStmt->bind_param('i', $assignedTo);
$userStmt->execute();
$assignedExists = (bool) $userStmt->get_result()->fetch_row();
$userStmt->close();
if (!$assignedExists) {
    $con->close();
    errorResponse('El usuario asignado no existe o está inactivo.', 422, 'INVALID_ASSIGNEE');
}

$con->begin_transaction();
try {
    $temporaryFolio = 'TMP-' . bin2hex(random_bytes(10));
    $insert = $con->prepare(
        "INSERT INTO operativo_requerimiento_compra
            (folio, auto_id, cliente_nombre, cliente_telefono, cliente_email,
             cliente_identificacion, monto_propuesto, forma_pago, comentarios,
             estatus, creado_por, asignado_a)
         VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, NULLIF(?, ''),
                 'Solicitado', ?, ?)"
    );
    $insert->bind_param(
        'sissssdssii',
        $temporaryFolio,
        $autoId,
        $clientName,
        $clientPhone,
        $clientEmail,
        $clientId,
        $amount,
        $payment,
        $comments,
        $user['id'],
        $assignedTo
    );
    if (!$insert->execute()) {
        throw new RuntimeException($insert->error, $insert->errno);
    }
    $requirementId = (int) $con->insert_id;
    $insert->close();

    $folio = 'CPR-' . date('Ym') . '-' . str_pad((string) $requirementId, 6, '0', STR_PAD_LEFT);
    $folioStmt = $con->prepare('UPDATE operativo_requerimiento_compra SET folio = ? WHERE id = ?');
    $folioStmt->bind_param('si', $folio, $requirementId);
    $folioStmt->execute();
    $folioStmt->close();

    addRequirementHistory(
        $con,
        $requirementId,
        'CREACION',
        null,
        'Solicitado',
        'Requerimiento creado para el cliente ' . $clientName . '.',
        (int) $user['id']
    );

    $con->commit();
    $con->close();
    okResponse([
        'id' => $requirementId,
        'folio' => $folio,
        'estatus' => 'Solicitado',
    ], 'Requerimiento creado correctamente.', 201);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
