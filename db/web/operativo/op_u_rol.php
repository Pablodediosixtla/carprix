<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN']);

$roleId = positiveInt($input['rol_id'] ?? null, 'rol_id');
$name = requireString($input, 'nombre', 'nombre', 100);
$description = cleanString($input['descripcion'] ?? '', 255);

$stmt = $con->prepare('SELECT codigo, es_sistema FROM operativo_rol WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $roleId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$existing) {
    $con->close();
    errorResponse('Rol no encontrado.', 404, 'ROLE_NOT_FOUND');
}

$code = (string) $existing['codigo'];
if (!(bool) $existing['es_sistema']) {
    $rawCode = mb_strtoupper(requireString($input, 'codigo', 'código', 50), 'UTF-8');
    $code = preg_replace('/[^A-Z0-9_]/', '_', $rawCode) ?? '';
    if (!preg_match('/^[A-Z][A-Z0-9_]{2,49}$/', $code)) {
        $con->close();
        errorResponse('El código debe iniciar con letra y contener únicamente A-Z, números y guion bajo.', 422, 'VALIDATION_ERROR', ['field' => 'codigo']);
    }
}

$update = $con->prepare("UPDATE operativo_rol
                         SET codigo = ?, nombre = ?, descripcion = NULLIF(?, '')
                         WHERE id = ?");
$update->bind_param('sssi', $code, $name, $description, $roleId);
if (!$update->execute()) {
    $errno = $update->errno;
    $update->close();
    if ($errno === 1062) {
        $con->close();
        errorResponse('El código o nombre del rol ya existe.', 409, 'DUPLICATE_ROLE');
    }
    databaseError($con);
}
$update->close();
$con->close();
okResponse([], 'Rol actualizado correctamente.');
