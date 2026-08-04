<?php
declare(strict_types=1);

function fetchUserContext(mysqli $con, int $userId): ?array
{
    $sql = "SELECT
                u.id,
                u.username,
                u.email,
                u.nombre,
                u.apellido_paterno,
                u.apellido_materno,
                u.telefono,
                u.estatus,
                u.debe_cambiar_password,
                u.ultimo_login_at,
                GROUP_CONCAT(DISTINCT r.codigo ORDER BY r.codigo SEPARATOR ',') AS roles
            FROM operativo_usuario u
            LEFT JOIN operativo_usuario_rol ur
                ON ur.usuario_id = u.id
               AND ur.activo = 1
            LEFT JOIN operativo_rol r
                ON r.id = ur.rol_id
               AND r.activo = 1
            WHERE u.id = ?
            GROUP BY
                u.id, u.username, u.email, u.nombre,
                u.apellido_paterno, u.apellido_materno, u.telefono,
                u.estatus, u.debe_cambiar_password, u.ultimo_login_at";

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        errorResponse('No fue posible validar la sesión.', 500, 'SESSION_QUERY_ERROR');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $roles = trim((string) ($row['roles'] ?? ''));
    $row['id'] = (int) $row['id'];
    $row['debe_cambiar_password'] = (bool) $row['debe_cambiar_password'];
    $row['roles'] = $roles === '' ? [] : explode(',', $roles);
    unset($row['roles_csv']);

    return $row;
}

function requireAuthenticated(mysqli $con, bool $allowPendingPasswordChange = false): array
{
    enforceSessionIdleTimeout();
    $sessionUser = currentSessionUser();

    if (!$sessionUser || empty($sessionUser['id'])) {
        errorResponse('Debes iniciar sesión.', 401, 'UNAUTHENTICATED');
    }

    $user = fetchUserContext($con, (int) $sessionUser['id']);

    if (!$user) {
        destroyOperativoSession();
        errorResponse('La sesión ya no es válida.', 401, 'SESSION_INVALID');
    }

    if ($user['estatus'] !== 'Activo') {
        destroyOperativoSession();
        errorResponse('El usuario no se encuentra activo.', 403, 'USER_NOT_ACTIVE');
    }

    if ($user['roles'] === []) {
        destroyOperativoSession();
        errorResponse('El usuario no tiene roles activos asignados.', 403, 'USER_WITHOUT_ROLES');
    }

    setSessionUser($user);

    if (!$allowPendingPasswordChange && $user['debe_cambiar_password']) {
        errorResponse('Debes cambiar tu contraseña temporal antes de continuar.', 403, 'PASSWORD_CHANGE_REQUIRED');
    }

    return $user;
}
