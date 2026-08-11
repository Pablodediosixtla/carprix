<?php
declare(strict_types=1);

function requirementAllowedStatuses(): array
{
    return ['Solicitado', 'Apartado', 'Vendido', 'Rechazado'];
}

function requirementNextStatus(string $currentStatus): ?string
{
    return match ($currentStatus) {
        'Solicitado' => 'Apartado',
        'Apartado' => 'Vendido',
        default => null,
    };
}

function validateRequirementTransition(string $currentStatus, string $requestedStatus): void
{
    $expected = requirementNextStatus($currentStatus);
    if ($expected === null || $requestedStatus !== $expected) {
        throw new DomainException('INVALID_STATUS_TRANSITION');
    }
}

function canManageCatalog(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO']);
}

function canCreateRequirement(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'VENTAS']);
}

function canViewAllRequirements(array $user): bool
{
    // SUPER_ADMIN y ADMIN_OPERATIVO conservan visibilidad global.
    // INVENTARIO y SOLO_LECTURA mantienen la consulta global definida para
    // esos perfiles. Un AUTORIZADOR/Supervisor NO obtiene acceso global:
    // su alcance se limita a él mismo y a toda su línea de subordinados.
    return hasAnyRole($user, [
        'SUPER_ADMIN',
        'ADMIN_OPERATIVO',
        'INVENTARIO',
        'SOLO_LECTURA',
    ]);
}

function canAuthorizeRequirements(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);
}

function resolveHierarchyApprover(mysqli $con, int $requesterId): ?int
{
    $sql = "SELECT j.supervisor_id
            FROM operativo_usuario_jerarquia j
            INNER JOIN operativo_usuario s
                ON s.id = j.supervisor_id
               AND s.estatus = 'Activo'
            WHERE j.usuario_id = ?
              AND j.activo = 1
            LIMIT 1";

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        databaseError($con);
    }
    $stmt->bind_param('i', $requesterId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['supervisor_id'] : null;
}

function addRequirementHistory(
    mysqli $con,
    int $requirementId,
    string $eventType,
    ?string $previousStatus,
    ?string $newStatus,
    string $detail,
    int $userId
): void {
    $stmt = $con->prepare(
        "INSERT INTO operativo_requerimiento_historial
            (requerimiento_id, tipo_evento, estatus_anterior, estatus_nuevo, detalle, usuario_id)
         VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)"
    );

    if (!$stmt) {
        databaseError($con);
    }

    $previous = $previousStatus ?? '';
    $next = $newStatus ?? '';
    $stmt->bind_param('issssi', $requirementId, $eventType, $previous, $next, $detail, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        databaseError($con);
    }
    $stmt->close();
}

function userHasActiveRole(mysqli $con, int $userId, array $roleCodes): bool
{
    if ($roleCodes === []) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
    $types = 'i' . str_repeat('s', count($roleCodes));
    $params = array_merge([$userId], $roleCodes);

    $stmt = $con->prepare(
        "SELECT 1
         FROM operativo_usuario_rol ur
         INNER JOIN operativo_rol r
             ON r.id = ur.rol_id
            AND r.activo = 1
         WHERE ur.usuario_id = ?
           AND ur.activo = 1
           AND r.codigo IN ({$placeholders})
         LIMIT 1"
    );

    bindDynamicParams($stmt, $types, $params);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $found;
}

function canManagePeople(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);
}

function canCreatePeople(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);
}

function canFullyManagePeople(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);
}

function personLevelsAllowed(array $user): array
{
    if (isSuperAdmin($user)) {
        return ['VENDEDOR', 'SUPERVISOR', 'RESPONSABLE_INVENTARIO', 'GERENTE_OPERACIONES'];
    }

    if (hasAnyRole($user, ['ADMIN_OPERATIVO'])) {
        return ['VENDEDOR', 'SUPERVISOR', 'RESPONSABLE_INVENTARIO'];
    }

    return [];
}

function personRoleCodes(string $level, bool $supervisorAlsoSells = false): array
{
    return match ($level) {
        'VENDEDOR' => ['VENTAS'],
        'SUPERVISOR' => $supervisorAlsoSells ? ['AUTORIZADOR', 'VENTAS'] : ['AUTORIZADOR'],
        'RESPONSABLE_INVENTARIO' => ['INVENTARIO'],
        'GERENTE_OPERACIONES' => ['ADMIN_OPERATIVO', 'AUTORIZADOR'],
        default => [],
    };
}

function operationalLevelFromRoles(array $roles): string
{
    if (in_array('SUPER_ADMIN', $roles, true)) {
        return 'SUPER_ADMIN';
    }
    if (in_array('ADMIN_OPERATIVO', $roles, true)) {
        return 'GERENTE_OPERACIONES';
    }
    if (in_array('AUTORIZADOR', $roles, true)) {
        return 'SUPERVISOR';
    }
    if (in_array('INVENTARIO', $roles, true)) {
        return 'RESPONSABLE_INVENTARIO';
    }
    if (in_array('VENTAS', $roles, true)) {
        return 'VENDEDOR';
    }
    return 'OTRO';
}

function normalizeBirthDate(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d') !== $raw) {
        errorResponse('La fecha de nacimiento no es válida.', 422, 'VALIDATION_ERROR', ['field' => 'fecha_nacimiento']);
    }

    $today = new DateTimeImmutable('today');
    if ($date > $today) {
        errorResponse('La fecha de nacimiento no puede ser futura.', 422, 'VALIDATION_ERROR', ['field' => 'fecha_nacimiento']);
    }

    if ($date < $today->modify('-100 years')) {
        errorResponse('Revisa la fecha de nacimiento capturada.', 422, 'VALIDATION_ERROR', ['field' => 'fecha_nacimiento']);
    }

    return $raw;
}

function hierarchyDescendantIds(mysqli $con, int $rootUserId): array
{
    $visited = [$rootUserId => true];
    $frontier = [$rootUserId];

    for ($depth = 0; $depth < 30 && $frontier !== []; $depth++) {
        $placeholders = implode(',', array_fill(0, count($frontier), '?'));
        $types = str_repeat('i', count($frontier));
        $params = array_values($frontier);

        $stmt = $con->prepare(
            "SELECT usuario_id
             FROM operativo_usuario_jerarquia
             WHERE activo = 1
               AND supervisor_id IN ({$placeholders})"
        );
        if (!$stmt) {
            databaseError($con);
        }

        bindDynamicParams($stmt, $types, $params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $next = [];
        foreach ($rows as $row) {
            $id = (int) $row['usuario_id'];
            if ($id > 0 && !isset($visited[$id])) {
                $visited[$id] = true;
                $next[] = $id;
            }
        }
        $frontier = $next;
    }

    return array_map('intval', array_keys($visited));
}


/**
 * Devuelve el alcance de usuarios cuyos requerimientos puede consultar el
 * usuario actual.
 *
 * - null: acceso global.
 * - Supervisor/AUTORIZADOR: usuario actual + todos sus descendientes.
 * - VENTAS u otro perfil sin acceso global: únicamente el usuario actual.
 *
 * Importante: deliberadamente NO agrega ancestros. Así un supervisor nunca
 * ve requerimientos de su manager o de niveles superiores.
 */
function requirementVisibleUserIds(mysqli $con, array $user): ?array
{
    if (canViewAllRequirements($user)) {
        return null;
    }

    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }

    if (hasAnyRole($user, ['AUTORIZADOR'])) {
        return hierarchyDescendantIds($con, $userId);
    }

    return [$userId];
}

function canViewRequirementRecord(
    mysqli $con,
    array $user,
    int $createdBy,
    int $assignedTo
): bool {
    $visibleIds = requirementVisibleUserIds($con, $user);
    if ($visibleIds === null) {
        return true;
    }

    return in_array($createdBy, $visibleIds, true)
        || in_array($assignedTo, $visibleIds, true);
}

function isSalesOnlyOperationalUser(array $user): bool
{
    $roles = array_values(array_unique(array_map(
        static fn(mixed $role): string => strtoupper(trim((string) $role)),
        is_array($user['roles'] ?? null) ? $user['roles'] : []
    )));

    if (!in_array('VENTAS', $roles, true)) {
        return false;
    }

    // Si además de VENTAS tiene cualquier otro rol, se considera ese otro
    // perfil operativo y debe ver todos los totalizadores.
    return count($roles) === 1;
}


function hierarchyAncestorIds(mysqli $con, int $userId): array
{
    $ids = [];
    $current = $userId;

    for ($depth = 0; $depth < 30; $depth++) {
        $stmt = $con->prepare(
            "SELECT supervisor_id
             FROM operativo_usuario_jerarquia
             WHERE usuario_id = ?
               AND activo = 1
             LIMIT 1"
        );
        if (!$stmt) {
            databaseError($con);
        }

        $stmt->bind_param('i', $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            break;
        }

        $supervisorId = (int) $row['supervisor_id'];
        if ($supervisorId <= 0 || in_array($supervisorId, $ids, true)) {
            break;
        }

        $ids[] = $supervisorId;
        $current = $supervisorId;
    }

    return $ids;
}

function hierarchyVisibleUserIds(mysqli $con, array $user): array
{
    if (hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO'])) {
        return [];
    }

    $userId = (int) $user['id'];
    $visible = [$userId];

    if (hasAnyRole($user, ['AUTORIZADOR'])) {
        $visible = array_merge($visible, hierarchyDescendantIds($con, $userId));
    }

    $visible = array_merge($visible, hierarchyAncestorIds($con, $userId));
    $visible = array_values(array_unique(array_map('intval', $visible)));

    return array_values(array_filter($visible, static fn(int $id): bool => $id > 0));
}

function targetHasProtectedAdminRole(array $target): bool
{
    return hasAnyRole($target, ['SUPER_ADMIN']);
}

function canFullyManageTargetUser(mysqli $con, array $currentUser, int $targetUserId): bool
{
    if (!canFullyManagePeople($currentUser)) {
        return false;
    }

    return fetchUserContext($con, $targetUserId) !== null;
}

function canSupervisorManageTargetUser(mysqli $con, array $currentUser, int $targetUserId): bool
{
    if (!hasAnyRole($currentUser, ['AUTORIZADOR']) || hasAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO'])) {
        return false;
    }

    $currentId = (int) $currentUser['id'];
    if ($targetUserId <= 0 || $targetUserId === $currentId) {
        return false;
    }

    if (!isHierarchyAncestorOf($con, $currentId, $targetUserId)) {
        return false;
    }

    $target = fetchUserContext($con, $targetUserId);
    if (!$target || hasAnyRole($target, ['SUPER_ADMIN', 'ADMIN_OPERATIVO'])) {
        return false;
    }

    return true;
}

function canManageTargetStatusOrPassword(mysqli $con, array $currentUser, int $targetUserId): bool
{
    return canFullyManageTargetUser($con, $currentUser, $targetUserId)
        || canSupervisorManageTargetUser($con, $currentUser, $targetUserId);
}

function assertHierarchyAssignmentValid(
    mysqli $con,
    int $employeeId,
    int $supervisorId
): void {
    if ($supervisorId <= 0) {
        return;
    }

    if ($employeeId === $supervisorId) {
        errorResponse('Un usuario no puede ser su propio supervisor.', 422, 'INVALID_HIERARCHY');
    }

    assertActiveApprover($con, $supervisorId);

    $current = $supervisorId;
    for ($depth = 0; $depth < 30; $depth++) {
        if ($current === $employeeId) {
            errorResponse('La relación generaría un ciclo jerárquico.', 422, 'HIERARCHY_CYCLE');
        }

        $stmt = $con->prepare(
            "SELECT supervisor_id
             FROM operativo_usuario_jerarquia
             WHERE usuario_id = ?
               AND activo = 1
             LIMIT 1"
        );
        if (!$stmt) {
            databaseError($con);
        }
        $stmt->bind_param('i', $current);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            break;
        }

        $current = (int) $row['supervisor_id'];
    }
}

function saveHierarchyAssignment(
    mysqli $con,
    int $employeeId,
    int $supervisorId,
    int $assignedBy
): void {
    if ($supervisorId <= 0) {
        $stmt = $con->prepare(
            "UPDATE operativo_usuario_jerarquia
             SET activo = 0,
                 asignado_por = ?,
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE usuario_id = ?"
        );
        if (!$stmt) {
            databaseError($con);
        }
        $stmt->bind_param('ii', $assignedBy, $employeeId);
        if (!$stmt->execute()) {
            $stmt->close();
            databaseError($con);
        }
        $stmt->close();
        return;
    }

    assertHierarchyAssignmentValid($con, $employeeId, $supervisorId);

    $stmt = $con->prepare(
        "INSERT INTO operativo_usuario_jerarquia
            (usuario_id, supervisor_id, activo, asignado_por)
         VALUES (?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE
            supervisor_id = VALUES(supervisor_id),
            activo = 1,
            asignado_por = VALUES(asignado_por),
            actualizado_en = CURRENT_TIMESTAMP"
    );
    if (!$stmt) {
        databaseError($con);
    }

    $stmt->bind_param('iii', $employeeId, $supervisorId, $assignedBy);
    if (!$stmt->execute()) {
        $stmt->close();
        databaseError($con);
    }
    $stmt->close();
}

function assertActiveApprover(mysqli $con, int $supervisorId): void
{
    $stmt = $con->prepare("SELECT id FROM operativo_usuario WHERE id = ? AND estatus = 'Activo' LIMIT 1");
    if (!$stmt) {
        databaseError($con);
    }
    $stmt->bind_param('i', $supervisorId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$exists) {
        errorResponse('El supervisor seleccionado no existe o está inactivo.', 422, 'SUPERVISOR_NOT_AVAILABLE');
    }

    if (!userHasActiveRole($con, $supervisorId, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR'])) {
        errorResponse('El supervisor debe tener rol AUTORIZADOR, ADMIN_OPERATIVO o SUPER_ADMIN.', 422, 'SUPERVISOR_ROLE_REQUIRED');
    }
}


function hasFullRequestApprovalAccess(array $user): bool
{
    return isSuperAdmin($user) || hasAnyRole($user, ['ADMIN_OPERATIVO']);
}

function currentDirectManagerId(mysqli $con, int $userId): ?int
{
    return resolveHierarchyApprover($con, $userId);
}

function isCurrentDirectManagerOf(mysqli $con, int $managerId, int $subordinateId): bool
{
    if ($managerId <= 0 || $subordinateId <= 0) {
        return false;
    }

    $stmt = $con->prepare(
        "SELECT 1
         FROM operativo_usuario_jerarquia
         WHERE usuario_id = ?
           AND supervisor_id = ?
           AND activo = 1
         LIMIT 1"
    );
    if (!$stmt) {
        databaseError($con);
    }
    $stmt->bind_param('ii', $subordinateId, $managerId);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $found;
}

function canResolveHierarchyRequest(mysqli $con, array $user, int $requesterId): bool
{
    if (hasFullRequestApprovalAccess($user)) {
        return true;
    }

    $currentUserId = (int) ($user['id'] ?? 0);
    if ($currentUserId <= 0 || $requesterId <= 0 || $currentUserId === $requesterId) {
        return false;
    }

    // Para supervisores normales, únicamente el manager DIRECTO vigente del
    // solicitante puede resolver. Ser ancestro indirecto no concede permiso.
    return isCurrentDirectManagerOf($con, $currentUserId, $requesterId);
}

function isHierarchyAncestorOf(mysqli $con, int $managerId, int $subordinateId): bool
{
    if ($managerId <= 0 || $subordinateId <= 0 || $managerId === $subordinateId) {
        return false;
    }

    $descendants = hierarchyDescendantIds($con, $managerId);
    return in_array($subordinateId, $descendants, true);
}

function taskAssignableUserIds(mysqli $con, array $user): array
{
    if (isSuperAdmin($user)) {
        $result = $con->query("SELECT id FROM operativo_usuario WHERE estatus = 'Activo' ORDER BY id");
        if (!$result) {
            databaseError($con);
        }
        return array_map('intval', array_column($result->fetch_all(MYSQLI_ASSOC), 'id'));
    }

    return hierarchyDescendantIds($con, (int) $user['id']);
}

function canAccessTask(mysqli $con, array $user, array $task): bool
{
    if (isSuperAdmin($user)) {
        return true;
    }

    $userId = (int) $user['id'];
    $creatorId = (int) ($task['creado_por'] ?? 0);
    $assigneeId = (int) ($task['asignado_a'] ?? 0);
    $approverId = (int) ($task['aprobador_id'] ?? 0);

    if (in_array($userId, [$creatorId, $assigneeId, $approverId], true)) {
        return true;
    }

    return isHierarchyAncestorOf($con, $userId, $assigneeId);
}

function canApproveTask(mysqli $con, array $user, array $task): bool
{
    if (isSuperAdmin($user)) {
        return true;
    }

    $assigneeId = (int) ($task['asignado_a'] ?? 0);
    return isCurrentDirectManagerOf($con, (int) $user['id'], $assigneeId);
}

function taskStatusValues(): array
{
    return ['Pendiente', 'En progreso', 'En revision', 'Completada', 'Cancelada'];
}

function addTaskHistory(
    mysqli $con,
    int $taskId,
    string $eventType,
    ?string $previousStatus,
    ?string $newStatus,
    string $detail,
    int $userId
): void {
    $stmt = $con->prepare(
        "INSERT INTO operativo_tarea_historial
            (tarea_id, tipo_evento, estatus_anterior, estatus_nuevo, detalle, usuario_id)
         VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)"
    );
    if (!$stmt) {
        databaseError($con);
    }

    $previous = $previousStatus ?? '';
    $next = $newStatus ?? '';
    $stmt->bind_param('issssi', $taskId, $eventType, $previous, $next, $detail, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        databaseError($con);
    }
    $stmt->close();
}


function canAuthorizeCatalogRequests(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);
}

function canViewAllCatalogRequests(array $user): bool
{
    return hasFullRequestApprovalAccess($user);
}

function createCatalogPublicationRequest(
    mysqli $con,
    int $autoId,
    int $requesterId,
    string $reason
): int {
    $pendingStmt = $con->prepare(
        "SELECT id
         FROM operativo_catalogo_requerimiento
         WHERE auto_id = ?
           AND decision = 'Pendiente'
         LIMIT 1"
    );
    if (!$pendingStmt) {
        databaseError($con);
    }
    $pendingStmt->bind_param('i', $autoId);
    $pendingStmt->execute();
    $pending = $pendingStmt->get_result()->fetch_assoc();
    $pendingStmt->close();

    if ($pending) {
        return (int) $pending['id'];
    }

    $approverId = resolveHierarchyApprover($con, $requesterId);
    $requesterHasFullAccess = userHasActiveRole($con, $requesterId, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);
    if (!$requesterHasFullAccess && $approverId === null) {
        throw new DomainException('HIERARCHY_NOT_CONFIGURED');
    }
    if ($approverId !== null && !userHasActiveRole(
        $con,
        $approverId,
        ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']
    )) {
        throw new DomainException('HIERARCHY_APPROVER_ROLE_REQUIRED');
    }
    $reason = cleanString($reason, 500);
    if ($reason === '') {
        $reason = 'Solicitud para publicar el auto en el catálogo.';
    }

    $insert = $con->prepare(
        "INSERT INTO operativo_catalogo_requerimiento
            (auto_id, tipo, estatus_origen, estatus_solicitado, motivo,
             solicitado_por, aprobador_id, decision)
         VALUES (?, 'PUBLICACION', 'Oculto', 'Disponible', ?, ?, NULLIF(?, 0), 'Pendiente')"
    );
    if (!$insert) {
        databaseError($con);
    }

    $approver = $approverId ?? 0;
    $insert->bind_param('isii', $autoId, $reason, $requesterId, $approver);
    if (!$insert->execute()) {
        $insert->close();
        databaseError($con);
    }

    $requestId = (int) $con->insert_id;
    $insert->close();
    return $requestId;
}
