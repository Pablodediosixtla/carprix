<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
$taskId = positiveInt($input['tarea_id'] ?? null, 'tarea_id');

$stmt = $con->prepare(
    "SELECT
        t.*,
        CONCAT_WS(' ', cu.nombre, cu.apellido_paterno, cu.apellido_materno) AS creado_por_nombre,
        CONCAT_WS(' ', au.nombre, au.apellido_paterno, au.apellido_materno) AS asignado_a_nombre,
        CONCAT_WS(' ', ap.nombre, ap.apellido_paterno, ap.apellido_materno) AS aprobador_nombre
     FROM operativo_tarea t
     INNER JOIN operativo_usuario cu ON cu.id = t.creado_por
     INNER JOIN operativo_usuario au ON au.id = t.asignado_a
     LEFT JOIN operativo_usuario ap ON ap.id = t.aprobador_id
     WHERE t.id = ?
     LIMIT 1"
);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('i', $taskId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    $con->close();
    errorResponse('Tarea no encontrada.', 404, 'TASK_NOT_FOUND');
}
if (!canAccessTask($con, $user, $task)) {
    $con->close();
    errorResponse('No tienes acceso a esta tarea.', 403, 'TASK_FORBIDDEN');
}

$commentStmt = $con->prepare(
    "SELECT
        tc.id, tc.comentario, tc.creado_en, tc.usuario_id,
        CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS usuario_nombre
     FROM operativo_tarea_comentario tc
     INNER JOIN operativo_usuario u ON u.id = tc.usuario_id
     WHERE tc.tarea_id = ?
     ORDER BY tc.creado_en ASC, tc.id ASC"
);
if (!$commentStmt) {
    databaseError($con);
}
$commentStmt->bind_param('i', $taskId);
$commentStmt->execute();
$comments = $commentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$commentStmt->close();

$approvalStmt = $con->prepare(
    "SELECT
        ta.id, ta.decision, ta.comentario, ta.fecha_solicitud, ta.fecha_decision,
        ta.solicitado_por, ta.aprobador_id,
        CONCAT_WS(' ', su.nombre, su.apellido_paterno, su.apellido_materno) AS solicitado_por_nombre,
        CONCAT_WS(' ', ap.nombre, ap.apellido_paterno, ap.apellido_materno) AS aprobador_nombre
     FROM operativo_tarea_aprobacion ta
     INNER JOIN operativo_usuario su ON su.id = ta.solicitado_por
     INNER JOIN operativo_usuario ap ON ap.id = ta.aprobador_id
     WHERE ta.tarea_id = ?
     ORDER BY ta.fecha_solicitud DESC, ta.id DESC"
);
if (!$approvalStmt) {
    databaseError($con);
}
$approvalStmt->bind_param('i', $taskId);
$approvalStmt->execute();
$approvals = $approvalStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$approvalStmt->close();

$historyStmt = $con->prepare(
    "SELECT
        h.id, h.tipo_evento, h.estatus_anterior, h.estatus_nuevo,
        h.detalle, h.creado_en, h.usuario_id,
        CONCAT_WS(' ', u.nombre, u.apellido_paterno, u.apellido_materno) AS usuario_nombre
     FROM operativo_tarea_historial h
     INNER JOIN operativo_usuario u ON u.id = h.usuario_id
     WHERE h.tarea_id = ?
     ORDER BY h.creado_en DESC, h.id DESC
     LIMIT 100"
);
if (!$historyStmt) {
    databaseError($con);
}
$historyStmt->bind_param('i', $taskId);
$historyStmt->execute();
$history = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$historyStmt->close();

$task['id'] = (int) $task['id'];
$task['creado_por'] = (int) $task['creado_por'];
$task['asignado_a'] = (int) $task['asignado_a'];
$task['aprobador_id'] = $task['aprobador_id'] !== null ? (int) $task['aprobador_id'] : null;
$task['requiere_aprobacion'] = (bool) $task['requiere_aprobacion'];

$pendingApprovalId = null;
foreach ($approvals as &$approval) {
    $approval['id'] = (int) $approval['id'];
    $approval['solicitado_por'] = (int) $approval['solicitado_por'];
    $approval['aprobador_id'] = (int) $approval['aprobador_id'];
    if ($approval['decision'] === 'Pendiente' && $pendingApprovalId === null) {
        $pendingApprovalId = $approval['id'];
    }
}
unset($approval);
foreach ($comments as &$comment) {
    $comment['id'] = (int) $comment['id'];
    $comment['usuario_id'] = (int) $comment['usuario_id'];
}
unset($comment);
foreach ($history as &$event) {
    $event['id'] = (int) $event['id'];
    $event['usuario_id'] = (int) $event['usuario_id'];
}
unset($event);

$status = (string) $task['estatus'];
$userId = (int) $user['id'];
$task['permisos'] = [
    'puede_iniciar' => $task['asignado_a'] === $userId && $status === 'Pendiente',
    'puede_completar' => $task['asignado_a'] === $userId && in_array($status, ['Pendiente', 'En progreso'], true),
    'puede_cancelar' => ($task['creado_por'] === $userId || isSuperAdmin($user)) && !in_array($status, ['Completada', 'Cancelada'], true),
    'puede_aprobar' => $status === 'En revision' && $pendingApprovalId !== null && canApproveTask($con, $user, $task),
    'puede_comentar' => true,
];
$task['aprobacion_pendiente_id'] = $pendingApprovalId;

$con->close();
okResponse([
    'tarea' => $task,
    'comentarios' => $comments,
    'aprobaciones' => $approvals,
    'historial' => $history,
]);
