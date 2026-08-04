<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);

if (!canManagePeople($currentUser)) {
    $con->close();
    errorResponse('No tienes permisos para agregar personas.', 403, 'FORBIDDEN');
}

$username = validateUsername(requireString($input, 'username', 'username', 80));
$email = validateEmailAddress(requireString($input, 'email', 'correo electrónico', 150));
$nombre = requireString($input, 'nombre', 'nombre', 100);
$apellidoPaterno = requireString($input, 'apellido_paterno', 'apellido paterno', 100);
$apellidoMaterno = cleanString($input['apellido_materno'] ?? '', 100);
$telefono = cleanString($input['telefono'] ?? '', 20);
$password = (string) ($input['password_temporal'] ?? '');
$level = strtoupper(cleanString($input['nivel'] ?? '', 40));
$supervisorId = (int) ($input['supervisor_id'] ?? 0);
$supervisorAlsoSells = filter_var(
    $input['supervisor_tambien_vende'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

validatePasswordPolicy($password);

$allowedLevels = personLevelsAllowed($currentUser);
if (!in_array($level, $allowedLevels, true)) {
    $con->close();
    errorResponse('No puedes crear personas en el nivel seleccionado.', 403, 'PERSON_LEVEL_FORBIDDEN');
}

$isSuperAdmin = isSuperAdmin($currentUser);
$isManager = hasAnyRole($currentUser, ['ADMIN_OPERATIVO']);
$isSupervisorOnly = !$isSuperAdmin && !$isManager && hasAnyRole($currentUser, ['AUTORIZADOR']);

if ($isSupervisorOnly) {
    $supervisorId = (int) $currentUser['id'];
}

if ($level === 'SUPERVISOR' && $isManager && $supervisorId <= 0) {
    $supervisorId = (int) $currentUser['id'];
}

if ($level === 'VENDEDOR' && $supervisorId <= 0) {
    $con->close();
    errorResponse('Debes asignar un supervisor al vendedor.', 422, 'SUPERVISOR_REQUIRED', ['field' => 'supervisor_id']);
}

if ($isSupervisorOnly && $supervisorId !== (int) $currentUser['id']) {
    $con->close();
    errorResponse('Como supervisor, los vendedores nuevos deben quedar en tu propia línea jerárquica.', 403, 'SUPERVISOR_SCOPE_FORBIDDEN');
}

if ($supervisorId > 0) {
    assertActiveApprover($con, $supervisorId);
}

$roleCodes = personRoleCodes($level, $supervisorAlsoSells);
if ($roleCodes === []) {
    $con->close();
    errorResponse('El nivel seleccionado no tiene roles configurados.', 422, 'INVALID_PERSON_LEVEL');
}

$placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
$roleTypes = str_repeat('s', count($roleCodes));
$roleParams = $roleCodes;
$roleStmt = $con->prepare(
    "SELECT id, codigo
     FROM operativo_rol
     WHERE activo = 1
       AND codigo IN ({$placeholders})"
);
if (!$roleStmt) {
    databaseError($con);
}
bindDynamicParams($roleStmt, $roleTypes, $roleParams);
$roleStmt->execute();
$roleRows = $roleStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$roleStmt->close();

if (count($roleRows) !== count($roleCodes)) {
    $con->close();
    errorResponse('Falta uno de los roles operativos requeridos. Revisa el catálogo de roles.', 409, 'REQUIRED_ROLE_NOT_FOUND');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    $con->close();
    errorResponse('No fue posible proteger la contraseña temporal.', 500, 'PASSWORD_HASH_ERROR');
}

$con->begin_transaction();

try {
    $insert = $con->prepare(
        "INSERT INTO operativo_usuario
            (username, email, nombre, apellido_paterno, apellido_materno, telefono,
             password_hash, estatus, debe_cambiar_password)
         VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, 'Activo', 1)"
    );
    if (!$insert) {
        throw new RuntimeException($con->error);
    }
    $insert->bind_param(
        'sssssss',
        $username,
        $email,
        $nombre,
        $apellidoPaterno,
        $apellidoMaterno,
        $telefono,
        $passwordHash
    );
    if (!$insert->execute()) {
        throw new RuntimeException($insert->error, $insert->errno);
    }
    $userId = (int) $con->insert_id;
    $insert->close();

    $assign = $con->prepare(
        "INSERT INTO operativo_usuario_rol
            (usuario_id, rol_id, activo, asignado_por, asignado_en)
         VALUES (?, ?, 1, ?, CURRENT_TIMESTAMP)"
    );
    if (!$assign) {
        throw new RuntimeException($con->error);
    }
    $creatorId = (int) $currentUser['id'];
    foreach ($roleRows as $role) {
        $roleId = (int) $role['id'];
        $assign->bind_param('iii', $userId, $roleId, $creatorId);
        if (!$assign->execute()) {
            throw new RuntimeException($assign->error, $assign->errno);
        }
    }
    $assign->close();

    if ($supervisorId > 0) {
        $hierarchy = $con->prepare(
            "INSERT INTO operativo_usuario_jerarquia
                (usuario_id, supervisor_id, activo, asignado_por)
             VALUES (?, ?, 1, ?)"
        );
        if (!$hierarchy) {
            throw new RuntimeException($con->error);
        }
        $hierarchy->bind_param('iii', $userId, $supervisorId, $creatorId);
        if (!$hierarchy->execute()) {
            throw new RuntimeException($hierarchy->error, $hierarchy->errno);
        }
        $hierarchy->close();
    }

    $con->commit();
    $created = fetchUserContext($con, $userId);
    $con->close();

    okResponse([
        'usuario' => $created,
        'nivel' => $level,
        'supervisor_id' => $supervisorId > 0 ? $supervisorId : null,
    ], 'Persona agregada correctamente.', 201);
} catch (Throwable $e) {
    $con->rollback();
    $errno = (int) $e->getCode();
    if ($errno === 1062) {
        $con->close();
        errorResponse('El username o correo electrónico ya está registrado.', 409, 'DUPLICATE_USER');
    }
    databaseError($con, $e);
}
