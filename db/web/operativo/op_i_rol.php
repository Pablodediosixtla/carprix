<?php
declare(strict_types=1);

require_once __DIR__ . '/../../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$currentUser = requireAuthenticated($con);
requireAnyRole($currentUser, ['SUPER_ADMIN']);

$rawCode = mb_strtoupper(requireString($input, 'codigo', 'código', 50), 'UTF-8');
$code = preg_replace('/[^A-Z0-9_]/', '_', $rawCode) ?? '';
$name = requireString($input, 'nombre', 'nombre', 100);
$description = cleanString($input['descripcion'] ?? '', 255);

if (!preg_match('/^[A-Z][A-Z0-9_]{2,49}$/', $code)) {
    $con->close();
    errorResponse('El código debe iniciar con letra y contener únicamente A-Z, números y guion bajo.', 422, 'VALIDATION_ERROR', ['field' => 'codigo']);
}

$stmt = $con->prepare("INSERT INTO operativo_rol
    (codigo, nombre, descripcion, es_sistema, activo)
    VALUES (?, ?, NULLIF(?, ''), 0, 1)");
$stmt->bind_param('sss', $code, $name, $description);

if (!$stmt->execute()) {
    $errno = $stmt->errno;
    $stmt->close();
    if ($errno === 1062) {
        $con->close();
        errorResponse('El código o nombre del rol ya existe.', 409, 'DUPLICATE_ROLE');
    }
    databaseError($con);
}

$roleId = (int) $con->insert_id;
$stmt->close();
$con->close();
okResponse(['rol_id' => $roleId], 'Rol creado correctamente.', 201);
