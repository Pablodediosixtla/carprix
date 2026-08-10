<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO']);

$marca = requireString($input, 'marca', 'marca', 50);
$modelo = requireString($input, 'modelo', 'modelo', 100);
$tipo = cleanString($input['tipo'] ?? '', 50);
$anio = (int) ($input['anio'] ?? 0);
$precio = (float) ($input['precio'] ?? 0);
$mensualidad = (float) ($input['mensualidad'] ?? 0);
$ubicacion = requireString($input, 'ubicacion', 'ubicación', 100);
$kilometraje = max(0, (int) ($input['kilometraje'] ?? 0));
$transmision = requireString($input, 'transmision', 'transmisión', 50);
$color = cleanString($input['color'] ?? '', 50);
$motor = cleanString($input['motor'] ?? '', 50);
$combustible = cleanString($input['combustible'] ?? '', 50);
$pasajeros = max(1, (int) ($input['pasajeros'] ?? 5));
$traccion = cleanString($input['traccion'] ?? '', 50);
$duenos = max(1, (int) ($input['duenos'] ?? 1));
$imgPrincipal = cleanString($input['img_principal'] ?? '', 500);

if ($anio < 1950 || $anio > ((int) date('Y') + 1) || $precio <= 0) {
    $con->close();
    errorResponse('Año o precio no válido.', 422, 'VALIDATION_ERROR');
}

// Todo auto nuevo entra oculto. Su publicación se resuelve mediante
// operativo_catalogo_requerimiento.
$estatus = 'Oculto';

$con->begin_transaction();
try {
    $sql = "INSERT INTO autos
            (marca, modelo, tipo, anio, precio, mensualidad, ubicacion, kilometraje,
             transmision, color, motor, combustible, pasajeros, traccion, duenos,
             img_principal, estatus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($con->error);
    }
    $stmt->bind_param(
        'sssiddsissssisiss',
        $marca,
        $modelo,
        $tipo,
        $anio,
        $precio,
        $mensualidad,
        $ubicacion,
        $kilometraje,
        $transmision,
        $color,
        $motor,
        $combustible,
        $pasajeros,
        $traccion,
        $duenos,
        $imgPrincipal,
        $estatus
    );
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error, $stmt->errno);
    }
    $autoId = (int) $con->insert_id;
    $stmt->close();

    $requestId = createCatalogPublicationRequest(
        $con,
        $autoId,
        (int) $user['id'],
        "Alta de auto nuevo #{$autoId}. Se solicita autorización para publicarlo como Disponible."
    );

    $con->commit();
    $con->close();

    okResponse([
        'id' => $autoId,
        'estatus' => 'Oculto',
        'requerimiento_catalogo_id' => $requestId,
    ], 'Auto agregado como Oculto y enviado a autorización de catálogo.', 201);
} catch (Throwable $e) {
    $con->rollback();
    databaseError($con, $e);
}
