<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$employeeId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$supervisorId = (int) ($input['supervisor_id'] ?? 0);

if ($employeeId === (int) $user['id'] && $supervisorId === 0 && !isSuperAdmin($user)) {
    $con->close();
    errorResponse('No puedes retirar tu propia jerarquía.', 403, 'FORBIDDEN');
}

$employeeStmt = $con->prepare("SELECT id FROM operativo_usuario WHERE id = ? AND estatus = 'Activo' LIMIT 1");
$employeeStmt->bind_param('i', $employeeId);
$employeeStmt->execute();
$employeeExists = (bool) $employeeStmt->get_result()->fetch_row();
$employeeStmt->close();
if (!$employeeExists) {
    $con->close();
    errorResponse('El trabajador no existe o está inactivo.', 404, 'USER_NOT_FOUND');
}

if ($supervisorId <= 0) {
    $stmt = $con->prepare('UPDATE operativo_usuario_jerarquia SET activo = 0, asignado_por = ? WHERE usuario_id = ?');
    $currentUserId = (int) $user['id'];
    $stmt->bind_param('ii', $currentUserId, $employeeId);
    $stmt->execute();
    $stmt->close();
    $con->close();
    okResponse([], 'Jerarquía desactivada correctamente.');
}

if ($employeeId === $supervisorId) {
    $con->close();
    errorResponse('Un usuario no puede ser su propio supervisor.', 422, 'INVALID_HIERARCHY');
}

$supervisorStmt = $con->prepare("SELECT id FROM operativo_usuario WHERE id = ? AND estatus = 'Activo' LIMIT 1");
$supervisorStmt->bind_param('i', $supervisorId);
$supervisorStmt->execute();
$supervisorExists = (bool) $supervisorStmt->get_result()->fetch_row();
$supervisorStmt->close();
if (!$supervisorExists) {
    $con->close();
    errorResponse('El supervisor no existe o está inactivo.', 404, 'SUPERVISOR_NOT_FOUND');
}

if (!userHasActiveRole($con, $supervisorId, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'])) {
    $con->close();
    errorResponse('El supervisor debe tener rol AUTORIZADOR, ADMIN_OPERATIVO o SUPER_ADMIN.', 422, 'SUPERVISOR_ROLE_REQUIRED');
}

$current = $supervisorId;
for ($depth = 0; $depth < 30; $depth++) {
    if ($current === $employeeId) {
        $con->close();
        errorResponse('La relación generaría un ciclo jerárquico.', 422, 'HIERARCHY_CYCLE');
    }
    $chainStmt = $con->prepare(
        'SELECT supervisor_id FROM operativo_usuario_jerarquia
         WHERE usuario_id = ? AND activo = 1 LIMIT 1'
    );
    $chainStmt->bind_param('i', $current);
    $chainStmt->execute();
    $next = $chainStmt->get_result()->fetch_assoc();
    $chainStmt->close();
    if (!$next) {
        break;
    }
    $current = (int) $next['supervisor_id'];
}

$sql = "INSERT INTO operativo_usuario_jerarquia
            (usuario_id, supervisor_id, activo, asignado_por)
        VALUES (?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            supervisor_id = VALUES(supervisor_id),
            activo = 1,
            asignado_por = VALUES(asignado_por),
            actualizado_en = CURRENT_TIMESTAMP";
$stmt = $con->prepare($sql);
$currentUserId = (int) $user['id'];
$stmt->bind_param('iii', $employeeId, $supervisorId, $currentUserId);
$stmt->execute();
$stmt->close();
$con->close();

okResponse([], 'Jerarquía actualizada correctamente.');
