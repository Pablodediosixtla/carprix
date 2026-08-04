<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
$autoId = positiveInt($input['id'] ?? null, 'id');

$stmt = $con->prepare('SELECT * FROM autos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $autoId);
$stmt->execute();
$auto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$auto) {
    $con->close();
    errorResponse('Auto no encontrado.', 404, 'AUTO_NOT_FOUND');
}

$imagesStmt = $con->prepare('SELECT id, ruta_imagen, orden FROM imagenes_autos WHERE auto_id = ? ORDER BY orden, id');
$imagesStmt->bind_param('i', $autoId);
$imagesStmt->execute();
$images = $imagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imagesStmt->close();
$con->close();

$auto['id'] = (int) $auto['id'];
$auto['anio'] = (int) $auto['anio'];
$auto['precio'] = (float) $auto['precio'];
$auto['mensualidad'] = (float) $auto['mensualidad'];
$auto['kilometraje'] = (int) $auto['kilometraje'];
$auto['imagenes'] = $images;

okResponse([
    'auto' => $auto,
    'permisos' => ['puede_editar' => canManageCatalog($user)],
]);
