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
