<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

bootstrapApi(false);
$con = connectDatabase();
$sessionUser = currentSessionUser();

if (!$sessionUser || empty($sessionUser['id'])) {
    $con->close();
    okResponse([
        'authenticated' => false,
        'usuario' => null,
        'csrf_token' => null,
    ], 'No existe una sesión activa.');
}

$user = fetchUserContext($con, (int) $sessionUser['id']);
if (!$user || $user['estatus'] !== 'Activo' || $user['roles'] === []) {
    destroyOperativoSession();
    $con->close();
    okResponse([
        'authenticated' => false,
        'usuario' => null,
        'csrf_token' => null,
    ], 'La sesión dejó de ser válida.');
}

setSessionUser($user);
$token = csrfToken();
$con->close();

okResponse([
    'authenticated' => true,
    'usuario' => $user,
    'csrf_token' => $token,
], 'Sesión activa.');
