<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_bootstrap.php';

function normalizeAutoUploadFiles(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    if (!is_array($files['name'])) {
        return [[
            'name' => (string) $files['name'],
            'type' => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'] ?? 0),
        ]];
    }

    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $normalized;
}

function decodeIdArray(mixed $value): array
{
    $decoded = json_decode((string) ($value ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_unique(array_filter(
        array_map('intval', $decoded),
        static fn(int $id): bool => $id > 0
    )));
}

function decodePathArray(mixed $value): array
{
    $decoded = json_decode((string) ($value ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $paths = [];
    foreach ($decoded as $path) {
        $clean = cleanString($path, 500);
        if ($clean !== '') {
            $paths[$clean] = true;
        }
    }
    return array_keys($paths);
}

function localAutoImageAbsolutePath(string $projectRoot, int $autoId, string $route): ?string
{
    $route = ltrim(str_replace('\\', '/', trim($route)), '/');
    $prefix = "Catalogo/{$autoId}/";

    if ($route === '' || !str_starts_with($route, $prefix) || str_contains($route, '..')) {
        return null;
    }

    return rtrim($projectRoot, '/') . '/' . $route;
}

$input = bootstrapMultipartApi(true);
$con = connectDatabase();
$user = requireAuthenticated($con);
requireAnyRole($user, ['SUPER_ADMIN', 'ADMIN_OPERATIVO', 'INVENTARIO']);

$autoId = positiveInt($input['auto_id'] ?? null, 'auto_id');
$removeIds = decodeIdArray($input['remove_image_ids'] ?? '[]');
$removePaths = decodePathArray($input['remove_image_paths'] ?? '[]');
$primaryExistingPath = cleanString($input['primary_existing_path'] ?? '', 500);
$primaryNewIndex = isset($input['primary_new_index']) && $input['primary_new_index'] !== ''
    ? (int) $input['primary_new_index']
    : -1;

$autoStmt = $con->prepare('SELECT id, img_principal FROM autos WHERE id = ? LIMIT 1');
if (!$autoStmt) {
    databaseError($con);
}
$autoStmt->bind_param('i', $autoId);
$autoStmt->execute();
$auto = $autoStmt->get_result()->fetch_assoc();
$autoStmt->close();

if (!$auto) {
    $con->close();
    errorResponse('Auto no encontrado.', 404, 'AUTO_NOT_FOUND');
}

$imageStmt = $con->prepare(
    'SELECT id, ruta_imagen, orden FROM imagenes_autos WHERE auto_id = ? ORDER BY orden, id'
);
if (!$imageStmt) {
    databaseError($con);
}
$imageStmt->bind_param('i', $autoId);
$imageStmt->execute();
$existingRows = $imageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$imageStmt->close();

$knownById = [];
$knownPaths = [];
$currentPrimary = cleanString($auto['img_principal'] ?? '', 500);
if ($currentPrimary !== '') {
    $knownPaths[$currentPrimary] = true;
}
foreach ($existingRows as $row) {
    $id = (int) $row['id'];
    $path = cleanString($row['ruta_imagen'] ?? '', 500);
    if ($path === '') {
        continue;
    }
    $knownById[$id] = $path;
    $knownPaths[$path] = true;
}

foreach ($removeIds as $id) {
    if (!isset($knownById[$id])) {
        $con->close();
        errorResponse('Una de las imágenes que intentas retirar no pertenece al auto.', 422, 'IMAGE_NOT_OWNED');
    }
}
foreach ($removePaths as $path) {
    if (!isset($knownPaths[$path])) {
        $con->close();
        errorResponse('Una de las rutas de imagen no pertenece al auto.', 422, 'IMAGE_NOT_OWNED');
    }
}

$files = normalizeAutoUploadFiles($_FILES['imagenes'] ?? []);
$files = array_values(array_filter(
    $files,
    static fn(array $file): bool => $file['error'] !== UPLOAD_ERR_NO_FILE
));

$maxFileBytes = 8 * 1024 * 1024;
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
$validatedFiles = [];

foreach ($files as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $con->close();
        errorResponse('Una imagen no pudo cargarse. Revisa el tamaño y vuelve a intentarlo.', 422, 'IMAGE_UPLOAD_ERROR');
    }
    if ($file['size'] <= 0 || $file['size'] > $maxFileBytes) {
        $con->close();
        errorResponse('Cada imagen debe pesar máximo 8 MB.', 422, 'IMAGE_TOO_LARGE');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $con->close();
        errorResponse('El archivo recibido no es una carga válida.', 422, 'INVALID_UPLOAD');
    }

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
    }
    if ($mime === '' && function_exists('mime_content_type')) {
        $mime = (string) mime_content_type($file['tmp_name']);
    }
    if (!isset($allowedMime[$mime])) {
        $con->close();
        errorResponse('Solo se permiten imágenes JPG, PNG o WEBP.', 422, 'IMAGE_TYPE_NOT_ALLOWED');
    }

    $file['extension'] = $allowedMime[$mime];
    $validatedFiles[] = $file;
}

$removePathMap = array_fill_keys($removePaths, true);
foreach ($removeIds as $id) {
    $removePathMap[$knownById[$id]] = true;
}

$survivingPaths = [];
foreach (array_keys($knownPaths) as $path) {
    if (!isset($removePathMap[$path])) {
        $survivingPaths[$path] = true;
    }
}

if ((count($survivingPaths) + count($validatedFiles)) > 12) {
    $con->close();
    errorResponse('El auto puede tener máximo 12 imágenes.', 422, 'IMAGE_LIMIT_EXCEEDED');
}

$projectRoot = dirname(__DIR__, 3);
$relativeDirectory = "Catalogo/{$autoId}";
$absoluteDirectory = $projectRoot . '/' . $relativeDirectory;

if ($validatedFiles !== [] && !is_dir($absoluteDirectory)) {
    if (!mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        $con->close();
        errorResponse('No fue posible crear la carpeta de imágenes del auto.', 500, 'IMAGE_DIRECTORY_ERROR');
    }
}
if ($validatedFiles !== [] && !is_writable($absoluteDirectory)) {
    $con->close();
    errorResponse(
        'La carpeta del catálogo no tiene permisos de escritura. Revisa el almacenamiento del App Service.',
        500,
        'IMAGE_DIRECTORY_NOT_WRITABLE'
    );
}

$newRoutes = [];
$newAbsoluteFiles = [];
foreach ($validatedFiles as $file) {
    $filename = 'Img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $file['extension'];
    $absolutePath = $absoluteDirectory . '/' . $filename;
    $route = '/' . $relativeDirectory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        foreach ($newAbsoluteFiles as $createdFile) {
            @unlink($createdFile);
        }
        $con->close();
        errorResponse('No fue posible guardar una de las imágenes.', 500, 'IMAGE_SAVE_ERROR');
    }

    @chmod($absolutePath, 0644);
    $newRoutes[] = $route;
    $newAbsoluteFiles[] = $absolutePath;
}

$con->begin_transaction();

try {
    if ($removeIds !== []) {
        $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
        $types = 'i' . str_repeat('i', count($removeIds));
        $params = array_merge([$autoId], $removeIds);
        $deleteStmt = $con->prepare(
            "DELETE FROM imagenes_autos WHERE auto_id = ? AND id IN ({$placeholders})"
        );
        if (!$deleteStmt) {
            throw new RuntimeException($con->error);
        }
        bindDynamicParams($deleteStmt, $types, $params);
        if (!$deleteStmt->execute()) {
            throw new RuntimeException($deleteStmt->error, $deleteStmt->errno);
        }
        $deleteStmt->close();
    }

    if ($removePaths !== []) {
        $placeholders = implode(',', array_fill(0, count($removePaths), '?'));
        $types = 'i' . str_repeat('s', count($removePaths));
        $params = array_merge([$autoId], $removePaths);
        $deletePathStmt = $con->prepare(
            "DELETE FROM imagenes_autos WHERE auto_id = ? AND ruta_imagen IN ({$placeholders})"
        );
        if (!$deletePathStmt) {
            throw new RuntimeException($con->error);
        }
        bindDynamicParams($deletePathStmt, $types, $params);
        if (!$deletePathStmt->execute()) {
            throw new RuntimeException($deletePathStmt->error, $deletePathStmt->errno);
        }
        $deletePathStmt->close();
    }

    $orderRow = $con->query(
        'SELECT COALESCE(MAX(orden), 0) AS max_orden FROM imagenes_autos WHERE auto_id = ' . (int) $autoId
    )->fetch_assoc();
    $nextOrder = (int) ($orderRow['max_orden'] ?? 0) + 1;

    if ($newRoutes !== []) {
        $insertImage = $con->prepare(
            'INSERT INTO imagenes_autos (auto_id, ruta_imagen, orden) VALUES (?, ?, ?)'
        );
        if (!$insertImage) {
            throw new RuntimeException($con->error);
        }
        foreach ($newRoutes as $route) {
            $insertImage->bind_param('isi', $autoId, $route, $nextOrder);
            if (!$insertImage->execute()) {
                throw new RuntimeException($insertImage->error, $insertImage->errno);
            }
            $nextOrder++;
        }
        $insertImage->close();
    }

    $allRemaining = array_keys($survivingPaths);
    foreach ($newRoutes as $route) {
        if (!in_array($route, $allRemaining, true)) {
            $allRemaining[] = $route;
        }
    }

    $primaryPath = '';
    if ($primaryNewIndex >= 0 && isset($newRoutes[$primaryNewIndex])) {
        $primaryPath = $newRoutes[$primaryNewIndex];
    } elseif ($primaryExistingPath !== '' && in_array($primaryExistingPath, $allRemaining, true)) {
        $primaryPath = $primaryExistingPath;
    } elseif ($currentPrimary !== '' && in_array($currentPrimary, $allRemaining, true)) {
        $primaryPath = $currentPrimary;
    } elseif ($allRemaining !== []) {
        $primaryPath = $allRemaining[0];
    }

    $updateAuto = $con->prepare('UPDATE autos SET img_principal = ? WHERE id = ?');
    if (!$updateAuto) {
        throw new RuntimeException($con->error);
    }
    $updateAuto->bind_param('si', $primaryPath, $autoId);
    if (!$updateAuto->execute()) {
        throw new RuntimeException($updateAuto->error, $updateAuto->errno);
    }
    $updateAuto->close();

    $con->commit();

    foreach (array_keys($removePathMap) as $route) {
        if (in_array($route, $allRemaining, true)) {
            continue;
        }
        $absolutePath = localAutoImageAbsolutePath($projectRoot, $autoId, $route);
        if ($absolutePath !== null && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    $con->close();
    okResponse([
        'auto_id' => $autoId,
        'img_principal' => $primaryPath,
        'imagenes_agregadas' => $newRoutes,
        'total_imagenes' => count($allRemaining),
    ], 'Imágenes actualizadas correctamente.');
} catch (Throwable $e) {
    $con->rollback();
    foreach ($newAbsoluteFiles as $createdFile) {
        @unlink($createdFile);
    }
    databaseError($con, $e);
}
