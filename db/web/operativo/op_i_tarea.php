<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$title = requireString($input, 'titulo', 'título', 150);
$description = cleanString($input['descripcion'] ?? '', 4000);
$priority = cleanString($input['prioridad'] ?? 'Media', 20);
$assigneeId = positiveInt($input['asignado_a'] ?? null, 'asignado_a');
$requiresApproval = filter_var($input['requiere_aprobacion'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$requiresApproval = $requiresApproval ?? true;

if (!in_array($priority, ['Baja', 'Media', 'Alta', 'Urgente'], true)) {
    $con->close();
    errorResponse('Prioridad no válida.', 422, 'VALIDATION_ERROR');
}

$parseDate = static function (mixed $value, string $field, bool $required = true): ?string {
    $raw = trim((string) $value);
    if ($raw === '') {
        if ($required) {
            errorResponse("El campo {$field} es obligatorio.", 422, 'VALIDATION_ERROR', ['field' => $field]);
        }
        return null;
    }
    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        errorResponse("El campo {$field} no contiene una fecha válida.", 422, 'VALIDATION_ERROR', ['field' => $field]);
    }
    return date('Y-m-d H:i:s', $timestamp);
};

$startDate = $parseDate($input['fecha_inicio'] ?? '', 'fecha_inicio', true);
$endDate = $parseDate($input['fecha_fin'] ?? '', 'fecha_fin', false);
if ($endDate !== null && strtotime($endDate) < strtotime((string) $startDate)) {
    $con->close();
    errorResponse('La fecha fin no puede ser anterior a la fecha inicio.', 422, 'VALIDATION_ERROR');
}

$allowedIds = taskAssignableUserIds($con, $user);
if (!in_array($assigneeId, $allowedIds, true)) {
    $con->close();
    errorResponse('Solo puedes asignar tareas a ti mismo o a usuarios de tu línea jerárquica descendente.', 403, 'TASK_ASSIGNMENT_FORBIDDEN');
}

$assigneeStmt = $con->prepare("SELECT id FROM operativo_usuario WHERE id = ? AND estatus = 'Activo' LIMIT 1");
if (!$assigneeStmt) {
    databaseError($con);
}
$assigneeStmt->bind_param('i', $assigneeId);
$assigneeStmt->execute();
$assigneeExists = (bool) $assigneeStmt->get_result()->fetch_row();
$assigneeStmt->close();
if (!$assigneeExists) {
    $con->close();
    errorResponse('El usuario asignado no existe o está inactivo.', 422, 'ASSIGNEE_NOT_AVAILABLE');
}

$approverId = null;
if ($requiresApproval) {
    $approverId = currentDirectManagerId($con, $assigneeId);

    // Un usuario superior sin manager puede crear tareas, pero una tarea
    // autoasignada en la cima de la jerarquía no tiene a quién escalarse.
    if ($approverId === null && $assigneeId === (int) $user['id']) {
        $requiresApproval = false;
    } elseif ($approverId === null) {
        // Caso excepcional de un usuario sin manager. Si el creador es un
        // superior válido dentro de la línea, él será el aprobador.
        if (isSuperAdmin($user) || isHierarchyAncestorOf($con, (int) $user['id'], $assigneeId)) {
            $approverId = (int) $user['id'];
        } else {
            $con->close();
            errorResponse('El usuario asignado no tiene un manager configurado para aprobar la tarea.', 409, 'TASK_APPROVER_NOT_CONFIGURED');
        }
    }
}

$folio = 'TSK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
$creatorId = (int) $user['id'];
$approvalFlag = $requiresApproval ? 1 : 0;

$con->begin_transaction();
try {
    $stmt = $con->prepare(
        "INSERT INTO operativo_tarea
            (folio, titulo, descripcion, prioridad, estatus, fecha_inicio, fecha_fin,
             creado_por, asignado_a, aprobador_id, requiere_aprobacion)
         VALUES (?, ?, NULLIF(?, ''), ?, 'Pendiente', ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException($con->error);
    }
    $stmt->bind_param(
        'ssssssiiii',
        $folio,
        $title,
        $description,
        $priority,
        $startDate,
        $endDate,
        $creatorId,
        $assigneeId,
        $approverId,
        $approvalFlag
    );
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error, $stmt->errno);
    }
    $taskId = (int) $con->insert_id;
    $stmt->close();

    addTaskHistory(
        $con,
        $taskId,
        'CREACION',
        null,
        'Pendiente',
        $assigneeId === $creatorId ? 'Tarea creada para el propio usuario.' : 'Tarea creada y asignada a un subordinado.',
        $creatorId
    );

    $con->commit();
    $con->close();
    okResponse([
        'id' => $taskId,
        'folio' => $folio,
        'estatus' => 'Pendiente',
        'aprobador_id' => $approverId,
        'requiere_aprobacion' => $requiresApproval,
    ], 'Tarea creada correctamente.', 201);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
