<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$username = validateUsername(requireString($input, 'username', 'username', 80));
$email = validateEmailAddress(requireString($input, 'email', 'correo electrónico', 150));
$nombre = requireString($input, 'nombre', 'nombre', 100);
$apellidoPaterno = requireString($input, 'apellido_paterno', 'apellido paterno', 100);
$apellidoMaterno = cleanString($input['apellido_materno'] ?? '', 100);
$telefono = cleanString($input['telefono'] ?? '', 20);
$password = (string) ($input['password_temporal'] ?? '');
$roles = $input['roles'] ?? [];

if (!is_array($roles) || $roles === []) {
    $con->close();
    errorResponse('Debes asignar al menos un rol.', 422, 'VALIDATION_ERROR', ['field' => 'roles']);
}

validatePasswordPolicy($password);
$roleIds = array_values(array_unique(array_map('intval', $roles)));
$roleIds = array_values(array_filter($roleIds, static fn(int $id): bool => $id > 0));

if ($roleIds === []) {
    $con->close();
    errorResponse('Los roles enviados no son válidos.', 422, 'VALIDATION_ERROR', ['field' => 'roles']);
}

$placeholders = implode(',', array_fill(0, count($roleIds), '?'));
$types = str_repeat('i', count($roleIds));
$roleParams = $roleIds;
$roleStmt = $con->prepare("SELECT id, codigo FROM operativo_rol WHERE activo = 1 AND id IN ({$placeholders})");
bindDynamicParams($roleStmt, $types, $roleParams);
$roleStmt->execute();
$roleRows = $roleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$roleStmt->close();

if (count($roleRows) !== count($roleIds)) {
    $con->close();
    errorResponse('Uno o más roles no existen o están inactivos.', 422, 'INVALID_ROLES');
}

foreach ($roleRows as $role) {
    if ($role['codigo'] === 'SUPER_ADMIN' && !isSuperAdmin($currentUser)) {
        $con->close();
        errorResponse('Solo un superadministrador puede asignar el rol SUPER_ADMIN.', 403, 'FORBIDDEN_ROLE_ASSIGNMENT');
    }
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$con->begin_transaction();

try {
    $insert = $con->prepare("INSERT INTO operativo_usuario
        (username, email, nombre, apellido_paterno, apellido_materno, telefono,
         password_hash, estatus, debe_cambiar_password)
        VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, 'Activo', 1)");
    $insert->bind_param('sssssss', $username, $email, $nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $passwordHash);
    if (!$insert->execute()) {
        throw new RuntimeException($insert->error, $insert->errno);
    }
    $userId = (int) $con->insert_id;
    $insert->close();

    $assign = $con->prepare("INSERT INTO operativo_usuario_rol
        (usuario_id, rol_id, activo, asignado_por, asignado_en)
        VALUES (?, ?, 1, ?, CURRENT_TIMESTAMP)");

    foreach ($roleIds as $roleId) {
        $assign->bind_param('iii', $userId, $roleId, $currentUser['id']);
        if (!$assign->execute()) {
            throw new RuntimeException($assign->error, $assign->errno);
        }
    }
    $assign->close();

    $con->commit();
    $created = fetchUserContext($con, $userId);
    $con->close();
    okResponse(['usuario' => $created], 'Usuario creado correctamente.', 201);
} catch (Throwable $e) {
    $con->rollback();
    if ((int) $e->getCode() === 1062) {
        $con->close();
        errorResponse('El username o correo electrónico ya está registrado.', 409, 'DUPLICATE_USER');
    }
    databaseError($con, $e);
}
