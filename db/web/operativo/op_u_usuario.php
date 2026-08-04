<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN', 'ADMIN_OPERATIVO']);

$userId = positiveInt($input['usuario_id'] ?? null, 'usuario_id');
$username = validateUsername(requireString($input, 'username', 'username', 80));
$email = validateEmailAddress(requireString($input, 'email', 'correo electrónico', 150));
$nombre = requireString($input, 'nombre', 'nombre', 100);
$apellidoPaterno = requireString($input, 'apellido_paterno', 'apellido paterno', 100);
$apellidoMaterno = cleanString($input['apellido_materno'] ?? '', 100);
$telefono = cleanString($input['telefono'] ?? '', 20);

$target = fetchUserContext($con, $userId);
if (!$target) {
    $con->close();
    errorResponse('Usuario no encontrado.', 404, 'USER_NOT_FOUND');
}

if (hasAnyRole($target, ['SUPER_ADMIN']) && !isSuperAdmin($currentUser)) {
    $con->close();
    errorResponse('Solo un superadministrador puede modificar otro superadministrador.', 403, 'FORBIDDEN');
}

$stmt = $con->prepare("UPDATE operativo_usuario
    SET username = ?, email = ?, nombre = ?, apellido_paterno = ?,
        apellido_materno = NULLIF(?, ''), telefono = NULLIF(?, '')
    WHERE id = ?");
$stmt->bind_param('ssssssi', $username, $email, $nombre, $apellidoPaterno, $apellidoMaterno, $telefono, $userId);

if (!$stmt->execute()) {
    $errno = $stmt->errno;
    $stmt->close();
    if ($errno === 1062) {
        $con->close();
        errorResponse('El username o correo electrónico ya está registrado.', 409, 'DUPLICATE_USER');
    }
    databaseError($con);
}
$stmt->close();
$updated = fetchUserContext($con, $userId);

if ($userId === (int) $currentUser['id'] && $updated) {
    setSessionUser($updated);
}

$con->close();
okResponse(['usuario' => $updated], 'Usuario actualizado correctamente.');
