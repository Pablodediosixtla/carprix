<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

if (!canFullyManageTargetUser($con, $currentUser, $userId)) {
    $con->close();
    errorResponse('No tienes permisos para editar este usuario.', 403, 'FORBIDDEN');
}

$username = validateUsername(requireString($input, 'username', 'username', 80));
$email = validateEmailAddress(requireString($input, 'email', 'correo electrónico', 150));
$nombre = requireString($input, 'nombre', 'nombre', 100);
$apellidoPaterno = requireString($input, 'apellido_paterno', 'apellido paterno', 100);
$apellidoMaterno = cleanString($input['apellido_materno'] ?? '', 100);
$telefono = cleanString($input['telefono'] ?? '', 20);
$fechaNacimiento = normalizeBirthDate($input['fecha_nacimiento'] ?? null);
$status = requireString($input, 'estatus', 'estatus', 20);
$level = strtoupper(cleanString($input['nivel'] ?? '', 40));
$supervisorId = (int) ($input['supervisor_id'] ?? 0);
$supervisorAlsoSells = filter_var($input['supervisor_tambien_vende'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!in_array($status, ['Activo', 'Inactivo', 'Bloqueado'], true)) {
    $con->close();
    errorResponse('El estatus no es válido.', 422, 'VALIDATION_ERROR', ['field' => 'estatus']);
}
if ($userId === (int) $currentUser['id'] && $status !== 'Activo') {
    $con->close();
    errorResponse('No puedes inactivar o bloquear tu propio usuario.', 409, 'SELF_STATUS_CHANGE_NOT_ALLOWED');
}

$targetIsSuperAdmin = hasAnyRole($target, ['SUPER_ADMIN']);
$currentLevel = operationalLevelFromRoles($target['roles'] ?? []);
if ($targetIsSuperAdmin) {
    // Conservamos el nivel raíz para evitar retirar accidentalmente SUPER_ADMIN,
    // pero SUPER_ADMIN y ADMIN_OPERATIVO sí pueden editar sus datos y estatus.
    $level = 'SUPER_ADMIN';
    $supervisorId = 0;
} else {
    $allowedLevels = personLevelsAllowed($currentUser);
    if (!in_array($level, $allowedLevels, true)) {
        // Un gerente operativo puede editar otro gerente existente sin otorgar ese nivel a terceros.
        if (!(hasAnyRole($currentUser, ['ADMIN_OPERATIVO'])
            && $currentLevel === 'GERENTE_OPERACIONES'
            && $level === 'GERENTE_OPERACIONES')) {
            $con->close();
            errorResponse('No puedes asignar el nivel seleccionado.', 403, 'PERSON_LEVEL_FORBIDDEN');
        }
    }

    if (in_array($level, ['VENDEDOR', 'RESPONSABLE_INVENTARIO'], true) && $supervisorId <= 0) {
        $con->close();
        errorResponse('Debes asignar un supervisor directo.', 422, 'SUPERVISOR_REQUIRED', ['field' => 'supervisor_id']);
    }
    if ($supervisorId > 0) {
        assertHierarchyAssignmentValid($con, $userId, $supervisorId);
    }
}

if ($targetIsSuperAdmin && $status !== 'Activo') {
    $stmt = $con->prepare(
        "SELECT COUNT(DISTINCT u.id) AS total
         FROM operativo_usuario u
         INNER JOIN operativo_usuario_rol ur ON ur.usuario_id = u.id AND ur.activo = 1
         INNER JOIN operativo_rol r ON r.id = ur.rol_id AND r.activo = 1
         WHERE u.estatus = 'Activo' AND r.codigo = 'SUPER_ADMIN'"
    );
    $stmt->execute();
    $totalActiveAdmins = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    if ($totalActiveAdmins <= 1) {
        $con->close();
        errorResponse('No puedes desactivar al último superadministrador activo.', 409, 'LAST_SUPER_ADMIN');
    }
}

$con->begin_transaction();
try {
    $update = $con->prepare(
        "UPDATE operativo_usuario
         SET username = ?, email = ?, nombre = ?, apellido_paterno = ?,
             apellido_materno = NULLIF(?, ''), telefono = NULLIF(?, ''),
             fecha_nacimiento = ?, estatus = ?,
             intentos_fallidos = IF(? = 'Activo', 0, intentos_fallidos),
             bloqueado_hasta = IF(? = 'Activo', NULL, bloqueado_hasta)
         WHERE id = ?"
    );
    if (!$update) {
        throw new RuntimeException($con->error);
    }
    $update->bind_param(
        'ssssssssssi',
        $username,
        $email,
        $nombre,
        $apellidoPaterno,
        $apellidoMaterno,
        $telefono,
        $fechaNacimiento,
        $status,
        $status,
        $status,
        $userId
    );
    if (!$update->execute()) {
        throw new RuntimeException($update->error, $update->errno);
    }
    $update->close();

    if (!$targetIsSuperAdmin) {
        $roleCodes = personRoleCodes($level, $supervisorAlsoSells);
        if ($roleCodes === []) {
            throw new DomainException('INVALID_PERSON_LEVEL');
        }

        $managedCodes = ['VENTAS', 'AUTORIZADOR', 'INVENTARIO', 'ADMIN_OPERATIVO'];
        $placeholders = implode(',', array_fill(0, count($managedCodes), '?'));
        $roleLookup = $con->prepare(
            "SELECT id, codigo FROM operativo_rol
             WHERE activo = 1 AND codigo IN ({$placeholders})"
        );
        if (!$roleLookup) {
            throw new RuntimeException($con->error);
        }
        $lookupTypes = str_repeat('s', count($managedCodes));
        $lookupParams = $managedCodes;
        bindDynamicParams($roleLookup, $lookupTypes, $lookupParams);
        $roleLookup->execute();
        $roleRows = $roleLookup->get_result()->fetch_all(MYSQLI_ASSOC);
        $roleLookup->close();

        $roleMap = [];
        foreach ($roleRows as $roleRow) {
            $roleMap[(string) $roleRow['codigo']] = (int) $roleRow['id'];
        }
        foreach ($roleCodes as $code) {
            if (!isset($roleMap[$code])) {
                throw new DomainException('REQUIRED_ROLE_NOT_FOUND');
            }
        }

        $managedIds = array_values($roleMap);
        if ($managedIds !== []) {
            $idPlaceholders = implode(',', array_fill(0, count($managedIds), '?'));
            $deactivate = $con->prepare(
                "UPDATE operativo_usuario_rol
                 SET activo = 0
                 WHERE usuario_id = ? AND rol_id IN ({$idPlaceholders})"
            );
            if (!$deactivate) {
                throw new RuntimeException($con->error);
            }
            $deactivateTypes = 'i' . str_repeat('i', count($managedIds));
            $deactivateParams = array_merge([$userId], $managedIds);
            bindDynamicParams($deactivate, $deactivateTypes, $deactivateParams);
            if (!$deactivate->execute()) {
                throw new RuntimeException($deactivate->error, $deactivate->errno);
            }
            $deactivate->close();
        }

        $assign = $con->prepare(
            "INSERT INTO operativo_usuario_rol
                (usuario_id, rol_id, activo, asignado_por, asignado_en)
             VALUES (?, ?, 1, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                activo = 1,
                asignado_por = VALUES(asignado_por),
                asignado_en = CURRENT_TIMESTAMP"
        );
        if (!$assign) {
            throw new RuntimeException($con->error);
        }
        $editorId = (int) $currentUser['id'];
        foreach ($roleCodes as $code) {
            $roleId = $roleMap[$code];
            $assign->bind_param('iii', $userId, $roleId, $editorId);
            if (!$assign->execute()) {
                throw new RuntimeException($assign->error, $assign->errno);
            }
        }
        $assign->close();

        saveHierarchyAssignment($con, $userId, $supervisorId, (int) $currentUser['id']);
    }

    $con->commit();
    $updated = fetchUserContext($con, $userId);
    if ($userId === (int) $currentUser['id'] && $updated) {
        setSessionUser($updated);
    }
    $con->close();
    okResponse(['usuario' => $updated], 'Usuario actualizado correctamente.');
} catch (Throwable $e) {
    $con->rollback();
    if ((int) $e->getCode() === 1062) {
        $con->close();
        errorResponse('El username o correo electrónico ya está registrado.', 409, 'DUPLICATE_USER');
    }
    if ($e instanceof DomainException && $e->getMessage() === 'INVALID_PERSON_LEVEL') {
        $con->close();
        errorResponse('El nivel seleccionado no tiene roles configurados.', 422, 'INVALID_PERSON_LEVEL');
    }
    if ($e instanceof DomainException && $e->getMessage() === 'REQUIRED_ROLE_NOT_FOUND') {
        $con->close();
        errorResponse('Falta uno de los roles operativos requeridos.', 409, 'REQUIRED_ROLE_NOT_FOUND');
    }
    databaseError($con, $e);
}
