<?php
declare(strict_types=1);

function requirementAllowedStatuses(): array
{
    return ['Solicitado', 'Apartado', 'Vendido'];
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
    return hasAnyRole($user, [
        'SUPER_ADMIN',
        'ADMIN_OPERATIVO',
        'AUTORIZADOR',
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

function personLevelsAllowed(array $user): array
{
    if (isSuperAdmin($user)) {
        return ['VENDEDOR', 'SUPERVISOR', 'GERENTE_OPERACIONES'];
    }

    if (hasAnyRole($user, ['ADMIN_OPERATIVO'])) {
        return ['VENDEDOR', 'SUPERVISOR'];
    }

    if (hasAnyRole($user, ['AUTORIZADOR'])) {
        return ['VENDEDOR'];
    }

    return [];
}

function personRoleCodes(string $level, bool $supervisorAlsoSells = false): array
{
    return match ($level) {
        'VENDEDOR' => ['VENTAS'],
        'SUPERVISOR' => $supervisorAlsoSells ? ['AUTORIZADOR', 'VENTAS'] : ['AUTORIZADOR'],
        'GERENTE_OPERACIONES' => ['ADMIN_OPERATIVO', 'AUTORIZADOR'],
        default => [],
    };
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


function canAuthorizeCatalogRequests(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']);
}

function canViewAllCatalogRequests(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);
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
    if ($approverId !== null && !userHasActiveRole(
        $con,
        $approverId,
        ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'AUTORIZADOR']
    )) {
        $approverId = null;
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
