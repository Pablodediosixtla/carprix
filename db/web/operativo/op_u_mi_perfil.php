<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);

$email = validateEmailAddress(requireString($input, 'email', 'correo electrónico', 150));
$telefono = cleanString($input['telefono'] ?? '', 20);
$userId = (int) $user['id'];

$stmt = $con->prepare(
    "UPDATE operativo_usuario
     SET email = ?, telefono = NULLIF(?, '')
     WHERE id = ?"
);
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('ssi', $email, $telefono, $userId);
if (!$stmt->execute()) {
    $errno = $stmt->errno;
    $stmt->close();
    if ($errno === 1062) {
        $con->close();
        errorResponse('El correo electrónico ya está registrado por otro usuario.', 409, 'DUPLICATE_EMAIL');
    }
    databaseError($con);
}
$stmt->close();

$updated = fetchUserContext($con, $userId);
if (!$updated) {
    $con->close();
    errorResponse('No fue posible actualizar la sesión.', 500, 'SESSION_UPDATE_ERROR');
}

setSessionUser($updated);
$con->close();

okResponse(['usuario' => $updated], 'Tu información fue actualizada correctamente.');
