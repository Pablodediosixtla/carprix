<?php
declare(strict_types=1);

function hasAnyRole(array $user, array $allowedRoles): bool
{
    $userRoles = array_map('strtoupper', $user['roles'] ?? []);
    $allowed = array_map('strtoupper', $allowedRoles);
    return count(array_intersect($userRoles, $allowed)) > 0;
}

function requireAnyRole(array $user, array $allowedRoles): void
{
    if (!hasAnyRole($user, $allowedRoles)) {
        errorResponse('No tienes permisos para realizar esta operación.', 403, 'FORBIDDEN');
    }
}

function isSuperAdmin(array $user): bool
{
    return hasAnyRole($user, ['SUPER_ADMIN']);
}
