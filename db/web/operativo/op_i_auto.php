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
$estatus = cleanString($input['estatus'] ?? 'Disponible', 30);

if ($anio < 1950 || $anio > ((int) date('Y') + 1) || $precio <= 0) {
    $con->close();
    errorResponse('Año o precio no válido.', 422, 'VALIDATION_ERROR');
}
if (!in_array($estatus, ['Disponible', 'Vendido', 'Oculto'], true)) {
    $con->close();
    errorResponse('Estatus no válido.', 422, 'VALIDATION_ERROR');
}

$sql = "INSERT INTO autos
        (marca, modelo, tipo, anio, precio, mensualidad, ubicacion, kilometraje,
         transmision, color, motor, combustible, pasajeros, traccion, duenos,
         img_principal, estatus)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $con->prepare($sql);
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
    $stmt->close();
    databaseError($con);
}
$autoId = (int) $con->insert_id;
$stmt->close();
$con->close();

okResponse(['id' => $autoId], 'Auto agregado correctamente.', 201);
