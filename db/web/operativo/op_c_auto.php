<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

$input = bootstrapApi(false);
$con = connectDatabase();
$user = requireAuthenticated($con);
$autoId = positiveInt($input['id'] ?? null, 'id');

$stmt = $con->prepare('SELECT * FROM autos WHERE id = ? LIMIT 1');
if (!$stmt) {
    databaseError($con);
}
$stmt->bind_param('i', $autoId);
$stmt->execute();
$auto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$auto) {
    $con->close();
    errorResponse('Auto no encontrado.', 404, 'AUTO_NOT_FOUND');
}

$imagesStmt = $con->prepare(
    'SELECT id, ruta_imagen, orden FROM imagenes_autos WHERE auto_id = ? ORDER BY orden, id'
);
if (!$imagesStmt) {
    databaseError($con);
}
$imagesStmt->bind_param('i', $autoId);
$imagesStmt->execute();
$imageRows = $imagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imagesStmt->close();
$con->close();

$primaryPath = cleanString($auto['img_principal'] ?? '', 500);
$galleryByPath = [];

foreach ($imageRows as $image) {
    $path = cleanString($image['ruta_imagen'] ?? '', 500);
    if ($path === '') {
        continue;
    }
    $galleryByPath[$path] = [
        'id' => (int) $image['id'],
        'ruta_imagen' => $path,
        'orden' => (int) $image['orden'],
        'es_principal' => $path === $primaryPath,
    ];
}

if ($primaryPath !== '' && !isset($galleryByPath[$primaryPath])) {
    $galleryByPath = [
        $primaryPath => [
            'id' => null,
            'ruta_imagen' => $primaryPath,
            'orden' => 0,
            'es_principal' => true,
        ],
    ] + $galleryByPath;
}

$gallery = array_values($galleryByPath);
usort($gallery, static function (array $a, array $b): int {
    if ($a['es_principal'] !== $b['es_principal']) {
        return $a['es_principal'] ? -1 : 1;
    }
    return $a['orden'] <=> $b['orden'];
});

$auto['id'] = (int) $auto['id'];
$auto['anio'] = (int) $auto['anio'];
$auto['precio'] = (float) $auto['precio'];
$auto['mensualidad'] = (float) $auto['mensualidad'];
$auto['kilometraje'] = (int) $auto['kilometraje'];
$auto['pasajeros'] = $auto['pasajeros'] !== null ? (int) $auto['pasajeros'] : null;
$auto['duenos'] = $auto['duenos'] !== null ? (int) $auto['duenos'] : null;
$auto['imagenes'] = $gallery;

okResponse([
    'auto' => $auto,
    'permisos' => [
        'puede_editar' => canManageCatalog($user),
        'max_imagenes' => 12,
        'max_mb_por_imagen' => 8,
    ],
]);
